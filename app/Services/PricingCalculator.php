<?php

namespace App\Services;

use App\Models\Property;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class PricingCalculator
{
    public static function calculate(
        Property $property,
        Carbon $checkIn,
        Carbon $checkOut,
        int $adults = 1,
        int $children = 0,
        Collection|array $selectedFeatures = []
    ): array {
        $establishment = $property->establishment;
        $nights = max(1, (int) $checkIn->diffInDays($checkOut));
        $baseRate = (float) $property->nightly_rate_xof;
        $roomSubtotal = round($baseRate * $nights, 2);

        $extrasCollection = is_array($selectedFeatures) ? collect($selectedFeatures) : $selectedFeatures;
        $extrasSubtotal = round((float) $extrasCollection->sum('cost_xof'), 2);
        $subtotal = round($roomSubtotal + $extrasSubtotal, 2);

        // Service Fee
        $serviceFeePercent = (float) ($establishment?->service_fee_percent ?? 10.00);
        $fees = round($subtotal * ($serviceFeePercent / 100), 2);

        // VAT / TVA
        $vatPercent = (float) ($establishment?->vat_percent ?? 18.00);
        $vatIncluded = (bool) ($establishment?->vat_included ?? true);

        if ($vatPercent > 0) {
            if ($vatIncluded) {
                $vatAmount = round($subtotal - ($subtotal / (1 + ($vatPercent / 100))), 2);
                $addedVat = 0.00;
            } else {
                $vatAmount = round(($subtotal + $fees) * ($vatPercent / 100), 2);
                $addedVat = $vatAmount;
            }
        } else {
            $vatAmount = 0.00;
            $addedVat = 0.00;
        }

        // City / Local Tax
        $cityTaxType = $establishment?->city_tax_type ?? 'percent';
        $cityTaxAmount = (float) ($establishment?->city_tax_amount ?? 5.00);
        $totalGuests = max(1, $adults + $children);

        $localTax = match ($cityTaxType) {
            'per_night' => round($cityTaxAmount * $nights, 2),
            'per_guest_night' => round($cityTaxAmount * $totalGuests * $nights, 2),
            'percent' => round($subtotal * ($cityTaxAmount / 100), 2),
            default => 0.00,
        };

        $taxes = round($addedVat + $localTax, 2);
        $totalAmount = round($subtotal + $fees + $taxes, 2);
        $currency = $property->currency ?? 'XOF';

        // Price Lines for display & database storage
        $priceLines = [];

        $priceLines[] = [
            'label' => __('messages.pricing.room_nights', ['count' => $nights]),
            'amount' => $roomSubtotal,
            'currency' => $currency,
        ];

        foreach ($extrasCollection as $feature) {
            $priceLines[] = [
                'label' => __('messages.pricing.extra_option', ['name' => $feature->name]),
                'amount' => (float) $feature->cost_xof,
                'currency' => $currency,
            ];
        }

        if ($fees > 0) {
            $formattedPercent = rtrim(rtrim(number_format($serviceFeePercent, 2, ',', ' '), '0'), ',');
            $priceLines[] = [
                'label' => __('messages.pricing.service_fee', ['percent' => $formattedPercent]),
                'amount' => $fees,
                'currency' => $currency,
            ];
        }

        if ($vatPercent > 0) {
            $formattedVatPercent = rtrim(rtrim(number_format($vatPercent, 2, ',', ' '), '0'), ',');
            if ($vatIncluded) {
                $priceLines[] = [
                    'label' => __('messages.pricing.vat_included', ['percent' => $formattedVatPercent]),
                    'amount' => $vatAmount,
                    'currency' => $currency,
                ];
            } else {
                $priceLines[] = [
                    'label' => __('messages.pricing.vat_excluded', ['percent' => $formattedVatPercent]),
                    'amount' => $vatAmount,
                    'currency' => $currency,
                ];
            }
        }

        if ($localTax > 0) {
            $priceLines[] = [
                'label' => __('messages.pricing.city_tax'),
                'amount' => $localTax,
                'currency' => $currency,
            ];
        }

        return [
            'nights' => $nights,
            'guests' => $totalGuests,
            'room_subtotal' => $roomSubtotal,
            'extras_subtotal' => $extrasSubtotal,
            'subtotal' => $subtotal,
            'service_fee_percent' => $serviceFeePercent,
            'fees' => $fees,
            'vat_percent' => $vatPercent,
            'vat_included' => $vatIncluded,
            'vat_amount' => $vatAmount,
            'city_tax_type' => $cityTaxType,
            'city_tax_amount' => $cityTaxAmount,
            'local_tax_amount' => $localTax,
            'taxes' => $taxes,
            'total_amount' => $totalAmount,
            'currency' => $currency,
            'price_lines' => $priceLines,
        ];
    }
}
