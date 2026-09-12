<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerSelfServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_reset_link_and_support_pages_are_available(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->get('/login')
            ->assertOk()
            ->assertSee('Mot de passe oublié ?', false)
            ->assertSee('href="' . route('password.request') . '"', false)
            ->assertSee('href="' . route('privacy') . '"', false)
            ->assertSee('href="' . route('terms') . '"', false)
            ->assertSee('href="' . route('cookies') . '"', false)
            ->assertSee('© ' . now()->year . ' Afrik Appart. Tous droits réservés.', false);

        $user = User::factory()->create([
            'name' => 'Amina Diallo',
            'email' => 'amina@example.com',
            'role' => 'customer',
        ]);

        $this->get('/forgot-password')
            ->assertOk()
            ->assertSee('Mot de passe oublié');

        $this->post('/forgot-password', ['email' => 'amina@example.com'])
            ->assertSessionHas('status');

        $this->assertDatabaseHas('email_outbox', [
            'recipient_email' => 'amina@example.com',
            'template' => 'password_reset_requested',
            'status' => 'queued',
        ]);

        $this->get('/contact')->assertOk()->assertSee('Contactez-nous');
        $this->get('/about')->assertOk()->assertSee('À propos');
        $this->get('/privacy')->assertOk()->assertSee('Politique de confidentialité');
        $this->get('/terms')->assertOk()->assertSee('Conditions générales');
        $this->get('/cookies')->assertOk()->assertSee('Politique relative aux cookies');

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk();
    }
}
