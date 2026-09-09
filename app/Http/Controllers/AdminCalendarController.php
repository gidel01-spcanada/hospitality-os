<?php

namespace App\Http\Controllers;

use App\Models\ExternalCalendarEvent;
use App\Models\ExternalCalendarFeed;
use App\Models\Property;
use App\Services\CalendarFeedSyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AdminCalendarController extends Controller
{
    public function index(Property $property)
    {
        $feeds = $property->calendarFeeds()->latest()->get();

        return view('admin.properties.calendar', compact('property', 'feeds'));
    }

    public function storeFeed(Request $request, Property $property): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'url' => ['required', 'url', 'max:2048'],
            'is_enabled' => ['sometimes', 'boolean'],
        ]);

        $property->calendarFeeds()->create([
            'name' => $validated['name'],
            'url' => $validated['url'],
            'is_enabled' => $request->boolean('is_enabled'),
            'status' => 'stale',
            'sync_interval_minutes' => 60,
        ]);

        return redirect()->to(route('admin.properties.edit', $property) . '#calendars')->with('success', __('messages.flash.calendar_saved'));
    }

    public function syncFeed(Property $property, ExternalCalendarFeed $feed, CalendarFeedSyncService $service): RedirectResponse
    {
        abort_unless($feed->property_id === $property->id, 404);

        $service->sync($feed);

        return redirect()->to(route('admin.properties.edit', $property) . '#calendars')->with('success', __('messages.flash.calendar_synced'));
    }

    public function updateFeed(Request $request, Property $property, ExternalCalendarFeed $feed): RedirectResponse
    {
        abort_unless($feed->property_id === $property->id, 404);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'url' => ['required', 'url', 'max:2048'],
            'is_enabled' => ['sometimes', 'boolean'],
        ]);

        $feed->update([
            'name' => $validated['name'],
            'url' => $validated['url'],
            'is_enabled' => $request->boolean('is_enabled'),
            'status' => $feed->url !== $validated['url'] ? 'stale' : $feed->status,
        ]);

        return redirect()->to(route('admin.properties.edit', $property) . '#calendars')->with('success', __('messages.flash.calendar_updated'));
    }

    public function deleteFeed(Property $property, ExternalCalendarFeed $feed): RedirectResponse
    {
        abort_unless($feed->property_id === $property->id, 404);
        $feed->events()->delete();
        $feed->delete();

        return redirect()->to(route('admin.properties.edit', $property) . '#calendars')->with('success', __('messages.flash.calendar_deleted'));
    }

    public function export(Property $property)
    {
        $events = $property->reservations()->where('status', 'confirmed')->get()->map(function ($reservation) {
            return [
                'uid' => 'reservation-' . $reservation->id,
                'summary' => 'Reserved: ' . $reservation->reservation_ref,
                'start' => $reservation->check_in->format('Ymd'),
                'end' => $reservation->check_out->format('Ymd'),
            ];
        })->all();

        $blocks = $property->availabilityBlocks()->get()->map(function ($block) {
            return [
                'uid' => 'block-' . $block->id,
                'summary' => $block->reason ?: 'Admin block',
                'start' => $block->start_date->format('Ymd'),
                'end' => $block->end_date->format('Ymd'),
            ];
        })->all();

        $calendar = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//Afrik Appart//Calendar//EN\r\nCALSCALE:GREGORIAN\r\n";

        foreach (array_merge($events, $blocks) as $event) {
            $calendar .= "BEGIN:VEVENT\r\nUID:" . $event['uid'] . "\r\nDTSTART:" . $event['start'] . "\r\nDTEND:" . $event['end'] . "\r\nSUMMARY:" . str_replace(["\r", "\n"], '', $event['summary']) . "\r\nEND:VEVENT\r\n";
        }

        $calendar .= "END:VCALENDAR\r\n";

        return response($calendar, 200, [
            'Content-Type' => 'text/calendar; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . Str::slug($property->slug) . '-calendar.ics"',
        ]);
    }

    public function publicExport(Property $property, string $token)
    {
        abort_unless($property->calendar_export_token && hash_equals($property->calendar_export_token, $token), 404);

        return $this->export($property);
    }
}
