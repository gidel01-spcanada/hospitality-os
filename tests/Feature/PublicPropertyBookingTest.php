<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Artisan;
use App\Models\Establishment;
use App\Models\ExternalCalendarEvent;
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

        $this->postJson('/properties/' . $property->slug . '/availability', [
            'check_in' => now()->addMonths(2)->startOfMonth()->toDateString(),
            'check_out' => now()->addMonths(2)->startOfMonth()->addDays(2)->toDateString(),
        ])->assertOk()->assertJson(['available' => true]);
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

    public function test_unverified_existing_account_email_receives_confirmation_email_instead_of_login_prompt(): void
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

        $response = $this->post('/properties/' . $property->slug . '/reserve', [
            'full_name' => 'Unverified Customer',
            'email' => 'unverified@example.com',
            'check_in' => now()->addDay()->toDateString(),
            'check_out' => now()->addDays(3)->toDateString(),
            'adults' => 2,
            'children' => 0,
            'infants' => 0,
        ]);

        $response->assertRedirect();
        $this->assertNotSame(route('login'), $response->headers->get('Location'));
        $response->assertSessionHas('pending_public_reservation');
        $response->assertSessionHas('status', __('messages.auth.confirm_email_before_reservation'));

        $this->assertDatabaseMissing('reservations', ['email' => 'unverified@example.com']);
        Notification::assertSentTo($unverified, GuestAccountSetupNotification::class);
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

        $response = $this->post('/properties/appartement-401/reserve', [
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
        $account = User::query()->where('email', 'alice@example.com')->firstOrFail();
        $this->assertSame($account->id, $reservation->user_id);
        Notification::assertSentTo($account, GuestAccountSetupNotification::class);
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

        $this->get('/properties?establishment=' . $other->id . '&guests=5&bedrooms=2')
            ->assertOk()
            ->assertSee('Porto-Novo Suite')
            ->assertDontSee('Appartement 401');
    }

    public function test_home_search_accepts_one_traveler(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->get('/')
            ->assertOk()
            ->assertSee('<option value="1"', false)
            ->assertSee('1 voyageur')
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
            'check_out' => '2031-12-12',
        ])->assertOk()->assertJson(['available' => false]);

        $this->postJson('/properties/' . $property->slug . '/availability', [
            'check_in' => '2031-12-13',
            'check_out' => '2031-12-14',
        ])->assertOk()->assertJson(['available' => true]);

        $feed->update(['is_enabled' => false]);

        $this->postJson('/properties/' . $property->slug . '/availability', [
            'check_in' => '2031-12-11',
            'check_out' => '2031-12-12',
        ])->assertOk()->assertJson(['available' => true]);
    }

    public function test_home_search_redirects_to_properties_with_selected_filters(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->get('/')
            ->assertOk()
            ->assertSee('action="' . route('properties.index') . '"', false)
            ->assertSee('name="destination"', false)
            ->assertSee('name="guests"', false)
            ->assertSee('type="submit"', false);

        $this->get('/?destination=Cotonou&guests=3&check_in=2026-10-01&check_out=2026-10-03')
            ->assertOk()
            ->assertSee('<option value="Cotonou" selected>Cotonou</option>', false)
            ->assertSee('name="check_in" value="2026-10-01"', false)
            ->assertSee('name="check_out" value="2026-10-03"', false)
            ->assertSee('name="guests"', false)
            ->assertSee('<option value="3" selected>3 voyageurs</option>', false);

        $this->get('/properties?destination=Cotonou&guests=3&check_in=2026-10-01&check_out=2026-10-03')
            ->assertOk()
            ->assertSee('<option value="Cotonou" selected>Cotonou</option>', false)
            ->assertSee('value="3"', false)
            ->assertSee('name="check_in" value="2026-10-01" data-date-range-start', false)
            ->assertSee('name="check_out" value="2026-10-03" data-date-range-end', false);

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

    public function test_property_detail_displays_reviews_map_and_social_preview_data(): void
    {
        $this->seed(DatabaseSeeder::class);
        $property = Property::where('slug', 'appartement-401')->firstOrFail();
        $property->establishment->update([
            'latitude' => 6.3703,
            'longitude' => 2.3912,
            'google_maps_url' => 'https://maps.google.com/?q=6.3703,2.3912',
        ]);
        SiteReview::create([
            'tenant_id' => $property->establishment->tenant_id,
            'source' => 'google',
            'reviewer_name' => 'Awa',
            'rating' => 5,
            'review_text' => 'Wonderful stay',
            'is_active' => true,
            'reviewed_at' => '2026-09-11 10:00:00',
        ]);
        SiteReview::create([
            'tenant_id' => $property->establishment->tenant_id,
            'source' => 'booking',
            'reviewer_name' => 'Moussa',
            'rating' => 4,
            'review_text' => 'Excellent location',
            'is_active' => true,
            'reviewed_at' => '2026-09-10 10:00:00',
        ]);
        SiteReview::create([
            'tenant_id' => $property->establishment->tenant_id,
            'source' => 'booking',
            'reviewer_name' => 'Fatou',
            'rating' => 5,
            'review_text' => 'Très propre',
            'is_active' => true,
            'reviewed_at' => '2026-09-09 10:00:00',
        ]);
        SiteReview::create([
            'tenant_id' => $property->establishment->tenant_id,
            'source' => 'google',
            'reviewer_name' => 'Jean',
            'rating' => 3,
            'review_text' => 'Bon séjour',
            'is_active' => true,
            'reviewed_at' => '2026-09-08 10:00:00',
        ]);
        BrandSettings::set([
            'review_source_booking_url' => 'https://www.booking.com/hotel/example',
            'review_source_google_url' => 'https://maps.google.com/?cid=example',
        ]);

        $this->get('/properties/appartement-401')
            ->assertOk()
            ->assertSee('Wonderful stay')
            ->assertSee('Excellent location')
            ->assertSee('11/09/2026')
            ->assertSee('Basé sur 4 avis ajoutés à la plateforme.')
            ->assertSee('Voir tous les avis')
            ->assertSee('Bénin')
            ->assertDontSee('>BJ<', false)
            ->assertSee('google')
            ->assertSee('Booking.com')
            ->assertDontSee('https://www.booking.com/hotel/example', false)
            ->assertDontSee('https://maps.google.com/?cid=example', false)
            ->assertSee('location-map', false)
            ->assertSee('property="og:image"', false)
            ->assertSee('facebook.com/sharer', false);

            $this->get('/reviews')
                ->assertOk()
                ->assertSee('Tous les avis voyageurs')
                ->assertSee('Awa')
                ->assertSee('Moussa')
                ->assertSee('Booking.com')
                ->assertSee('Fatou')
                ->assertSee('Jean')
                ->assertSee('Basé sur 4 avis ajoutés à la plateforme.');
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
            ->assertSee(route('properties.index'), false);
    }

    public function test_home_shows_map_only_for_one_establishment(): void
    {
        $this->seed(DatabaseSeeder::class);
        Establishment::firstOrFail()->update(['city' => 'Cotonou', 'latitude' => null, 'longitude' => null]);

        $this->get('/')->assertOk()->assertSee('home-location-map', false)->assertSee('Cotonou', false);

        Establishment::create([
            'name' => 'Second Residence',
            'slug' => 'second-residence',
            'country_code' => 'BJ',
            'currency' => 'XOF',
        ]);

        $this->get('/')->assertOk()->assertDontSee('home-location-map', false);
    }

    public function test_home_renders_translated_content_for_english_guests(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->withCookie('locale', 'en')
            ->get('/')
            ->assertOk()
            ->assertSee('Apartments designed for stress-free stays.')
            ->assertSee('A stay experience designed for simplicity')
            ->assertSee('Search');
    }
}
