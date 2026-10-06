<?php

namespace Tests\Feature;

use App\Models\EmailOutbox;
use App\Models\Property;
use App\Models\Reservation;
use App\Models\ReservationGuest;
use App\Models\User;
use App\Notifications\GuestAccountSetupNotification;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class PaymentWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_invoice_uses_saved_prices_and_distinct_establishment_property_photos(): void
    {
        $tenant = \App\Models\Tenant::firstOrCreate(['slug' => 'default'], ['name' => 'Default', 'status' => 'active']);
        $establishment = \App\Models\Establishment::create([
            'tenant_id' => $tenant->id, 'name' => 'Résidence Facture', 'slug' => 'residence-facture', 'currency' => 'XOF',
            'city' => 'Cotonou', 'address' => '12 Avenue Marina', 'country_code' => 'BJ', 'cover_image' => 'uploads/establishments/reception.jpg',
            'payment_methods' => ['pay_later' => ['enabled' => true, 'mode' => 'manual', 'instructions' => 'Paiement sur place.']],
        ]);
        $establishment->translations()->create(['locale' => 'en', 'name' => 'Invoice Residence']);
        $property = $establishment->properties()->create([
            'name' => 'Suite Facture', 'slug' => 'suite-facture', 'status' => 'published', 'is_active' => true, 'currency' => 'XOF',
            'nightly_rate_xof' => 99999, 'max_guests' => 4, 'bedrooms' => 2, 'beds' => 2, 'bathrooms' => 1,
        ]);
        $property->translations()->create(['locale' => 'en', 'name' => 'Invoice Suite']);
        $property->images()->create(['file_path' => 'uploads/properties/suite-header.jpg', 'file_name' => 'suite.jpg', 'is_cover' => true]);
        $customer = User::factory()->create(['role' => 'customer', 'locale' => 'fr', 'email_verified_at' => now(), 'last_login_at' => now()]);
        $guest = ReservationGuest::create(['full_name' => $customer->name, 'email' => $customer->email]);
        $reservation = Reservation::create([
            'property_id' => $property->id, 'user_id' => $customer->id, 'guest_id' => $guest->id,
            'reservation_ref' => 'AFK-SAVED-INVOICE', 'status' => 'pending_payment', 'email' => $customer->email,
            'check_in' => now()->addDays(30)->toDateString(), 'check_out' => now()->addDays(32)->toDateString(),
            'adults' => 2, 'children' => 1, 'infants' => 1, 'currency' => 'XOF',
            'subtotal' => 53000, 'fees' => 5000, 'taxes' => 1000, 'total_amount' => 56500,
        ]);
        $reservation->priceLines()->createMany([
            ['label' => 'Nuit(s) x 2', 'amount' => 50000, 'currency' => 'XOF'],
            ['label' => 'Option: Petit-déjeuner', 'amount' => 3000, 'currency' => 'XOF'],
            ['label' => 'Frais de service', 'amount' => 5000, 'currency' => 'XOF'],
            ['label' => 'TVA (18% inclue)', 'amount' => 8084.75, 'currency' => 'XOF'],
            ['label' => 'Taxe locale / séjour', 'amount' => 1000, 'currency' => 'XOF'],
            ['label' => 'Promotion réservée', 'amount' => -2500, 'currency' => 'XOF'],
        ]);
        $url = route('checkout.show', ['reservation' => $reservation, 'token' => $reservation->checkout_token]);
        $response = $this->actingAs($customer)->get($url)->assertOk()
            ->assertSee('Résidence Facture')->assertSee('Suite Facture')->assertSee('12 Avenue Marina')
            ->assertSee('2 nuit(s) × 25 000 XOF / nuit')->assertSee('Option: Petit-déjeuner')->assertSee('Promotion réservée')
            ->assertSee('8 084,75 XOF')->assertSee('56 500 XOF')->assertDontSee('99 999 XOF')
            ->assertSee(__('messages.checkout.review_before_payment'));
        $document = new \DOMDocument();
        @$document->loadHTML($response->getContent());
        $xpath = new \DOMXPath($document);
        $this->assertSame(asset('uploads/establishments/reception.jpg'), $xpath->query('//img[@class="checkout-establishment-image"]')->item(0)->getAttribute('src'));
        $this->assertSame(asset('uploads/properties/suite-header.jpg'), $xpath->query('//img[@class="checkout-property-image"]')->item(0)->getAttribute('src'));
        $this->assertSame(6, $xpath->query('//tr[@data-invoice-line]')->length);
        $this->assertSame('56500.00', $xpath->query('//*[@data-checkout-order-total]')->item(0)->getAttribute('data-amount'));
        $this->assertEquals(56500, (float) $reservation->fresh()->total_amount);

        $customer->update(['locale' => 'en']);
        $this->get($url)->assertOk()->assertSee('Invoice Residence')->assertSee('Invoice Suite')
            ->assertSee('2 night(s) × 25,000 XOF / night')->assertSee('56,500 XOF');
        $reservation->priceLines()->delete();
        $this->get($url)->assertOk()->assertSee(__('messages.checkout.saved_subtotal'))->assertSee('53,000 XOF')->assertSee('56,500 XOF')
            ->assertSee(__('messages.checkout.saved_adjustment'))->assertSee('-2,500 XOF');
        $reservation->update(['total_amount' => 56500.25]);
        $this->get($url)->assertOk()->assertSee('<strong>56,500.25 XOF</strong>', false);
        $reservation->update(['total_amount' => 56500]);
        $this->post($url, ['provider' => 'pay_later'])->assertRedirect();
        $this->assertDatabaseHas('payment_attempts', ['reservation_id' => $reservation->id, 'amount' => 56500, 'currency' => 'XOF', 'provider' => 'pay_later']);
    }

    public function test_invalid_checkout_link_redirects_to_account_access_recovery_page(): void
    {
        $this->seed(DatabaseSeeder::class);
        $property = Property::where('slug', 'appartement-401')->firstOrFail();
        $guest = ReservationGuest::query()->create(['full_name' => 'Bob Doe', 'email' => 'bob@example.com']);
        $reservation = Reservation::query()->create([
            'property_id' => $property->id, 'guest_id' => $guest->id, 'reservation_ref' => 'AFK-ACCESS-001',
            'status' => 'cancelled', 'check_in' => now()->addDay()->toDateString(), 'check_out' => now()->addDays(2)->toDateString(),
            'adults' => 1, 'children' => 0, 'infants' => 0, 'currency' => 'XOF', 'email' => 'bob@example.com',
            'subtotal' => 1000, 'fees' => 0, 'taxes' => 0, 'total_amount' => 1000, 'source' => 'website',
        ]);

        $this->get(route('checkout.show', ['reservation' => $reservation, 'token' => str_repeat('0', 64)]))
            ->assertRedirect(route('reservation.access'));

        $this->get(route('reservation.access'))
            ->assertOk()
            ->assertSee(__('messages.auth.sign_in'))
            ->assertSee(__('messages.auth.create_account'));
    }

    public function test_customer_can_skip_checkout_password_setup_and_continue_to_payment(): void
    {
        $this->seed(DatabaseSeeder::class);
        $property = Property::query()->where('slug', 'appartement-401')->firstOrFail();
        $customer = User::factory()->create([
            'email' => 'skip-checkout@example.com',
            'role' => 'customer',
            'email_verified_at' => null,
            'last_login_at' => null,
        ]);
        $reservation = Reservation::query()->create([
            'user_id' => $customer->id,
            'property_id' => $property->id,
            'reservation_ref' => 'AFK-SKIP-SETUP-001',
            'status' => 'pending_payment',
            'check_in' => now()->addDay()->toDateString(),
            'check_out' => now()->addDays(2)->toDateString(),
            'adults' => 1,
            'currency' => 'XOF',
            'email' => $customer->email,
            'total_amount' => 10000,
        ]);
        $checkoutUrl = route('checkout.show', ['reservation' => $reservation, 'token' => $reservation->checkout_token]);

        $this->get($checkoutUrl)
            ->assertOk()
            ->assertSee(__('messages.checkout.identity_title'))
            ->assertSee(__('messages.checkout.skip_password_setup'))
            ->assertDontSee('id="checkout-payment"', false);

        $this->post(route('checkout.skip-password-setup', $reservation), ['token' => $reservation->checkout_token])
            ->assertRedirect($checkoutUrl);

        $this->get($checkoutUrl)->assertOk()->assertSee('id="checkout-payment"', false);
    }

    public function test_checkout_password_setup_confirms_email_and_returns_to_payment(): void
    {
        \Illuminate\Support\Facades\Notification::fake();
        $this->seed(DatabaseSeeder::class);
        $property = Property::query()->where('slug', 'appartement-401')->firstOrFail();
        $customer = User::factory()->create([
            'email' => 'setup-checkout@example.com',
            'role' => 'customer',
            'email_verified_at' => null,
            'last_login_at' => null,
        ]);
        $reservation = Reservation::query()->create([
            'user_id' => $customer->id,
            'property_id' => $property->id,
            'reservation_ref' => 'AFK-SETUP-RETURN-001',
            'status' => 'pending_payment',
            'check_in' => now()->addDay()->toDateString(),
            'check_out' => now()->addDays(2)->toDateString(),
            'adults' => 1,
            'currency' => 'XOF',
            'email' => $customer->email,
            'total_amount' => 10000,
        ]);
        $checkoutUrl = route('checkout.show', ['reservation' => $reservation, 'token' => $reservation->checkout_token]);

        $this->post(route('checkout.password-setup', $reservation), ['token' => $reservation->checkout_token])
            ->assertRedirect();

        $setupMail = null;
        \Illuminate\Support\Facades\Notification::assertSentTo($customer, GuestAccountSetupNotification::class, function ($notification) use ($customer, &$setupMail): bool {
            $setupMail = $notification->toMail($customer);

            return true;
        });
        $resetUrl = $setupMail->actionUrl;
        parse_str((string) parse_url($resetUrl, PHP_URL_QUERY), $resetQuery);
        $resetToken = basename((string) parse_url($resetUrl, PHP_URL_PATH));

        $this->get($resetUrl)->assertOk()->assertSee('name="checkout_return"', false);
        $this->post(route('password.update'), [
            'token' => $resetToken,
            'email' => $customer->email,
            'checkout_return' => $resetQuery['checkout_return'],
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ])->assertRedirect($checkoutUrl);

        $this->assertNotNull($customer->fresh()->email_verified_at);
        $this->assertNotNull($customer->fresh()->last_login_at);
        $this->get($checkoutUrl)->assertOk()->assertSee('id="checkout-payment"', false);
    }

    public function test_checkout_keeps_last_selected_payment_method_accordion_open_by_default(): void
    {
        $this->seed(DatabaseSeeder::class);

        $property = Property::where('slug', 'appartement-401')->firstOrFail();
        $property->establishment->update([
            'payment_methods' => [
                'paypal' => ['enabled' => true, 'mode' => 'sandbox', 'instructions' => ''],
                'fedapay' => ['enabled' => true, 'mode' => 'sandbox', 'instructions' => ''],
                'pay_later' => ['enabled' => true, 'mode' => 'manual', 'instructions' => 'Pay on arrival.'],
            ],
        ]);
        $guest = ReservationGuest::query()->create([
            'full_name' => 'Charlie Doe',
            'email' => 'charlie@example.com',
            'phone' => '+229 11 11 11 11',
            'country' => 'Bénin',
            'metadata' => ['source' => 'test'],
        ]);
        $customer = User::factory()->create([
            'email' => 'charlie@example.com',
            'role' => 'customer',
            'email_verified_at' => now(),
            'last_login_at' => now(),
        ]);

        $reservation = Reservation::query()->create([
            'user_id' => $customer->id,
            'property_id' => $property->id,
            'guest_id' => $guest->id,
            'reservation_ref' => 'AFK-PAY-OPEN-001',
            'status' => 'pending_payment',
            'check_in' => now()->addDay()->toDateString(),
            'check_out' => now()->addDays(2)->toDateString(),
            'adults' => 2,
            'children' => 0,
            'infants' => 0,
            'currency' => 'XOF',
            'email' => 'charlie@example.com',
            'subtotal' => 100000,
            'fees' => 10000,
            'taxes' => 5000,
            'total_amount' => 115000,
            'source' => 'website',
        ]);

        $reservation->paymentAttempts()->create([
            'provider' => 'fedapay',
            'provider_reference' => 'fedapay-123',
            'currency' => 'XOF',
            'amount' => 115000,
            'status' => 'created',
            'idempotency_key' => 'attempt-fedapay-1',
            'payload' => ['mode' => 'sandbox'],
        ]);

        $response = $this->get(route('checkout.show', ['reservation' => $reservation, 'token' => $reservation->checkout_token]))
            ->assertOk()
            ->assertSee('name="payment-method-accordion" open', false)
            ->assertSee(__('messages.checkout.fedapay'), false);

        $this->assertSame(1, substr_count($response->getContent(), 'name="payment-method-accordion" open'));
    }

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
            ->assertRedirect(route('reservation.access'));

        $this->get(route('checkout.show', ['reservation' => $reservation, 'token' => str_repeat('0', 64)]))
            ->assertRedirect(route('reservation.access'));

        $this->withSession(['checkout_identity_skipped' => [$reservation->id]])
            ->get($checkoutUrl)
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

        $this->withSession(['checkout_identity_skipped' => [$reservation->id]])
            ->get($checkoutUrl)
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
        $this->withSession(['checkout_identity_skipped' => [$reservation->id]])
            ->get($checkoutUrl)
            ->assertOk()
            ->assertSee('FedaPay')
            ->assertSee('type="hidden" name="provider" value="fedapay"', false)
            ->assertDontSee('id="provider"', false)
            ->assertDontSee('Paiement différé');

        $this->post($checkoutUrl, ['provider' => 'pay_later'])
            ->assertStatus(422);
    }

    public function test_checkout_shows_wise_procedure_details_even_as_sole_payment_method(): void
    {
        $this->seed(DatabaseSeeder::class);
        $property = Property::where('slug', 'appartement-401')->with('establishment')->firstOrFail();
        $property->establishment->update([
            'secondary_currency' => 'EUR',
            'secondary_currency_rate' => 650.0,
            'payment_methods' => [
                'pay_later' => ['enabled' => false, 'instructions' => ''],
                'paypal' => ['enabled' => false, 'instructions' => ''],
                'wise' => ['enabled' => true, 'instructions' => '', 'email' => 'wise@afrikappart.test'],
            ],
        ]);
        $guest = ReservationGuest::create(['full_name' => 'Wise Guest', 'email' => 'wise-guest@example.com']);
        $reservation = Reservation::create([
            'property_id' => $property->id, 'guest_id' => $guest->id, 'reservation_ref' => 'AFK-PAY-WISE',
            'status' => 'pending', 'check_in' => now()->addDay()->toDateString(), 'check_out' => now()->addDays(3)->toDateString(),
            'adults' => 2, 'children' => 0, 'infants' => 0, 'currency' => 'XOF', 'email' => $guest->email,
            'subtotal' => 100000, 'fees' => 10000, 'taxes' => 5000, 'total_amount' => 115000, 'source' => 'website',
        ]);

        $this->withSession(['checkout_identity_skipped' => [$reservation->id]])
            ->get(route('checkout.show', ['reservation' => $reservation, 'token' => $reservation->checkout_token]))
            ->assertOk()
            ->assertSee(__('messages.checkout.wise_email', ['email' => 'wise@afrikappart.test']));
    }

    public function test_guest_can_submit_offline_payment_proof_and_host_is_notified(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        $this->seed(DatabaseSeeder::class);
        $property = Property::where('slug', 'appartement-401')->with('establishment')->firstOrFail();
        $property->establishment->update([
            'secondary_currency' => 'EUR',
            'secondary_currency_rate' => 650.0,
            'payment_methods' => [
                'pay_later' => ['enabled' => false, 'instructions' => ''],
                'paypal' => ['enabled' => false, 'instructions' => ''],
                'wise' => ['enabled' => true, 'instructions' => '', 'email' => 'wise@afrikappart.test'],
            ],
        ]);
        $guest = ReservationGuest::create(['full_name' => 'Proof Guest', 'email' => 'proof-guest@example.com']);
        $customer = User::factory()->create(['name' => 'Proof Guest', 'email' => $guest->email, 'role' => 'customer']);
        $reservation = Reservation::create([
            'property_id' => $property->id, 'guest_id' => $guest->id, 'reservation_ref' => 'AFK-PAY-PROOF',
            'status' => 'pending', 'check_in' => now()->addDay()->toDateString(), 'check_out' => now()->addDays(3)->toDateString(),
            'adults' => 2, 'children' => 0, 'infants' => 0, 'currency' => 'XOF', 'email' => $guest->email,
            'subtotal' => 100000, 'fees' => 10000, 'taxes' => 5000, 'total_amount' => 115000, 'source' => 'website',
        ]);

        $checkoutUrl = route('checkout.show', ['reservation' => $reservation, 'token' => $reservation->checkout_token]);
        $offlineProofUrl = route('checkout.offline-proof', ['reservation' => $reservation, 'token' => $reservation->checkout_token]);

        $this->withSession(['checkout_identity_skipped' => [$reservation->id]])
            ->get($checkoutUrl)
            ->assertOk()
            ->assertSee(__('messages.checkout.i_have_paid'));

        $this->post($offlineProofUrl, [
            'provider' => 'wise',
            'payment_proof' => UploadedFile::fake()->create('receipt.pdf', 100, 'application/pdf'),
            'provider_reference' => ' WISE-TRANSFER-789 ',
        ])->assertRedirect($checkoutUrl);

        $this->assertDatabaseHas('reservations', ['id' => $reservation->id, 'status' => 'pending_validation']);
        $this->assertDatabaseHas('payment_attempts', ['reservation_id' => $reservation->id, 'provider' => 'wise', 'status' => 'awaiting_validation']);

        $attempt = $reservation->paymentAttempts()->where('provider', 'wise')->firstOrFail();
        $this->assertSame('WISE-TRANSFER-789', $attempt->provider_reference);
        $this->assertStringStartsWith('payment-proofs/' . $reservation->id . '/', data_get($attempt->payload, 'payment_proof.path'));
        \Illuminate\Support\Facades\Storage::disk('local')->assertExists(data_get($attempt->payload, 'payment_proof.path'));
        $proofUrl = route('reservations.payment-proof.download', ['reservation' => $reservation, 'attempt' => $attempt]);
        $this->get(route('reservations.payment-proof.download', [
            'reservation' => $reservation,
            'attempt' => $attempt,
            'token' => $reservation->checkout_token,
        ]))->assertOk()->assertHeader('Content-Disposition', 'inline; filename=receipt.pdf');
        $this->get($proofUrl)->assertForbidden();

        $this->actingAs($customer)
            ->get(route('dashboard.reservations.show', $reservation))
            ->assertOk()
            ->assertSee($proofUrl, false);
        $this->actingAs($customer)->get($proofUrl)->assertOk();

        $tenantId = $property->establishment->tenant_id;
        $assignedHost = User::factory()->create(['role' => 'host', 'tenant_id' => $tenantId]);
        $assignedHost->establishments()->attach($property->establishment);
        $otherHost = User::factory()->create(['role' => 'host', 'tenant_id' => $tenantId]);
        $this->actingAs($assignedHost)->get($proofUrl)->assertOk();
        $this->actingAs($otherHost)->get($proofUrl)->assertForbidden();

        $legacyRoot = storage_path('framework/testing/legacy-web-root/uploads');
        config(['filesystems.public_upload_path' => $legacyRoot]);
        \Illuminate\Support\Facades\File::ensureDirectoryExists($legacyRoot . '/reservations/' . $reservation->id);
        file_put_contents($legacyRoot . '/reservations/' . $reservation->id . '/legacy.pdf', '%PDF-1.4 legacy');
        $legacyAttempt = $reservation->paymentAttempts()->create([
            'provider' => 'wise', 'provider_reference' => 'legacy-proof', 'idempotency_key' => 'legacy-proof', 'currency' => 'XOF',
            'amount' => 1, 'status' => 'awaiting_validation',
            'payload' => ['payment_proof' => ['path' => 'uploads/reservations/' . $reservation->id . '/legacy.pdf', 'original_name' => 'legacy.pdf']],
        ]);
        $this->actingAs($assignedHost)->get(route('reservations.payment-proof.download', ['reservation' => $reservation, 'attempt' => $legacyAttempt]))->assertOk();
        \Illuminate\Support\Facades\File::deleteDirectory(storage_path('framework/testing/legacy-web-root'));
        $this->actingAs($customer);

        $admin = User::where('email', 'admin@afrikappart.test')->firstOrFail();
        $this->assertDatabaseHas('email_outbox', [
            'recipient_email' => $admin->email,
            'template' => 'payment_proof_submitted',
            'status' => 'queued',
        ]);

        $this->get($checkoutUrl)
            ->assertOk()
            ->assertSee(__('messages.checkout.awaiting_validation'))
            ->assertDontSee(__('messages.checkout.i_have_paid'));

        $this->actingAs($admin)->get(route('admin.reservations.show', $reservation))
            ->assertOk()
                ->assertSee(route('reservations.payment-proof.download', ['reservation' => $reservation, 'attempt' => $attempt]), false);
    }

    public function test_only_staff_can_simulate_payment_in_production(): void
    {
        $this->seed(DatabaseSeeder::class);
        $property = Property::where('slug', 'appartement-401')->firstOrFail();
        $guest = ReservationGuest::create(['full_name' => 'Staff Sim Guest', 'email' => 'staff-sim@example.com']);
        $reservation = Reservation::create([
            'property_id' => $property->id, 'guest_id' => $guest->id, 'reservation_ref' => 'AFK-PAY-SIM',
            'status' => 'pending', 'check_in' => now()->addDay()->toDateString(), 'check_out' => now()->addDays(3)->toDateString(),
            'adults' => 2, 'children' => 0, 'infants' => 0, 'currency' => 'XOF', 'email' => $guest->email,
            'subtotal' => 100000, 'fees' => 10000, 'taxes' => 5000, 'total_amount' => 115000, 'source' => 'website',
        ]);
        $checkoutUrl = route('checkout.show', ['reservation' => $reservation, 'token' => $reservation->checkout_token]);
        $this->post($checkoutUrl, ['provider' => 'pay_later'])->assertRedirect($checkoutUrl);

        $this->app->instance('env', 'production');
        $admin = User::query()->where('email', 'admin@afrikappart.test')->firstOrFail();

        $this->actingAs($admin)->get($checkoutUrl)
            ->assertOk()
            ->assertSee(__('messages.checkout.simulate'));

        $completeUrl = route('checkout.complete', ['reservation' => $reservation, 'token' => $reservation->checkout_token]);
        $this->actingAs($admin)->post($completeUrl)->assertRedirect($checkoutUrl);

        $this->assertDatabaseHas('reservations', ['id' => $reservation->id, 'status' => 'pending_payment']);
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

        $this->withSession(['checkout_identity_skipped' => [$reservation->id]])
            ->get($checkoutUrl)
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

    public function test_admin_can_generate_receipt_for_confirmed_reservation_without_one(): void
    {
        $this->seed(DatabaseSeeder::class);
        $property = Property::where('slug', 'appartement-401')->firstOrFail();
        $guest = ReservationGuest::create(['full_name' => 'No Receipt Guest', 'email' => 'no-receipt@example.com']);
        $reservation = Reservation::create([
            'property_id' => $property->id, 'guest_id' => $guest->id, 'reservation_ref' => 'AFK-GEN-RECEIPT-001',
            'status' => 'confirmed', 'check_in' => now()->addDay()->toDateString(), 'check_out' => now()->addDays(3)->toDateString(),
            'adults' => 2, 'children' => 0, 'infants' => 0, 'currency' => 'XOF', 'email' => $guest->email,
            'subtotal' => 100000, 'fees' => 10000, 'taxes' => 5000, 'total_amount' => 115000, 'source' => 'website',
        ]);
        $reservation->paymentAttempts()->create([
            'provider' => 'offline', 'provider_reference' => 'offline-gen-1', 'currency' => 'XOF',
            'amount' => 115000, 'status' => 'paid', 'idempotency_key' => 'attempt-offline-gen-1', 'payload' => [],
        ]);

        $admin = User::where('email', 'admin@afrikappart.test')->firstOrFail();

        $this->actingAs($admin)->get(route('admin.reservations.show', $reservation))
            ->assertOk()
            ->assertSee(__('messages.receipts.generate'));

        $this->actingAs($admin)
            ->post(route('admin.reservations.receipt.generate', $reservation))
            ->assertRedirect(route('admin.reservations.show', $reservation));

        $this->assertDatabaseHas('receipts', ['reservation_id' => $reservation->id]);

        $this->actingAs($admin)->get(route('admin.reservations.show', $reservation))
            ->assertOk()
            ->assertDontSee(__('messages.receipts.generate'));
    }

    public function test_receipt_can_be_generated_from_a_guest_payment_proof(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        $this->seed(DatabaseSeeder::class);
        $property = Property::where('slug', 'appartement-401')->firstOrFail();
        $admin = User::where('email', 'admin@afrikappart.test')->firstOrFail();
        $proofService = app(\App\Services\PaymentProofService::class);

        foreach (['generate', 'status'] as $index => $action) {
            $guest = ReservationGuest::create(['full_name' => 'Proof Receipt Guest', 'email' => "proof-receipt-{$action}@example.com"]);
            $reservation = Reservation::create([
                'property_id' => $property->id, 'guest_id' => $guest->id, 'reservation_ref' => 'AFK-PROOF-RCT-' . $index,
                'status' => 'pending_validation', 'check_in' => now()->addDays(10 + $index * 5)->toDateString(), 'check_out' => now()->addDays(12 + $index * 5)->toDateString(),
                'adults' => 2, 'currency' => 'XOF', 'email' => $guest->email,
                'subtotal' => 100000, 'total_amount' => 100000, 'source' => 'website',
            ]);
            $attempt = $reservation->paymentAttempts()->create([
                'provider' => 'wise', 'provider_reference' => 'wise-proof-' . $action, 'idempotency_key' => 'wise-proof-' . $action,
                'currency' => 'XOF', 'amount' => 100000, 'status' => 'awaiting_validation', 'payload' => [],
            ]);
            $proofService->attach($reservation, $attempt, UploadedFile::fake()->create('proof.pdf', 10, 'application/pdf'), 'guest');

            $request = $this->actingAs($admin);
            $response = $action === 'generate'
                ? $request->post(route('admin.reservations.receipt.generate', $reservation), ['provider_reference' => 'ADMIN-REF-1'])
                : $request->patch(route('admin.reservations.update-status', $reservation), ['status' => 'confirmed']);
            $response->assertRedirect(route('admin.reservations.show', $reservation));

            $this->assertSame('paid', $attempt->fresh()->status);
            $this->assertSame('confirmed', $reservation->fresh()->status);
            $this->assertDatabaseHas('receipts', ['reservation_id' => $reservation->id, 'payment_attempt_id' => $attempt->id]);
            $this->assertDatabaseHas('email_outbox', ['recipient_email' => $guest->email, 'template' => 'receipt_issued']);
            $this->assertSame('0.00', \App\Support\ReservationSummary::make($reservation->fresh())['due']);
            $this->assertSame($action === 'generate' ? 'ADMIN-REF-1' : 'wise-proof-status', $attempt->fresh()->provider_reference);

            $this->actingAs($admin)->patch(route('admin.reservations.payment-reference.update', ['reservation' => $reservation, 'attempt' => $attempt]), ['provider_reference' => ' FIXED-REF-' . $index . ' '])
                ->assertRedirect(route('admin.reservations.show', $reservation));
            $this->assertSame('FIXED-REF-' . $index, $attempt->fresh()->provider_reference);
            $this->actingAs($admin)->get(route('reservations.receipt', $reservation))->assertOk()->assertHeader('Content-Type', 'application/pdf');
            $receiptHtml = view('receipts.show', ['receipt' => $reservation->receipts()->firstOrFail(), 'reservation' => $reservation->fresh()])->render();
            foreach (['FIXED-REF-' . $index, $reservation->reservation_ref, __('messages.transactional.status_paid'), __('messages.transactional.paid_confirmed'), __('messages.transactional.transaction_number'), $guest->email] as $expected) {
                $this->assertStringContainsString(e($expected), $receiptHtml);
            }
            $this->actingAs($admin)->get(route('admin.reservations.show', $reservation))->assertOk()->assertSee('value="FIXED-REF-' . $index . '"', false);
            $previousReservation ??= $reservation;
            if ($previousReservation->isNot($reservation)) {
                $this->actingAs($admin)->patch(route('admin.reservations.payment-reference.update', ['reservation' => $previousReservation, 'attempt' => $attempt]), ['provider_reference' => 'WRONG'])->assertNotFound();
            }
        }
    }

    public function test_cancelling_a_paid_offline_reservation_requires_a_refund_proof_except_cash(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        $this->seed(DatabaseSeeder::class);
        $property = Property::where('slug', 'appartement-401')->firstOrFail();
        $admin = User::where('email', 'admin@afrikappart.test')->firstOrFail();

        foreach (['wise', 'pay_later'] as $index => $provider) {
            $customer = User::factory()->create(['role' => 'customer', 'email' => "refund-{$provider}@example.com"]);
            $reservation = Reservation::create([
                'property_id' => $property->id, 'user_id' => $customer->id, 'reservation_ref' => 'AFK-REFUND-' . $index,
                'status' => 'confirmed', 'check_in' => now()->addDays(20 + $index * 5)->toDateString(), 'check_out' => now()->addDays(22 + $index * 5)->toDateString(),
                'adults' => 1, 'currency' => 'XOF', 'email' => $customer->email, 'subtotal' => 50000, 'total_amount' => 50000, 'source' => 'website',
            ]);
            $attempt = $reservation->paymentAttempts()->create([
                'provider' => $provider, 'provider_reference' => 'refund-' . $provider, 'idempotency_key' => 'refund-' . $provider,
                'currency' => 'XOF', 'amount' => 50000, 'status' => 'paid', 'payload' => [],
            ]);
            $statusUrl = route('admin.reservations.update-status', $reservation);

            if ($provider === 'pay_later') {
                $this->actingAs($admin)->patch($statusUrl, ['status' => 'cancelled'])->assertSessionHasNoErrors();
                $this->assertSame('cancelled', $reservation->fresh()->status);
                $this->assertSame('paid', $attempt->fresh()->status);
                continue;
            }

            $this->actingAs($admin)->get(route('admin.reservations.show', $reservation))->assertOk()->assertSee('data-refund-proof-fields', false);
            $this->actingAs($admin)->from(route('admin.reservations.show', $reservation))->patch($statusUrl, ['status' => 'cancelled'])
                ->assertSessionHasErrors('refund_proof');
            $this->assertSame('confirmed', $reservation->fresh()->status);

            $this->actingAs($admin)->patch($statusUrl, [
                'status' => 'cancelled',
                'refund_proof' => UploadedFile::fake()->create('refund.pdf', 10, 'application/pdf'),
                'refund_reference' => 'REFUND-123',
            ])->assertRedirect(route('admin.reservations.show', $reservation))->assertSessionHasNoErrors();

            $attempt->refresh();
            $this->assertSame('cancelled', $reservation->fresh()->status);
            $this->assertSame('refunded', $attempt->status);
            $this->assertSame('REFUND-123', data_get($attempt->payload, 'refund_reference'));
            $refundUrl = route('reservations.payment-proof.download', ['reservation' => $reservation, 'attempt' => $attempt, 'type' => 'refund']);
            $this->actingAs($customer)->get(route('dashboard.reservations.show', $reservation))->assertOk()->assertSee($refundUrl, false);
            $this->actingAs($customer)->get($refundUrl)->assertOk()->assertHeader('Content-Disposition', 'inline; filename=refund.pdf');
            $summary = \App\Support\ReservationSummary::make($reservation->fresh());
            $this->assertSame('50000.00', $summary['refunded']);
            $payload = \App\Models\EmailOutbox::where('template', 'reservation_status_updated')->where('recipient_email', $customer->email)->latest('id')->firstOrFail()->payload;
            $payload['reservation_summary'] = $summary;
            $this->assertStringContainsString('data-email-refunded', view('emails.reservation-status-updated', $payload)->render());
        }
    }
}
