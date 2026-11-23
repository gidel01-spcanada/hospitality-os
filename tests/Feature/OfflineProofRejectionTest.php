<?php

namespace Tests\Feature;

use App\Models\EmailOutbox;
use App\Models\Property;
use App\Models\Reservation;
use App\Models\ReservationGuest;
use App\Models\User;
use App\Services\AvailabilityService;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OfflineProofRejectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_rejection_notifies_guest_preserves_hold_and_allows_auditable_replacement(): void
    {
        Storage::fake('local');
        $this->seed(DatabaseSeeder::class);
        $property = Property::where('slug', 'appartement-401')->with('establishment')->firstOrFail();
        $property->establishment->update(['secondary_currency' => 'EUR', 'secondary_currency_rate' => 650, 'payment_methods' => ['wise' => ['enabled' => true, 'instructions' => '', 'email' => 'wise@example.com']]]);
        $guest = ReservationGuest::create(['full_name' => 'Proof Guest', 'email' => 'guest@example.com']);
        $reservation = Reservation::create([
            'property_id' => $property->id, 'guest_id' => $guest->id, 'reservation_ref' => 'AFK-REJECT',
            'status' => 'pending_payment', 'locale' => 'en', 'email' => $guest->email,
            'check_in' => now()->addDays(10)->toDateString(), 'check_out' => now()->addDays(12)->toDateString(),
            'adults' => 1, 'children' => 0, 'infants' => 0, 'currency' => 'XOF',
            'subtotal' => 1000, 'fees' => 0, 'taxes' => 0, 'total_amount' => 1000, 'source' => 'website',
        ]);
        $createdAt = $reservation->created_at->copy();
        $checkoutUrl = route('checkout.show', ['reservation' => $reservation, 'token' => $reservation->checkout_token]);
        $submitUrl = route('checkout.offline-proof', ['reservation' => $reservation, 'token' => $reservation->checkout_token]);
        $this->post($submitUrl, ['provider' => 'wise', 'payment_proof' => UploadedFile::fake()->create('original.pdf', 10, 'application/pdf')])->assertRedirect($checkoutUrl);
        $attempt = $reservation->paymentAttempts()->firstOrFail();
        $originalPath = data_get($attempt->payload, 'payment_proof.path');
        $rejectUrl = route('admin.reservations.payment-proof.reject', ['reservation' => $reservation, 'attempt' => $attempt]);
        $admin = User::where('email', 'admin@afrikappart.test')->firstOrFail();
        $this->travel(47)->hours();

        $this->actingAs($admin)->get(route('admin.reservations.show', $reservation))
            ->assertOk()->assertSee($rejectUrl, false)->assertSee('name="reason"', false);
        $this->post($rejectUrl, ['reason' => '   '])->assertSessionHasErrors('reason');
        $this->patch(route('admin.reservations.update-status', $reservation), ['status' => 'pending_payment'])->assertSessionHasErrors('status');
        $this->assertSame('awaiting_validation', $attempt->fresh()->status);
        $this->assertDatabaseMissing('email_outbox', ['template' => 'payment_proof_rejected']);

        $reason = 'Reference is missing <please correct>';
        $this->post($rejectUrl, ['reason' => '  ' . $reason . '  '])->assertRedirect(route('admin.reservations.show', $reservation));
        $this->assertSame('rejected', $attempt->fresh()->status);
        $this->assertSame($reason, data_get($attempt->fresh()->payload, 'proof_rejection.reason'));
        $this->assertSame($admin->id, data_get($attempt->fresh()->payload, 'proof_rejection.rejected_by'));
        $this->assertNotNull(data_get($attempt->fresh()->payload, 'proof_rejection.rejected_at'));
        $this->assertSame('pending_payment', $reservation->fresh()->status);
        $this->assertTrue($createdAt->equalTo($reservation->fresh()->created_at));
        $this->assertFalse(app(AvailabilityService::class)->isAvailable($property, Carbon::parse($reservation->check_in), Carbon::parse($reservation->check_out)));
        $this->post($rejectUrl, ['reason' => 'Again'])->assertStatus(409);
        $this->assertSame(1, EmailOutbox::where('template', 'payment_proof_rejected')->count());
        $this->get(route('admin.reservations.show', $reservation))->assertOk()->assertSee($reason);

        $notification = EmailOutbox::where('template', 'payment_proof_rejected')->firstOrFail();
        $this->assertSame($guest->email, $notification->recipient_email);
        $this->assertSame('en', $notification->payload['locale']);
        $this->assertSame($reason, $notification->payload['reason']);
        $this->assertStringContainsString($reservation->checkout_token, $notification->payload['checkout_url']);
        EmailOutbox::where('template', 'payment_proof_submitted')->update(['status' => 'sent']);
        Mail::shouldReceive('send')->once()->andReturnUsing(function ($view, $payload) use ($reason): void {
            $this->assertSame('emails.payment-proof-rejected', $view);
            $html = view($view, $payload)->render();
            $this->assertStringContainsString(e($reason), $html);
            $this->assertStringContainsString('48-hour', $html);
        });
        $this->artisan('messages:send-email-notifications')->assertExitCode(0);
        $this->assertSame('sent', $notification->fresh()->status);

        $this->withSession(['checkout_identity_skipped' => [$reservation->id]])->get($checkoutUrl)
            ->assertOk()->assertSee(__('messages.checkout.i_have_paid'))->assertSee($reason);
        $this->post(route('checkout.complete', ['reservation' => $reservation, 'token' => $reservation->checkout_token]))->assertStatus(409);
        $this->post($submitUrl, ['provider' => 'wise', 'payment_proof' => UploadedFile::fake()->create('replacement.pdf', 10, 'application/pdf')])
            ->assertRedirect($checkoutUrl);
        $replacement = $reservation->paymentAttempts()->latest('id')->firstOrFail();
        $this->assertNotSame($attempt->id, $replacement->id);
        $this->assertSame('awaiting_validation', $replacement->status);
        $this->assertSame('pending_validation', $reservation->fresh()->status);
        $this->assertSame($originalPath, data_get($attempt->fresh()->payload, 'payment_proof.path'));
        Storage::disk('local')->assertExists($originalPath);
        Storage::disk('local')->assertExists(data_get($replacement->payload, 'payment_proof.path'));
        $this->assertSame($reason, data_get($attempt->fresh()->payload, 'proof_rejection.reason'));
        $this->assertTrue($createdAt->equalTo($reservation->fresh()->created_at));
        $replacementRejectUrl = route('admin.reservations.payment-proof.reject', ['reservation' => $reservation, 'attempt' => $replacement]);
        $this->post($replacementRejectUrl, ['reason' => 'Still unreadable'])->assertRedirect();
        $this->post($submitUrl, ['provider' => 'wise', 'payment_proof' => UploadedFile::fake()->create('third.pdf', 10, 'application/pdf')])->assertRedirect();
        $this->assertSame(3, $reservation->paymentAttempts()->count());
        $this->assertSame('rejected', $replacement->fresh()->status);
    }

    public function test_rejection_does_not_extend_expired_checkout_or_hold_and_requires_authorized_staff(): void
    {
        Storage::fake('local');
        $this->seed(DatabaseSeeder::class);
        $property = Property::where('slug', 'appartement-401')->with('establishment')->firstOrFail();
        $property->establishment->update(['secondary_currency' => 'EUR', 'secondary_currency_rate' => 650, 'payment_methods' => ['wise' => ['enabled' => true, 'instructions' => '', 'email' => 'wise@example.com']]]);
        $reservation = Reservation::create([
            'property_id' => $property->id, 'reservation_ref' => 'AFK-REJECT-EXPIRED',
            'status' => 'pending_payment', 'email' => 'expired@example.com',
            'check_in' => now()->addDays(10)->toDateString(), 'check_out' => now()->addDays(12)->toDateString(),
            'adults' => 1, 'children' => 0, 'infants' => 0, 'currency' => 'XOF',
            'subtotal' => 1000, 'fees' => 0, 'taxes' => 0, 'total_amount' => 1000, 'source' => 'website',
        ]);
        $submitUrl = route('checkout.offline-proof', ['reservation' => $reservation, 'token' => $reservation->checkout_token]);
        $this->post($submitUrl, ['provider' => 'wise', 'payment_proof' => UploadedFile::fake()->create('original.pdf', 10, 'application/pdf')])->assertRedirect();
        $attempt = $reservation->paymentAttempts()->firstOrFail();
        $rejectUrl = route('admin.reservations.payment-proof.reject', ['reservation' => $reservation, 'attempt' => $attempt]);
        $customer = User::factory()->create(['role' => 'customer']);
        $unassignedHost = User::factory()->create(['role' => 'host', 'tenant_id' => $property->establishment->tenant_id]);
        $this->actingAs($customer)->post($rejectUrl, ['reason' => 'No'])->assertForbidden();
        $this->actingAs($unassignedHost)->post($rejectUrl, ['reason' => 'No'])->assertForbidden();
        $otherTenant = \App\Models\Tenant::create(['name' => 'Other tenant', 'slug' => 'other-tenant', 'status' => 'active']);
        $otherAdmin = User::factory()->create(['role' => 'admin', 'tenant_id' => $otherTenant->id]);
        $this->actingAs($otherAdmin)->post($rejectUrl, ['reason' => 'No'])->assertNotFound();
        $this->assertSame('awaiting_validation', $attempt->fresh()->status);
        $admin = User::where('email', 'admin@afrikappart.test')->firstOrFail();
        $this->actingAs($admin);
        $attempt->update(['status' => 'paid']);
        $this->post($rejectUrl, ['reason' => 'No'])->assertStatus(409);
        $attempt->update(['status' => 'awaiting_validation', 'provider' => 'paypal']);
        $this->post($rejectUrl, ['reason' => 'No'])->assertStatus(409);
        $attempt->update(['provider' => 'wise', 'payload' => array_merge($attempt->payload, ['is_guarantee' => true])]);
        $this->post($rejectUrl, ['reason' => 'No'])->assertStatus(409);
        $payload = $attempt->payload;
        unset($payload['is_guarantee']);
        $attempt->update(['payload' => $payload]);
        $reservation->update(['status' => 'confirmed']);
        $this->post($rejectUrl, ['reason' => 'No'])->assertStatus(409);
        $reservation->update(['status' => 'pending_validation']);
        $this->assertDatabaseMissing('email_outbox', ['template' => 'payment_proof_rejected']);
        $this->travel(49)->hours();
        $this->post($rejectUrl, ['reason' => 'Unreadable'])->assertRedirect();
        $this->assertSame('pending_payment', $reservation->fresh()->status);
        $this->assertTrue(app(AvailabilityService::class)->isAvailable($property, Carbon::parse($reservation->check_in), Carbon::parse($reservation->check_out)));
        $this->post($submitUrl, ['provider' => 'wise', 'payment_proof' => UploadedFile::fake()->create('late.pdf', 10, 'application/pdf')])->assertStatus(410);
        $originalPath = data_get($attempt->fresh()->payload, 'payment_proof.path');
        $this->patch(route('admin.reservations.update-status', $reservation), [
            'status' => 'pending_payment', 'payment_proof' => UploadedFile::fake()->create('staff-status.pdf', 10, 'application/pdf'),
        ])->assertRedirect();
        $this->post(route('admin.reservations.payment-proof.upload', $reservation), [
            'payment_proof' => UploadedFile::fake()->create('staff-upload.pdf', 10, 'application/pdf'),
        ])->assertRedirect();
        $this->post(route('admin.reservations.confirm-offline-payment', $reservation), [
            'payment_proof' => UploadedFile::fake()->create('staff-confirm.pdf', 10, 'application/pdf'),
        ])->assertRedirect();
        $this->assertSame('rejected', $attempt->fresh()->status);
        $this->assertSame('Unreadable', data_get($attempt->fresh()->payload, 'proof_rejection.reason'));
        $this->assertSame($originalPath, data_get($attempt->fresh()->payload, 'payment_proof.path'));
        Storage::disk('local')->assertExists($originalPath);
    }
}
