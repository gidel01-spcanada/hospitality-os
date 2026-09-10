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
            ->assertSee('AFK-CUST-001')
            ->assertSee('AFK-CUST-002');

        $this->actingAs($user)
            ->get('/dashboard/reservations/' . $reservation->id)
            ->assertOk()
            ->assertSee('Détails')
            ->assertSee('Confirmée');
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
            ->assertSee('Informations générales')
            ->assertSee('Communications par e-mail');

        $this->actingAs($admin)
            ->post('/admin/settings', [
                'site_name' => 'Maison Bleu',
                'site_icon' => 'MB',
                'customer_theme' => 'ocean-coral',
                'site_tagline' => 'Vivez des séjours paisibles en Afrique.',
                'footer_copyright' => '© :year Maison Bleu. Tous droits réservés.',
                'contact_email' => 'hello@maisonbleu.example',
                'support_phone' => '+229 97 00 00 00',
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
            ])
            ->assertRedirect('/admin/settings');

        $this->assertDatabaseHas('settings', ['key' => 'site_name', 'value' => 'Maison Bleu']);
        $this->assertDatabaseHas('settings', ['key' => 'site_icon', 'value' => 'MB']);
        $this->assertDatabaseHas('settings', ['key' => 'customer_theme', 'value' => 'ocean-coral']);
        $this->assertDatabaseHas('settings', ['key' => 'contact_email', 'value' => 'hello@maisonbleu.example']);
        $this->assertDatabaseHas('settings', ['key' => 'footer_copyright', 'value' => '© :year Maison Bleu. Tous droits réservés.']);
        $this->assertDatabaseHas('settings', ['key' => 'review_source_booking_url', 'value' => 'https://www.booking.com/hotel/fr/maison-bleu.html']);
        $this->assertDatabaseHas('settings', ['key' => 'review_source_google_url', 'value' => 'https://maps.google.com/?q=Maison+Bleu']);
        $this->assertDatabaseHas('settings', ['key' => 'email_sender_name', 'value' => 'Maison Bleu']);
        $this->assertDatabaseHas('settings', ['key' => 'customer_confirmation_subject', 'value' => 'Confirmation de votre demande']);

        $this->get('/')
            ->assertOk()
            ->assertSee('data-site-theme="ocean-coral"', false)
            ->assertSee('class="brand-mark">MB</span>', false);

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

        $this->get('/')->assertOk()->assertSee('Amina D.');
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
