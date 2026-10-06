<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerSelfServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_security_emails_use_the_shared_frame_recipient_locale_and_original_tokens(): void
    {
        $user = User::factory()->create(['name' => 'Amina Customer', 'locale' => 'en', 'role' => 'customer']);
        app()->setLocale('fr');
        $user->sendPasswordResetNotification('original-reset-token');
        $transport = \Illuminate\Support\Facades\Mail::mailer()->getSymfonyTransport();
        $reset = $transport->messages()->last()->getOriginalMessage();
        $html = $reset->getHtmlBody();
        $this->assertStringContainsString('Hello Amina,', $html);
        $this->assertStringContainsString('Reset your password', $html);
        $this->assertStringContainsString('original-reset-token', $html);
        $this->assertStringContainsString(urlencode($user->email), $html);
        $this->assertStringContainsString('This link is valid for 24 hour(s).', $html);
        $this->assertSame('Reset your password', $reset->getSubject());
        $this->assertSame('fr', app()->getLocale());
        $checkoutReturn = url('/reservations/123/checkout?token=checkout-token');
        $notification = new \App\Notifications\GuestAccountSetupNotification('original-activation-token', 'ACCOUNT-RESERVATION', $checkoutReturn);
        $user->notify($notification);
        $activation = $transport->messages()->last()->getOriginalMessage();
        $html = $activation->getHtmlBody();
        $this->assertStringContainsString('Hello Amina,', $html);
        $this->assertStringContainsString('Welcome to', $html);
        $this->assertStringContainsString('original-activation-token', $html);
        $this->assertStringContainsString(urlencode($checkoutReturn), $html);
        $this->assertStringContainsString('ACCOUNT-RESERVATION', $html);
        $this->assertStringContainsString(route('privacy'), $html);
        $this->assertSame('Follow your reservation – Reservation number ACCOUNT-RESERVATION', $activation->getSubject());
        $this->assertSame('fr', app()->getLocale());
        \App\Support\BrandSettings::set(['guest_account_setup_subject' => 'Personal account subject', 'guest_account_setup_message' => 'Hello :name, booking :reservation_ref.']);
        $mail = $notification->toMail($user);
        $this->assertSame('Personal account subject', $mail->subject);
        $this->assertSame('Hello Amina Customer, booking ACCOUNT-RESERVATION.', $mail->viewData['account_message']);
        $this->assertStringContainsString('original-activation-token', $mail->actionUrl);
    }

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

    public function test_customer_can_reset_password_and_invalid_links_explain_what_to_do(): void
    {
        $this->seed(DatabaseSeeder::class);
        \Illuminate\Support\Facades\Notification::fake();
        $customer = User::factory()->create(['role' => 'customer', 'email' => 'reset-guest@example.com', 'email_verified_at' => null]);

        $this->post(route('password.email'), ['email' => 'unknown@example.com'])
            ->assertSessionHas('status', __('messages.auth.reset_link_sent'))->assertSessionHasNoErrors();
        $this->post(route('password.email'), ['email' => $customer->email])->assertSessionHas('status', __('messages.auth.reset_link_sent'));
        $this->post(route('password.email'), ['email' => $customer->email])->assertSessionHasErrors(['email' => __('messages.auth.reset_throttled')]);

        $token = null;
        \Illuminate\Support\Facades\Notification::assertSentTo($customer, \Illuminate\Auth\Notifications\ResetPassword::class, function ($notification) use (&$token) {
            $token = $notification->token;

            return true;
        });

        $resetUrl = route('password.reset', ['token' => $token, 'email' => $customer->email]);
        $this->from($resetUrl)->post(route('password.update'), [
            'token' => 'stale-token', 'email' => $customer->email,
            'password' => 'NewPassword123!', 'password_confirmation' => 'NewPassword123!',
        ])->assertRedirect(route('password.request'))->assertSessionHasErrors(['email' => __('messages.auth.reset_link_invalid')]);
        $this->get(route('password.request'))->assertSee(__('messages.auth.reset_link_invalid'))->assertSee('value="' . $customer->email . '"', false);

        $this->from($resetUrl)->post(route('password.update'), [
            'token' => $token, 'email' => $customer->email,
            'password' => 'NewPassword123!', 'password_confirmation' => 'NewPassword123!',
        ])->assertRedirect(route('login'));
        $this->get(route('login'))->assertSee(__('messages.auth.reset_complete'));

        $this->post(route('login'), ['email' => $customer->email, 'password' => 'NewPassword123!'])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($customer);
    }
}
