<?php

namespace App\Support;

use RuntimeException;

class CalendarFeedUrl
{
    /**
     * Resolve all addresses before connecting, so mixed public/private DNS answers fail closed.
     *
     * @return list<string>
     */
    public function assertPublic(string $url): array
    {
        $parts = parse_url($url);
        $host = strtolower(rtrim((string) ($parts['host'] ?? ''), '.'));
        if (! $parts || ! in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true) || $host === '') {
            throw new RuntimeException('Calendar feed URLs must use HTTP or HTTPS.');
        }

        $host = trim($host, '[]');
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $addresses = [$host];
        } else {
            // Only ordinary DNS names are accepted, not alternate numeric IP spellings.
            if (! preg_match('/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)*[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/D', $host)
                || preg_match('/^(?:0x[0-9a-f]+|[0-9]+)(?:\.(?:0x[0-9a-f]+|[0-9]+))*$/iD', $host)) {
                throw new RuntimeException('Calendar feed host is invalid.');
            }
            $addresses = $this->resolve($host);
        }

        if ($addresses === []) {
            throw new RuntimeException('Calendar feed host could not be resolved.');
        }

        foreach ($addresses as $address) {
            if (! $this->isPublicAddress($address)) {
                throw new RuntimeException('Calendar feed URLs cannot target private or reserved addresses.');
            }
        }

        return array_values(array_unique($addresses));
    }

    /** @return list<string> */
    protected function resolve(string $host): array
    {
        $records = dns_get_record($host, DNS_A | DNS_AAAA);
        if ($records === false) {
            throw new RuntimeException('Calendar feed host could not be resolved.');
        }

        $addresses = [];
        foreach ($records as $record) {
            if (isset($record['ip'])) {
                $addresses[] = $record['ip'];
            } elseif (isset($record['ipv6'])) {
                $addresses[] = $record['ipv6'];
            }
        }

        return $addresses;
    }

    private function isPublicAddress(string $address): bool
    {
        $packed = @inet_pton($address);
        if ($packed === false) {
            return false;
        }

        if (strlen($packed) === 4) {
            $blocked = [
                ['0.0.0.0', 8], ['10.0.0.0', 8], ['100.64.0.0', 10],
                ['127.0.0.0', 8], ['169.254.0.0', 16], ['172.16.0.0', 12],
                ['192.0.0.0', 24], ['192.0.2.0', 24], ['192.88.99.0', 24],
                ['192.168.0.0', 16], ['198.18.0.0', 15], ['198.51.100.0', 24],
                ['203.0.113.0', 24], ['224.0.0.0', 4], ['240.0.0.0', 4],
            ];
        } else {
            // Only global unicast, excluding special-purpose and transition ranges.
            if (! $this->inRange($packed, '2000::', 3)) {
                return false;
            }
            $blocked = [['2001::', 23], ['2001:db8::', 32], ['2002::', 16]];
        }

        foreach ($blocked as [$network, $prefix]) {
            if ($this->inRange($packed, $network, $prefix)) {
                return false;
            }
        }

        return true;
    }

    private function inRange(string $address, string $network, int $prefix): bool
    {
        $network = inet_pton($network);
        $bytes = intdiv($prefix, 8);
        $bits = $prefix % 8;

        return substr($address, 0, $bytes) === substr($network, 0, $bytes)
            && ($bits === 0 || (ord($address[$bytes]) & (0xff << (8 - $bits))) === (ord($network[$bytes]) & (0xff << (8 - $bits))));
    }
}
