<?php

namespace App\Support;

use App\Models\Reservation;

class ReservationSummary
{
    public static function make(Reservation $reservation, ?string $locale = null): array
    {
        $locale ??= app()->getLocale();
        $reservation->loadMissing(['property.establishment.translations', 'property.translations', 'property.images', 'guest', 'user', 'priceLines']);
        $reservation->load('paymentAttempts');
        $property = $reservation->property;
        $establishment = $property?->establishment;
        $nights = $reservation->check_in && $reservation->check_out ? (int) $reservation->check_in->diffInDays($reservation->check_out) : 0;
        $cover = $property?->images->firstWhere('is_cover', true) ?? $property?->images->sortBy('sort_order')->first();
        $propertyImage = $cover?->file_path ?: $property?->cover_image;
        $propertyDescription = $property?->localized('summary', $locale);
        if (! filled($propertyDescription) || str_starts_with(strtolower((string) $propertyDescription), 'draft ')) {
            $propertyDescription = null;
        }
        $establishmentImage = $establishment?->publicImagePaths()[0] ?? null;
        $address = $property?->address;
        if (! filled($address) || str_starts_with(strtolower((string) $address), 'draft ')) {
            $address = $establishment?->address;
        }
        if (str_starts_with(strtolower((string) $address), 'draft ')) {
            $address = null;
        }
        $roomLabels = [__('messages.pricing.room_nights', ['count' => $nights], 'fr'), __('messages.pricing.room_nights', ['count' => $nights], 'en')];
        $lines = $reservation->priceLines->map(fn ($line) => [
            'label' => $line->label,
            'amount' => (string) $line->amount,
            'currency' => $line->currency ?: $reservation->currency,
            'is_room' => $nights > 0 && in_array($line->label, $roomLabels, true),
            'nightly_rate' => $nights > 0 && in_array($line->label, $roomLabels, true) ? number_format((float) $line->amount / $nights, 2, '.', '') : null,
        ])->all();
        $payments = $reservation->paymentAttempts
            ->reject(fn ($attempt) => (bool) data_get($attempt->payload, 'is_guarantee', false))
            ->filter(fn ($attempt) => in_array($attempt->status, ['paid', 'completed'], true))
            ->unique(fn ($attempt) => $attempt->provider . ':' . ($attempt->provider_reference ?: 'id-' . $attempt->id));
        $paidMinor = $payments->where('currency', $reservation->currency)->sum(fn ($attempt) => (int) round((float) $attempt->amount * 100));
        $foreignPayments = $payments->reject(fn ($attempt) => $attempt->currency === $reservation->currency)
            ->map(fn ($attempt) => ['amount' => (string) $attempt->amount, 'currency' => $attempt->currency])->values()->all();
        $totalMinor = (int) round((float) $reservation->total_amount * 100);
        $dueMinor = max(0, $totalMinor - $paidMinor);
        $unreconciled = ! empty($foreignPayments) && $dueMinor > 0;
        $awaitingValidation = $reservation->status === 'pending_validation' || $reservation->paymentAttempts->contains(fn ($attempt) => in_array($attempt->status, ['pending_validation', 'awaiting_validation'], true));
        $validatingMinor = $reservation->paymentAttempts
            ->reject(fn ($attempt) => (bool) data_get($attempt->payload, 'is_guarantee', false))
            ->filter(fn ($attempt) => in_array($attempt->status, ['pending_validation', 'awaiting_validation'], true) && $attempt->currency === $reservation->currency)
            ->sum(fn ($attempt) => (int) round((float) $attempt->amount * 100));
        $paymentStatus = match (true) {
            $reservation->status === 'cancelled' => 'cancelled',
            $unreconciled => 'unreconciled',
            $paidMinor > 0 && $dueMinor === 0 => in_array($reservation->status, ['confirmed', 'completed'], true) ? 'confirmed' : 'paid',
            $awaitingValidation => 'pending_validation',
            $paidMinor > 0 => 'partial',
            $reservation->status === 'payment_failed' => 'failed',
            $totalMinor === 0 => 'not_required',
            default => 'pending',
        };

        return [
            'locale' => $locale,
            'reference' => $reservation->reservation_ref,
            'booked_at' => $reservation->created_at?->toDateString(),
            'check_in' => $reservation->check_in?->toDateString(),
            'check_out' => $reservation->check_out?->toDateString(),
            'nights' => $nights,
            'adults' => (int) $reservation->adults,
            'children' => (int) $reservation->children,
            'infants' => (int) $reservation->infants,
            'guest_name' => $reservation->guest?->full_name ?: $reservation->user?->name ?: $reservation->email,
            'guest_email' => $reservation->email,
            'customer_note' => $reservation->customer_note,
            'status' => $reservation->status,
            'establishment' => $establishment ? [
                'name' => $establishment->localized('name', $locale),
                'image' => $establishmentImage ? asset($establishmentImage) : null,
                'location' => implode(', ', array_filter([$establishment->city, $establishment->country_code])),
                'email' => filter_var($establishment->email, FILTER_VALIDATE_EMAIL) ? $establishment->email : null,
                'phone' => filled($establishment->phone) ? $establishment->phone : null,
                'description' => $establishment->localized('description', $locale) && ! str_starts_with(strtolower((string) $establishment->localized('description', $locale)), 'draft ') ? strip_tags($establishment->localized('description', $locale)) : null,
            ] : null,
            'property' => [
                'name' => $property?->localized('name', $locale) ?? __('messages.checkout.property', [], $locale),
                'image' => $propertyImage ? asset($propertyImage) : null,
                'location' => implode(', ', array_filter([$address, $property?->city ?: $establishment?->city, $property?->country ?: $establishment?->country_code])),
                'capacity' => $property ? __('messages.properties.guest_summary_full', ['guests' => $property->max_guests, 'bedrooms' => $property->bedrooms, 'beds' => $property->beds, 'bathrooms' => $property->bathrooms], $locale) : null,
                'description' => $propertyDescription ? strip_tags($propertyDescription) : null,
            ],
            'lines' => $lines,
            'subtotal' => (string) $reservation->subtotal,
            'fees' => (string) $reservation->fees,
            'taxes' => (string) $reservation->taxes,
            'adjustment' => number_format((float) $reservation->total_amount - (float) $reservation->subtotal - (float) $reservation->fees - (float) $reservation->taxes, 2, '.', ''),
            'total' => (string) $reservation->total_amount,
            'currency' => $reservation->currency,
            'paid' => number_format($paidMinor / 100, 2, '.', ''),
            'due' => $unreconciled ? null : number_format($dueMinor / 100, 2, '.', ''),
            'validating' => number_format(min($validatingMinor, $dueMinor) / 100, 2, '.', ''),
            'refunded' => number_format($reservation->paymentAttempts
                ->filter(fn ($attempt) => $attempt->status === 'refunded' && $attempt->currency === $reservation->currency && ! data_get($attempt->payload, 'is_guarantee'))
                ->sum(fn ($attempt) => (int) round((float) $attempt->amount * 100)) / 100, 2, '.', ''),
            'foreign_payments' => $foreignPayments,
            'payment_status' => $paymentStatus,
            'stay_notes' => self::stayNotes($establishment, $locale),
        ];
    }

    /** Conditions charged or applied outside the reservation total (e.g. electricity, cancellation fee). */
    public static function stayNotes(?\App\Models\Establishment $establishment, string $locale): array
    {
        if (! $establishment) {
            return [];
        }

        $notes = [];
        if ($establishment->electricity_billed_separately) {
            $notes[] = ['type' => 'electricity', 'text' => filled($establishment->electricity_policy_note) ? $establishment->electricity_policy_note : __('messages.properties.electricity_note', [], $locale)];
        }
        if ((float) $establishment->cancellation_fee_percent > 0) {
            $notes[] = ['type' => 'cancellation', 'text' => __('messages.properties.cancellation_fee_note', [
                'percent' => rtrim(rtrim(number_format((float) $establishment->cancellation_fee_percent, 2, ',', ' '), '0'), ','),
                'days' => (int) $establishment->cancellation_fee_days,
            ], $locale)];
        }

        return $notes;
    }

    public static function money(string|float|int $amount, string $currency, ?string $locale = null): string
    {
        $locale ??= app()->getLocale();
        return number_format((float) $amount, (float) $amount == round((float) $amount) ? 0 : 2, $locale === 'en' ? '.' : ',', $locale === 'en' ? ',' : ' ') . ' ' . $currency;
    }
}