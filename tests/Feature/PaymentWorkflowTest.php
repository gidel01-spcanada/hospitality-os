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

    public function test_customer_cannot_simulate_offline_payment_verification_outside_dev_environment(): void
    {
        $this->seed(DatabaseSeeder::class);

        $property = Property::where('slug', 'appartement-401')->firstOrFail();
        $guest = ReservationGuest::query()->create([
            'full_name' => 'Bob Roe',
            'email' => 'bob@example.com',
            'metadata' => ['source' => 'test'],
        ]);
        $reservation = Reservation::query()->create([
            'property_id' => $property->id,
            'guest_id' => $guest->id,
            'reservation_ref' => 'AFK-PAY-002',
            'status' => 'pending',
            'check_in' => now()->addDay()->toDateString(),
            'check_out' => now()->addDays(2)->toDateString(),
            'adults' => 1,
            'children' => 0,
            'infants' => 0,
            'currency' => 'XOF',
            'email' => 'bob@example.com',
            'subtotal' => 40000,
            'fees' => 4000,
            'taxes' => 2000,
            'total_amount' => 46000,
            'source' => 'website',
        ]);
        $checkoutUrl = route('checkout.show', ['reservation' => $reservation, 'token' => $reservation->checkout_token]);

        $this->app->instance('env', 'production');

        $this->get($checkoutUrl)
            ->assertOk()
            ->assertDontSee(__('messages.checkout.simulate'));

        $this->post($checkoutUrl, ['provider' => 'pay_later'])->assertRedirect($checkoutUrl);

        $this->get($checkoutUrl)
            ->assertOk()
            ->assertDontSee(__('messages.checkout.simulate'))
            ->assertSee(__('messages.checkout.simulate_unavailable'));

        $completeUrl = route('checkout.complete', ['reservation' => $reservation, 'token' => $reservation->checkout_token]);
        $this->post($completeUrl)->assertForbidden();

        $this->assertDatabaseHas('reservations', ['id' => $reservation->id, 'status' => 'pending_payment']);
        $this->assertDatabaseMissing('payment_attempts', ['reservation_id' => $reservation->id, 'status' => 'paid']);
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
            ->assertSee('FedaPay')
            ->assertSee('type="hidden" name="provider" value="fedapay"', false)
            ->assertDontSee('id="provider"', false)
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

    public function test_cancellation_fee_applies_inside_the_establishment_window(): void
    {
        $this->seed(DatabaseSeeder::class);
        $property = Property::where('slug', 'appartement-401')->firstOrFail();
        $property->establishment->forceFill([
            'cancellation_fee_percent' => 30,
            'cancellation_fee_days' => 7,
        ])->save();

        $guest = ReservationGuest::create([
            'full_name' => 'Late Canceller',
            'email' => 'late-cancel@example.com',
        ]);

        $makeReservation = fn (string $ref, int $daysBeforeCheckIn) => Reservation::create([
            'property_id' => $property->id,
            'guest_id' => $guest->id,
            'reservation_ref' => $ref,
            'status' => 'pending',
            'check_in' => now()->addDays($daysBeforeCheckIn)->toDateString(),
            'check_out' => now()->addDays($daysBeforeCheckIn + 2)->toDateString(),
            'adults' => 2,
            'children' => 0,
            'infants' => 0,
            'currency' => 'XOF',
            'email' => $guest->email,
            'subtotal' => 100000,
            'fees' => 0,
            'taxes' => 0,
            'total_amount' => 100000,
            'source' => 'website',
        ]);

        $insideWindow = $makeReservation('AFK-CANCEL-FEE-001', 3);
        $this->post(route('checkout.cancel', ['reservation' => $insideWindow, 'token' => $insideWindow->checkout_token]))
            ->assertRedirect();

        $this->assertDatabaseHas('reservation_price_lines', [
            'reservation_id' => $insideWindow->id,
            'amount' => '30000.00',
        ]);

        $outsideWindow = $makeReservation('AFK-CANCEL-FEE-002', 30);
        $this->post(route('checkout.cancel', ['reservation' => $outsideWindow, 'token' => $outsideWindow->checkout_token]))
            ->assertRedirect();

        $this->assertDatabaseMissing('reservation_price_lines', ['reservation_id' => $outsideWindow->id]);
    }

    public function test_paypal_smart_buttons_create_and_capture_order(): void
    {
        $this->seed(DatabaseSeeder::class);
        $property = Property::where('slug', 'appartement-401')->firstOrFail();
        $property->establishment->forceFill([
            'payment_methods' => [
                'pay_later' => ['enabled' => true, 'mode' => 'production'],
                'paypal' => ['enabled' => true, 'mode' => 'sandbox'],
            ],
        ])->save();

        $guest = ReservationGuest::create([
            'full_name' => 'PayPal Guest',
            'email' => 'paypal@example.com',
        ]);

        $reservation = Reservation::create([
            'property_id' => $property->id,
            'guest_id' => $guest->id,
            'reservation_ref' => 'AFK-PAYPAL-001',
            'status' => 'pending',
            'check_in' => now()->addDays(5)->toDateString(),
            'check_out' => now()->addDays(8)->toDateString(),
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

        $createUrl = route('checkout.paypal.create', ['reservation' => $reservation, 'token' => $reservation->checkout_token]);
        $captureUrl = route('checkout.paypal.capture', ['reservation' => $reservation, 'token' => $reservation->checkout_token]);

        $response = $this->postJson($createUrl);
        $response->assertOk()->assertJsonStructure(['orderID', 'attempt_id']);
        $orderID = $response->json('orderID');

        $this->assertDatabaseHas('reservations', ['id' => $reservation->id, 'status' => 'pending_payment']);
        $this->assertDatabaseHas('payment_attempts', ['reservation_id' => $reservation->id, 'provider' => 'paypal', 'provider_reference' => $orderID]);

        $captureResponse = $this->postJson($captureUrl, ['orderID' => $orderID]);
        $captureResponse->assertOk()->assertJson(['status' => 'COMPLETED']);

        $this->assertDatabaseHas('reservations', ['id' => $reservation->id, 'status' => 'confirmed']);
        $this->assertDatabaseHas('payment_attempts', ['reservation_id' => $reservation->id, 'provider' => 'paypal', 'status' => 'paid']);
    }

    public function test_pay_later_requires_and_creates_guarantee_hold_when_cancellation_fee_configured(): void
    {
        $this->seed(DatabaseSeeder::class);
        $property = Property::where('slug', 'appartement-401')->firstOrFail();
        $property->establishment->forceFill([
            'cancellation_fee_percent' => 30,
            'cancellation_fee_days' => 7,
            'payment_methods' => [
                'pay_later' => ['enabled' => true, 'mode' => 'production'],
                'fedapay' => ['enabled' => true, 'mode' => 'sandbox'],
            ],
        ])->save();

        $guest = ReservationGuest::create([
            'full_name' => 'Pay On Site Guest',
            'email' => 'onsite@example.com',
        ]);

        $reservation = Reservation::create([
            'property_id' => $property->id,
            'guest_id' => $guest->id,
            'reservation_ref' => 'AFK-GUARANTEE-001',
            'status' => 'pending',
            'check_in' => now()->addDays(5)->toDateString(),
            'check_out' => now()->addDays(8)->toDateString(),
            'adults' => 2,
            'children' => 0,
            'infants' => 0,
            'currency' => 'XOF',
            'email' => $guest->email,
            'subtotal' => 100000,
            'fees' => 0,
            'taxes' => 0,
            'total_amount' => 100000,
            'source' => 'website',
        ]);

        $checkoutUrl = route('checkout.show', ['reservation' => $reservation, 'token' => $reservation->checkout_token]);

        $this->get($checkoutUrl)
            ->assertOk()
            ->assertSee('Garantie d’annulation requise');

        $this->post($checkoutUrl, [
            'provider' => 'pay_later',
            'guarantee_provider' => 'fedapay',
        ])->assertRedirect();

        $this->assertDatabaseHas('payment_attempts', [
            'reservation_id' => $reservation->id,
            'provider' => 'fedapay',
            'amount' => '30000.00',
            'status' => 'authorized',
        ]);
        $this->assertDatabaseHas('payment_attempts', [
            'reservation_id' => $reservation->id,
            'provider' => 'pay_later',
        ]);
    }
}
