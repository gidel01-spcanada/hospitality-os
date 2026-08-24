<?php

namespace App\Services;

use App\Models\ExternalCalendarEvent;
use App\Models\ExternalCalendarFeed;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class CalendarFeedSyncService
{
    public function sync(ExternalCalendarFeed $feed): bool
    {
        $feed->update([
            'last_sync_at' => now(),
            'status' => 'syncing',
        ]);

        try {
            $response = Http::timeout(15)->get($feed->url);

            if (! $response->successful()) {
                throw new \RuntimeException('Calendar feed request failed with status ' . $response->status());
            }

            $events = $this->parse($response->body());

            foreach ($events as $event) {
                ExternalCalendarEvent::query()->updateOrCreate(
                    ['feed_id' => $feed->id, 'uid' => $event['uid']],
                    [
                        'summary' => $event['summary'],
                        'start_date' => $event['start_date'],
                        'end_date' => $event['end_date'],
                    ]
                );
            }

            $feed->update([
                'last_successful_sync_at' => now(),
                'last_sync_error' => null,
                'status' => 'fresh',
                'is_enabled' => true,
            ]);

            return true;
        } catch (\Throwable $e) {
            $feed->update([
                'last_sync_error' => $e->getMessage(),
                'status' => 'stale',
            ]);

            return false;
        }
    }

    protected function parse(string $calendar): array
    {
        $lines = preg_split('/\r\n|\n|\r/', trim($calendar));
        $events = [];
        $current = [];

        foreach ($lines as $line) {
            if ($line === 'BEGIN:VEVENT') {
                $current = [];
                continue;
            }

            if ($line === 'END:VEVENT') {
                if (! empty($current['uid'])) {
                    $events[] = [
                        'uid' => $current['uid'],
                        'summary' => $current['summary'] ?? 'Imported availability',
                        'start_date' => $this->normalizeDate($current['dtstart'] ?? null),
                        'end_date' => $this->normalizeDate($current['dtend'] ?? ($current['dtstart'] ?? null)),
                    ];
                }

                $current = [];
                continue;
            }

            if (empty($current) && $line === 'BEGIN:VCALENDAR') {
                continue;
            }

            if (str_starts_with($line, 'UID:')) {
                $current['uid'] = substr($line, 4);
            } elseif (str_starts_with($line, 'SUMMARY:')) {
                $current['summary'] = substr($line, 8);
            } elseif (str_starts_with($line, 'DTSTART')) {
                $current['dtstart'] = substr($line, strpos($line, ':') + 1);
            } elseif (str_starts_with($line, 'DTEND')) {
                $current['dtend'] = substr($line, strpos($line, ':') + 1);
            }
        }

        return $events;
    }

    protected function normalizeDate(?string $value): ?string
    {
        if (! $value) {
            return null;
        }

        $value = trim($value);
        $value = preg_replace('/^TZID=.*?:/', '', $value, 1) ?? $value;

        if (preg_match('/^\d{8}$/', $value)) {
            return date('Y-m-d', strtotime($value));
        }

        if (preg_match('/^\d{8}T\d{6}Z?$/', $value)) {
            return date('Y-m-d', strtotime(substr($value, 0, 8)));
        }

        return null;
    }
}
