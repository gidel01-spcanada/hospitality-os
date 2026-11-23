<?php

namespace App\Support;

use RuntimeException;

class CalendarFeedUrl
{
    public static function assertPublic(string $url): void
    {
        $parts = parse_url($url);
        $host = strtolower(rtrim((string) ($parts['host'] ?? ''), '.'));
        if (! $parts || ! in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true) || $host === '') {
            throw new RuntimeException('Calendar feed URLs must use HTTP or HTTPS.');
        }

        if (app()->environment('testing') && ! filter_var($host, FILTER_VALIDATE_IP)) {
            return;
        }

        $addresses = filter_var($host, FILTER_VALIDATE_IP)
            ? [$host]
            : collect(dns_get_record($host, DNS_A | DNS_AAAA) ?: [])
                ->pluck('ip')
                ->merge(collect(dns_get_record($host, DNS_AAAA) ?: [])->pluck('ipv6'))
                ->filter()
                ->unique()
                ->values()
                ->all();

        if ($addresses === []) {
            throw new RuntimeException('Calendar feed host could not be resolved.');
        }

        foreach ($addresses as $address) {
            if (! filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                throw new RuntimeException('Calendar feed URLs cannot target private or reserved addresses.');
            }
        }
    }
}
