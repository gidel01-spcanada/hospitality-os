<?php

namespace Tests\Feature;

use App\Models\Property;
use App\Models\Reservation;
use App\Models\ReservationGuest;
use App\Models\User;
use App\Models\PaymentAttempt;
use App\Models\Receipt;
use App\Jobs\CompletePastReservations;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReservationWorkflowAndEmailTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_set_guest_preferred_locale_and_email_is_queued_in_that_language(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::where('email', 'admin@afrikappart.test')->firstOrFail();
        $property = Property::where('slug', 'appartement-401')->firstOrFail();

        // Admin's own session locale is French; the guest's chosen locale should still win.
        $this->actingAs($admin)->post(route('admin.reservations.store'), [
            'property_id' => $property->id,
            'full_name' => 'English Guest',
            'email' => 'english-guest@example.com',
            'phone' => '+22900000000',
            'country' => 'CA',
            'locale' => 'en',
            'check_in' => now()->addDays(5)->toDateString(),
            'check_out' => now()->addDays(7)->toDateString(),
            'adults' => 1,
            'children' => 0,
            'infants' => 0,
        ])->assertRedirect();

        $reservation = Reservation::query()->where('email', 'english-guest@example.com')->firstOrFail();
        $this->assertSame('en', $reservation->locale);

        $notification = \App\Models\EmailOutbox::query()->where('template', 'reservation_created')->latest()->firstOrFail();
        $this->assertSame('en', $notification->payload['locale']);
        $this->assertSame('Your reservation confirmation: ' . $reservation->reservation_ref, $notification->payload['subject']);
        $this->assertStringContainsString('lang=en', $notification->payload['checkout_url']);
    }

    public function test_only_admin_can_delete_reservation_and_associated_payment_records(): void
    {
        $this->seed(DatabaseSeeder::class);
        $property = Property::where('slug', 'appartement-401')->firstOrFail();
        $guest = ReservationGuest::query()->create(['full_name' => 'Delete Me', 'email' => 'delete-me@example.com']);
        $reservation = Reservation::query()->create([
            'property_id' => $property->id, 'guest_id' => $guest->id, 'reservation_ref' => 'AFK-DELETE-001',
            'status' => 'pending', 'check_in' => now()->addDay()->toDateString(), 'check_out' => now()->addDays(2)->toDateString(),
            'adults' => 1, 'children' => 0, 'infants' => 0, 'currency' => 'XOF', 'email' => $guest->email,
            'subtotal' => 1000, 'fees' => 0, 'taxes' => 0, 'total_amount' => 1000, 'source' => 'website',
        ]);
        $attempt = PaymentAttempt::query()->create([
            'reservation_id' => $reservation->id, 'provider' => 'offline', 'provider_reference' => 'delete-ref',
            'currency' => 'XOF', 'amount' => 1000, 'status' => 'created', 'idempotency_key' => 'delete-key', 'payload' => [],
        ]);
        $receipt = Receipt::query()->create([
            'reservation_id' => $reservation->id, 'payment_attempt_id' => $attempt->id, 'receipt_number' => 'RC-DELETE-001',
            'amount' => 1000, 'currency' => 'XOF', 'issued_at' => now(),
        ]);
        $admin = User::where('email', 'admin@afrikappart.test')->firstOrFail();
        $customer = User::factory()->create(['role' => 'customer']);

        $this->actingAs($customer)->delete(route('admin.reservations.destroy', $reservation))->assertForbidden();
        $this->actingAs($admin)->delete(route('admin.reservations.destroy', $reservation))->assertRedirect(route('admin.reservations.index'));

        $this->assertDatabaseMissing('reservations', ['id' => $reservation->id]);
        $this->assertDatabaseMissing('payment_attempts', ['id' => $attempt->id]);
        $this->assertDatabaseMissing('receipts', ['id' => $receipt->id]);
        $this->assertDatabaseMissing('reservation_guests', ['id' => $guest->id]);
    }

    public function test_admin_can_review_reservations_and_queue_status_updates(): void
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
            'reservation_ref' => 'AFK-TEST-001',
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
            'notes' => 'Awaiting review.',
        ]);

        $admin = User::where('email', 'admin@afrikappart.test')->firstOrFail();

        $this->actingAs($admin)
            ->get('/admin/reservations')
            ->assertOk();

        $this->actingAs($admin)
            ->get('/admin/bookings')
            ->assertOk()
            ->assertSee('Alice Doe')
            ->assertSee('138,000.00 XOF')
            ->assertSee('En attente');

        $this->actingAs($admin)
            ->get('/admin/reservations/' . $reservation->id)
            ->assertOk();

        $this->actingAs($admin)
            ->put('/admin/reservations/' . $reservation->id, [
                'property_id' => $property->id,
                'full_name' => 'Alice Updated',
                'email' => 'alice@example.com',
                'phone' => '+229 99 99 99 99',
                'country' => 'Bénin',
                'check_in' => now()->addDays(10)->toDateString(),
                'check_out' => now()->addDays(14)->toDateString(),
                'adults' => 2,
                'children' => 0,
                'infants' => 0,
                'notes' => 'Dates updated.',
            ])
            ->assertRedirect('/admin/reservations/' . $reservation->id);

        $this->assertDatabaseHas('reservations', [
            'id' => $reservation->id,
            'subtotal' => 100000,
            'fees' => 10000,
            'taxes' => 5000,
            'total_amount' => 115000,
        ]);
        $this->assertDatabaseHas('reservation_price_lines', ['reservation_id' => $reservation->id, 'label' => 'Nuit(s) x 4', 'amount' => 100000]);

        $this->actingAs($admin)
            ->put('/admin/reservations/' . $reservation->id, [
                'property_id' => $property->id,
                'full_name' => 'Alice Updated',
                'email' => 'alice@example.com',
                'check_in' => now()->addDays(10)->toDateString(),
                'check_out' => now()->addDays(15)->toDateString(),
                'adults' => 2,
            ])
            ->assertRedirect('/admin/reservations/' . $reservation->id);

        $this->assertDatabaseHas('reservations', ['id' => $reservation->id, 'total_amount' => 143750]);

        $this->actingAs($admin)
            ->post('/admin/reservations/' . $reservation->id . '/payment-link')
            ->assertRedirect('/admin/reservations/' . $reservation->id);

        $this->assertDatabaseHas('email_outbox', [
            'recipient_email' => 'alice@example.com',
            'template' => 'payment_link',
            'status' => 'queued',
        ]);
        $paymentLink = \App\Models\EmailOutbox::query()->where('template', 'payment_link')->latest()->firstOrFail();
        $this->assertSame(route('checkout.show', ['reservation' => $reservation, 'token' => $reservation->checkout_token, 'lang' => 'fr']), $paymentLink->payload['checkout_url']);

        $this->actingAs($admin)
            ->patch('/admin/reservations/' . $reservation->id . '/status', [
                'status' => 'confirmed',
                'notes' => 'Client confirmed and deposit requested.',
            ])
            ->assertRedirect('/admin/reservations/' . $reservation->id);

        $this->assertDatabaseHas('reservations', ['id' => $reservation->id, 'status' => 'confirmed']);
        $this->assertDatabaseHas('email_outbox', ['recipient_email' => 'alice@example.com', 'template' => 'reservation_status_updated', 'status' => 'queued']);
        $this->actingAs($admin)
            ->post('/admin/reservations/' . $reservation->id . '/payment-link')
            ->assertForbidden();

        $reservation->update(['status' => 'pending']);
        $reservation->paymentAttempts()->create([
            'provider' => 'pay_later',
            'provider_reference' => 'verified-payment',
            'currency' => 'XOF',
            'amount' => 143750,
            'status' => 'verified',
            'idempotency_key' => 'verified-payment-link-test',
        ]);

        $this->actingAs($admin)
            ->post('/admin/reservations/' . $reservation->id . '/payment-link')
            ->assertForbidden();
    }

    public function test_confirmed_payment_automatically_creates_and_emails_receipt(): void
    {
        $this->seed(DatabaseSeeder::class);
        $property = Property::where('slug', 'appartement-401')->firstOrFail();
        $guest = ReservationGuest::create(['full_name' => 'Receipt Guest', 'email' => 'receipt@example.com']);
        $reservation = Reservation::create([
            'property_id' => $property->id,
            'guest_id' => $guest->id,
            'reservation_ref' => 'AFK-RECEIPT-001',
            'status' => 'pending_payment',
            'check_in' => now()->addDays(5)->toDateString(),
            'check_out' => now()->addDays(8)->toDateString(),
            'adults' => 1,
            'children' => 0,
            'infants' => 0,
            'currency' => 'XOF',
            'email' => $guest->email,
            'subtotal' => 75000,
            'fees' => 7500,
            'taxes' => 3750,
            'total_amount' => 86250,
            'source' => 'website',
        ]);
        $attempt = $reservation->paymentAttempts()->create([
            'provider' => 'pay_later',
            'provider_reference' => 'receipt-payment-001',
            'currency' => 'XOF',
            'amount' => 86250,
            'status' => 'paid',
            'idempotency_key' => 'receipt-payment-001',
        ]);

        app(\App\Services\ReservationEmailService::class)->issueReceiptAndQueueEmail($reservation, $attempt);
        app(\App\Services\ReservationEmailService::class)->issueReceiptAndQueueEmail($reservation, $attempt);

        $this->assertDatabaseHas('receipts', ['reservation_id' => $reservation->id, 'payment_attempt_id' => $attempt->id, 'amount' => '86250.00']);
        $this->assertSame(1, $reservation->receipts()->count());
        $this->assertSame(1, \App\Models\EmailOutbox::query()->where('template', 'receipt_issued')->where('recipient_email', $guest->email)->count());
    }

    public function test_past_confirmed_reservations_are_completed_by_job_and_email_status_update(): void
    {
        $this->seed(DatabaseSeeder::class);
        $property = Property::where('slug', 'appartement-401')->firstOrFail();
        $guest = ReservationGuest::create(['full_name' => 'Past Stay Guest', 'email' => 'past-stay@example.com']);
        $reservation = Reservation::create([
            'property_id' => $property->id,
            'guest_id' => $guest->id,
            'reservation_ref' => 'AFK-COMPLETE-001',
            'status' => 'confirmed',
            'check_in' => now()->subDays(4)->toDateString(),
            'check_out' => now()->subDays(2)->toDateString(),
            'adults' => 1,
            'children' => 0,
            'infants' => 0,
            'currency' => 'XOF',
            'email' => $guest->email,
            'subtotal' => 50000,
            'fees' => 5000,
            'taxes' => 2500,
            'total_amount' => 57500,
            'source' => 'website',
        ]);

        dispatch_sync(new CompletePastReservations());

        $this->assertDatabaseHas('reservations', ['id' => $reservation->id, 'status' => 'completed']);
        $this->assertDatabaseHas('email_outbox', ['recipient_email' => $guest->email, 'template' => 'reservation_status_updated', 'status' => 'queued']);
    }
}
