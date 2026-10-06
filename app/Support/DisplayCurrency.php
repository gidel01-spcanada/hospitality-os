<?php

namespace App\Support;

use App\Models\Property;

class DisplayCurrency
{
    public static function amount(float $amount, Property $property, string $currency): ?float
    {
        if ($property->currency === $currency) {
            return $amount;
        }

        $establishment = $property->establishment;
        if ($establishment && $establishment->currency === $property->currency && $establishment->secondary_currency === $currency
            && (float) $establishment->secondary_currency_rate > 0) {
            return $establishment->secondaryDisplayAmount($amount);
        }

        return null;
    }

    public static function label(float $amount, Property $property, string $currency): string
    {
        $converted = self::amount($amount, $property, $currency);

        return $converted === null ? __('messages.comparison.rate_missing') : ReservationSummary::money($converted, $currency);
    }
}