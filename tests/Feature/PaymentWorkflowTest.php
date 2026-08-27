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

        $checkoutUrl = route('checkout.show', ['reservation' => $reservation, 'token' => $reservation->checkout_token]);

        $this->get('/reservations/' . $reservation->id . '/checkout')
            ->assertForbidden();

        $this->get(route('checkout.show', ['reservation' => $reservation, 'token' => str_repeat('0', 64)]))
            ->assertForbidden();

        $this->get($checkoutUrl)
            ->assertOk()
            ->assertSee('Finaliser la réservation');

        $this->post($checkoutUrl, [
            'provider' => 'pay_later',
        ])->assertRedirect($checkoutUrl);

        $this->assertDatabaseHas('payment_attempts', [
            'reservation_id' => $reservation->id,
            'provider' => 'pay_later',
            'status' => 'created',
        ]);

        $completeUrl = route('checkout.complete', ['reservation' => $reservation, 'token' => $reservation->checkout_token]);
        $this->post($completeUrl)
            ->assertRedirect($checkoutUrl);

        $this->assertDatabaseHas('reservations', ['id' => $reservation->id, 'status' => 'pending_payment']);
        $this->assertDatabaseHas('payment_attempts', ['reservation_id' => $reservation->id, 'provider' => 'pay_later', 'status' => 'created']);

        $attempt = $reservation->paymentAttempts()->latest()->firstOrFail();
        $webhookPayload = [
            'provider_reference' => $attempt->provider_reference,
            'status' => 'paid',
        ];
        $webhookBody = json_encode($webhookPayload, JSON_THROW_ON_ERROR);

        $this->postJson('/webhooks/pay_later', [
            'provider_reference' => 'unknown-reference',
            'status' => 'paid',
        ])->assertForbidden();

        $this->postJson('/webhooks/unknown-provider', [
            'provider_reference' => $attempt->provider_reference,
            'status' => 'paid',
        ])->assertNotFound();

        $this->assertDatabaseHas('payment_attempts', ['reservation_id' => $reservation->id, 'status' => 'created']);

        $this->call('POST', '/webhooks/pay_later', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_WEBHOOK_SIGNATURE' => hash_hmac('sha256', $webhookBody, config('services.payments.webhook_secret')),
        ], $webhookBody)->assertOk();

        $this->post($completeUrl)
            ->assertRedirect($checkoutUrl);

        $this->assertDatabaseHas('reservations', ['id' => $reservation->id, 'status' => 'confirmed']);
        $this->assertDatabaseHas('payment_attempts', ['reservation_id' => $reservation->id, 'provider' => 'pay_later', 'status' => 'paid']);

        $failureBody = json_encode([
            'provider_reference' => $attempt->provider_reference,
            'status' => 'failed',
        ], JSON_THROW_ON_ERROR);

        $this->call('POST', '/webhooks/pay_later', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_WEBHOOK_SIGNATURE' => hash_hmac('sha256', $failureBody, config('services.payments.webhook_secret')),
        ], $failureBody)->assertOk();

        $this->assertDatabaseHas('reservations', ['id' => $reservation->id, 'status' => 'confirmed']);
        $this->assertDatabaseHas('payment_attempts', ['reservation_id' => $reservation->id, 'status' => 'paid']);

        $unknownStatusBody = json_encode([
            'provider_reference' => $attempt->provider_reference,
            'status' => 'forged-status',
        ], JSON_THROW_ON_ERROR);

        $this->call('POST', '/webhooks/pay_later', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_WEBHOOK_SIGNATURE' => hash_hmac('sha256', $unknownStatusBody, config('services.payments.webhook_secret')),
        ], $unknownStatusBody)->assertOk();

        $this->assertDatabaseHas('payment_attempts', ['reservation_id' => $reservation->id, 'status' => 'paid']);
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

        $checkoutUrl = route('checkout.show', ['reservation' => $reservation, 'token' => $reservation->checkout_token]);
        $this->get($checkoutUrl)
            ->assertOk()
            ->assertSee('FedaPay sandbox')
            ->assertDontSee('Paiement différé');

        $this->post($checkoutUrl, ['provider' => 'pay_later'])
            ->assertStatus(422);
    }

    public function test_customer_can_cancel_an_unfinalized_reservation_from_checkout(): void
    {
        $this->seed(DatabaseSeeder::class);
        $property = Property::where('slug', 'appartement-401')->firstOrFail();
        $guest = ReservationGuest::create([
            'full_name' => 'Cancellation Guest',
            'email' => 'cancellation@example.com',
        ]);
        $reservation = Reservation::create([
            'property_id' => $property->id,
            'guest_id' => $guest->id,
            'reservation_ref' => 'AFK-CANCEL-001',
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

        $cancelUrl = route('checkout.cancel', ['reservation' => $reservation, 'token' => $reservation->checkout_token]);

        $this->post(route('checkout.cancel', ['reservation' => $reservation, 'token' => str_repeat('0', 64)]))
            ->assertForbidden();

        $this->post($cancelUrl)
            ->assertRedirect(route('checkout.show', ['reservation' => $reservation, 'token' => $reservation->checkout_token]));

        $this->assertDatabaseHas('reservations', ['id' => $reservation->id, 'status' => 'cancelled']);

        $this->post($cancelUrl)->assertStatus(422);
        $reservation->update(['status' => 'confirmed']);
        $this->post($cancelUrl)->assertStatus(422);
    }
}
