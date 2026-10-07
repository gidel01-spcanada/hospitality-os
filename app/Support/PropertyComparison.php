<?php

namespace App\Support;

use App\Models\Property;
use App\Services\AvailabilityService;
use App\Services\PricingCalculator;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class PropertyComparison
{
    public static function make(Collection $properties, array $criteria, AvailabilityService $availability): array
    {
        $hasDates = filled($criteria['check_in'] ?? null) && filled($criteria['check_out'] ?? null);
        $columns = $properties->map(function (Property $property) use ($criteria, $hasDates, $availability) {
            $pricing = null;
            $available = null;
            if ($hasDates) {
                $arrival = Carbon::parse($criteria['check_in']);
                $departure = Carbon::parse($criteria['check_out']);
                $pricing = (float) $property->nightly_rate_xof > 0 ? PricingCalculator::calculate($property, $arrival, $departure, (int) ($criteria['guests'] ?? 1)) : null;
                $available = (int) $property->max_guests >= (int) ($criteria['guests'] ?? 1)
                    && $availability->isAvailable($property, $arrival, $departure);
            }
            $image = $property->images->firstWhere('is_cover', true) ?? $property->images->first();

            return [
                'property' => $property,
                'image' => $image?->file_path ?: $property->cover_image,
                'pricing' => $pricing,
                'available' => $available,
                'rating' => $property->reviews->isEmpty() ? null : (float) $property->reviews->avg('rating'),
                'review_count' => $property->reviews->count(),
                'url' => route('properties.show', array_filter([
                    'property' => $property,
                    'check_in' => $criteria['check_in'] ?? null,
                    'check_out' => $criteria['check_out'] ?? null,
                    'guests' => $criteria['guests'] ?? null,
                ], fn ($value) => $value !== null && $value !== '')),
            ];
        })->values();
        $currencies = $properties->flatMap(fn ($property) => [$property->currency, $property->establishment?->secondary_currency])->filter()->unique()->values();
        $currency = $criteria['currency'] ?? session('comparison_currency') ?? $properties->first()?->currency ?? 'XOF';
        if (! $currencies->contains($currency)) {
            $currency = $properties->first()?->currency ?? 'XOF';
        }

        $groups = collect();
        $priceRows = [
            self::row('nightly', 'properties.compare_nightly', $columns->map(fn ($column) => (float) $column['property']->nightly_rate_xof > 0 ? (float) $column['property']->nightly_rate_xof : null), 'money', 'min'),
        ];
        if ($hasDates) {
            foreach (['nights', 'room_subtotal', 'fees', 'taxes', 'extras_subtotal', 'discount_total', 'total_amount'] as $field) {
                $priceRows[] = self::row($field, 'properties.compare_' . $field, $columns->map(fn ($column) => $column['pricing'][$field] ?? null), $field === 'nights' ? 'number' : 'money', $field === 'total_amount' ? 'min' : null);
            }
            $includedTaxLabels = $columns->flatMap(fn ($column) => collect($column['pricing']['tax_lines'] ?? [])->where('included', true)->pluck('label'))->unique();
            foreach ($includedTaxLabels as $label) {
                $priceRows[] = self::row('included-tax-' . count($priceRows), $label, $columns->map(fn ($column) => collect($column['pricing']['tax_lines'] ?? [])->firstWhere('label', $label)['amount'] ?? null), 'money', null, false);
            }
        }
        $priceRows[] = self::row('available', 'properties.compare_availability', $columns->pluck('available'), 'availability');
        $groups->push(['key' => 'price', 'label' => __('messages.properties.compare_price_section'), 'rows' => $priceRows, 'open' => true]);

        $capacityRows = [];
        foreach (['max_guests', 'bedrooms', 'beds', 'bathrooms', 'area', 'property_type'] as $field) {
            $values = $field === 'area'
                ? $properties->map(fn ($property) => $property->area ? $property->area . ' ' . ($property->area_unit ?: 'm²') : null)
                : $properties->pluck($field);
            $capacityRows[] = self::row($field, 'properties.compare_' . $field, $values, $field === 'property_type' ? 'type' : 'number', $field === 'max_guests' ? 'max' : null);
        }
        $groups->push(['key' => 'capacity', 'label' => __('messages.properties.compare_capacity_section'), 'rows' => $capacityRows, 'open' => true]);

        $amenityRows = $properties->flatMap->amenities->unique('id')->sortBy('sort_order')->map(fn ($amenity) => self::row(
            'amenity-' . $amenity->id,
            $amenity->name,
            $properties->map(fn ($property) => $property->amenities->contains('id', $amenity->id)),
            'amenity',
            null,
            false,
        ))->values()->all();
        $groups->push(['key' => 'amenities', 'label' => __('messages.properties.compare_amenities'), 'rows' => $amenityRows, 'open' => false]);

        $locationRows = [];
        foreach (['city', 'address', 'country'] as $field) {
            $locationRows[] = self::row($field, 'properties.compare_' . $field, $properties->map(function ($property) use ($field) {
                $value = $property->{$field} ?: $property->establishment?->{match ($field) { 'country' => 'country_code', default => $field }};

                return str_starts_with(strtolower((string) $value), 'draft ') ? null : $value;
            }), 'text');
        }
        $locationRows[] = self::row('map', 'properties.location', $properties->map(function ($property) {
            $latitude = $property->establishment?->latitude ?? $property->latitude;
            $longitude = $property->establishment?->longitude ?? $property->longitude;

            return $latitude !== null && $longitude !== null ? 'https://www.google.com/maps?q=' . urlencode($latitude . ',' . $longitude) : null;
        }), 'map');
        $groups->push(['key' => 'location', 'label' => __('messages.properties.location'), 'rows' => $locationRows, 'open' => true]);
        $groups->push(['key' => 'reviews', 'label' => __('messages.properties.compare_reviews_section'), 'rows' => [
            self::row('rating', 'properties.compare_rating', $columns->pluck('rating'), 'rating', 'max'),
            self::row('review_count', 'properties.compare_review_count', $columns->pluck('review_count'), 'number'),
        ], 'open' => true]);
        $groups->push(['key' => 'conditions', 'label' => __('messages.properties.compare_conditions_section'), 'rows' => [
            self::row('minimum_stay', 'properties.compare_minimum_stay', $properties->pluck('minimum_stay'), 'number'),
            self::row('cancellation', 'properties.compare_cancellation', $properties->map(fn ($property) => $property->establishment ? ((float) $property->establishment->cancellation_fee_percent <= 0
                ? __('messages.properties.flexible_cancellation')
                : __('messages.properties.cancellation_fee_note', ['percent' => (float) $property->establishment->cancellation_fee_percent, 'days' => $property->establishment->cancellation_fee_days])) : null), 'text'),
            self::row('electricity', 'properties.compare_electricity', $properties->map(fn ($property) => $property->establishment ? ($property->establishment->electricity_billed_separately
                ? ($property->establishment->electricity_policy_note ?: __('messages.properties.electricity_note')) : null) : null), 'text'),
        ], 'open' => true]);

        return compact('columns', 'groups', 'currencies', 'currency', 'hasDates');
    }

    private static function row(string $key, string $label, Collection $values, string $type, ?string $highlight = null, bool $translate = true): array
    {
        $values = $values->values()->all();
        $different = count(array_unique(array_map(fn ($value) => json_encode($value), $values))) > 1;
        $known = array_filter($values, fn ($value) => $value !== null && $value !== '');
        $best = $different && $highlight && count($known) === count($values)
            ? ($highlight === 'min' ? min($known) : max($known)) : null;

        return ['key' => $key, 'label' => $translate ? __('messages.' . $label) : $label, 'values' => $values, 'type' => $type, 'different' => $different, 'best' => $best];
    }
}