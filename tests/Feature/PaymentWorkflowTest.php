<?php

namespace Tests\Feature;

use App\Models\Property;
use App\Models\Reservation;
use App\Models\ReservationGuest;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_and_verified_payment_flow_work(): void
    {
        $this->seed(DatabaseSeeder::class);

        $property = Property::where('slug', 'appartement-401')->firstOrFail();
        $guest = ReservationGuest::query()->create([
            'full_name' => 'Alice Doe',
            'email' => 'alice@example.com',
            'phone' => '+229 99 99 99 99',
            'country' => 'Bénin',
            'metadata' => ['source' => 'test'],
        ]);

        $reservation = Reservation::query()->create([
            'property_id' => $property->id,
            'guest_id' => $guest->id,
            'reservation_ref' => 'AFK-PAY-001',
            'status' => 'pending',
            'check_in' => now()->addDay()->toDateString(),
            'check_out' => now()->addDays(3)->toDateString(),
            'adults' => 2,
            'children' => 0,
            'infants' => 0,
            'currency' => 'XOF',
            'email' => 'alice@example.com',
            'subtotal' => 120000,
            'fees' => 12000,
            'taxes' => 6000,
            'total_amount' => 138000,
            'source' => 'website',
            'notes' => 'Awaiting payment.',
        ]);

        $this->get('/reservations/' . $reservation->id . '/checkout')
            ->assertOk()
            ->assertSee('Finaliser la réservation');

        $this->post('/reservations/' . $reservation->id . '/checkout', [
            'provider' => 'pay_later',
        ])->assertRedirect('/reservations/' . $reservation->id . '/checkout');

        $this->assertDatabaseHas('payment_attempts', [
            'reservation_id' => $reservation->id,
            'provider' => 'pay_later',
            'status' => 'created',
        ]);

        $this->post('/reservations/' . $reservation->id . '/checkout/complete')
            ->assertRedirect('/reservations/' . $reservation->id . '/checkout');

        $this->assertDatabaseHas('reservations', ['id' => $reservation->id, 'status' => 'confirmed']);
        $this->assertDatabaseHas('payment_attempts', ['reservation_id' => $reservation->id, 'provider' => 'pay_later', 'status' => 'paid']);
    }

    public function test_checkout_uses_establishment_payment_methods(): void
    {
        $this->seed(DatabaseSeeder::class);
        $property = Property::where('slug', 'appartement-401')->with('establishment')->firstOrFail();
        $property->establishment->update([
            'payment_methods' => [
                'pay_later' => ['enabled' => false, 'instructions' => ''],
                'fedapay' => ['enabled' => true, 'instructions' => 'Mobile money'],
                'paypal' => ['enabled' => false, 'instructions' => ''],
            ],
        ]);
        $guest = ReservationGuest::create([
            'full_name' => 'Payment Guest',
            'email' => 'payment@example.com',
        ]);
        $reservation = Reservation::create([
            'property_id' => $property->id,
            'guest_id' => $guest->id,
            'reservation_ref' => 'AFK-PAY-002',
            'status' => 'pending',
            'check_in' => now()->addDay()->toDateString(),
            'check_out' => now()->addDays(3)->toDateString(),
            'adults' => 2,
            'children' => 0,
            'infants' => 0,
            'currency' => 'XOF',
            'email' => $guest->email,
            'subtotal' => 100000,
            'fees' => 10000,
            'taxes' => 5000,
            'total_amount' => 115000,
            'source' => 'website',
        ]);

        $this->get('/reservations/' . $reservation->id . '/checkout')
            ->assertOk()
            ->assertSee('FedaPay sandbox')
            ->assertDontSee('Paiement différé');

        $this->post('/reservations/' . $reservation->id . '/checkout', ['provider' => 'pay_later'])
            ->assertStatus(422);
    }
}
