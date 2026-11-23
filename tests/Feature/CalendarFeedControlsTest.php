<?php

namespace Tests\Feature;

use App\Models\ExternalCalendarFeed;
use App\Models\ExternalCalendarEvent;
use App\Models\Property;
use App\Models\Reservation;
use App\Models\User;
use App\Services\CalendarFeedSyncService;
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

        $this->actingAs($admin)->get('/admin/properties/' . $property->id . '/edit')
            ->assertOk()
            ->assertSee(route('admin.properties.calendar.create', $property), false)
            ->assertSee('data-copy-text="' . route('calendar.public-export', [$property, 'token' => $property->calendar_export_token]) . '"', false);

        $this->actingAs($admin)->get(route('admin.properties.calendar.create', $property))
            ->assertOk()
            ->assertSee('name="provider"', false);

        Http::fake([
            'https://example.com/blocked.ics' => Http::sequence()
                ->push("BEGIN:VCALENDAR\r\nBEGIN:VEVENT\r\nUID:test-event-1\r\nDTSTART:20260910\r\nDTEND:20260912\r\nSUMMARY:Maintenance\r\nEND:VEVENT\r\nBEGIN:VEVENT\r\nUID:cancelled-event\r\nDTSTART:20260913\r\nDTEND:20260914\r\nSTATUS:CANCELLED\r\nEND:VEVENT\r\nEND:VCALENDAR")
                ->push("BEGIN:VCALENDAR\r\nBEGIN:VEVENT\r\nUID:test-event-1\r\nDTSTART:20260910\r\nDTEND:20260912\r\nSUMMARY:Maintenance\r\nEND:VEVENT\r\nBEGIN:VEVENT\r\nUID:cancelled-event\r\nDTSTART:20260913\r\nDTEND:20260914\r\nSTATUS:CANCELLED\r\nEND:VEVENT\r\nEND:VCALENDAR")
                ->push("BEGIN:VCALENDAR\r\nBEGIN:VEVENT\r\nUID:test-event-2\r\nDTSTART;VALUE=DATE:20260915\r\nDTEND;VALUE=DATE:20260916\r\nSUMMARY:Long;\r\n description\, escaped\r\nEND:VEVENT\r\nEND:VCALENDAR")
                ->push("BEGIN:VCALENDAR\r\nEND:VCALENDAR"),
        ]);

        $create = $this->actingAs($admin)->post('/admin/properties/' . $property->id . '/calendar/feeds', [
            'name' => 'Airbnb feed',
            'provider' => 'airbnb',
            'url' => 'https://example.com/blocked.ics',
            'is_enabled' => true,
        ]);

        $create->assertRedirect('/admin/properties/' . $property->id . '/edit#calendars');

        $feed = ExternalCalendarFeed::firstOrFail();
        $this->assertDatabaseHas('external_calendar_events', ['feed_id' => $feed->id, 'uid' => 'test-event-1']);
        $this->assertNotNull($feed->fresh()->last_successful_sync_at);

        $this->actingAs($admin)
            ->put('/admin/properties/' . $property->id . '/calendar/feeds/' . $feed->id, [
                'name' => 'Updated Airbnb feed',
                'provider' => 'airbnb',
                'url' => 'https://example.com/blocked.ics',
                'is_enabled' => true,
            ])
            ->assertRedirect('/admin/properties/' . $property->id . '/edit#calendars');

        $this->assertDatabaseHas('external_calendar_feeds', ['id' => $feed->id, 'name' => 'Updated Airbnb feed']);

        $this->actingAs($admin)
            ->post('/admin/properties/' . $property->id . '/calendar/feeds/' . $feed->id . '/sync')
            ->assertRedirect('/admin/properties/' . $property->id . '/edit#calendars');

        $this->assertDatabaseHas('external_calendar_events', ['feed_id' => $feed->id, 'uid' => 'test-event-1']);
        $this->assertDatabaseMissing('external_calendar_events', ['feed_id' => $feed->id, 'uid' => 'cancelled-event']);
        $this->assertNotNull($feed->fresh()->last_successful_sync_at);

        $this->actingAs($admin)
            ->post('/admin/properties/' . $property->id . '/calendar/feeds/' . $feed->id . '/sync')
            ->assertRedirect();
        $this->assertDatabaseMissing('external_calendar_events', ['feed_id' => $feed->id, 'uid' => 'test-event-1']);
        $this->assertDatabaseHas('external_calendar_events', ['feed_id' => $feed->id, 'uid' => 'test-event-2']);
        $this->assertSame('2026-09-15', ExternalCalendarEvent::query()->where('feed_id', $feed->id)->where('uid', 'test-event-2')->value('start_date')->format('Y-m-d'));

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
            ->assertSee('data-external="[[&quot;2026-09-15&quot;,&quot;2026-09-16&quot;]]', false);

        $export = $this->actingAs($admin)->get('/admin/properties/' . $property->id . '/calendar/export.ics');

        $export->assertOk();
        $export->assertHeader('Content-Type', 'text/calendar; charset=UTF-8');
        $export->assertSee('BEGIN:VCALENDAR');
        $export->assertSee('Unavailable');
        $export->assertDontSee('AFK-CALENDAR-CONFIRMED');
        $export->assertDontSee('Maintenance');
        $export->assertDontSee('confirmed@example.com');

        $this->actingAs($admin)
            ->post('/admin/properties/' . $property->id . '/calendar/feeds/' . $feed->id . '/sync')
            ->assertRedirect();
        $this->assertDatabaseHas('external_calendar_events', ['feed_id' => $feed->id, 'uid' => 'test-event-2']);
        $this->assertSame('stale', $feed->fresh()->status);
        $export->assertDontSee('AFK-CALENDAR-PENDING');

        $this->actingAs($admin)
            ->delete('/admin/properties/' . $property->id . '/calendar/feeds/' . $feed->id)
            ->assertRedirect('/admin/properties/' . $property->id . '/edit#calendars');

        $this->assertDatabaseMissing('external_calendar_feeds', ['id' => $feed->id]);
        $this->assertDatabaseMissing('external_calendar_events', ['feed_id' => $feed->id]);
    }

    public function test_adding_a_calendar_feed_with_an_unreachable_url_flags_the_sync_failure(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::where('email', 'admin@afrikappart.test')->firstOrFail();
        $property = Property::firstOrFail();

        Http::fake(['https://example.com/broken.ics' => Http::response('', 500)]);

        $this->actingAs($admin)->post('/admin/properties/' . $property->id . '/calendar/feeds', [
            'name' => 'Broken feed',
            'provider' => 'other',
            'url' => 'https://example.com/broken.ics',
            'is_enabled' => true,
        ])->assertRedirect('/admin/properties/' . $property->id . '/edit#calendars')
            ->assertSessionHas('error');

        $feed = ExternalCalendarFeed::firstOrFail();
        $this->assertNotNull($feed->last_sync_error);
        $this->assertNull($feed->last_successful_sync_at);
    }

    public function test_calendar_feed_credentials_are_encrypted_and_sync_failures_keep_existing_events(): void
    {
        $this->seed(DatabaseSeeder::class);
        $property = Property::firstOrFail();
        $feedUrl = 'https://feed-user:feed-password@example.com/calendar.ics?api_token=query-token';
        $feed = $property->calendarFeeds()->create([
            'name' => 'Credentialed feed',
            'provider' => 'other',
            'url' => $feedUrl,
            'is_enabled' => false,
            'status' => 'stale',
        ]);

        $this->assertSame($feedUrl, $feed->url);
        $this->assertSame('', $feed->getRawOriginal('url'));
        $this->assertNotSame($feedUrl, $feed->getRawOriginal('encrypted_url'));
        $this->assertStringNotContainsString('feed-password', json_encode($feed->getAttributes()));

        $event = $feed->events()->create([
            'uid' => 'keep-existing-event',
            'summary' => 'Blocked dates',
            'start_date' => '2026-09-10',
            'end_date' => '2026-09-12',
        ]);

        $this->actingAs(User::where('email', 'admin@afrikappart.test')->firstOrFail())
            ->post(route('admin.properties.calendar.store', $property), [
                'name' => 'Loopback feed',
                'provider' => 'other',
                'url' => 'http://127.0.0.1/private.ics',
                'is_enabled' => true,
            ])
            ->assertSessionHasErrors('url');

        $feed->update(['url' => 'https://example.com/feed.ics']);
        Http::fake(['https://example.com/feed.ics' => Http::response('', 302, ['Location' => 'http://127.0.0.1/private.ics'])]);
        $this->assertFalse(app(CalendarFeedSyncService::class)->sync($feed->fresh()));
        $this->assertDatabaseHas('external_calendar_events', ['id' => $event->id, 'feed_id' => $feed->id]);
        $this->assertFalse($feed->fresh()->is_enabled);
        $this->assertSame('stale', $feed->fresh()->status);

        Http::fake(['https://example.com/feed.ics' => Http::response(str_repeat('x', 2 * 1024 * 1024 + 1))]);
        $this->assertFalse(app(CalendarFeedSyncService::class)->sync($feed->fresh()));
        $this->assertDatabaseHas('external_calendar_events', ['id' => $event->id, 'feed_id' => $feed->id]);
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
