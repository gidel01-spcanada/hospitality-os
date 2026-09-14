<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class InternationalCurrencyConverter
{
    public function toCad(float $amount, ?string $currency): ?float
    {
        $currency = strtoupper((string) $currency);
        if ($currency === 'CAD') {
            return round($amount, 2);
        }

        $rate = Cache::remember('exchange-rate:' . $currency . ':CAD', now()->addHours(6), function () use ($currency): ?float {
            try {
                $response = Http::acceptJson()->timeout(5)->get('https://api.frankfurter.app/latest', [
                    'from' => $currency,
                    'to' => 'CAD',
                ]);

                $value = $response->successful() ? $response->json('rates.CAD') : null;

                return is_numeric($value) && (float) $value > 0 ? (float) $value : null;
            } catch (\Throwable) {
                return null;
            }
        });

        if ($rate === null) {
            return null;
        }

        return round($amount * $rate, 2);
    }
}
