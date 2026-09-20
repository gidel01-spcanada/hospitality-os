<?php

namespace App\Services;

use App\Models\ExternalCalendarEvent;
use App\Models\ExternalCalendarFeed;
use Illuminate\Support\Facades\Http;

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

            $eventUids = collect($events)->pluck('uid')->all();
            if ($eventUids === []) {
                $feed->events()->delete();
            } else {
                $feed->events()->whereNotIn('uid', $eventUids)->delete();
            }

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
        $rawLines = preg_split('/\r\n|\n|\r/', trim($calendar)) ?: [];
        $lines = [];
        foreach ($rawLines as $line) {
            if (($line[0] ?? '') === ' ' || ($line[0] ?? '') === "\t") {
                if ($lines !== []) {
                    $lines[array_key_last($lines)] .= ltrim($line);
                }
            } else {
                $lines[] = $line;
            }
        }
        $events = [];
        $current = [];

        foreach ($lines as $line) {
            if ($line === 'BEGIN:VEVENT') {
                $current = [];
                continue;
            }

            if ($line === 'END:VEVENT') {
                if (! empty($current['uid'])) {
                    $startDate = $this->normalizeDate($current['dtstart'] ?? null);
                    $endDate = $this->normalizeDate($current['dtend'] ?? ($current['dtstart'] ?? null));
                    if ($startDate && $endDate && ($current['status'] ?? '') !== 'CANCELLED') {
                        $events[] = [
                        'uid' => $current['uid'],
                        'summary' => $this->unescape((string) ($current['summary'] ?? 'Imported availability')),
                        'start_date' => $startDate,
                        'end_date' => $endDate,
                        ];
                    }
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
            } elseif (str_starts_with($line, 'STATUS')) {
                $current['status'] = strtoupper(trim(substr($line, strpos($line, ':') + 1)));
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

        if (preg_match('/^\d{8}T\d{6}(Z|[+-]\d{4})?$/', $value)) {
            return date('Y-m-d', strtotime(substr($value, 0, 8)));
        }

        return null;
    }

    protected function unescape(string $value): string
    {
        return str_replace(['\\n', '\\N', '\\,', '\\;', '\\\\'], ["\n", "\n", ',', ';', '\\'], $value);
    }
}
