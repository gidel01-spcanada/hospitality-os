<?php

namespace Tests\Feature;

use App\Models\Property;
use App\Models\Reservation;
use App\Models\ReservationGuest;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminReservationDetailTest extends TestCase
{
    use RefreshDatabase;

    public function test_detail_page_shows_cockpit_and_supports_notes_payment_validation_and_history(): void
    {
        Storage::fake('local');
        $this->seed(DatabaseSeeder::class);
        $property = Property::where('slug', 'appartement-401')->firstOrFail();
        $admin = User::where('email', 'admin@afrikappart.test')->firstOrFail();
        $guest = ReservationGuest::create(['full_name' => 'Cockpit Guest', 'email' => 'cockpit@example.com', 'phone' => '+22990000000']);
        $reservation = Reservation::create([
            'property_id' => $property->id, 'guest_id' => $guest->id, 'reservation_ref' => 'AFK-COCKPIT-1',
            'status' => 'pending_validation', 'check_in' => now()->addDays(4)->toDateString(), 'check_out' => now()->addDays(7)->toDateString(),
            'adults' => 2, 'currency' => 'XOF', 'email' => $guest->email, 'subtotal' => 90000, 'total_amount' => 90000, 'source' => 'website',
            'customer_note' => 'Arrivée tardive.',
        ]);
        $reservation->priceLines()->create(['label' => 'Nuit(s) x 3', 'amount' => 90000, 'currency' => 'XOF']);
        $attempt = $reservation->paymentAttempts()->create([
            'provider' => 'wise', 'provider_reference' => 'COCKPIT-REF', 'idempotency_key' => 'cockpit-1',
            'currency' => 'XOF', 'amount' => 90000, 'status' => 'awaiting_validation', 'payload' => [],
        ]);
        app(\App\Services\PaymentProofService::class)->attach($reservation, $attempt, UploadedFile::fake()->create('cockpit.pdf', 10, 'application/pdf'), 'guest');
        $showUrl = route('admin.reservations.show', $reservation);

        $this->actingAs($admin)->get($showUrl)->assertOk()
            ->assertSee('data-rsv-validation-alert', false)
            ->assertSee('data-rsv-validating', false)
            ->assertSee(route('admin.reservations.payment.validate', ['reservation' => $reservation, 'attempt' => $attempt]), false)
            ->assertSee(route('reservations.payment-proof.download', ['reservation' => $reservation, 'attempt' => $attempt]), false)
            ->assertSee('COCKPIT-REF')
            ->assertSee('Arrivée tardive.')
            ->assertSee(__('messages.admin.timeline_created'))
            ->assertSee(__('messages.admin.timeline_proof_uploaded'));

        $this->actingAs($admin)->post(route('admin.reservations.internal-notes.store', $reservation), ['body' => 'Appel client : virement envoyé hier.'])
            ->assertRedirect($showUrl . '#reservation-notes');
        $this->assertDatabaseHas('reservation_notes', ['reservation_id' => $reservation->id, 'user_id' => $admin->id, 'body' => 'Appel client : virement envoyé hier.']);

        $this->actingAs($admin)->post(route('admin.reservations.payment.validate', ['reservation' => $reservation, 'attempt' => $attempt]))
            ->assertRedirect($showUrl);
        $this->assertSame('paid', $attempt->fresh()->status);
        $this->assertSame($admin->id, data_get($attempt->fresh()->payload, 'confirmed_by'));
        $this->assertSame('confirmed', $reservation->fresh()->status);
        $this->assertDatabaseHas('receipts', ['reservation_id' => $reservation->id, 'payment_attempt_id' => $attempt->id]);

        $this->actingAs($admin)->get($showUrl)->assertOk()
            ->assertDontSee('data-rsv-validation-alert', false)
            ->assertSee('data-rsv-status-history', false)
            ->assertSee(__('messages.admin.status_pending_validation') . ' → ' . __('messages.admin.status_confirmed'))
            ->assertSee(__('messages.admin.rsv_validated_by', ['name' => $admin->name]))
            ->assertSee('Appel client : virement envoyé hier.')
            ->assertSee(__('messages.transactional.status_paid'));

        $host = User::factory()->create(['role' => 'host', 'tenant_id' => $property->establishment->tenant_id]);
        $this->actingAs($host)->post(route('admin.reservations.internal-notes.store', $reservation), ['body' => 'Not allowed'])->assertForbidden();
        $customer = User::factory()->create(['role' => 'customer']);
        $this->actingAs($customer)->get($showUrl)->assertForbidden();
    }
}
