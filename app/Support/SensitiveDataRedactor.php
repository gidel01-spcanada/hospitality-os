<?php

namespace App\Support;

class SensitiveDataRedactor
{
    public static function redactArray(array $values): array
    {
        $redacted = [];

        foreach ($values as $key => $value) {
            if (preg_match('/password|secret|token|credential|security[_-]?answer|authorization|signature|api[_-]?key|encrypted/i', (string) $key)) {
                $redacted[$key] = '[redacted]';
                continue;
            }

            if (is_array($value)) {
                $redacted[$key] = self::redactArray($value);
                continue;
            }

            if (is_string($value) && ($key === 'url' || $key === 'feed_url')) {
                $redacted[$key] = self::redactUrl($value);
                continue;
            }

            if (is_string($value)) {
                $decoded = json_decode($value, true);
                $redacted[$key] = is_array($decoded)
                    ? json_encode(self::redactArray($decoded), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                    : $value;
                continue;
            }

            $redacted[$key] = $value;
        }

        return $redacted;
    }

    public static function redactUrl(string $url): string
    {
        $parts = parse_url($url);
        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            return $url;
        }

        $authority = '';
        if (isset($parts['user'])) {
            $authority = '[redacted]';
            if (isset($parts['pass'])) {
                $authority .= ':[redacted]';
            }
            $authority .= '@';
        }
        $authority .= $parts['host'];
        if (isset($parts['port'])) {
            $authority .= ':' . $parts['port'];
        }

        $query = '';
        if (isset($parts['query'])) {
            parse_str($parts['query'], $parameters);
            foreach (array_keys($parameters) as $key) {
                if (preg_match('/token|secret|password|credential|signature|api[_-]?key/i', (string) $key)) {
                    $parameters[$key] = '[redacted]';
                }
            }
            $query = $parameters ? '?' . http_build_query($parameters) : '';
        }

        return $parts['scheme'] . '://' . $authority . ($parts['path'] ?? '') . $query;
    }
}
