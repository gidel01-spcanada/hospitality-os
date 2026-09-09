<?php

namespace Tests\Feature;

use App\Models\ExternalCalendarFeed;
use App\Models\Property;
use App\Models\Reservation;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CalendarFeedControlsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_sync_external_calendar_feed_and_export_internal_calendar(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = User::where('email', 'admin@afrikappart.test')->firstOrFail();
        $property = Property::firstOrFail();

        Http::fake([
            'https://example.com/blocked.ics' => Http::response("BEGIN:VCALENDAR\r\nBEGIN:VEVENT\r\nUID:test-event-1\r\nDTSTART:20260910\r\nDTEND:20260912\r\nSUMMARY:Maintenance\r\nEND:VEVENT\r\nEND:VCALENDAR"),
        ]);

        $create = $this->actingAs($admin)->post('/admin/properties/' . $property->id . '/calendar/feeds', [
            'name' => 'Airbnb feed',
            'url' => 'https://example.com/blocked.ics',
            'is_enabled' => true,
        ]);

        $create->assertRedirect('/admin/properties/' . $property->id . '/edit#calendars');

        $feed = ExternalCalendarFeed::firstOrFail();

        $this->actingAs($admin)
            ->put('/admin/properties/' . $property->id . '/calendar/feeds/' . $feed->id, [
                'name' => 'Updated Airbnb feed',
                'url' => 'https://example.com/blocked.ics',
                'is_enabled' => true,
            ])
            ->assertRedirect('/admin/properties/' . $property->id . '/edit#calendars');

        $this->assertDatabaseHas('external_calendar_feeds', ['id' => $feed->id, 'name' => 'Updated Airbnb feed']);

        $this->actingAs($admin)
            ->post('/admin/properties/' . $property->id . '/calendar/feeds/' . $feed->id . '/sync')
            ->assertRedirect('/admin/properties/' . $property->id . '/edit#calendars');

        $this->assertDatabaseHas('external_calendar_events', ['feed_id' => $feed->id, 'uid' => 'test-event-1']);
        $this->assertNotNull($feed->fresh()->last_successful_sync_at);

        Reservation::query()->create([
            'property_id' => $property->id,
            'reservation_ref' => 'AFK-CALENDAR-CONFIRMED',
            'status' => 'confirmed',
            'check_in' => '2026-09-20',
            'check_out' => '2026-09-22',
            'email' => 'confirmed@example.com',
        ]);

        Reservation::query()->create([
            'property_id' => $property->id,
            'reservation_ref' => 'AFK-CALENDAR-PENDING',
            'status' => 'pending',
            'check_in' => '2026-09-23',
            'check_out' => '2026-09-25',
            'email' => 'pending@example.com',
        ]);

        $this->actingAs($admin)
            ->get('/admin/properties/' . $property->id . '/edit')
            ->assertOk()
            ->assertSee('data-external="[[&quot;2026-09-10&quot;,&quot;2026-09-12&quot;]]', false);

        $export = $this->actingAs($admin)->get('/admin/properties/' . $property->id . '/calendar/export.ics');

        $export->assertOk();
        $export->assertHeader('Content-Type', 'text/calendar; charset=UTF-8');
        $export->assertSee('BEGIN:VCALENDAR');
        $export->assertSee('AFK-CALENDAR-CONFIRMED');
        $export->assertDontSee('AFK-CALENDAR-PENDING');

        $this->actingAs($admin)
            ->delete('/admin/properties/' . $property->id . '/calendar/feeds/' . $feed->id)
            ->assertRedirect('/admin/properties/' . $property->id . '/edit#calendars');

        $this->assertDatabaseMissing('external_calendar_feeds', ['id' => $feed->id]);
        $this->assertDatabaseMissing('external_calendar_events', ['feed_id' => $feed->id]);
    }

    public function test_public_calendar_url_returns_ics_only_with_valid_token(): void
    {
        $this->seed(DatabaseSeeder::class);
        $property = Property::firstOrFail();

        $this->get('/calendar/' . $property->slug . '/' . $property->calendar_export_token . '.ics')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/calendar; charset=UTF-8');

        $this->get('/calendar/' . $property->slug . '/invalid-token.ics')->assertNotFound();
    }

    public function test_customer_cannot_access_calendar_admin_routes(): void
    {
        $this->seed(DatabaseSeeder::class);

        $customer = User::where('email', 'guest@afrikappart.test')->firstOrFail();
        $property = Property::firstOrFail();

        $this->actingAs($customer)
            ->get('/admin/properties/' . $property->id . '/calendar')
            ->assertForbidden();
    }
}
