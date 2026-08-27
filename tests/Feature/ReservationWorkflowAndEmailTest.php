<?php

namespace Tests\Feature;

use App\Models\Property;
use App\Models\Reservation;
use App\Models\ReservationGuest;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReservationWorkflowAndEmailTest extends TestCase
{
    use RefreshDatabase;

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
            ->patch('/admin/reservations/' . $reservation->id . '/status', [
                'status' => 'confirmed',
                'notes' => 'Client confirmed and deposit requested.',
            ])
            ->assertRedirect('/admin/reservations/' . $reservation->id);

        $this->assertDatabaseHas('reservations', ['id' => $reservation->id, 'status' => 'confirmed']);
        $this->assertDatabaseHas('email_outbox', ['recipient_email' => 'alice@example.com', 'template' => 'reservation_status_updated', 'status' => 'queued']);
    }
}
