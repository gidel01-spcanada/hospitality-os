<?php

namespace Tests\Feature;

use App\Models\Property;
use App\Models\Reservation;
use App\Models\User;
use App\Services\PricingCalculator;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeasonalPricingTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_saved_rule_changes_matching_nights_and_reservation_totals(): void
    {
        $this->seed(DatabaseSeeder::class);
        $property = Property::firstOrFail();
        $property->update(['nightly_rate_xof' => 10000]);
        $property->establishment->update([
            'service_fee_percent' => 10,
            'vat_percent' => 20,
            'vat_included' => false,
            'city_tax_type' => 'percent',
            'city_tax_amount' => 5,
        ]);

        $this->actingAs(User::where('email', 'admin@afrikappart.test')->firstOrFail())
            ->post(route('admin.properties.price-rules.store', $property), [
                'effective_from' => '2027-01-02',
                'effective_to' => '2027-01-03',
                'nightly_rate_xof' => 20000,
                'minimum_stay' => 1,
                'rule_type' => 'seasonal',
            ])
            ->assertRedirect();

        $checkIn = Carbon::parse('2027-01-01');
        $checkOut = Carbon::parse('2027-01-04');
        $pricing = PricingCalculator::calculate($property, $checkIn, $checkOut, 1, 0, [
            (object) ['name' => 'Breakfast', 'cost_xof' => 5000],
        ]);

        $this->assertEquals(50000, $pricing['room_subtotal']);
        $this->assertEquals(16666.67, $pricing['nightly_rate']);
        $this->assertEquals(55000, $pricing['subtotal']);
        $this->assertEquals(5500, $pricing['fees']);
        $this->assertEquals(14850, $pricing['taxes']);
        $this->assertEquals(75350, $pricing['total_amount']);
        $this->assertEquals(50000, $pricing['price_lines'][0]['amount']);
        $this->assertSame('2027-01-01', $checkIn->toDateString());
        $this->assertSame('2027-01-04', $checkOut->toDateString());
    }

    public function test_non_matching_rules_leave_base_pricing_unchanged(): void
    {
        $this->seed(DatabaseSeeder::class);
        $property = Property::firstOrFail();
        $property->update(['nightly_rate_xof' => 10000]);
        $property->rateRules()->create([
            'effective_from' => '2027-01-04',
            'effective_to' => '2027-01-05',
            'nightly_rate_xof' => 20000,
            'minimum_stay' => 1,
            'rule_type' => 'seasonal',
        ]);
        $property->rateRules()->create([
            'effective_from' => '2026-12-01',
            'effective_to' => '2026-12-31',
            'nightly_rate_xof' => 25000,
            'minimum_stay' => 1,
            'rule_type' => 'seasonal',
        ]);
        Property::query()->whereKeyNot($property->id)->firstOrFail()->rateRules()->create([
            'effective_from' => '2027-01-01',
            'effective_to' => '2027-01-03',
            'nightly_rate_xof' => 30000,
            'minimum_stay' => 1,
            'rule_type' => 'seasonal',
        ]);

        $pricing = PricingCalculator::calculate($property, Carbon::parse('2027-01-01'), Carbon::parse('2027-01-04'));

        $this->assertEquals(10000, $pricing['nightly_rate']);
        $this->assertEquals(30000, $pricing['room_subtotal']);
        $this->assertEquals(30000, $pricing['subtotal']);
    }

    public function test_public_quote_and_saved_reservation_use_seasonal_pricing(): void
    {
        $this->seed(DatabaseSeeder::class);
        $property = Property::query()->where('slug', 'appartement-401')->firstOrFail();
        $property->update(['nightly_rate_xof' => 10000, 'minimum_stay' => 1]);
        $property->establishment->update([
            'service_fee_percent' => 10,
            'vat_percent' => 0,
            'city_tax_type' => 'percent',
            'city_tax_amount' => 0,
        ]);
        $checkIn = now()->addMonths(2)->startOfMonth();
        $checkOut = $checkIn->copy()->addDays(2);
        $property->rateRules()->create([
            'effective_from' => $checkIn->copy()->addDay()->toDateString(),
            'effective_to' => null,
            'nightly_rate_xof' => 20000,
            'minimum_stay' => 1,
            'rule_type' => 'seasonal',
        ]);
        $dates = ['check_in' => $checkIn->toDateString(), 'check_out' => $checkOut->toDateString(), 'adults' => 1];

        $this->assertEquals(30000, PricingCalculator::calculate($property, Carbon::parse($dates['check_in']), Carbon::parse($dates['check_out']))['room_subtotal']);

        $this->postJson(route('properties.availability', $property->slug), $dates)
            ->assertOk()
            ->assertJsonPath('available', true)
            ->assertJsonPath('breakdown.room_subtotal', 30000)
            ->assertJsonPath('estimated_total', 33000);

        $customer = User::factory()->create(['role' => 'customer', 'email_verified_at' => now()]);
        $this->actingAs($customer)
            ->post(route('properties.reserve', $property->slug), $dates)
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $reservation = Reservation::query()->where('user_id', $customer->id)->firstOrFail();
        $this->assertEquals(30000, (float) $reservation->subtotal);
        $this->assertEquals(33000, (float) $reservation->total_amount);
        $this->assertEquals(30000, (float) $reservation->priceLines()->firstOrFail()->amount);
    }

    public function test_overlapping_rules_use_latest_start_then_newest_rule_and_resume_open_ended_rule(): void
    {
        $this->seed(DatabaseSeeder::class);
        $property = Property::firstOrFail();
        $property->update(['nightly_rate_xof' => 10000]);
        foreach ([
            ['effective_from' => '2027-01-02', 'effective_to' => null, 'nightly_rate_xof' => 15000],
            ['effective_from' => '2027-01-03', 'effective_to' => '2027-01-04', 'nightly_rate_xof' => 20000],
            ['effective_from' => '2027-01-03', 'effective_to' => '2027-01-04', 'nightly_rate_xof' => 25000],
        ] as $rule) {
            $property->rateRules()->create($rule + ['minimum_stay' => 1, 'rule_type' => 'standard']);
        }

        $pricing = PricingCalculator::calculate($property, Carbon::parse('2027-01-01'), Carbon::parse('2027-01-06'));

        $this->assertEquals(90000, $pricing['room_subtotal']);
        $this->assertEquals(18000, $pricing['nightly_rate']);
    }
}
