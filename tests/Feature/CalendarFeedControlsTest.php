<?php

namespace Tests\Feature;

use App\Models\ExternalCalendarFeed;
use App\Models\ExternalCalendarEvent;
use App\Models\Property;
use App\Models\Reservation;
use App\Models\User;
use App\Services\AvailabilityService;
use App\Services\CalendarFeedSyncService;
use Carbon\Carbon;
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

    public function test_manual_sync_preserves_disabled_state_until_the_feed_is_explicitly_enabled(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::where('email', 'admin@afrikappart.test')->firstOrFail();
        $property = Property::firstOrFail();
        $feed = $property->calendarFeeds()->create([
            'name' => 'Disabled feed',
            'provider' => 'other',
            'url' => 'https://example.com/disabled.ics',
            'is_enabled' => false,
            'status' => 'stale',
        ]);
        Http::fake(['https://example.com/disabled.ics' => Http::response(
            "BEGIN:VCALENDAR\r\nBEGIN:VEVENT\r\nUID:disabled-event\r\nDTSTART:20291210\r\nDTEND:20291212\r\nEND:VEVENT\r\nEND:VCALENDAR"
        )]);

        $this->actingAs($admin)->post(route('admin.properties.calendar.sync', [$property, $feed]))
            ->assertRedirect(route('admin.properties.edit', $property) . '#calendars')
            ->assertSessionHas('success', __('messages.flash.calendar_synced'))
            ->assertSessionMissing('error');

        $this->assertFalse($feed->fresh()->is_enabled);
        $this->assertSame('fresh', $feed->fresh()->status);
        $this->assertDatabaseHas('external_calendar_events', ['feed_id' => $feed->id, 'uid' => 'disabled-event']);
        $this->actingAs($admin)->get(route('admin.properties.calendar.edit', [$property, $feed]))
            ->assertSee('name="is_enabled" value="1" ', false)
            ->assertDontSee('name="is_enabled" value="1" checked', false);
        $this->actingAs($admin)->get(route('admin.properties.edit', $property))
            ->assertSee('data-external="[]"', false);
        $availability = app(AvailabilityService::class);
        $checkIn = Carbon::parse('2029-12-10');
        $checkOut = Carbon::parse('2029-12-12');
        $this->assertFalse($availability->hasExternalCalendarConflict($property, $checkIn, $checkOut));

        $this->actingAs($admin)->put(route('admin.properties.calendar.update', [$property, $feed]), [
            'name' => 'Disabled feed',
            'provider' => 'other',
            'url' => 'https://example.com/disabled.ics',
            'is_enabled' => true,
        ])->assertSessionHas('success', __('messages.flash.calendar_updated'));
        $this->assertTrue($feed->fresh()->is_enabled);
        $this->assertTrue($availability->hasExternalCalendarConflict($property, $checkIn, $checkOut));
        $this->actingAs($admin)->post(route('admin.properties.calendar.sync', [$property, $feed]))
            ->assertSessionHas('success', __('messages.flash.calendar_synced'))
            ->assertSessionMissing('error');
        $this->assertTrue($feed->fresh()->is_enabled);
        $this->actingAs($admin)->get(route('admin.properties.edit', $property))
            ->assertSee('data-external="[[&quot;2029-12-10&quot;,&quot;2029-12-12&quot;]]', false);
    }

    public function test_manual_sync_failure_reports_error_and_retains_previous_events(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::where('email', 'admin@afrikappart.test')->firstOrFail();
        $property = Property::firstOrFail();
        $feed = $property->calendarFeeds()->create([
            'name' => 'Unavailable feed',
            'provider' => 'other',
            'url' => 'https://example.com/unavailable.ics',
            'is_enabled' => true,
            'status' => 'fresh',
            'last_successful_sync_at' => now()->subDay(),
        ]);
        $lastSuccess = $feed->last_successful_sync_at;
        $event = $feed->events()->create([
            'uid' => 'retained-event',
            'summary' => 'Existing event',
            'start_date' => '2029-12-10',
            'end_date' => '2029-12-12',
        ]);
        Http::fake(['https://example.com/unavailable.ics' => Http::response('', 500)]);

        $this->actingAs($admin)->post(route('admin.properties.calendar.sync', [$property, $feed]))
            ->assertRedirect(route('admin.properties.edit', $property) . '#calendars')
            ->assertSessionHas('error', __('messages.flash.calendar_sync_failed'))
            ->assertSessionMissing('success');

        $feed->refresh();
        $this->assertTrue($feed->is_enabled);
        $this->assertSame('stale', $feed->status);
        $this->assertTrue($lastSuccess->equalTo($feed->last_successful_sync_at));
        $this->assertNotNull($feed->last_sync_error);
        $this->assertDatabaseHas('external_calendar_events', ['id' => $event->id, 'feed_id' => $feed->id]);
        $this->actingAs($admin)->get(route('admin.properties.edit', $property))
            ->assertSee(__('messages.flash.calendar_sync_failed'));
    }

    public function test_uid_collision_rolls_back_event_changes_and_never_displays_sql_errors(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::where('email', 'admin@afrikappart.test')->firstOrFail();
        $property = Property::firstOrFail();
        $otherFeed = $property->calendarFeeds()->create([
            'name' => 'Other feed',
            'provider' => 'other',
            'url' => 'https://example.com/other.ics',
            'is_enabled' => true,
            'status' => 'fresh',
        ]);
        $otherFeed->events()->create([
            'uid' => 'shared-uid',
            'summary' => 'Other event',
            'start_date' => '2029-12-10',
            'end_date' => '2029-12-12',
        ]);
        $feed = $property->calendarFeeds()->create([
            'name' => 'Colliding feed',
            'provider' => 'other',
            'url' => 'https://example.com/collision.ics',
            'is_enabled' => true,
            'status' => 'fresh',
        ]);
        $oldEvent = $feed->events()->create([
            'uid' => 'old-event',
            'summary' => 'Old event',
            'start_date' => '2029-12-15',
            'end_date' => '2029-12-16',
        ]);
        Http::fake(['https://example.com/collision.ics' => Http::response(
            "BEGIN:VCALENDAR\r\nBEGIN:VEVENT\r\nUID:new-event\r\nDTSTART:20291217\r\nDTEND:20291218\r\nEND:VEVENT\r\nBEGIN:VEVENT\r\nUID:shared-uid\r\nDTSTART:20291219\r\nDTEND:20291220\r\nEND:VEVENT\r\nEND:VCALENDAR"
        )]);

        $this->actingAs($admin)->post(route('admin.properties.calendar.sync', [$property, $feed]))
            ->assertSessionHas('error', __('messages.flash.calendar_sync_failed'))
            ->assertSessionMissing('success');
        $this->assertSame('stale', $feed->fresh()->status);
        $this->assertSame('Calendar feed sync failed.', $feed->fresh()->last_sync_error);
        $this->assertDatabaseHas('external_calendar_events', ['id' => $oldEvent->id, 'feed_id' => $feed->id]);
        $this->assertDatabaseHas('external_calendar_events', ['feed_id' => $otherFeed->id, 'uid' => 'shared-uid']);
        $this->assertDatabaseMissing('external_calendar_events', ['feed_id' => $feed->id, 'uid' => 'new-event']);

        $feed->update(['last_sync_error' => 'SQLSTATE[23000]: secret SQL INSERT INTO external_calendar_events']);
        foreach ([route('admin.properties.edit', $property), route('admin.properties.calendar', $property)] as $url) {
            $this->actingAs($admin)->get($url)
                ->assertOk()
                ->assertSee(__('messages.flash.calendar_sync_failed'))
                ->assertDontSee('SQLSTATE')
                ->assertDontSee('secret SQL');
        }
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
