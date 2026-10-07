<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Artisan;
use App\Models\Establishment;
use App\Models\ExternalCalendarEvent;
use App\Models\Amenity;
use App\Models\ExternalCalendarFeed;
use App\Models\Property;
use App\Models\Reservation;
use App\Models\SiteReview;
use App\Models\User;
use App\Notifications\GuestAccountSetupNotification;
use App\Support\BrandSettings;
use Database\Seeders\DatabaseSeeder;
use Tests\TestCase;
use Illuminate\Support\Facades\Notification;

class PublicPropertyBookingTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_check_availability_without_name_or_email(): void
    {
        $this->seed(DatabaseSeeder::class);
        $property = Property::query()->where('status', 'published')->firstOrFail();
        $checkIn = now()->addMonths(2)->startOfMonth();
        $checkOut = $checkIn->copy()->addDays(2);

        $response = $this->postJson('/properties/' . $property->slug . '/availability', [
            'check_in' => $checkIn->toDateString(),
            'check_out' => $checkOut->toDateString(),
            'adults' => 2,
            'children' => 1,
        ]);

        $response->assertOk()->assertJson(['available' => true])->assertHeader('Cache-Control', 'no-store, private');
        $breakdown = $response->json('breakdown');
        $additiveTaxes = collect($breakdown['tax_lines'])->reject(fn ($line) => $line['included'])->sum('amount');

        $this->assertSame(2, $breakdown['nights']);
        $this->assertEquals((float) $property->nightly_rate_xof, $breakdown['nightly_rate']);
        $this->assertEquals($response->json('estimated_total'), $breakdown['total_amount']);
        $this->assertEquals(
            $breakdown['room_subtotal'] + collect($breakdown['extra_lines'])->sum('amount') + $breakdown['fees'] + $additiveTaxes - $breakdown['discount_total'],
            $breakdown['total_amount']
        );
        $this->assertSame('XOF', $breakdown['currency']);
    }

    public function test_property_detail_revalidates_search_dates_and_handles_expired_links(): void
    {
        $this->seed(DatabaseSeeder::class);
        $property = Property::query()->where('slug', 'appartement-401')->firstOrFail();
        $checkIn = now()->addDays(30)->toDateString();
        $checkOut = now()->addDays(32)->toDateString();
        $url = route('properties.show', $property) . '?' . http_build_query([
            'check_in' => $checkIn,
            'check_out' => $checkOut,
            'guests' => 2,
        ]);

        $this->get($url)
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertSee('data-availability-state="available"', false)
            ->assertSee(__('messages.properties.availability_available'))
            ->assertSee('57 500 XOF')
            ->assertSee('data-validated-stay-total-value', false)
            ->assertSee('data-pricing-final-total', false)
            ->assertSee(__('messages.properties.night_count'))
            ->assertSee(__('messages.properties.nightly_rate'))
            ->assertSee('25 000 XOF')
            ->assertSee(__('messages.properties.accommodation_subtotal'))
            ->assertSee(__('messages.properties.fees'))
            ->assertSee(__('messages.pricing.city_tax'))
            ->assertSee(__('messages.properties.final_total'));

        Reservation::query()->create([
            'property_id' => $property->id,
            'reservation_ref' => 'AFK-AVAILABILITY-REFRESH',
            'status' => 'confirmed',
            'check_in' => $checkIn,
            'check_out' => $checkOut,
            'adults' => 2,
            'currency' => 'XOF',
            'email' => 'availability@example.com',
            'total_amount' => 50000,
        ]);

        $this->get($url)
            ->assertOk()
            ->assertSee('data-availability-state="unavailable"', false)
            ->assertSee(__('messages.properties.availability_unavailable_reselect'));

        $expiredCheckIn = now()->subDays(3)->toDateString();
        $expiredCheckOut = now()->subDay()->toDateString();
        $this->get(route('properties.show', $property) . '?' . http_build_query([
            'check_in' => $expiredCheckIn,
            'check_out' => $expiredCheckOut,
        ]))
            ->assertOk()
            ->assertSee('data-availability-state="expired"', false)
            ->assertSee(__('messages.properties.availability_dates_expired'))
            ->assertSee('name="check_in" value="' . $expiredCheckIn . '"', false);
    }

    public function test_existing_customer_email_requires_login_before_reservation_checkout(): void
    {
        $this->seed(DatabaseSeeder::class);
        $property = Property::query()->where('status', 'published')->firstOrFail();

        $this->post('/properties/' . $property->slug . '/reserve', [
            'full_name' => 'Guest Customer',
            'email' => 'guest@afrikappart.test',
            'check_in' => now()->addDay()->toDateString(),
            'check_out' => now()->addDays(3)->toDateString(),
            'adults' => 2,
            'children' => 0,
            'infants' => 0,
        ])->assertRedirect(route('login'))
            ->assertSessionHas('pending_public_reservation');

        $this->assertDatabaseMissing('reservations', ['email' => 'guest@afrikappart.test']);
        $this->post(route('login'), ['email' => 'guest@afrikappart.test', 'password' => 'Password123!'])->assertRedirect(route('reservation.resume'));
        $this->get(route('reservation.resume'))->assertRedirect();
        $reservation = Reservation::where('email', 'guest@afrikappart.test')->firstOrFail();
        $this->assertFalse($reservation->account_setup_deferred);
        $this->finishSavedReservationPayment($reservation);
    }

    public function test_authenticated_customer_can_reserve_without_reentering_name_or_email(): void
    {
        Notification::fake();
        $this->seed(DatabaseSeeder::class);
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $property = Property::query()->where('status', 'published')->firstOrFail();
        $customer = User::factory()->create([
            'name' => 'Amina Customer',
            'email' => 'amina@example.com',
            'role' => 'customer',
            'email_verified_at' => now(),
        ]);

        $response = $this->actingAs($customer)->post('/properties/' . $property->slug . '/reserve', [
            'check_in' => now()->addDay()->toDateString(),
            'check_out' => now()->addDays(3)->toDateString(),
            'adults' => 1,
            'children' => 0,
            'infants' => 0,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('reservations', [
            'email' => 'amina@example.com',
            'user_id' => $customer->id,
        ]);
        $this->assertDatabaseHas('reservation_guests', [
            'email' => 'amina@example.com',
            'full_name' => 'Amina Customer',
        ]);
    }

    public function test_unverified_existing_account_can_book_and_reach_checkout_without_activation(): void
    {
        Notification::fake();
        $this->seed(DatabaseSeeder::class);
        $property = Property::query()->where('status', 'published')->firstOrFail();
        $unverified = User::factory()->create([
            'email' => 'unverified@example.com',
            'role' => 'customer',
            'tenant_id' => $property->establishment?->tenant_id,
            'email_verified_at' => null,
        ]);

        $response = $this->withSession(['pending_public_reservation' => ['property_id' => $property->id, 'data' => ['email' => $unverified->email]]])
            ->post('/properties/' . $property->slug . '/reserve', [
            'full_name' => 'Unverified Customer',
            'email' => 'unverified@example.com',
            'check_in' => now()->addDay()->toDateString(),
            'check_out' => now()->addDays(3)->toDateString(),
            'adults' => 2,
            'children' => 0,
            'infants' => 0,
            'create_account' => false,
        ]);

        $response->assertRedirect();
        $this->assertNotSame(route('login'), $response->headers->get('Location'));
        $reservation = Reservation::where('email', $unverified->email)->firstOrFail();
        $response->assertRedirect(route('checkout.show', ['reservation' => $reservation, 'token' => $reservation->checkout_token]));
        $response->assertSessionMissing('pending_public_reservation');

        $this->assertSame($unverified->id, $reservation->user_id);
        $this->assertTrue($reservation->account_setup_deferred);
        $this->assertNull($unverified->fresh()->email_verified_at);
        $this->assertSame(1, User::where('email', $unverified->email)->count());
        $this->assertGuest();
        $this->get($response->headers->get('Location'))->assertOk()->assertViewIs('checkout.show')
            ->assertViewHas('accountActivationRequired', true)->assertSee('id="checkout-payment"', false)
            ->assertSee(__('messages.checkout.activation_title'))->assertSee(__('messages.checkout.activation_continue'))
            ->assertDontSee(__('messages.checkout.identity_title'));
        Notification::assertSentTo($unverified, GuestAccountSetupNotification::class);
        $checkoutUrl = route('checkout.show', ['reservation' => $reservation, 'token' => $reservation->checkout_token]);
        $this->flushSession();
        $this->get($checkoutUrl)->assertOk()->assertViewIs('checkout.show')->assertSee(__('messages.checkout.activation_title'));
        $this->from($checkoutUrl)->post(route('checkout.password-setup', ['reservation' => $reservation, 'token' => $reservation->checkout_token]))
            ->assertRedirect($checkoutUrl)->assertSessionHasNoErrors();
        $this->assertSame(2, Notification::sent($unverified, GuestAccountSetupNotification::class)->count());
        $this->assertSame(1, User::where('email', $unverified->email)->count());
        $activationUrl = Notification::sent($unverified, GuestAccountSetupNotification::class)->last()->toMail($unverified)->actionUrl;
        parse_str(parse_url($activationUrl, PHP_URL_QUERY), $activationQuery);
        $this->assertSame($checkoutUrl, $activationQuery['checkout_return']);

        $this->finishSavedReservationPayment($reservation);
        $this->assertNull($unverified->fresh()->email_verified_at);
        $this->get($checkoutUrl)->assertOk()->assertSee(__('messages.checkout.activation_title'))
            ->assertSee(__('messages.checkout.activation_after_payment'));
        $this->get($activationUrl)->assertOk()->assertSee('name="checkout_return"', false);
        $this->post(route('password.update'), [
            'token' => basename(parse_url($activationUrl, PHP_URL_PATH)),
            'email' => $unverified->email,
            'password' => 'ActivatedPassword123!',
            'password_confirmation' => 'ActivatedPassword123!',
            'checkout_return' => $activationQuery['checkout_return'],
        ])->assertRedirect($checkoutUrl)->assertSessionHasNoErrors();
        $this->assertNotNull($unverified->fresh()->email_verified_at);
        $this->get($checkoutUrl)->assertOk()->assertDontSee('data-account-activation-notice', false);
        $this->post(route('login'), ['email' => $unverified->email, 'password' => 'ActivatedPassword123!'])->assertRedirect(route('dashboard'));
        $this->get(route('dashboard'))->assertOk()->assertSee($reservation->reservation_ref);
        $this->assertSame(1, Reservation::where('email', $unverified->email)->count());
    }

    public function test_new_guest_continues_to_checkout_and_activates_account_later(): void
    {
        Notification::fake();
        $this->seed(DatabaseSeeder::class);
        $property = Property::query()->where('status', 'published')->firstOrFail();

        $response = $this->post('/properties/' . $property->slug . '/reserve', [
            'full_name' => 'New Guest',
            'email' => 'new-guest@example.com',
            'check_in' => now()->addDay()->toDateString(),
            'check_out' => now()->addDays(3)->toDateString(),
            'adults' => 1,
            'children' => 0,
            'infants' => 0,
        ]);

        $account = User::query()->where('email', 'new-guest@example.com')->firstOrFail();
        $reservation = Reservation::where('email', $account->email)->firstOrFail();
        $checkoutUrl = route('checkout.show', ['reservation' => $reservation, 'token' => $reservation->checkout_token]);
        $response->assertRedirect($checkoutUrl)->assertSessionMissing('pending_public_reservation');
        $this->assertNull($account->email_verified_at);
        $this->assertSame($account->id, $reservation->user_id);
        $this->assertTrue($reservation->account_setup_deferred);
        $this->get($checkoutUrl)->assertOk()->assertViewIs('checkout.show')->assertSee('id="checkout-payment"', false);
        Notification::assertSentTo($account, GuestAccountSetupNotification::class);
        $activationUrl = Notification::sent($account, GuestAccountSetupNotification::class)->first()->toMail($account)->actionUrl;
        $activationMail = Notification::sent($account, GuestAccountSetupNotification::class)->first()->toMail($account);
        $this->assertStringContainsString($reservation->reservation_ref, $activationMail->subject);
        $activationHtml = (string) $activationMail->render();
        $this->assertStringContainsString(__('messages.account_setup.reservation_number'), html_entity_decode($activationHtml));
        $this->assertMatchesRegularExpression('/data-account-reservation-reference[^>]*>' . preg_quote($reservation->reservation_ref, '/') . '</', $activationHtml);
        parse_str(parse_url($activationUrl, PHP_URL_QUERY), $activationQuery);
        $this->assertSame($checkoutUrl, $activationQuery['checkout_return']);
        $this->finishSavedReservationPayment($reservation);
        $this->post(route('password.update'), [
            'token' => basename(parse_url($activationUrl, PHP_URL_PATH)), 'email' => $account->email,
            'password' => 'NewGuestPassword123!', 'password_confirmation' => 'NewGuestPassword123!',
            'checkout_return' => $activationQuery['checkout_return'],
        ])->assertRedirect($checkoutUrl)->assertSessionHasNoErrors();
        $this->get($checkoutUrl)->assertOk()
            ->assertSee(__('messages.checkout.password_setup_complete'))
            ->assertSee(__('messages.checkout.password_setup_login_prompt'))
            ->assertSee(route('login'), false);
        $this->assertNotNull($account->fresh()->email_verified_at);
        $this->assertSame(1, Reservation::where('email', $account->email)->count());
    }

    private function finishSavedReservationPayment(Reservation $reservation): void
    {
        $checkoutUrl = route('checkout.show', ['reservation' => $reservation, 'token' => $reservation->checkout_token]);
        $this->get($checkoutUrl)->assertOk()->assertViewIs('checkout.show');
        $this->post($checkoutUrl, ['provider' => 'pay_later'])->assertRedirect($checkoutUrl);
        $attempt = $reservation->paymentAttempts()->latest('id')->firstOrFail();
        $this->assertEquals((float) $reservation->total_amount, (float) $attempt->amount);
        $body = json_encode(['provider_reference' => $attempt->provider_reference, 'status' => 'paid'], JSON_THROW_ON_ERROR);
        $this->call('POST', route('checkout.webhook', ['provider' => 'pay_later']), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_WEBHOOK_SIGNATURE' => hash_hmac('sha256', $body, config('services.payments.webhook_secret')),
        ], $body)->assertOk();
        $this->post(route('checkout.complete', ['reservation' => $reservation, 'token' => $reservation->checkout_token]))->assertRedirect($checkoutUrl);
        $this->assertSame('confirmed', $reservation->fresh()->status);
    }

    public function test_activation_email_failure_does_not_prevent_booking_or_payment(): void
    {
        $this->seed(DatabaseSeeder::class);
        $property = Property::query()->published()->firstOrFail();
        $customer = User::factory()->create([
            'email' => 'activation-failure@example.com', 'role' => 'customer',
            'tenant_id' => $property->establishment->tenant_id, 'email_verified_at' => null,
        ]);
        $this->mock(\Illuminate\Contracts\Notifications\Dispatcher::class, function ($mock): void {
            $mock->shouldReceive('send')->andThrow(new \RuntimeException('Notification transport unavailable for test.'));
        });
        $response = $this->post(route('properties.reserve', $property), [
            'full_name' => 'Failure Guest', 'email' => $customer->email,
            'check_in' => now()->addDays(30)->toDateString(), 'check_out' => now()->addDays(32)->toDateString(), 'adults' => 1,
        ])->assertRedirect()->assertSessionHasNoErrors()->assertSessionHas('account_activation_email_failed', true);
        $reservation = Reservation::where('email', $customer->email)->firstOrFail();
        $checkoutUrl = route('checkout.show', ['reservation' => $reservation, 'token' => $reservation->checkout_token]);
        $response->assertRedirect($checkoutUrl);
        $this->get($checkoutUrl)->assertOk()->assertSee(__('messages.checkout.activation_email_failed'))->assertSee('id="checkout-payment"', false);
        $this->from($checkoutUrl)->post(route('checkout.password-setup', ['reservation' => $reservation, 'token' => $reservation->checkout_token]))
            ->assertRedirect($checkoutUrl)->assertSessionHas('account_activation_email_failed', true);
        $this->finishSavedReservationPayment($reservation);
        $this->assertNull($customer->fresh()->email_verified_at);
        $this->assertSame(1, User::where('email', $customer->email)->count());
    }

    public function test_existing_unconfirmed_customer_is_not_duplicated_across_establishments(): void
    {
        Notification::fake();
        $this->seed(DatabaseSeeder::class);
        $property = Property::query()->published()->firstOrFail();
        $otherTenant = \App\Models\Tenant::create(['name' => 'Other', 'slug' => 'other-account', 'status' => 'active']);
        $customer = User::factory()->create(['email' => 'existing-shared@example.com', 'role' => 'customer', 'tenant_id' => $otherTenant->id, 'email_verified_at' => null]);
        $this->post(route('properties.reserve', $property), [
            'full_name' => 'Shared Guest', 'email' => $customer->email,
            'check_in' => now()->addDays(30)->toDateString(), 'check_out' => now()->addDays(32)->toDateString(), 'adults' => 1,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $reservation = Reservation::where('email', $customer->email)->firstOrFail();
        $this->assertSame($customer->id, $reservation->user_id);
        $this->assertTrue($reservation->account_setup_deferred);
        $this->assertSame(1, User::where('email', $customer->email)->count());
        $this->assertSame($otherTenant->id, $customer->fresh()->tenant_id);
        $this->assertGuest();
    }

    public function test_authenticated_staff_booking_does_not_defer_guest_account_setup(): void
    {
        Notification::fake();
        $this->seed(DatabaseSeeder::class);
        $property = Property::query()->published()->firstOrFail();
        $staff = User::factory()->create(['role' => 'admin', 'is_admin' => true, 'tenant_id' => $property->establishment->tenant_id, 'email_verified_at' => now()]);
        $customer = User::factory()->create(['role' => 'customer', 'email' => 'staff-booked@example.com', 'tenant_id' => $property->establishment->tenant_id, 'email_verified_at' => null]);
        $this->actingAs($staff)->post(route('properties.reserve', $property), [
            'full_name' => 'Staff Guest', 'email' => $customer->email,
            'check_in' => now()->addDays(30)->toDateString(), 'check_out' => now()->addDays(32)->toDateString(), 'adults' => 1,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $reservation = Reservation::where('email', $customer->email)->firstOrFail();
        $this->assertFalse($reservation->account_setup_deferred);
        Notification::assertNotSentTo($staff, GuestAccountSetupNotification::class);
        $this->assertNull($customer->fresh()->email_verified_at);
    }

    public function test_authenticated_customer_can_toggle_property_favorite(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = User::factory()->create(['role' => 'customer']);
        $property = Property::query()->where('status', 'published')->firstOrFail();

        $this->actingAs($user)
            ->get('/properties')
            ->assertOk()
            ->assertSee(__('messages.favorites.add'));

        $this->actingAs($user)
            ->post(route('properties.favorite.toggle', $property))
            ->assertRedirect();

        $this->assertDatabaseHas('property_favorites', ['user_id' => $user->id, 'property_id' => $property->id]);
        $this->actingAs($user)
            ->get('/properties')
            ->assertOk()
            ->assertSee('class="favorite-button is-favorite"', false)
            ->assertSee($property->localized('name'));

        $otherProperty = Property::query()
            ->whereKeyNot($property->id)
            ->where('status', 'published')
            ->firstOrFail();
        $orderedResponse = $this->actingAs($user)->get('/properties');
        $this->assertLessThan(
            strpos($orderedResponse->getContent(), route('properties.show', $otherProperty)),
            strpos($orderedResponse->getContent(), route('properties.show', $property))
        );

        $this->actingAs($user)
            ->get('/properties?favorites=1')
            ->assertOk()
            ->assertSee('name="favorites"', false)
            ->assertSee('checked', false)
            ->assertSee($property->localized('name'))
            ->assertDontSee($otherProperty->localized('name'));
        $this->actingAs($user)->get('/dashboard')->assertOk()->assertSee($property->localized('name'));

        $this->actingAs($user)->post(route('properties.favorite.toggle', $property))->assertRedirect();
        $this->assertDatabaseMissing('property_favorites', ['user_id' => $user->id, 'property_id' => $property->id]);
    }

    public function test_public_catalog_and_booking_flow_work(): void
    {
        ini_set('memory_limit', '1G');
        Notification::fake();
        $this->withoutMiddleware(ValidateCsrfToken::class);

        Artisan::call('db:seed');
        Artisan::call('property:sync-media', [
            '--source' => base_path('photo'),
            '--target' => sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'afrikappart-m04',
            '--manifest' => sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'afrikappart-m04-manifest.json',
        ]);

        $this->get('/properties')
            ->assertOk()
            ->assertSee('Appartement 401')
            ->assertSee('Découvrez nos logements disponibles');

        $this->get('/properties/appartement-401')
            ->assertOk()
            ->assertSee('Vérifier la disponibilité')
            ->assertSee('À propos de ce logement');

        $account = User::factory()->create([
            'name' => 'Alice Doe',
            'email' => 'alice@example.com',
            'role' => 'customer',
            'email_verified_at' => now(),
            'last_login_at' => now(),
        ]);

        $response = $this->actingAs($account)->post('/properties/appartement-401/reserve', [
            'full_name' => 'Alice Doe',
            'email' => 'alice@example.com',
            'country' => 'Bénin',
            'phone' => '+229 99 99 99 99',
            'check_in' => now()->addDay()->toDateString(),
            'check_out' => now()->addDays(3)->toDateString(),
            'adults' => 2,
            'children' => 0,
            'infants' => 0,
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
        $reservation = Reservation::query()->where('email', 'alice@example.com')->latest()->firstOrFail();
        $response->assertRedirect(route('checkout.show', ['reservation' => $reservation, 'token' => $reservation->checkout_token]));
        $this->assertDatabaseHas('reservation_guests', ['email' => 'alice@example.com']);
        $this->assertDatabaseHas('reservations', ['email' => 'alice@example.com', 'status' => 'pending']);
        $this->assertDatabaseHas('email_outbox', ['recipient_email' => 'alice@example.com', 'template' => 'reservation_received', 'status' => 'queued']);
        $this->assertSame($account->id, $reservation->user_id);
        $expectedPricing = \App\Services\PricingCalculator::calculate(
            $reservation->property,
            \Carbon\Carbon::parse($reservation->check_in),
            \Carbon\Carbon::parse($reservation->check_out),
            (int) $reservation->adults,
            (int) $reservation->children,
        );
        $this->assertEquals($expectedPricing['total_amount'], (float) $reservation->total_amount);
    }

    public function test_unpublished_properties_are_not_customer_visible(): void
    {
        $this->seed(DatabaseSeeder::class);
        $property = Property::where('slug', 'appartement-401')->firstOrFail();
        $property->update(['status' => 'draft']);

        $this->get('/')->assertOk()->assertDontSee('Appartement 401');
        $this->get('/properties')->assertOk()->assertDontSee('Appartement 401');
        $this->get('/properties/appartement-401')->assertNotFound();
    }

    public function test_properties_page_filters_by_establishment_and_capacity(): void
    {
        $this->seed(DatabaseSeeder::class);
        $other = Establishment::create([
            'name' => 'Porto-Novo Residence',
            'slug' => 'porto-novo-residence',
            'country_code' => 'BJ',
            'currency' => 'XOF',
        ]);
        Property::create([
            'establishment_id' => $other->id,
            'name' => 'Porto-Novo Suite',
            'slug' => 'porto-novo-suite',
            'status' => 'published',
            'is_published' => true,
            'max_guests' => 6,
            'bedrooms' => 3,
            'nightly_rate_xof' => 50000,
            'nightly_rate_eur' => 76,
        ]);

        $this->get('/properties')
            ->assertOk()
            ->assertSee('id="compare-form"', false)
            ->assertSee('data-compare-option', false)
            ->assertSee('data-number-stepper', false)
            ->assertSee('data-price-range', false)
            ->assertDontSee('data-amenities-section open', false)
            ->assertDontSee('class="filter-clear-all"', false)
            ->assertSee('hidden data-amenity-extra', false)
            ->assertSee('data-amenity-search', false)
            ->assertSee('amenity-option-popular', false)
            ->assertSee('data-amenity-more', false);

        $this->get('/properties?establishment=' . $other->id . '&guests=5&bedrooms=2')
            ->assertOk()
            ->assertSee('Porto-Novo Suite')
            ->assertDontSee('Appartement 401')
            ->assertDontSee('id="compare-form"', false)
            ->assertDontSee('data-compare-option', false);

        $this->get('/properties?sort=price_desc')
            ->assertOk()
            ->assertSee('name="sort"', false)
            ->assertSee(__('messages.properties.sort_price_desc'));

        $this->get('/properties?sort=rating')->assertOk()->assertSee('value="rating" selected', false);
        $this->get('/properties?sort=newest')->assertOk()->assertSee('value="newest" selected', false);

        $this->get('/properties?property_type=apartment&beds=1&flexible_cancellation=1')
            ->assertOk()
            ->assertSee('Appartement 401')
            ->assertSee('name="flexible_cancellation"', false);

        $wifi = Amenity::query()->where('slug', 'wifi')->firstOrFail();
        $this->get('/properties?amenities%5B%5D=' . $wifi->id)
            ->assertOk()
            ->assertSee('name="amenities[]"', false)
            ->assertSee('value="' . $wifi->id . '"', false)
            ->assertSee('checked', false)
            ->assertSee(__('messages.properties.filters_count', ['count' => 1]));
    }

        public function test_shared_filter_restores_query_values_and_builds_removable_chips_and_reset_link(): void
        {
            $this->seed(DatabaseSeeder::class);
            $wifi = Amenity::query()->where('slug', 'wifi')->firstOrFail();
            $query = ['destination' => 'Cotonou', 'guests' => 2, 'amenities' => [$wifi->id], 'min_price' => 10000];

            $this->get(route('properties.index', $query))->assertOk()
                ->assertSee('value="Cotonou" selected', false)
                ->assertSee('name="guests"', false)
                ->assertSee('value="2"', false)
                ->assertSee('value="10000"', false)
                ->assertSee('data-amenities-section', false)
                ->assertSee('class="selected-filter-chip"', false)
                ->assertSee('href="' . route('properties.index') . '" class="filter-clear-all"', false)
                ->assertSee('href="' . e(route('properties.index', ['destination' => 'Cotonou', 'guests' => 2, 'min_price' => 10000])) . '"', false);
        }

    public function test_property_detail_back_link_keeps_all_validated_search_criteria(): void
    {
        $this->seed(DatabaseSeeder::class);
        $property = Property::query()->where('slug', 'appartement-401')->firstOrFail();
        $wifi = Amenity::query()->where('slug', 'wifi')->firstOrFail();
        $filters = [
            'destination' => 'Cotonou',
            'check_in' => now()->addDays(10)->toDateString(),
            'check_out' => now()->addDays(12)->toDateString(),
            'guests' => 2,
            'bedrooms' => 1,
            'beds' => 1,
            'property_type' => 'apartment',
            'min_price' => 10000,
            'max_price' => 50000,
            'flexible_cancellation' => 1,
            'amenities' => [$wifi->id],
            'sort' => 'rating',
        ];

        $detail = $this->get(route('properties.show', ['property' => $property, ...$filters]))->assertOk();
        $document = new \DOMDocument();
        @$document->loadHTML($detail->getContent());
        $xpath = new \DOMXPath($document);
        $backHref = $xpath->query('//*[@data-listing-back-link]')->item(0)->getAttribute('href');
        parse_str((string) parse_url($backHref, PHP_URL_QUERY), $restoredFilters);
        $this->assertSame('/properties', parse_url($backHref, PHP_URL_PATH));
        foreach ($filters as $key => $value) {
            $this->assertEquals($value, $restoredFilters[$key] ?? null);
        }

        $this->get(route('properties.index', $filters))->assertOk()
            ->assertSee('destination=Cotonou', false)
            ->assertSee('sort=rating', false)
            ->assertSee('amenities%5B0%5D=' . $wifi->id, false);
    }

    public function test_home_search_accepts_one_traveler(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->get('/')
            ->assertOk()
            ->assertSee('data-property-filter-home', false)
            ->assertSee('name="property_type"', false)
            ->assertSee('name="guests"', false)
            ->assertSee('value="1"', false)
            ->assertSee('data-mobile-menu-toggle', false)
            ->assertSee('href="' . route('login') . '"', false);

        $this->get('/properties?guests=1')
            ->assertOk()
            ->assertSee('Appartement 401');
    }

    public function test_properties_date_filter_excludes_external_calendar_conflicts(): void
    {
        $this->seed(DatabaseSeeder::class);
        $property = Property::where('slug', 'appartement-401')->firstOrFail();
        $property->update(['minimum_stay' => 1]);
        $feed = ExternalCalendarFeed::query()->create([
            'property_id' => $property->id,
            'name' => 'External booking calendar',
            'url' => 'https://example.com/external.ics',
            'is_enabled' => true,
        ]);
        ExternalCalendarEvent::query()->create([
            'feed_id' => $feed->id,
            'uid' => 'external-conflict-1',
            'summary' => 'External booking',
            'start_date' => '2031-10-10',
            'end_date' => '2031-10-13',
        ]);

        $this->get('/properties?check_in=2031-10-11&check_out=2031-10-12')
            ->assertOk()
            ->assertDontSee('Appartement 401');

        $this->get('/properties?check_in=2031-10-13&check_out=2031-10-14')
            ->assertOk()
            ->assertSee('Appartement 401');

        $this->get('/properties/appartement-401')
            ->assertOk()
            ->assertSee('data-external="[]"', false)
            ->assertSee('2031-10-10', false)
            ->assertDontSee(__('messages.admin.external_bookings'));
    }

    public function test_availability_check_rejects_enabled_external_calendar_conflicts(): void
    {
        $this->seed(DatabaseSeeder::class);
        $property = Property::where('slug', 'appartement-401')->firstOrFail();
        $feed = ExternalCalendarFeed::query()->create([
            'property_id' => $property->id,
            'name' => 'External booking calendar',
            'url' => 'https://example.com/external.ics',
            'is_enabled' => true,
        ]);
        ExternalCalendarEvent::query()->create([
            'feed_id' => $feed->id,
            'uid' => 'external-api-conflict-1',
            'summary' => 'External booking',
            'start_date' => '2031-12-10',
            'end_date' => '2031-12-13',
        ]);

        $this->postJson('/properties/' . $property->slug . '/availability', [
            'check_in' => '2031-12-11',
            'check_out' => '2031-12-13',
        ])->assertOk()->assertJson(['available' => false]);

        $this->postJson('/properties/' . $property->slug . '/availability', [
            'check_in' => '2031-12-13',
            'check_out' => '2031-12-15',
        ])->assertOk()->assertJson(['available' => true]);

        $feed->update(['is_enabled' => false]);

        $this->postJson('/properties/' . $property->slug . '/availability', [
            'check_in' => '2031-12-11',
            'check_out' => '2031-12-13',
        ])->assertOk()->assertJson(['available' => true]);
    }

    public function test_home_search_redirects_to_properties_with_selected_filters(): void
    {
        $this->seed(DatabaseSeeder::class);
        $checkIn = now()->addDay()->toDateString();
        $checkOut = now()->addDays(3)->toDateString();

        $this->get('/')
            ->assertOk()
            ->assertSee('action="' . route('properties.index') . '"', false)
            ->assertSee('name="destination"', false)
            ->assertSee('name="guests"', false)
            ->assertSee('type="submit"', false);

        $this->get('/?' . http_build_query([
            'destination' => 'Cotonou',
            'guests' => 3,
            'check_in' => $checkIn,
            'check_out' => $checkOut,
        ]))
            ->assertRedirect(route('properties.index', [
                'destination' => 'Cotonou',
                'guests' => 3,
                'check_in' => $checkIn,
                'check_out' => $checkOut,
            ]));

        $this->get('/properties?' . http_build_query([
            'destination' => 'Cotonou',
            'guests' => 3,
            'check_in' => $checkIn,
            'check_out' => $checkOut,
        ]))
            ->assertOk()
            ->assertSee('<option value="Cotonou" selected>Cotonou</option>', false)
            ->assertSee('value="3"', false)
            ->assertSee('name="check_in" value="' . $checkIn . '" data-date-range-start', false)
            ->assertSee('name="check_out" value="' . $checkOut . '" data-date-range-end', false)
            ->assertSee(__('messages.properties.available_for_dates'));

        // Legacy links that still use the "city" parameter must keep working.
        $this->get('/properties?city=Cotonou')
            ->assertOk()
            ->assertSee('<option value="Cotonou" selected>Cotonou</option>', false);
    }

    public function test_properties_page_ignores_blank_filter_values(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->get('/properties?bedrooms=&destination=Cotonou&establishment=1&guests=&max_price=&min_price=')
            ->assertOk()
            ->assertSee('Appartement 401');

        $this->get('/properties?destination=&establishment=&guests=&bedrooms=&min_price=&max_price=')
            ->assertOk()
            ->assertSee('Appartement 401');
    }

    public function test_public_catalog_can_compare_two_properties(): void
    {
        $this->seed(DatabaseSeeder::class);
        $propertyIds = Property::query()->published()->limit(2)->pluck('id')->all();

        $this->get('/properties/compare?properties%5B%5D=' . $propertyIds[0] . '&properties%5B%5D=' . $propertyIds[1])
            ->assertOk()
            ->assertSee(__('messages.properties.compare_title'))
            ->assertSee('Appartement 401');

        $this->get('/properties/compare?properties%5B%5D=' . $propertyIds[0] . '&properties%5B%5D=' . $propertyIds[1] . '&check_in=2027-01-10&check_out=2027-01-12&guests=2')
            ->assertOk()
            ->assertSee(__('messages.properties.for_nights', ['nights' => 2]));

        $properties = Property::query()->whereIn('id', $propertyIds)->get();
        $properties->last()->update(['currency' => $properties->first()->currency === 'XOF' ? 'EUR' : 'XOF']);
        $this->get('/properties/compare?properties%5B%5D=' . $propertyIds[0] . '&properties%5B%5D=' . $propertyIds[1])
            ->assertOk()->assertSee(__('messages.comparison.rate_missing'));

        $properties->last()->update(['currency' => $properties->first()->currency, 'status' => 'draft', 'is_published' => false]);
        $this->get('/properties/compare?properties%5B%5D=' . $propertyIds[0] . '&properties%5B%5D=' . $propertyIds[1])
            ->assertOk()->assertSee(__('messages.comparison.missing', ['count' => 1]))
            ->assertSee(__('messages.comparison.choose_two'))->assertDontSee('class="comparison-matrix"', false);
    }

    public function test_comparison_supports_four_properties_real_pricing_availability_and_configured_currency(): void
    {
        $this->seed(DatabaseSeeder::class);
        $properties = Property::query()->published()->with('establishment')->take(4)->get();
        $this->assertCount(4, $properties);
        $establishment = $properties->first()->establishment;
        $establishment->update(['secondary_currency' => 'EUR', 'secondary_currency_rate' => 0.002]);
        $arrival = now()->addDays(30)->toDateString();
        $departure = now()->addDays(34)->toDateString();
        Reservation::create([
            'property_id' => $properties->first()->id, 'reservation_ref' => 'COMPARE-BLOCK',
            'email' => 'blocked@example.com', 'status' => 'confirmed', 'currency' => 'XOF',
            'check_in' => $arrival, 'check_out' => $departure, 'adults' => 1, 'total_amount' => 50000,
        ]);
        $query = ['properties' => $properties->pluck('id')->all(), 'check_in' => $arrival, 'check_out' => $departure, 'guests' => 2, 'currency' => 'EUR'];
        $response = $this->get(route('properties.compare', $query))->assertOk()
            ->assertSee('data-comparison-remove', false)
            ->assertSee(__('messages.comparison.unavailable'))
            ->assertSee(__('messages.comparison.approximate'))
            ->assertSee('aria-controls="comparison-group-amenities"', false)
            ->assertSee(__('messages.properties.compare_room_subtotal'))
            ->assertDontSee('properties.compare_nightly')
            ->assertDontSee('properties.compare_room_subtotal');
        $document = new \DOMDocument();
        @$document->loadHTML($response->getContent());
        $xpath = new \DOMXPath($document);
        $this->assertTrue($xpath->query('//*[@id="comparison-group-amenities"]')->item(0)->hasAttribute('hidden'));
        $this->assertSame(4, substr_count($response->getContent(), 'data-comparison-column='));
        $columns = $response->viewData('columns');
        $this->assertFalse($columns[0]['available']);
        $expected = \App\Services\PricingCalculator::calculate($properties[1], \Carbon\Carbon::parse($arrival), \Carbon\Carbon::parse($departure), 2);
        $this->assertSame($expected['total_amount'], $columns[1]['pricing']['total_amount']);
        $this->assertSame($expected['taxes'], $columns[1]['pricing']['taxes']);
        $this->assertSame('EUR', session('comparison_currency'));
        $this->assertNull(session('display_currency'));
        $this->get(route('properties.show', $properties[1]))->assertOk()->assertDontSee('data-display-currency="EUR"', false);

        $query['check_in'] = now()->addDays(60)->toDateString();
        $query['check_out'] = now()->addDays(64)->toDateString();
        $updated = $this->getJson(route('properties.compare', $query))->assertOk()->assertJsonPath('currency', 'EUR');
        $this->assertCount(4, $updated->json('properties'));
        $this->assertStringNotContainsString('comparison-availability is-unavailable', $updated->json('html'));
        $this->assertSame(1, Reservation::where('reservation_ref', 'COMPARE-BLOCK')->count());
        $this->assertSame('confirmed', Reservation::where('reservation_ref', 'COMPARE-BLOCK')->value('status'));
    }

    public function test_comparison_currency_is_remembered_without_changing_home_catalog_establishment_or_property_prices(): void
    {
        $this->seed(DatabaseSeeder::class);
        $properties = Property::published()->with('establishment')->take(2)->get();
        $property = $properties->first();
        $property->establishment->update(['secondary_currency' => 'EUR', 'secondary_currency_rate' => 0.002]);
        $this->withSession(['display_currency' => 'EUR']);
        $selection = ['properties' => $properties->pluck('id')->all()];
        $this->get(route('properties.compare', $selection))->assertOk()->assertViewHas('currency', 'XOF');
        $dates = ['check_in' => now()->addDays(30)->toDateString(), 'check_out' => now()->addDays(34)->toDateString(), 'guests' => 2];
        $urls = [
            route('home'),
            route('properties.index', $dates),
            route('establishments.show', ['establishment' => $property->establishment, ...$dates]),
            route('properties.show', ['property' => $property, ...$dates]),
        ];
        $prices = function ($response): array {
            $document = new \DOMDocument();
            @$document->loadHTML($response->getContent());
            $xpath = new \DOMXPath($document);
            $nodes = $xpath->query('//*[contains(@class, "property-price-footer") or contains(@class, "price-summary") or contains(@class, "mobile-booking-summary")]');
            $values = [];
            foreach ($nodes as $node) {
                $values[] = trim(preg_replace('/\s+/u', ' ', $node->textContent));
            }
            $this->assertNotEmpty($values);

            return $values;
        };
        $before = [];
        foreach ($urls as $url) {
            $before[$url] = $prices($this->get($url)->assertOk()->assertDontSee('data-display-currency="EUR"', false));
        }
        $this->get(route('properties.compare', $selection + ['currency' => 'EUR']))->assertOk()->assertViewHas('currency', 'EUR');
        $this->assertSame('EUR', session('comparison_currency'));
        $this->get(route('properties.compare', $selection))->assertOk()->assertViewHas('currency', 'EUR');
        foreach ($urls as $url) {
            $this->assertSame($before[$url], $prices($this->get($url)->assertOk()), $url);
        }
    }

    public function test_same_currency_secondary_prices_are_not_repeated(): void
    {
        $this->seed(DatabaseSeeder::class);
        $properties = Property::published()->with('establishment')->take(2)->get();
        $property = $properties->first();
        $property->establishment->update(['secondary_currency' => 'XOF', 'secondary_currency_rate' => 1]);
        $dates = ['check_in' => now()->addDays(30)->toDateString(), 'check_out' => now()->addDays(34)->toDateString(), 'guests' => 2];
        foreach ([
            route('home'),
            route('properties.index', $dates),
            route('establishments.show', ['establishment' => $property->establishment, ...$dates]),
            route('properties.show', ['property' => $property, ...$dates]),
            route('properties.compare', ['properties' => $properties->pluck('id')->all(), 'currency' => 'XOF']),
        ] as $url) {
            $response = $this->get($url)->assertOk();
            $document = new \DOMDocument();
            @$document->loadHTML('<?xml encoding="UTF-8">' . $response->getContent());
            $xpath = new \DOMXPath($document);
            $nodes = $xpath->query('//*[contains(@class, "property-price-footer") or contains(@class, "price-summary") or contains(@class, "comparison-product")]');
            foreach ($nodes as $node) {
                $this->assertStringNotContainsString('≈', $node->textContent, $url);
            }
        }
        $reservation = Reservation::create([
            'property_id' => $property->id, 'reservation_ref' => 'SAME-CURRENCY-CHECKOUT',
            'status' => 'pending_payment', 'email' => 'same-currency@example.com', 'currency' => 'XOF',
            'check_in' => $dates['check_in'], 'check_out' => $dates['check_out'], 'adults' => 2,
            'subtotal' => 100000, 'total_amount' => 100000,
        ]);
        $this->withSession(['checkout_identity_skipped' => [$reservation->id]])
            ->get(route('checkout.show', ['reservation' => $reservation, 'token' => $reservation->checkout_token]))->assertOk()
            ->assertDontSee('class="checkout-order-conversion"', false);
    }

    public function test_comparison_handles_small_selections_invalid_dates_and_missing_rates(): void
    {
        $this->seed(DatabaseSeeder::class);
        $ids = Property::query()->published()->take(2)->pluck('id')->all();
        $this->get(route('properties.compare'))->assertOk()->assertSee(__('messages.comparison.choose_two'));
        $this->get(route('properties.compare', ['properties' => [$ids[0]]]))->assertOk()
            ->assertSee(__('messages.comparison.choose_two'))->assertDontSee('class="comparison-matrix"', false);
        $this->getJson(route('properties.compare', ['properties' => $ids, 'check_in' => now()->addDay()->toDateString()]))
            ->assertUnprocessable()->assertJsonValidationErrors('check_out');
        $this->getJson(route('properties.compare', ['properties' => [1, 2, 3, 4, 5]]))
            ->assertUnprocessable()->assertJsonValidationErrors('properties');
        Property::whereKey($ids[1])->update(['currency' => 'USD']);
        $this->get(route('properties.compare', ['properties' => $ids]))->assertOk()->assertSee(__('messages.comparison.rate_missing'));
        $this->getJson(route('properties.compare', ['properties' => $ids, 'currency' => 'ZZZ']))->assertUnprocessable()->assertJsonValidationErrors('currency');
        $this->assertNull(session('comparison_currency'));
        $this->withSession(['locale' => 'en'])->get(route('properties.compare', ['properties' => $ids]))->assertOk()
            ->assertSee('Differences only')->assertSee('Exchange rate unavailable')->assertDontSee('messages.comparison.');
        Property::whereKey($ids[0])->update(['nightly_rate_xof' => 0]);
        $this->get(route('properties.compare', ['properties' => $ids]))->assertOk()->assertSee('Price unavailable');
    }

    public function test_comparison_does_not_highlight_equal_zero_fees_as_differences(): void
    {
        $this->seed(DatabaseSeeder::class);
        $properties = Property::published()->with('establishment')->take(2)->get();
        $properties->first()->establishment->update(['service_fee_percent' => 0, 'vat_percent' => 0, 'city_tax_type' => 'none']);
        $response = $this->get(route('properties.compare', [
            'properties' => $properties->pluck('id')->all(),
            'check_in' => now()->addDays(30)->toDateString(), 'check_out' => now()->addDays(34)->toDateString(),
        ]))->assertOk();
        $document = new \DOMDocument();
        @$document->loadHTML($response->getContent());
        $xpath = new \DOMXPath($document);
        $this->assertSame('false', $xpath->query('//*[@data-comparison-key="fees"]')->item(0)->getAttribute('data-different'));
        $this->assertTrue($xpath->query('//*[@data-comparison-key="fees"]')->item(0)->hasAttribute('hidden'));
    }

    public function test_property_detail_displays_reviews_map_and_social_preview_data(): void
    {
        $this->seed(DatabaseSeeder::class);
        $property = Property::where('slug', 'appartement-401')->firstOrFail();
        $property->establishment->update([
            'latitude' => 6.3703,
            'longitude' => 2.3912,
            'google_maps_url' => 'https://maps.google.com/?q=6.3703,2.3912',
        ]);
        $otherProperty = Property::where('slug', 'appartement-402')->firstOrFail();
        SiteReview::create([
            'tenant_id' => $property->establishment->tenant_id,
            'establishment_id' => $property->establishment_id,
            'property_id' => $otherProperty->id,
            'source' => 'google',
            'reviewer_name' => 'Awa',
            'rating' => 5,
            'review_text' => 'Wonderful stay',
            'is_active' => true,
            'reviewed_at' => '2026-09-11 10:00:00',
        ]);
        SiteReview::create([
            'tenant_id' => $property->establishment->tenant_id,
            'establishment_id' => $property->establishment_id,
            'property_id' => $otherProperty->id,
            'source' => 'booking',
            'reviewer_name' => 'Moussa',
            'rating' => 4,
            'review_text' => 'Excellent location',
            'is_active' => true,
            'reviewed_at' => '2026-09-10 10:00:00',
        ]);
        SiteReview::create([
            'tenant_id' => $property->establishment->tenant_id,
            'establishment_id' => $property->establishment_id,
            'property_id' => $otherProperty->id,
            'source' => 'booking',
            'reviewer_name' => 'Fatou',
            'rating' => 5,
            'review_text' => 'Très propre',
            'is_active' => true,
            'reviewed_at' => '2026-09-09 10:00:00',
        ]);
        SiteReview::create([
            'tenant_id' => $property->establishment->tenant_id,
            'establishment_id' => $property->establishment_id,
            'property_id' => $otherProperty->id,
            'source' => 'google',
            'reviewer_name' => 'Jean',
            'rating' => 3,
            'review_text' => 'Bon séjour',
            'is_active' => true,
            'reviewed_at' => '2026-09-08 10:00:00',
        ]);
        SiteReview::create([
            'tenant_id' => $property->establishment->tenant_id,
            'establishment_id' => $property->establishment_id,
            'property_id' => $property->id,
            'source' => 'google',
            'reviewer_name' => 'Nadia',
            'rating' => 4,
            'review_text' => null,
            'is_active' => true,
            'reviewed_at' => '2026-09-12 10:00:00',
        ]);
        BrandSettings::set([
            'review_source_booking_url' => 'https://www.booking.com/hotel/example',
            'review_source_google_url' => 'https://maps.google.com/?cid=example',
        ]);

        $this->get('/properties/appartement-401')
            ->assertOk()
            ->assertSee('Le client n’a pas laissé de commentaire.')
            ->assertDontSee('Wonderful stay')
            ->assertDontSee('Excellent location')
            ->assertSee('Le client n’a pas laissé de commentaire.')
            ->assertSee('12/09/2026')
            ->assertSee('Basé sur 1 avis ajoutés à la plateforme.')
            ->assertSee('Afficher les avis (1)')
            ->assertSee('data-review-property="' . $property->id . '"', false)
            ->assertSee('id="property-reviews-content" class="property-reviews-content" hidden', false)
            ->assertSee('Voir tous les avis')
            ->assertSee('Bénin')
            ->assertDontSee('>BJ<', false)
            ->assertSee('google')
            ->assertDontSee('https://www.booking.com/hotel/example', false)
            ->assertDontSee('https://maps.google.com/?cid=example', false)
            ->assertSee('location-map', false)
            ->assertSee('property="og:image"', false)
            ->assertSee('facebook.com/sharer', false)
            ->assertDontSee('Booking.com');

            $this->get('/reviews')
                ->assertOk()
                ->assertSee('Tous les avis voyageurs')
                ->assertSee('Retour à l’accueil')
                ->assertSee('Awa')
                ->assertSee('Nadia')
                ->assertSee('Le client n’a pas laissé de commentaire.')
                ->assertSee('Moussa')
                ->assertSee('Booking.com')
                ->assertSee('Fatou')
                ->assertSee('Jean')
                ->assertSee('data-review-property="' . $otherProperty->id . '"', false)
                ->assertSee('Basé sur 5 avis ajoutés à la plateforme.');

            $this->get(route('reviews', ['property' => $property->slug]))
                ->assertOk()
                ->assertSee('Retour au logement')
                ->assertSee(route('properties.show', $property), false);

            $this->get('/')
                ->assertOk()
                ->assertSee('Nadia')
                ->assertSee('data-review-property="' . $property->id . '"', false)
                ->assertSee('Le client n’a pas laissé de commentaire.');
    }

    public function test_reviews_show_the_linked_property_name_without_inventing_links_for_missing_or_inactive_properties(): void
    {
        $this->seed(DatabaseSeeder::class);
        $property = Property::where('slug', 'appartement-401')->firstOrFail();
        $property->translations()->updateOrCreate(['locale' => 'en'], ['name' => 'Marina Apartment 401']);
        $inactive = Property::where('slug', 'appartement-402')->firstOrFail();
        $inactive->update(['is_active' => false]);
        foreach (['Linked Guest' => $property->id, 'Unlinked Guest' => null, 'Inactive Guest' => $inactive->id] as $name => $propertyId) {
            SiteReview::create([
                'tenant_id' => $property->establishment->tenant_id, 'establishment_id' => $property->establishment_id,
                'property_id' => $propertyId, 'reviewer_name' => $name, 'source' => 'booking',
                'rating' => 5, 'is_active' => true, 'reviewed_at' => now(),
            ]);
        }
        $response = $this->withSession(['locale' => 'en'])->get(route('reviews'))->assertOk()->assertSee('Marina Apartment 401');
        $document = new \DOMDocument();
        @$document->loadHTML($response->getContent());
        $xpath = new \DOMXPath($document);
        $linkedCard = $xpath->query('//article[h3="Linked Guest"]')->item(0);
        $this->assertSame(1, $xpath->query('.//*[@data-review-property]/a', $linkedCard)->length);
        $inactiveCard = $xpath->query('//article[h3="Inactive Guest"]')->item(0);
        $this->assertSame(1, $xpath->query('.//*[@data-review-property]', $inactiveCard)->length);
        $this->assertSame(0, $xpath->query('.//*[@data-review-property]/a', $inactiveCard)->length);
        $unlinkedCard = $xpath->query('//article[h3="Unlinked Guest"]')->item(0);
        $this->assertSame(0, $xpath->query('.//*[@data-review-property]', $unlinkedCard)->length);
        $this->get(route('home'))->assertOk()->assertSee('Marina Apartment 401')->assertSee('data-review-property="' . $property->id . '"', false);
    }

    public function test_public_property_pages_replace_draft_placeholder_copy(): void
    {
        $this->seed(DatabaseSeeder::class);

        $property = Property::query()->firstOrFail();

        $this->get('/')
            ->assertOk()
            ->assertDontSee('Draft property summary awaiting confirmation.')
            ->assertSee($property->name);

        $this->get(route('properties.show', $property))
            ->assertOk()
            ->assertDontSee('Draft property summary awaiting confirmation.')
            ->assertDontSee('Draft description to be replaced with owner-supplied content.')
            ->assertSee($property->name);
    }

    public function test_property_detail_uses_establishment_location_information(): void
    {
        $this->seed(DatabaseSeeder::class);
        $property = Property::where('slug', 'appartement-401')->firstOrFail();
        $property->establishment->update([
            'address' => '12 Avenue de la Marina',
            'city' => 'Cotonou',
            'country_code' => 'BJ',
        ]);
        $property->update([
            'address' => 'Property-specific address',
            'city' => 'Property-specific city',
            'country' => 'XX',
        ]);

        $this->get('/properties/appartement-401')
            ->assertOk()
            ->assertSee('<div class="info-row"><span>Adresse</span><strong>12 Avenue de la Marina</strong></div>', false)
            ->assertSee('<div class="info-row"><span>Ville</span><strong>Cotonou</strong></div>', false)
            ->assertSee('<div class="info-row"><span>Pays</span><strong>Bénin</strong></div>', false)
            ->assertDontSee('<div class="info-row"><span>Adresse</span><strong>Property-specific address</strong></div>', false)
            ->assertDontSee('<div class="info-row"><span>Ville</span><strong>Property-specific city</strong></div>', false);
    }

    public function test_property_detail_renders_translated_labels_for_english_guests(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->withCookie('locale', 'en')
            ->get('/properties/appartement-401')
            ->assertOk()
            ->assertSee('Check availability')
            ->assertSee('Characteristics')
            ->assertSee('Included services');
    }

    public function test_public_pages_expose_seo_metadata_and_sitemap(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->get('/')
            ->assertOk()
            ->assertSee('<meta name="description"', false)
            ->assertSee('<link rel="canonical"', false)
            ->assertSee('name="robots" content="index,follow"', false)
            ->assertSee('property="og:image"', false);

        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml')
            ->assertSee("<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<urlset", false)
            ->assertSee('<urlset', false)
            ->assertSee(route('properties.index'), false)
            ->assertSee(route('establishments.show', Establishment::query()->firstOrFail()), false);
    }

    public function test_establishment_landing_page_only_shows_its_published_properties(): void
    {
        $this->seed(DatabaseSeeder::class);
        $establishment = Establishment::query()->firstOrFail();
        $property = Property::query()->where('establishment_id', $establishment->id)->published()->firstOrFail();

        $this->get(route('establishments.show', $establishment))
            ->assertOk()
            ->assertSee($establishment->localized('name'))
            ->assertSee($property->localized('name'))
            ->assertSee(route('properties.show', $property), false);

        $establishment->update(['is_published' => false]);
        $this->get(route('establishments.show', $establishment))->assertNotFound();
        $this->get('/sitemap.xml')->assertOk()->assertDontSee(route('establishments.show', $establishment), false);
    }

    public function test_establishment_page_localizes_metadata_filters_and_keeps_its_public_content(): void
    {
        $tenant = \App\Models\Tenant::firstOrCreate(['slug' => 'default'], ['name' => 'Default', 'status' => 'active']);
        $establishment = Establishment::create([
            'tenant_id' => $tenant->id, 'name' => 'Maison Marina', 'slug' => 'maison-marina',
            'description' => 'Une maison au bord de la mer.', 'city' => 'Cotonou', 'country_code' => 'BJ',
            'currency' => 'XOF', 'is_active' => true, 'is_published' => true,
            'cover_image' => 'uploads/properties/appartement-401/cover.jpg', 'address' => '12 Marina',
            'latitude' => 6.36, 'longitude' => 2.42, 'email' => 'marina@example.com', 'phone' => '+22912345678',
            'metadata' => ['nearby_points_of_interest' => ['en' => [['name' => 'Marina market', 'description' => 'Local market']], 'fr' => [['name' => 'Marché Marina']]]],
        ]);
        $establishment->translations()->create(['locale' => 'en', 'name' => 'Marina House', 'description' => 'A seaside home.', 'features' => ['Reception']]);
        $property = $establishment->properties()->create([
            'name' => 'Suite Marina', 'slug' => 'suite-marina', 'status' => 'published', 'is_active' => true,
            'max_guests' => 4, 'bedrooms' => 2, 'beds' => 2, 'nightly_rate_xof' => 25000, 'currency' => 'XOF', 'minimum_stay' => 2,
        ]);
        $property->images()->create(['file_path' => 'uploads/properties/appartement-402/1-card.webp', 'file_name' => 'suite.webp', 'is_cover' => true]);
        $amenity = Amenity::create(['tenant_id' => $tenant->id, 'slug' => 'wireless', 'name_fr' => 'Internet sans fil', 'name_en' => 'Wireless internet']);
        $property->amenities()->attach($amenity);
        $otherProperty = $establishment->properties()->create([
            'name' => 'Petite chambre', 'slug' => 'petite-chambre', 'status' => 'published', 'is_active' => true,
            'max_guests' => 1, 'bedrooms' => 1, 'nightly_rate_xof' => 10000, 'currency' => 'XOF',
        ]);
        $draft = $establishment->properties()->create(['name' => 'Draft unit', 'slug' => 'draft-unit', 'status' => 'draft', 'is_active' => true]);
        $inactive = $establishment->properties()->create(['name' => 'Inactive unit', 'slug' => 'inactive-unit', 'status' => 'published', 'is_active' => false]);
        $establishment->reviews()->create(['tenant_id' => $tenant->id, 'source' => 'google', 'reviewer_name' => 'Legacy guest', 'rating' => 5, 'is_active' => true]);
        $property->reviews()->create(['tenant_id' => $tenant->id, 'establishment_id' => $establishment->id, 'source' => 'booking', 'reviewer_name' => 'Suite guest', 'rating' => 4, 'is_active' => true]);
        $url = route('establishments.show', $establishment);
        $checkIn = now()->addDays(30)->toDateString();
        $checkOut = now()->addDays(32)->toDateString();

        $response = $this->withSession(['locale' => 'en'])->get($url)->assertOk()
            ->assertSee('Marina House')->assertSee('A seaside home.')->assertSee('Wireless internet')
            ->assertSee('Reception')->assertSee('Marina market')->assertSee('Legacy guest')->assertSee('Suite guest')
            ->assertSee('4.5 / 5')->assertSee('data-gallery', false)
            ->assertSee('12 Marina')->assertSee('marina@example.com')
            ->assertSee('<link rel="canonical" href="' . $url . '">', false)
            ->assertSee(route('properties.show', $property), false)
            ->assertSee(route('properties.show', $otherProperty), false)
            ->assertDontSee(route('properties.show', $draft), false)
            ->assertDontSee(route('properties.show', $inactive), false);
        $this->assertSame(1, substr_count($response->getContent(), 'property="og:image"'));
        preg_match('/<script type="application\/ld\+json">(.*?)<\/script>/s', $response->getContent(), $match);
        $schema = json_decode($match[1], true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('https://schema.org', $schema['@context']);
        $this->assertSame('LodgingBusiness', $schema['@type']);
        $this->assertSame('Marina House', $schema['name']);
        $this->assertEquals(4.5, $schema['aggregateRating']['ratingValue']);
        $this->assertSame($url, $schema['url']);

        $filteredUrl = $url . '?' . http_build_query(['check_in' => $checkIn, 'check_out' => $checkOut, 'guests' => 3, 'amenities' => [$amenity->id]]);
        $this->get($filteredUrl)->assertOk()
            ->assertSee(route('properties.show', $property), false)
            ->assertSee('check_in=' . $checkIn, false)
            ->assertSee('amenities%5B0%5D=' . $amenity->id, false)
            ->assertDontSee('href="' . route('properties.show', $otherProperty) . '"', false)
            ->assertSee('data-filter-drawer', false)
            ->assertSee('data-amenities-section', false)
            ->assertDontSee('data-amenities-section open', false)
            ->assertSee('name="check_in" value="' . $checkIn . '"', false)
            ->assertSee('Legacy guest')->assertSee('4.5 / 5');
        $property->update(['minimum_stay' => 3]);
        $this->get($filteredUrl)->assertOk()->assertSee(__('messages.establishments.no_matching_homes'));
        $property->update(['minimum_stay' => 2]);
        Reservation::create([
            'property_id' => $property->id, 'reservation_ref' => 'EST-BLOCKED', 'status' => 'confirmed',
            'check_in' => $checkIn, 'check_out' => $checkOut, 'email' => 'stay@example.com',
        ]);
        $this->get($filteredUrl)->assertOk()->assertSee(__('messages.establishments.no_matching_homes'))
            ->assertSee('Legacy guest')->assertSee('data-gallery', false);
        $this->withSession(['locale' => 'fr'])->get($url)->assertOk()->assertSee('Maison Marina')->assertSee('Marché Marina');
        $this->get(route('properties.show', $property))->assertOk()->assertSee('href="' . $url . '"', false);
        $this->get(route('properties.index'))->assertOk()->assertDontSee('href="' . $url . '"', false);
        $this->get('/')->assertOk()->assertSee('href="' . $url . '"', false);
    }

    public function test_public_establishment_visibility_and_empty_state_match_the_sitemap(): void
    {
        $this->assertFileDoesNotExist(public_path('sitemap.xml'));
        $tenant = \App\Models\Tenant::firstOrCreate(['slug' => 'default'], ['name' => 'Default', 'status' => 'active']);
        $empty = Establishment::create(['tenant_id' => $tenant->id, 'name' => 'Empty residence', 'slug' => 'empty-residence', 'currency' => 'XOF', 'is_active' => true, 'is_published' => true]);
        $draft = Establishment::create(['tenant_id' => $tenant->id, 'name' => 'Draft residence', 'slug' => 'draft-residence', 'currency' => 'XOF', 'is_active' => true, 'is_published' => false]);
        $inactive = Establishment::create(['tenant_id' => $tenant->id, 'name' => 'Inactive residence', 'slug' => 'inactive-residence', 'currency' => 'XOF', 'is_active' => false, 'is_published' => true]);
        $property = $draft->properties()->create(['name' => 'Hidden home', 'slug' => 'hidden-home', 'status' => 'published', 'is_active' => true]);
        $this->get(route('establishments.show', $empty))->assertOk()->assertSee(__('messages.establishments.no_homes_description'));
        $this->get(route('establishments.index'))->assertOk()->assertSee($empty->name)->assertDontSee($draft->name)->assertDontSee($inactive->name);
        $this->get('/sitemap.xml')->assertOk()->assertSee(route('establishments.show', $empty), false)
            ->assertDontSee(route('establishments.show', $draft), false)->assertDontSee(route('establishments.show', $inactive), false);
        $this->get(route('establishments.show', $draft))->assertNotFound();
        $this->get(route('establishments.show', $inactive))->assertNotFound();
        $this->get(route('properties.show', $property))->assertNotFound();
        $this->postJson(route('properties.availability', $property), [])->assertNotFound();
        $this->post(route('properties.reserve', $property), [])->assertNotFound();
    }

    public function test_establishment_publication_can_be_changed_in_the_editor(): void
    {
        $tenant = \App\Models\Tenant::firstOrCreate(['slug' => 'default'], ['name' => 'Default', 'status' => 'active']);
        $establishment = Establishment::create(['tenant_id' => $tenant->id, 'name' => 'Editor residence', 'slug' => 'editor-residence', 'currency' => 'XOF', 'country_code' => 'BJ', 'is_active' => true, 'is_published' => true]);
        $admin = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'admin', 'is_admin' => true]);
        $this->actingAs($admin)->get(route('admin.establishments.edit', $establishment))->assertOk()->assertSee('name="is_published"', false);
        $this->put(route('admin.establishments.update', $establishment), [
            'name' => $establishment->name, 'slug' => $establishment->slug, 'country_code' => 'BJ', 'currency' => 'XOF', 'is_published' => 0,
        ])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertFalse($establishment->fresh()->is_published);
        $this->get(route('establishments.show', $establishment))->assertNotFound();
    }

    public function test_catalog_and_establishment_share_cards_and_expand_active_filters(): void
    {
        $tenant = \App\Models\Tenant::firstOrCreate(['slug' => 'default'], ['name' => 'Default', 'status' => 'active']);
        $establishment = Establishment::create([
            'tenant_id' => $tenant->id, 'name' => 'Shared cards residence', 'slug' => 'shared-cards-residence',
            'currency' => 'XOF', 'secondary_currency' => 'EUR', 'secondary_currency_rate' => 0.002,
            'is_active' => true, 'is_published' => true,
            'description' => 'A clear description outside the gallery.',
            'cover_image' => 'uploads/properties/appartement-402/1-card.webp',
        ]);
        $properties = collect();
        foreach ([1, 2] as $number) {
            $property = $establishment->properties()->create([
                'name' => 'Shared home ' . $number, 'slug' => 'shared-home-' . $number,
                'status' => 'published', 'is_active' => true, 'currency' => 'XOF',
                'city' => 'Cotonou', 'max_guests' => 4, 'bedrooms' => 2, 'beds' => 2,
                'nightly_rate_xof' => $number * 25000, 'minimum_stay' => 2,
            ]);
            foreach (['uploads/properties/appartement-402/1-card.webp', 'uploads/properties/appartement-401/chatgpt-image-24-juill-2026-14-h-22-min-26-s-card.webp'] as $index => $path) {
                $property->images()->create(['file_path' => $path, 'file_name' => 'photo-' . $index . '.webp', 'sort_order' => $index, 'is_cover' => $index === 0]);
            }
            $properties->push($property);
        }
        $customer = User::factory()->create(['role' => 'customer', 'locale' => 'fr']);
        $this->actingAs($customer)->post(route('properties.favorite.toggle', $properties->first()))->assertRedirect();
        $catalog = $this->get(route('properties.index'))->assertOk();
        $establishmentPage = $this->get(route('establishments.show', $establishment))->assertOk();
        $documents = [];
        foreach ([$catalog, $establishmentPage] as $response) {
            $document = new \DOMDocument();
            @$document->loadHTML('<?xml encoding="UTF-8">' . $response->getContent());
            $documents[] = $document;
            $xpath = new \DOMXPath($document);
            $this->assertSame(2, $xpath->query('//article[@data-property-id]')->length);
            $this->assertSame(2, $xpath->query('//input[@data-compare-option]')->length);
            $this->assertSame(1, $xpath->query('//form[@id="compare-form"]')->length);
            $this->assertSame(0, $xpath->query('//article[@data-property-id]//a[contains(@href, "/establishments/")]')->length);
            $response->assertSee('class="favorite-button is-favorite"', false)->assertSee('data-gallery-next', false);
        }
        foreach ($properties as $property) {
            $cards = [];
            foreach ($documents as $document) {
                $xpath = new \DOMXPath($document);
                $card = $xpath->query('//article[@data-property-id="' . $property->id . '"]')->item(0);
                $cards[] = trim(preg_replace('/href="[^"]*"/', 'href="[contextual-url]"', $document->saveHTML($card)));
            }
            $this->assertSame($cards[0], $cards[1]);
        }
        $catalogXPath = new \DOMXPath($documents[0]);
        $simpleConvertedPrice = $catalogXPath->query('//article[@data-property-id]//span[contains(@class, "property-price-converted")]')->item(0);
        $this->assertNotNull($simpleConvertedPrice);
        $this->assertStringNotContainsString('is-calculated', $simpleConvertedPrice->getAttribute('class'));
        $this->assertFalse($catalogXPath->query('//details[@class="property-filters"]')->item(0)->hasAttribute('open'));
        $establishmentXPath = new \DOMXPath($documents[1]);
        $this->assertFalse($establishmentXPath->query('//*[@data-establishment-filters]')->item(0)->hasAttribute('open'));
        $this->assertSame(0, $establishmentXPath->query('//section[contains(@class, "establishment-hero")]//h1')->length);
        $this->assertSame(1, $establishmentXPath->query('//section[@class="establishment-introduction"]//h1')->length);

        $checkIn = now()->addDays(30)->toDateString();
        $checkOut = now()->addDays(32)->toDateString();
        $filtered = $this->get(route('properties.index', ['destination' => 'Cotonou', 'check_in' => $checkIn, 'check_out' => $checkOut]))->assertOk();
        $filtered->assertSee('name="check_in" value="' . $checkIn . '"', false)
            ->assertSee('<option value="Cotonou" selected>Cotonou</option>', false);
        $document = new \DOMDocument();
        @$document->loadHTML($filtered->getContent());
        $xpath = new \DOMXPath($document);
        $calculatedConvertedPrice = $xpath->query('//article[@data-property-id]//span[contains(@class, "property-price-converted")]')->item(0);
        $this->assertNotNull($calculatedConvertedPrice);
        $this->assertStringContainsString('is-calculated', $calculatedConvertedPrice->getAttribute('class'));
        $this->assertFalse($xpath->query('//details[@class="property-filters"]')->item(0)->hasAttribute('open'));
        $establishmentFiltered = $this->get(route('establishments.show', ['establishment' => $establishment, 'check_in' => $checkIn, 'check_out' => $checkOut, 'guests' => 2]))->assertOk();
        $establishmentFiltered->assertSee('name="check_in" value="' . $checkIn . '"', false)
            ->assertSee('id="property-filter-establishment-guests"', false)
            ->assertSee('name="guests" type="number" min="1" max="100"', false)
            ->assertSee('value="2"', false);
        $document = new \DOMDocument();
        @$document->loadHTML($establishmentFiltered->getContent());
        $xpath = new \DOMXPath($document);
        $this->assertFalse($xpath->query('//*[@data-establishment-filters]')->item(0)->hasAttribute('open'));
        $establishmentDefault = $this->get(route('establishments.show', ['establishment' => $establishment, 'sort' => 'recommended', 'guests' => '']))->assertOk();
        $document = new \DOMDocument();
        @$document->loadHTML($establishmentDefault->getContent());
        $xpath = new \DOMXPath($document);
        $this->assertFalse($xpath->query('//*[@data-establishment-filters]')->item(0)->hasAttribute('open'));
        $defaultFilters = $this->get(route('properties.index', ['sort' => 'recommended', 'destination' => '', 'guests' => '', 'favorites' => 0, 'flexible_cancellation' => 0]))->assertOk();
        $document = new \DOMDocument();
        @$document->loadHTML($defaultFilters->getContent());
        $xpath = new \DOMXPath($document);
        $this->assertFalse($xpath->query('//details[@class="property-filters"]')->item(0)->hasAttribute('open'));
        $properties->last()->update(['is_active' => false]);
        $this->get(route('establishments.show', $establishment))->assertOk()->assertDontSee('id="compare-form"', false)->assertDontSee('data-compare-option', false);
    }

    public function test_establishment_gallery_uses_its_cover_then_only_its_properties_primary_images(): void
    {
        $tenant = \App\Models\Tenant::firstOrCreate(['slug' => 'default'], ['name' => 'Default', 'status' => 'active']);
        $establishment = Establishment::create([
            'tenant_id' => $tenant->id, 'name' => 'Own photos residence', 'slug' => 'own-photos-residence',
            'is_active' => true, 'is_published' => true, 'currency' => 'XOF',
            'cover_image' => 'uploads/establishments/residence-cover.jpg',
            'metadata' => ['gallery_images' => ['uploads/establishments/residence-lobby.jpg']],
        ]);
        $property = $establishment->properties()->create(['name' => 'A Private suite', 'slug' => 'private-suite', 'status' => 'published', 'is_active' => true, 'currency' => 'XOF']);
        $property->images()->create(['file_path' => 'uploads/properties/suite-cover.jpg', 'file_name' => 'suite.jpg', 'is_cover' => true]);
        $property->images()->create(['file_path' => 'uploads/properties/suite-secondary.jpg', 'file_name' => 'secondary.jpg', 'is_cover' => false]);
        $establishment->properties()->create(['name' => 'B Header suite', 'slug' => 'header-suite', 'status' => 'published', 'is_active' => true, 'currency' => 'XOF', 'cover_image' => 'uploads/properties/header.jpg']);
        $missingCover = $establishment->properties()->create(['name' => 'C Missing cover', 'slug' => 'missing-cover', 'status' => 'published', 'is_active' => true, 'currency' => 'XOF']);
        $missingCover->images()->create(['file_path' => 'uploads/properties/non-primary.jpg', 'file_name' => 'non-primary.jpg', 'is_cover' => false]);
        $establishment->properties()->create(['name' => 'D Duplicate cover', 'slug' => 'duplicate-cover', 'status' => 'published', 'is_active' => true, 'currency' => 'XOF', 'cover_image' => 'uploads/properties/suite-cover.jpg']);
        $establishment->properties()->create(['name' => 'Draft suite', 'slug' => 'draft-gallery-suite', 'status' => 'draft', 'is_active' => true, 'cover_image' => 'uploads/properties/draft-gallery.jpg']);
        $establishment->properties()->create(['name' => 'Inactive suite', 'slug' => 'inactive-gallery-suite', 'status' => 'published', 'is_active' => false, 'cover_image' => 'uploads/properties/inactive-gallery.jpg']);
        $otherEstablishment = Establishment::create(['tenant_id' => $tenant->id, 'name' => 'Other residence', 'slug' => 'other-gallery-residence', 'is_active' => true, 'is_published' => true, 'currency' => 'XOF']);
        $otherEstablishment->properties()->create(['name' => 'Other suite', 'slug' => 'other-gallery-suite', 'status' => 'published', 'is_active' => true, 'cover_image' => 'uploads/properties/other-gallery.jpg']);
        $page = $this->get(route('establishments.show', $establishment))->assertOk();
        $this->assertSame([
            asset('uploads/establishments/residence-cover.jpg'), asset('uploads/properties/suite-cover.jpg'),
            asset('uploads/properties/header.jpg'), asset('uploads/establishments/residence-lobby.jpg'),
        ], $page->viewData('gallery')->pluck('url')->all());
        $document = new \DOMDocument();
        @$document->loadHTML($page->getContent());
        $xpath = new \DOMXPath($document);
        $cardImage = $xpath->query('//article[@data-property-id="' . $property->id . '"]//img')->item(0);
        $this->assertSame(asset('uploads/properties/suite-cover.jpg'), $cardImage->getAttribute('src'));
        $this->get(route('establishments.index'))->assertOk()->assertDontSee('uploads/properties/suite-cover.jpg');
        $this->get(route('establishments.show', ['establishment' => $establishment, 'guests' => 100]))->assertOk()
            ->assertViewHas('gallery', fn ($gallery) => $gallery->pluck('url')->all() === $page->viewData('gallery')->pluck('url')->all());
        $establishment->update(['cover_image' => null, 'metadata' => []]);
        $this->get(route('establishments.show', $establishment))->assertOk()->assertViewHas('gallery', fn ($gallery) => $gallery->count() === 2);
    }

    public function test_establishment_gallery_handles_one_home_one_image_and_no_photos(): void
    {
        $tenant = \App\Models\Tenant::firstOrCreate(['slug' => 'default'], ['name' => 'Default', 'status' => 'active']);
        $establishment = Establishment::create([
            'tenant_id' => $tenant->id, 'name' => 'Small residence', 'slug' => 'small-residence',
            'is_active' => true, 'is_published' => true, 'currency' => 'XOF',
            'cover_image' => 'uploads/establishments/small-cover.jpg',
        ]);
        $url = route('establishments.show', $establishment);
        $this->get($url)->assertOk()->assertViewHas('gallery', fn ($gallery) => $gallery->count() === 1)
            ->assertSee(__('messages.establishments.no_homes_description'))->assertDontSee('data-gallery-next', false);
        $property = $establishment->properties()->create([
            'name' => 'Only home', 'slug' => 'only-home', 'status' => 'published', 'is_active' => true,
            'currency' => 'XOF', 'max_guests' => 2, 'minimum_stay' => 2,
        ]);
        $this->get($url)->assertOk()->assertViewHas('gallery', fn ($gallery) => $gallery->count() === 1)
            ->assertSee(route('properties.show', $property), false)->assertDontSee('data-gallery-next', false);
        $property->update(['cover_image' => 'uploads/properties/only-cover.jpg']);
        $this->get($url)->assertOk()->assertViewHas('gallery', fn ($gallery) => $gallery->count() === 2)
            ->assertSee('data-gallery-next', false)->assertSee('data-gallery-caption', false);
        $establishment->update(['cover_image' => null]);
        $this->get($url)->assertOk()->assertViewHas('gallery', fn ($gallery) => $gallery->count() === 1);
        $property->update(['cover_image' => null]);
        $this->get($url)->assertOk()->assertViewHas('gallery', fn ($gallery) => $gallery->isEmpty())
            ->assertDontSee('class="establishment-hero gallery-panel"', false)->assertSee($establishment->name);
        $property->update(['is_active' => false]);
        $this->get($url)->assertOk()->assertSee(__('messages.establishments.no_homes_description'));
    }

    public function test_establishment_reviews_consolidate_unique_rows_with_weighted_rating_and_origin_filters(): void
    {
        $tenant = \App\Models\Tenant::firstOrCreate(['slug' => 'default'], ['name' => 'Default', 'status' => 'active']);
        $establishment = Establishment::create(['tenant_id' => $tenant->id, 'name' => 'Review residence', 'slug' => 'review-residence', 'currency' => 'XOF', 'is_active' => true, 'is_published' => true]);
        $reviewIds = [];
        foreach ([5, 5, 4] as $index => $rating) {
            $reviewIds[] = SiteReview::create(['tenant_id' => $tenant->id, 'establishment_id' => $establishment->id, 'source' => 'google', 'reviewer_name' => 'Residence guest ' . $index, 'rating' => $rating, 'review_text' => 'Residence review ' . $index, 'reviewed_at' => now()->subDays(20 + $index), 'is_active' => true])->id;
        }
        $homeRatings = [[1, 1, 1, 1, 1], [5, 5, 5, 5], [4, 4]];
        $homes = collect();
        foreach ($homeRatings as $number => $ratings) {
            $home = $establishment->properties()->create(['name' => 'Review home ' . $number, 'slug' => 'review-home-' . $number, 'status' => $number === 2 ? 'draft' : 'published', 'is_active' => true, 'currency' => 'XOF', 'max_guests' => 2]);
            $home->translations()->create(['locale' => 'en', 'name' => 'Translated home ' . $number]);
            $homes->push($home);
            foreach ($ratings as $index => $rating) {
                $reviewIds[] = SiteReview::create(['tenant_id' => $tenant->id, 'establishment_id' => $establishment->id, 'property_id' => $home->id, 'source' => 'booking', 'reviewer_name' => 'Home guest ' . $number . '-' . $index, 'rating' => $rating, 'review_text' => 'Home review ' . $number . '-' . $index, 'reviewed_at' => now()->subDays($number * 5 + $index), 'is_active' => true])->id;
            }
        }
        $otherEstablishment = Establishment::create(['tenant_id' => $tenant->id, 'name' => 'Foreign residence', 'slug' => 'foreign-residence', 'currency' => 'XOF', 'is_active' => true, 'is_published' => true]);
        $foreignHome = $otherEstablishment->properties()->create(['name' => 'Foreign home', 'slug' => 'foreign-home', 'status' => 'published', 'is_active' => true, 'currency' => 'XOF']);
        SiteReview::create(['tenant_id' => $tenant->id, 'establishment_id' => $establishment->id, 'property_id' => $foreignHome->id, 'source' => 'google', 'reviewer_name' => 'Foreign review must be excluded', 'rating' => 5, 'is_active' => true]);
        SiteReview::create(['tenant_id' => $tenant->id, 'establishment_id' => $establishment->id, 'source' => 'google', 'reviewer_name' => 'Inactive review', 'rating' => 1, 'is_active' => false]);
        $url = route('establishments.show', $establishment);
        $page = $this->get($url)->assertOk()->assertViewHas('reviewStats', ['count' => 14, 'average' => 3.4])
            ->assertDontSee('Foreign review must be excluded')->assertDontSee('Inactive review');
        $this->assertSame(14, $page->viewData('reviews')->total());
        $this->assertCount(6, $page->viewData('reviews')->items());
        $page->assertSee('Page 1 sur 3')->assertSee(__('messages.establishments.reviews_next'));
        $listedIds = [];
        foreach ([1, 2, 3] as $number) {
            $response = $this->get($url . '?reviews_page=' . $number)->assertOk();
            $listedIds = array_merge($listedIds, $response->viewData('reviews')->pluck('id')->all());
        }
        $this->assertCount(14, array_unique($listedIds));
        $this->assertCount(14, $listedIds);
        $this->assertEqualsCanonicalizing($reviewIds, $listedIds);
        $this->assertSame(array_slice($reviewIds, 3, 5), array_slice($listedIds, 0, 5));
        $direct = $this->get($url . '?review_scope=establishment')->assertOk()->assertViewHas('reviewStats', ['count' => 14, 'average' => 3.4]);
        $this->assertSame(3, $direct->viewData('reviews')->total());
        $this->assertTrue($direct->viewData('reviews')->every(fn ($review) => $review->property_id === null));
        $incompatible = $this->get($url . '?review_scope=establishment&review_property=' . $homes->first()->id)->assertOk();
        $this->assertSame(3, $incompatible->viewData('reviews')->total());
        $this->assertArrayNotHasKey('review_property', $incompatible->viewData('reviewFilters'));
        $propertyReviews = $this->get($url . '?review_scope=properties')->assertOk();
        $this->assertSame(11, $propertyReviews->viewData('reviews')->total());
        $this->assertTrue($propertyReviews->viewData('reviews')->every(fn ($review) => $homes->pluck('id')->contains($review->property_id)));
        $filtered = $this->get($url . '?review_scope=properties&review_property=' . $homes->first()->id . '&guests=2')->assertOk();
        $this->assertSame(5, $filtered->viewData('reviews')->total());
        $this->assertTrue($filtered->viewData('reviews')->every(fn ($review) => $review->property_id === $homes->first()->id));
        $filtered->assertSee('name="guests" value="2"', false);
        $this->assertSame(['count' => 14, 'average' => 3.4], $filtered->viewData('reviewStats'));
        $this->assertStringContainsString('review_scope=properties', $propertyReviews->viewData('reviews')->nextPageUrl());
        $this->assertStringContainsString('#establishment-reviews', $propertyReviews->viewData('reviews')->nextPageUrl());
        $withoutReviews = $establishment->properties()->create(['name' => 'Unreviewed home', 'slug' => 'unreviewed-home', 'status' => 'published', 'is_active' => true, 'currency' => 'XOF']);
        $this->get($url . '?review_property=' . $withoutReviews->id)->assertOk()->assertSee(__('messages.establishments.reviews_no_match'))
            ->assertViewHas('reviewStats', ['count' => 14, 'average' => 3.4]);
        $this->withSession(['locale' => 'en'])->get($url . '?review_property=' . $homes->last()->id)->assertOk()
            ->assertSee('Translated home 2')->assertDontSee('href="' . route('properties.show', $homes->last()) . '"', false);
        $this->getJson($url . '?review_property=' . $foreignHome->id)->assertUnprocessable();
    }

    public function test_home_shows_map_only_for_one_establishment(): void
    {
        $this->seed(DatabaseSeeder::class);
        $firstEstablishment = Establishment::firstOrFail();
        $firstEstablishment->update(['city' => 'Cotonou', 'latitude' => null, 'longitude' => null]);

        $this->get('/')
            ->assertOk()
            ->assertSee('home-location-map', false)
            ->assertSee('Cotonou', false)
            ->assertSee(route('establishments.show', $firstEstablishment), false)
            ->assertDontSee('href="' . route('establishments.index') . '"', false);

        $secondEstablishment = Establishment::create([
            'name' => 'Second Residence',
            'slug' => 'second-residence',
            'country_code' => 'BJ',
            'currency' => 'XOF',
        ]);

        $this->get('/')
            ->assertOk()
            ->assertDontSee('home-location-map', false)
            ->assertSee(route('establishments.show', $firstEstablishment), false);

        Property::query()->where('establishment_id', $firstEstablishment->id)->published()->firstOrFail()
            ->update(['establishment_id' => $secondEstablishment->id]);

        $this->get('/')
            ->assertOk()
            ->assertSee(route('establishments.index'), false);

        $this->get(route('establishments.index'))
            ->assertOk()
            ->assertSee($firstEstablishment->localized('name'))
            ->assertSee($secondEstablishment->name);
    }

    public function test_home_renders_translated_content_for_english_guests(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->withCookie('locale', 'en')
            ->get('/')
            ->assertOk()
            ->assertSee('Apartments designed for stress-free stays.')
            ->assertSee('A stay experience designed for simplicity')
            ->assertSee('© ' . now()->year . ' Afrik Appart. All rights reserved.')
            ->assertSee('This site is powered by Sprint Pay Canada.')
            ->assertSee('Send feedback to Sprint Pay Canada')
            ->assertDontSee('Tous droits réservés.')
            ->assertSee('Search');
    }
}
