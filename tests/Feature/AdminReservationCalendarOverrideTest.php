<?php

namespace Tests\Feature;

use App\Models\ExternalCalendarEvent;
use App\Models\ExternalCalendarFeed;
use App\Models\EmailOutbox;
use App\Models\Property;
use App\Models\Reservation;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminReservationCalendarOverrideTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_override_only_an_external_calendar_conflict(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::query()->where('email', 'admin@afrikappart.test')->firstOrFail();
        $property = Property::query()->firstOrFail();
        $this->createExternalConflict($property, '2032-10-10', '2032-10-15');
        $payload = $this->reservationPayload($property, '2032-10-11', '2032-10-14');

        $this->actingAs($admin)
            ->post(route('admin.reservations.store'), $payload)
            ->assertRedirect()
            ->assertSessionHasErrors('check_in');

        $this->actingAs($admin)
            ->post(route('admin.reservations.store'), $payload + ['ignore_external_calendar_conflicts' => '1'])
            ->assertRedirect();

        $reservation = Reservation::query()->where('email', $payload['email'])->firstOrFail();
        $this->assertStringContainsString('[Dérogation calendrier externe]', $reservation->notes);
        $this->assertStringContainsString($admin->name, $reservation->notes);
        $this->assertDatabaseHas('email_outbox', [
            'recipient_email' => $payload['email'],
            'template' => 'reservation_created',
            'status' => 'queued',
        ]);

        $this->artisan('messages:send-email-notifications')->assertExitCode(0);
        $this->assertDatabaseHas('email_outbox', [
            'recipient_email' => $payload['email'],
            'template' => 'reservation_created',
            'status' => 'sent',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.reservations.store'), array_merge($payload, [
                'email' => 'second-override@example.com',
                'ignore_external_calendar_conflicts' => '1',
            ]))
            ->assertRedirect()
            ->assertSessionHasErrors('check_in');
    }

    public function test_assigned_host_can_override_but_concierge_cannot(): void
    {
        $this->seed(DatabaseSeeder::class);
        $property = Property::query()->firstOrFail();
        $tenantId = $property->establishment->tenant_id;
        $this->createExternalConflict($property, '2032-12-10', '2032-12-15');
        $payload = $this->reservationPayload($property, '2032-12-11', '2032-12-14') + [
            'ignore_external_calendar_conflicts' => '1',
        ];
        $host = User::factory()->create(['tenant_id' => $tenantId, 'role' => 'host', 'is_admin' => false]);
        $host->establishments()->sync([$property->establishment_id]);
        $concierge = User::factory()->create(['tenant_id' => $tenantId, 'role' => 'concierge', 'is_admin' => false]);

        $this->actingAs($host)
            ->get(route('admin.reservations.create'))
            ->assertOk()
            ->assertSee(__('messages.admin.ignore_external_calendar_conflicts'));

        $this->actingAs($host)
            ->post(route('admin.reservations.store'), $payload)
            ->assertRedirect();

        $this->actingAs($concierge)
            ->get(route('admin.reservations.create'))
            ->assertOk()
            ->assertDontSee(__('messages.admin.ignore_external_calendar_conflicts'));

        $this->actingAs($concierge)
            ->post(route('admin.reservations.store'), array_merge($payload, ['email' => 'concierge-override@example.com']))
            ->assertForbidden();
    }

    private function createExternalConflict(Property $property, string $start, string $end): void
    {
        $feed = ExternalCalendarFeed::query()->create([
            'property_id' => $property->id,
            'name' => 'External booking calendar',
            'url' => 'https://example.com/external.ics',
            'is_enabled' => true,
        ]);
        ExternalCalendarEvent::query()->create([
            'feed_id' => $feed->id,
            'uid' => 'override-'.$start,
            'summary' => 'External booking',
            'start_date' => $start,
            'end_date' => $end,
        ]);
    }

    private function reservationPayload(Property $property, string $checkIn, string $checkOut): array
    {
        return [
            'property_id' => $property->id,
            'full_name' => 'Calendar Override Guest',
            'email' => 'calendar-override@example.com',
            'phone' => '+22900000000',
            'country' => 'BJ',
            'check_in' => $checkIn,
            'check_out' => $checkOut,
            'adults' => 1,
            'children' => 0,
            'infants' => 0,
            'notes' => 'Created by staff for the customer.',
        ];
    }
}
