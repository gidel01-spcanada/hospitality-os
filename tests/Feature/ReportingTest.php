<?php

namespace Tests\Feature;

use App\Models\Establishment;
use App\Models\AdminAvailabilityBlock;
use App\Models\PaymentAttempt;
use App\Models\Property;
use App\Models\Receipt;
use App\Models\Reservation;
use App\Models\User;
use App\Services\ReportingService;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportingTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_reporting_calculates_operational_and_financial_metrics(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::query()->where('email', 'admin@afrikappart.test')->firstOrFail();
        $property = Property::query()->firstOrFail();

        $confirmed = Reservation::query()->create([
            'property_id' => $property->id,
            'reservation_ref' => 'REPORT-CONFIRMED',
            'status' => 'confirmed',
            'check_in' => '2026-09-05',
            'check_out' => '2026-09-08',
            'adults' => 2,
            'children' => 1,
            'currency' => 'XOF',
            'email' => 'confirmed-report@example.com',
            'subtotal' => 80000,
            'fees' => 10000,
            'taxes' => 10000,
            'total_amount' => 100000,
        ]);
        Reservation::query()->create([
            'property_id' => $property->id,
            'reservation_ref' => 'REPORT-PENDING',
            'status' => 'pending_payment',
            'check_in' => '2026-09-12',
            'check_out' => '2026-09-14',
            'adults' => 1,
            'currency' => 'XOF',
            'email' => 'pending-report@example.com',
            'total_amount' => 50000,
        ]);
        $attempt = PaymentAttempt::query()->create([
            'reservation_id' => $confirmed->id,
            'provider' => 'offline',
            'provider_reference' => 'REPORT-PAID',
            'currency' => 'XOF',
            'amount' => 100000,
            'status' => 'paid',
        ]);
        Receipt::query()->create([
            'reservation_id' => $confirmed->id,
            'payment_attempt_id' => $attempt->id,
            'receipt_number' => 'RPT-0001',
            'amount' => 100000,
            'currency' => 'XOF',
            'issued_at' => '2026-09-06 12:00:00',
            'issued_by' => (string) $admin->id,
        ]);
        AdminAvailabilityBlock::query()->create([
            'property_id' => $property->id,
            'start_date' => '2026-09-20',
            'end_date' => '2026-09-22',
            'reason' => 'Maintenance',
        ]);

        $properties = collect([$property->load(['establishment', 'availabilityBlocks', 'calendarFeeds.events'])]);
        $report = app(ReportingService::class)->build(
            $properties,
            CarbonImmutable::parse('2026-09-01'),
            CarbonImmutable::parse('2026-09-30'),
        );

        $this->assertSame(2, $report['operations']['reservations']);
        $this->assertSame(3, $report['operations']['occupied_nights']);
        $this->assertSame(2, $report['operations']['blocked_nights']);
        $this->assertSame(28, $report['operations']['sellable_nights']);
        $this->assertSame(4, $report['operations']['guests']);
        $this->assertSame(10.7, $report['operations']['occupancy_rate']);
        $this->assertSame('website', $report['operations']['channels']->first()['source']);
        $this->assertSame(2, $report['operations']['channels']->first()['count']);
        $this->assertSame(100000.0, $report['finance']['collected']->first()['amount']);
        $this->assertSame(50000.0, $report['finance']['outstanding']->first()['amount']);
        $this->assertSame(100.0, $report['finance']['payment_success_rate']);

        $this->actingAs($admin)
            ->get(route('admin.reports.index', [
                'date_from' => '2026-09-01',
                'date_to' => '2026-09-30',
                'property' => $property->id,
            ]))
            ->assertOk()
            ->assertSee('Rapports')
            ->assertSee('Taux d’occupation')
            ->assertSee('100 000,00 XOF')
            ->assertSee('50 000,00 XOF')
            ->assertSee('La marge nette, les dépenses, les commissions OTA et les remboursements ne sont pas calculés');

        $csv = $this->actingAs($admin)
            ->get(route('admin.reports.export', [
                'date_from' => '2026-09-01',
                'date_to' => '2026-09-30',
                'property' => $property->id,
            ]));

        $csv->assertOk()
            ->assertDownload('reports-2026-09-01-2026-09-30.csv');
        $content = $csv->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);
        $this->assertStringContainsString('Section;Indicateur;', $content);
        $this->assertStringContainsString('"Revenus encaissés";;XOF;100000', $content);
        $this->assertStringContainsString($property->name, $content);
    }

    public function test_host_reporting_is_limited_to_assigned_establishments(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::query()->where('email', 'admin@afrikappart.test')->firstOrFail();
        $assigned = Establishment::query()->firstOrFail();
        $ownProperty = Property::query()->where('establishment_id', $assigned->id)->firstOrFail();
        $other = Establishment::query()->create([
            'tenant_id' => $assigned->tenant_id,
            'name' => 'Hidden Report Establishment',
            'slug' => 'hidden-report-establishment',
            'country_code' => 'BJ',
            'currency' => 'XOF',
        ]);
        $otherProperty = Property::query()->create([
            'establishment_id' => $other->id,
            'name' => 'Hidden Report Property',
            'slug' => 'hidden-report-property',
            'property_type' => 'apartment',
            'status' => 'published',
            'is_published' => true,
            'is_active' => true,
            'currency' => 'XOF',
            'nightly_rate_xof' => 30000,
            'minimum_stay' => 1,
            'max_guests' => 2,
            'bedrooms' => 1,
            'bathrooms' => 1,
            'beds' => 1,
        ]);
        $host = User::factory()->create([
            'role' => 'host',
            'is_admin' => false,
            'tenant_id' => $admin->tenant_id,
        ]);
        $host->establishments()->sync([$assigned->id]);

        $this->actingAs($host)
            ->get(route('admin.reports.index'))
            ->assertOk()
            ->assertSee($assigned->name)
            ->assertSee($ownProperty->name)
            ->assertDontSee($other->name)
            ->assertDontSee($otherProperty->name);

        $this->actingAs($host)
            ->get(route('admin.reports.index', ['establishment' => $other->id]))
            ->assertForbidden();

        $this->actingAs($host)
            ->get(route('admin.reports.export', ['establishment' => $other->id]))
            ->assertForbidden();
    }

    public function test_customer_cannot_access_reporting(): void
    {
        $this->seed(DatabaseSeeder::class);
        $customer = User::factory()->create(['role' => 'customer', 'is_admin' => false]);

        $this->actingAs($customer)
            ->get(route('admin.reports.index'))
            ->assertForbidden();

        $this->actingAs($customer)
            ->get(route('admin.reports.export'))
            ->assertForbidden();
    }
}
