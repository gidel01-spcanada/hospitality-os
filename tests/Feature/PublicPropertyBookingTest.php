<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use App\Models\Establishment;
use App\Models\Property;
use App\Models\Reservation;
use App\Models\SiteReview;
use App\Models\User;
use App\Notifications\GuestAccountSetupNotification;
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
        $this->actingAs($user)->get('/dashboard')->assertOk()->assertSee($property->localized('name'));

        $this->actingAs($user)->post(route('properties.favorite.toggle', $property))->assertRedirect();
        $this->assertDatabaseMissing('property_favorites', ['user_id' => $user->id, 'property_id' => $property->id]);
    }

    public function test_public_catalog_and_booking_flow_work(): void
    {
        ini_set('memory_limit', '1G');
        Notification::fake();

        Artisan::call('db:seed');
        Artisan::call('property:sync-media', [
            '--source' => dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'photo',
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

    public function test_home_search_redirects_to_properties_with_selected_filters(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->get('/')
            ->assertOk()
            ->assertSee('action="' . route('properties.index') . '"', false)
            ->assertSee('name="destination"', false)
            ->assertSee('name="guests"', false)
            ->assertSee('type="submit"', false);

        $this->get('/properties?destination=Cotonou&guests=3')
            ->assertOk()
            ->assertSee('<option value="Cotonou" selected>Cotonou</option>', false)
            ->assertSee('value="3"', false);

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
            'source' => 'google',
            'reviewer_name' => 'Awa',
            'rating' => 5,
            'review_text' => 'Wonderful stay',
            'is_active' => true,
            'reviewed_at' => now(),
        ]);

        $this->get('/properties/appartement-401')
            ->assertOk()
            ->assertSee('Wonderful stay')
            ->assertSee('location-map', false)
            ->assertSee('property="og:image"', false)
            ->assertSee('facebook.com/sharer', false);
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
