<?php

namespace Tests\Feature;

use App\Models\Property;
use App\Models\Reservation;
use App\Models\ReservationGuest;
use App\Models\SiteReview;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CustomerDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_dashboard_lists_and_shows_their_reservations(): void
    {
        $this->seed(DatabaseSeeder::class);

        $user = User::factory()->create([
            'name' => 'Amina Diallo',
            'email' => 'amina@example.com',
            'role' => 'customer',
            'is_admin' => false,
            'locale' => 'fr',
            'email_booking_updates' => true,
            'email_marketing' => false,
            'email_newsletter' => false,
        ]);

        $property = Property::where('slug', 'appartement-401')->firstOrFail();
        $property->features()->update(['is_active' => false]);
        $guest = ReservationGuest::query()->create([
            'full_name' => 'Amina Diallo',
            'email' => 'amina@example.com',
            'country' => 'Bénin',
            'metadata' => ['source' => 'dashboard-test'],
        ]);

        $reservation = Reservation::query()->create([
            'property_id' => $property->id,
            'guest_id' => $guest->id,
            'user_id' => $user->id,
            'reservation_ref' => 'AFK-CUST-001',
            'status' => 'confirmed',
            'check_in' => now()->addDays(4)->toDateString(),
            'check_out' => now()->addDays(7)->toDateString(),
            'adults' => 2,
            'children' => 0,
            'infants' => 0,
            'currency' => 'XOF',
            'email' => 'amina@example.com',
            'subtotal' => 150000,
            'fees' => 15000,
            'taxes' => 7500,
            'total_amount' => 172500,
            'source' => 'website',
            'notes' => 'Booked as authenticated customer.',
        ]);

        Reservation::query()->create([
            'property_id' => $property->id,
            'guest_id' => $guest->id,
            'user_id' => $user->id,
            'reservation_ref' => 'AFK-CUST-002',
            'status' => 'pending',
            'check_in' => now()->addDays(15)->toDateString(),
            'check_out' => now()->addDays(18)->toDateString(),
            'adults' => 2,
            'children' => 0,
            'infants' => 0,
            'currency' => 'XOF',
            'email' => 'amina@example.com',
            'subtotal' => 150000,
            'fees' => 15000,
            'taxes' => 7500,
            'total_amount' => 172500,
            'source' => 'website',
            'notes' => 'Pending review.',
        ]);

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('data-portal-sidebar', false)
            ->assertSee(route('messages.index'), false)
            ->assertSee(route('account.preferences'), false)
            ->assertDontSee(route('admin.reservations.index'), false)
            ->assertSee('AFK-CUST-001')
            ->assertSee('AFK-CUST-002');

        $this->actingAs($user)
            ->get('/dashboard/reservations/' . $reservation->id)
            ->assertOk()
            ->assertSee('Détails')
            ->assertSee('Confirmée')
            ->assertSee(__('messages.reservation.taxes'))
            ->assertSee('7 500 XOF')
            ->assertDontSee(__('messages.properties.extra_options'))
            ->assertSee(route('dashboard.reservations.show', ['reservation' => $reservation, 'view' => 'edit']))
            ->assertSee(__('messages.messages.contact_concierge'))
            ->assertDontSee('modification_check_in');

        $this->actingAs($user)
            ->get(route('dashboard.reservations.show', ['reservation' => $reservation, 'view' => 'edit']))
            ->assertOk()
            ->assertSee('modification_check_in')
            ->assertSee(__('messages.messages.contact_concierge'))
            ->assertSee(__('messages.messages.send'));
    }

    public function test_customer_reservation_periods_show_only_matching_stays(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = User::factory()->create(['role' => 'customer', 'email' => 'periods@example.com']);
        $property = Property::where('slug', 'appartement-401')->firstOrFail();
        $guest = ReservationGuest::create(['full_name' => $user->name, 'email' => $user->email]);
        $reservations = [
            ['reference' => 'AFK-PERIOD-UPCOMING', 'status' => 'pending_payment', 'check_in' => now()->addDays(8), 'check_out' => now()->addDays(10)],
            ['reference' => 'AFK-PERIOD-CURRENT', 'status' => 'checked_in', 'check_in' => now()->subDay(), 'check_out' => now()->addDay()],
            ['reference' => 'AFK-PERIOD-COMPLETED', 'status' => 'completed', 'check_in' => now()->subDays(5), 'check_out' => now()->subDays(3)],
            ['reference' => 'AFK-PERIOD-CANCELLED', 'status' => 'cancelled', 'check_in' => now()->addDays(12), 'check_out' => now()->addDays(14)],
        ];

        foreach ($reservations as $item) {
            Reservation::create([
                'property_id' => $property->id,
                'guest_id' => $guest->id,
                'user_id' => $user->id,
                'reservation_ref' => $item['reference'],
                'status' => $item['status'],
                'check_in' => $item['check_in']->toDateString(),
                'check_out' => $item['check_out']->toDateString(),
                'adults' => 1,
                'children' => 0,
                'infants' => 0,
                'currency' => 'XOF',
                'email' => $user->email,
                'subtotal' => 50000,
                'total_amount' => 50000,
                'source' => 'website',
            ]);
        }

        foreach ([
            'upcoming' => ['AFK-PERIOD-UPCOMING', ['AFK-PERIOD-CURRENT', 'AFK-PERIOD-COMPLETED', 'AFK-PERIOD-CANCELLED']],
            'current' => ['AFK-PERIOD-CURRENT', ['AFK-PERIOD-UPCOMING', 'AFK-PERIOD-COMPLETED', 'AFK-PERIOD-CANCELLED']],
            'completed' => ['AFK-PERIOD-COMPLETED', ['AFK-PERIOD-UPCOMING', 'AFK-PERIOD-CURRENT', 'AFK-PERIOD-CANCELLED']],
            'cancelled' => ['AFK-PERIOD-CANCELLED', ['AFK-PERIOD-UPCOMING', 'AFK-PERIOD-CURRENT', 'AFK-PERIOD-COMPLETED']],
        ] as $period => [$visible, $hidden]) {
            $response = $this->actingAs($user)->get(route('dashboard', ['period' => $period]))->assertOk()->assertSee($visible);
            foreach ($hidden as $reference) {
                $response->assertDontSee($reference);
            }
        }
    }

    public function test_concierge_dashboard_does_not_show_customer_reservations_matching_their_email(): void
    {
        $this->seed(DatabaseSeeder::class);
        $property = Property::where('slug', 'appartement-401')->firstOrFail();
        $concierge = User::factory()->create([
            'email' => 'shared-staff-booking@example.com',
            'role' => 'concierge',
            'is_admin' => false,
            'tenant_id' => $property->establishment->tenant_id,
        ]);
        $guest = ReservationGuest::create(['full_name' => 'Staff Email Guest', 'email' => $concierge->email]);
        Reservation::create([
            'property_id' => $property->id,
            'guest_id' => $guest->id,
            'reservation_ref' => 'AFK-STAFF-EMAIL-COLLISION',
            'status' => 'confirmed',
            'check_in' => now()->addDays(10)->toDateString(),
            'check_out' => now()->addDays(12)->toDateString(),
            'adults' => 1,
            'children' => 0,
            'infants' => 0,
            'currency' => 'XOF',
            'email' => $concierge->email,
            'subtotal' => 50000,
            'total_amount' => 50000,
            'source' => 'website',
        ]);

        $this->actingAs($concierge)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('data-portal-sidebar', false)
            ->assertDontSee('AFK-STAFF-EMAIL-COLLISION')
            ->assertSee(route('admin.reservations.index'), false)
            ->assertSee(route('admin.messages.index'), false)
            ->assertDontSee(route('admin.properties.index'), false);
    }

    public function test_customer_can_modify_reservation_dates_and_guests(): void
    {
        $this->seed(DatabaseSeeder::class);

        $user = User::factory()->create([
            'name' => 'Reservation Customer',
            'email' => 'modify@example.com',
            'role' => 'customer',
        ]);
        $property = Property::where('slug', 'appartement-401')->firstOrFail();
        $guest = ReservationGuest::create(['full_name' => $user->name, 'email' => $user->email]);
        $reservation = Reservation::create([
            'property_id' => $property->id,
            'guest_id' => $guest->id,
            'user_id' => $user->id,
            'reservation_ref' => 'AFK-MODIFY-001',
            'status' => 'confirmed',
            'check_in' => now()->addDays(30)->toDateString(),
            'check_out' => now()->addDays(33)->toDateString(),
            'adults' => 1,
            'children' => 0,
            'infants' => 0,
            'currency' => 'XOF',
            'email' => $user->email,
            'subtotal' => 75000,
            'fees' => 7500,
            'taxes' => 3750,
            'total_amount' => 86250,
            'source' => 'website',
        ]);
        $newCheckIn = now()->addDays(40)->toDateString();
        $newCheckOut = now()->addDays(44)->toDateString();

        $this->actingAs($user)
            ->put(route('dashboard.reservations.update', $reservation), [
                'check_in' => $newCheckIn,
                'check_out' => $newCheckOut,
                'adults' => 1,
                'children' => 1,
                'infants' => 0,
            ])
            ->assertRedirect(route('dashboard.reservations.show', $reservation));

        $updated = $reservation->fresh();
    $this->assertSame($newCheckIn, $updated->check_in?->toDateString());
        $this->assertSame(1, $updated->adults);
        $this->assertSame(1, $updated->children);
        $this->assertSame('pending_payment', $updated->status);
        $this->assertDatabaseHas('reservation_price_lines', ['reservation_id' => $reservation->id, 'label' => 'Nuit(s) x 4']);
    }

    public function test_customer_can_update_language_and_email_preferences(): void
    {
        $this->seed(DatabaseSeeder::class);

        $user = User::factory()->create([
            'name' => 'Amina Diallo',
            'email' => 'preferences@example.com',
            'role' => 'customer',
            'is_admin' => false,
            'locale' => 'fr',
            'email_booking_updates' => true,
            'email_marketing' => false,
            'email_newsletter' => false,
        ]);

        $this->actingAs($user)
            ->from('/dashboard')
            ->post('/dashboard/preferences', [
                'locale' => 'en',
                'email_booking_updates' => '1',
                'email_marketing' => '1',
                'email_newsletter' => '0',
            ])
            ->assertRedirect('/dashboard');

        $user->refresh();

        $this->assertSame('en', $user->locale);
        $this->assertTrue($user->email_booking_updates);
        $this->assertTrue($user->email_marketing);
        $this->assertFalse($user->email_newsletter);
        $this->assertSame('en', session('locale'));
    }

    public function test_admin_can_update_brand_settings(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = User::factory()->create([
            'name' => 'Nadia Admin',
            'email' => 'admin-brand@example.com',
            'role' => 'admin',
            'is_admin' => true,
            'locale' => 'fr',
        ]);

        $this->actingAs($admin)
            ->get('/admin/settings')
            ->assertOk()
            ->assertSee('Paramètres du site')
            ->assertSee('Générale')
            ->assertSee('Réseaux sociaux')
            ->assertSee('Communications par e-mail');

        $settings = [
                'site_name' => 'Maison Bleu',
                'site_icon' => 'MB',
                'site_icon_only' => '1',
                'site_favicon_url' => 'https://images.example.com/favicon.png',
                'customer_theme' => 'ocean-coral',
                'site_tagline' => 'Vivez des séjours paisibles en Afrique.',
            'homepage_background_image' => 'https://images.example.com/home.jpg',
                'footer_copyright' => '© :year Maison Bleu. Tous droits réservés.',
                'contact_email' => 'hello@maisonbleu.example',
                'support_phone' => '+229 97 00 00 00',
                'social_instagram_url' => 'https://instagram.com/maisonbleu',
                'social_whatsapp_url' => 'https://wa.me/22997000000',
                'default_locale' => 'fr',
                'secondary_locale' => 'en',
                'review_source_booking_url' => 'https://www.booking.com/hotel/fr/maison-bleu.html',
                'review_source_google_url' => 'https://maps.google.com/?q=Maison+Bleu',
                'email_sender_name' => 'Maison Bleu',
                'email_sender_email' => 'hello@maisonbleu.example',
                'customer_confirmation_subject' => 'Confirmation de votre demande',
                'customer_confirmation_message' => 'Merci, nous avons bien reçu votre demande.',
                'pre_arrival_subject' => 'Votre arrivée',
                'pre_arrival_message' => 'Nous vous attendons bientôt.',
                'post_stay_subject' => 'Merci pour votre séjour',
                'post_stay_message' => 'Merci pour votre confiance.',
            ];

        $this->actingAs($admin)
            ->post('/admin/settings', $settings)
            ->assertRedirect('/admin/settings');

        $this->assertDatabaseHas('settings', ['key' => 'site_name', 'value' => 'Maison Bleu']);
        $this->assertDatabaseHas('settings', ['key' => 'site_icon', 'value' => 'MB']);
        $this->assertDatabaseHas('settings', ['key' => 'site_icon_only', 'value' => '1']);
        $this->assertDatabaseHas('settings', ['key' => 'site_favicon_url', 'value' => 'https://images.example.com/favicon.png']);
        $this->assertDatabaseHas('settings', ['key' => 'customer_theme', 'value' => 'ocean-coral']);
        $this->assertDatabaseHas('settings', ['key' => 'contact_email', 'value' => 'hello@maisonbleu.example']);
        $this->assertDatabaseHas('settings', ['key' => 'social_instagram_url', 'value' => 'https://instagram.com/maisonbleu']);
        $this->assertDatabaseHas('settings', ['key' => 'social_whatsapp_url', 'value' => 'https://wa.me/22997000000']);
        $this->assertDatabaseHas('settings', ['key' => 'footer_copyright', 'value' => '© :year Maison Bleu. Tous droits réservés.']);
        $this->assertDatabaseHas('settings', ['key' => 'review_source_booking_url', 'value' => 'https://www.booking.com/hotel/fr/maison-bleu.html']);
        $this->assertDatabaseHas('settings', ['key' => 'review_source_google_url', 'value' => 'https://maps.google.com/?q=Maison+Bleu']);
        $this->assertDatabaseHas('settings', ['key' => 'homepage_background_image', 'value' => 'https://images.example.com/home.jpg']);
        $this->assertDatabaseHas('settings', ['key' => 'email_sender_name', 'value' => 'Maison Bleu']);
        $this->assertDatabaseHas('settings', ['key' => 'customer_confirmation_subject', 'value' => 'Confirmation de votre demande']);

        $this->get('/')
            ->assertOk()
            ->assertSee('data-site-theme="ocean-coral"', false)
            ->assertSee('class="brand-mark brand-mark-image" src="https://images.example.com/favicon.png"', false)
            ->assertDontSee('class="brand-mark">MB</span>', false)
            ->assertSee('rel="icon" href="https://images.example.com/favicon.png"', false)
            ->assertSee('Maison Bleu')
            ->assertSee('href="https://instagram.com/maisonbleu"', false)
            ->assertSee('href="https://wa.me/22997000000"', false)
            ->assertSee('aria-label="Réseaux sociaux"', false)
            ->assertSee('aria-label="Instagram" title="Instagram"', false)
            ->assertSee('aria-label="WhatsApp" title="WhatsApp"', false)
            ->assertSee('<svg', false)
            ->assertSee('https://images.example.com/home.jpg');

        $settingsWithoutSocial = array_replace($settings, ['social_instagram_url' => '', 'social_whatsapp_url' => '']);
        $this->actingAs($admin)->post('/admin/settings', $settingsWithoutSocial)->assertRedirect('/admin/settings');
        $this->assertDatabaseMissing('settings', ['key' => 'social_instagram_url']);
        $this->get('/')->assertOk()->assertDontSee('aria-label="Réseaux sociaux"', false);

        $this->actingAs($admin)->post('/admin/settings', array_replace($settings, ['social_instagram_url' => 'javascript:alert(1)']))
            ->assertSessionHasErrors('social_instagram_url');

        $this->actingAs($admin)
            ->post('/admin/settings', $settings + ['restore_homepage_background_image' => '1'])
            ->assertRedirect('/admin/settings');

        $this->assertDatabaseMissing('settings', ['key' => 'homepage_background_image']);
        $this->get('/')
            ->assertOk()
            ->assertSee('https://images.unsplash.com/photo-1505693416388-ac5ce068fe85?auto=format');

        $this->actingAs($admin)
            ->get('/admin/users')
            ->assertOk()
            ->assertSee('Gestion des utilisateurs');

        $this->actingAs($admin)
            ->post('/admin/reviews', [
                'source' => 'booking',
                'reviewer_name' => 'Amina D.',
                'rating' => 5,
                'review_text' => 'Très bon accueil et appartement magnifique.',
                'source_url' => 'https://www.booking.com/1',
                'reviewed_at' => now()->toDateString(),
                'is_active' => true,
            ])
            ->assertRedirect('/admin/reviews');

        $this->assertDatabaseHas('site_reviews', ['reviewer_name' => 'Amina D.', 'source' => 'booking']);

        $this->get('/')->assertOk()->assertDontSee('Amina D.');
    }

    public function test_admin_can_import_booking_reviews_from_csv(): void
    {
        $this->seed(DatabaseSeeder::class);

        $csv = implode("\n", [
            '"Date du commentaire","Nom du client","Numéro de réservation","Titre du commentaire","Commentaire positif","Commentaire négatif","Note des commentaires"',
            '"2026-09-09 15:17:26","KOFFI","6659892745","","Très propre","","10"',
            '"2026-09-07 01:34:33","Sesede","5227705165","Exceptional","Feels like home","","9"',
        ]);
        $path = storage_path('framework/testing-booking-reviews.csv');
        file_put_contents($path, $csv);

        $this->artisan('reviews:import-booking-csv', ['path' => $path])
            ->expectsOutput('Import complete. Inserted: 2; updated: 0; skipped: 0.')
            ->assertExitCode(0);

        $this->artisan('reviews:import-booking-csv', ['path' => $path])
            ->expectsOutput('Import complete. Inserted: 0; updated: 2; skipped: 0.')
            ->assertExitCode(0);

        $this->assertDatabaseHas('site_reviews', [
            'source' => 'booking',
            'reviewer_name' => 'KOFFI',
            'rating' => 5,
            'review_text' => 'Très propre',
            'source_url' => null,
            'is_active' => true,
        ]);
        $this->assertSame(2, SiteReview::query()->where('source', 'booking')->count());

        @unlink($path);
    }

    public function test_admin_can_update_and_delete_reviews(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = User::factory()->create([
            'name' => 'Nadia Admin',
            'email' => 'admin-reviews@example.com',
            'role' => 'admin',
            'is_admin' => true,
            'locale' => 'fr',
        ]);

        $review = SiteReview::query()->create([
            'source' => 'booking',
            'reviewer_name' => 'Amina D.',
            'rating' => 5,
            'review_text' => 'Très bon accueil.',
            'source_url' => 'https://www.booking.com/1',
            'reviewed_at' => now(),
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->put('/admin/reviews/' . $review->id, [
                'source' => 'google',
                'reviewer_name' => 'Amina D. Modifiée',
                'rating' => 4,
                'review_text' => 'Très bon accueil et emplacement parfait.',
                'source_url' => 'https://maps.google.com/review/1',
                'reviewed_at' => now()->toDateString(),
                'is_active' => '1',
            ])
            ->assertRedirect('/admin/reviews');

        $this->assertDatabaseHas('site_reviews', [
            'id' => $review->id,
            'source' => 'google',
            'reviewer_name' => 'Amina D. Modifiée',
            'rating' => 4,
        ]);

        $this->actingAs($admin)
            ->delete('/admin/reviews/' . $review->id)
            ->assertRedirect('/admin/reviews');

        $this->assertDatabaseMissing('site_reviews', ['id' => $review->id]);
    }

    public function test_admin_can_filter_reviews_by_property_source_rating_and_date(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::query()->where('email', 'admin@afrikappart.test')->firstOrFail();
        $property = Property::query()->firstOrFail();
        SiteReview::query()->create([
            'tenant_id' => $property->establishment->tenant_id,
            'property_id' => $property->id,
            'establishment_id' => $property->establishment_id,
            'source' => 'google',
            'reviewer_name' => 'Visible Review',
            'rating' => 5,
            'reviewed_at' => '2027-01-15',
            'is_active' => true,
        ]);
        SiteReview::query()->create([
            'tenant_id' => $property->establishment->tenant_id,
            'establishment_id' => $property->establishment_id,
            'source' => 'booking',
            'reviewer_name' => 'Hidden Review',
            'rating' => 2,
            'reviewed_at' => '2026-01-15',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.reviews', [
                'property' => $property->id,
                'source' => 'google',
                'rating' => 5,
                'date_from' => '2027-01-01',
                'date_to' => '2027-01-31',
            ]))
            ->assertOk()
            ->assertSee('Visible Review')
            ->assertDontSee('Hidden Review');
    }

    public function test_customer_can_update_profile_name_and_open_preferences_page(): void
    {
        $this->seed(DatabaseSeeder::class);

        $user = User::factory()->create([
            'name' => 'Amina Diallo',
            'email' => 'profile@example.com',
            'role' => 'customer',
            'is_admin' => false,
            'locale' => 'fr',
        ]);

        $this->actingAs($user)
            ->get('/account')
            ->assertOk()
            ->assertSee('Mon profil');

        $this->actingAs($user)
            ->post('/account', ['name' => 'Amina B. Diallo'])
            ->assertRedirect('/account');

        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'Amina B. Diallo']);

        $this->actingAs($user)
            ->get('/account/preferences')
            ->assertOk()
            ->assertSee('Préférences');

        $this->actingAs($user)
            ->get('/account/security')
            ->assertOk()
            ->assertSee('Sécurité du compte');

        $this->actingAs($user)
            ->post('/account/security', [
                'current_password' => 'password',
                'password' => 'newPassword123',
                'password_confirmation' => 'newPassword123',
            ])
            ->assertRedirect('/account/security');

        $this->assertTrue(Hash::check('newPassword123', $user->fresh()->password));
    }
}
