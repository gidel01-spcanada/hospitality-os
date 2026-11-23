<?php

namespace App\Services;

use App\Models\ExternalCalendarEvent;
use App\Models\ExternalCalendarFeed;
use App\Support\CalendarFeedUrl;
use GuzzleHttp\Handler\StreamHandler;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Psr\Http\Message\RequestInterface;
use RuntimeException;

class CalendarFeedSyncService
{
    private const MAX_BYTES = 2 * 1024 * 1024;
    private const MAX_REDIRECTS = 5;

    public function sync(ExternalCalendarFeed $feed): bool
    {
        $feed->update([
            'last_sync_at' => now(),
            'status' => 'syncing',
        ]);

        try {
            $events = $this->parse($this->fetch((string) $feed->url));
            if ($events === []) {
                throw new RuntimeException('Calendar feed contains no valid events; existing dates were retained.');
            }

            DB::transaction(function () use ($feed, $events): void {
                $eventUids = collect($events)->pluck('uid')->all();
                $feed->events()->whereNotIn('uid', $eventUids)->delete();

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
            });

            return true;
        } catch (\Throwable $e) {
            Log::warning('Calendar feed sync failed.', [
                'feed_id' => $feed->id,
                'exception_type' => get_class($e),
            ]);
            $feed->update([
                'last_sync_error' => 'Calendar feed sync failed.',
                'status' => 'stale',
            ]);

            return false;
        }
    }

    private function fetch(string $url): string
    {
        $deadline = microtime(true) + 15;
        for ($redirects = 0; $redirects <= self::MAX_REDIRECTS; $redirects++) {
            $addresses = app(CalendarFeedUrl::class)->assertPublic($url);
            $uri = new Uri($url);
            $host = trim($uri->getHost(), '[]');
            $address = $addresses[0];
            $remaining = $deadline - microtime(true);
            if ($remaining <= 0) {
                throw new RuntimeException('Calendar feed request timed out.');
            }

            $request = Http::withHeaders(['Accept-Encoding' => 'identity'])->withOptions([
                'allow_redirects' => false,
                'proxy' => '',
                'stream' => true,
                'decode_content' => true,
                'timeout' => $remaining,
                'read_timeout' => min(5, $remaining),
            ]);
            if ($uri->getUserInfo() !== '') {
                [$username, $password] = array_pad(explode(':', $uri->getUserInfo(), 2), 2, '');
                $request = $request->withBasicAuth(rawurldecode($username), rawurldecode($password));
            }
            $transport = $this->streamHandler();
            // The handler connects to the validated IP, but retains the original Host and TLS name.
            $request->setHandler(static function (RequestInterface $request, array $options) use ($host, $address, $transport) {
                $options['stream_context']['ssl']['peer_name'] = $host;
                $options['stream_context']['ssl']['SNI_server_name'] = $host;
                $ipHost = str_contains($address, ':') ? '[' . $address . ']' : $address;
                $request = $request->withUri($request->getUri()->withHost($ipHost), true);

                return $transport($request, $options);
            });
            $response = $request->get((string) $uri->withUserInfo(''));
            $body = $response->toPsrResponse()->getBody();

            try {
                if (in_array($response->status(), [301, 302, 303, 307, 308], true)) {
                    $location = $response->header('Location');
                    if ($redirects === self::MAX_REDIRECTS || ! is_string($location) || $location === '') {
                        throw new RuntimeException('Calendar feed redirect is invalid or exceeds the limit.');
                    }
                    $next = UriResolver::resolve($uri, new Uri($location));
                    if ($next->getHost() !== $uri->getHost() || $next->getPort() !== $uri->getPort() || $next->getScheme() !== $uri->getScheme()) {
                        $next = $next->withUserInfo('');
                    }
                    $url = (string) $next;
                    continue;
                }

                if (! $response->successful()) {
                    throw new RuntimeException('Calendar feed request failed with status ' . $response->status());
                }
                $encoding = strtolower(trim($response->header('Content-Encoding') ?? ''));
                if ($encoding !== '' && $encoding !== 'identity') {
                    throw new RuntimeException('Calendar feed content encoding is not supported.');
                }
                $contentLength = $response->header('Content-Length');
                if ($contentLength !== '' && (! ctype_digit($contentLength) || (float) $contentLength > self::MAX_BYTES)) {
                    throw new RuntimeException('Calendar feed response has an invalid or oversized content length.');
                }
                $content = '';
                while (! $body->eof()) {
                    if (microtime(true) >= $deadline) {
                        throw new RuntimeException('Calendar feed request timed out.');
                    }
                    $chunk = $body->read(min(8192, self::MAX_BYTES + 1 - strlen($content)));
                    if ($chunk === '' && ! $body->eof()) {
                        throw new RuntimeException('Calendar feed response could not be read.');
                    }
                    $content .= $chunk;
                    if (strlen($content) > self::MAX_BYTES) {
                        throw new RuntimeException('Calendar feed exceeds the 2 MB size limit.');
                    }
                }
                if ($contentLength !== '' && strlen($content) !== (int) $contentLength) {
                    throw new RuntimeException('Calendar feed response was incomplete.');
                }

                return $content;
            } finally {
                $body->close();
            }
        }

        throw new RuntimeException('Calendar feed redirect limit exceeded.');
    }

    protected function streamHandler(): callable
    {
        return new StreamHandler;
    }

    protected function parse(string $calendar): array
    {
        if (! str_contains($calendar, 'BEGIN:VCALENDAR') || ! str_contains($calendar, 'END:VCALENDAR')) {
            throw new RuntimeException('Calendar feed is malformed; existing dates were retained.');
        }

        $events = [];
        $current = [];

        foreach ($this->unfoldLines($calendar) as $line) {
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

    private function unfoldLines(string $calendar): \Generator
    {
        $offset = 0;
        $line = null;
        $length = strlen($calendar);
        while ($offset < $length && preg_match('/[^\r\n]*(?:\r\n|\r|\n|$)/', $calendar, $match, 0, $offset)) {
            $offset += strlen($match[0]);
            $part = rtrim($match[0], "\r\n");
            if (($part[0] ?? '') === ' ' || ($part[0] ?? '') === "\t") {
                if ($line !== null) {
                    $line .= ltrim($part);
                }
            } else {
                if ($line !== null) {
                    yield $line;
                }
                $line = $part;
            }
        }

        if ($line !== null) {
            yield $line;
        }
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
