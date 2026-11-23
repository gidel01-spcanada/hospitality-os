<?php

namespace App\Services;

use App\Models\ExternalCalendarEvent;
use App\Models\ExternalCalendarFeed;
use App\Support\CalendarFeedUrl;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class CalendarFeedSyncService
{
    public function sync(ExternalCalendarFeed $feed): bool
    {
        $feed->update([
            'last_sync_at' => now(),
            'status' => 'syncing',
        ]);

        try {
            CalendarFeedUrl::assertPublic((string) $feed->url);
            $response = Http::timeout(15)->withOptions([
                'allow_redirects' => false,
                'connect_timeout' => 5,
                'progress' => static function (int $downloadTotal, int $downloadedBytes, int $uploadTotal, int $uploadedBytes): void {
                    if ($downloadedBytes > 2 * 1024 * 1024) {
                        throw new RuntimeException('Calendar feed exceeds the 2 MB size limit.');
                    }
                },
            ])->get($feed->url);

            if (! $response->successful()) {
                throw new \RuntimeException('Calendar feed request failed with status ' . $response->status());
            }

            $events = $this->parse($response->body());
            if (strlen($response->body()) > 2 * 1024 * 1024) {
                throw new RuntimeException('Calendar feed exceeds the 2 MB size limit.');
            }
            if ($events === []) {
                throw new RuntimeException('Calendar feed contains no valid events; existing dates were retained.');
            }

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
        if (! str_contains($calendar, 'BEGIN:VCALENDAR') || ! str_contains($calendar, 'END:VCALENDAR')) {
            throw new RuntimeException('Calendar feed is malformed; existing dates were retained.');
        }

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
