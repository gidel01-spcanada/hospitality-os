<?php

namespace App\Services;

use App\Models\PaymentAttempt;
use App\Models\Property;
use App\Models\Receipt;
use App\Models\Reservation;
use App\Models\SiteReview;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ReportingService
{
    private const OCCUPIED_STATUSES = ['confirmed', 'checked_in', 'completed'];
    private const OUTSTANDING_STATUSES = ['pending', 'pending_payment'];
    private const SUCCESSFUL_PAYMENT_STATUSES = ['paid', 'verified'];

    public function build(Collection $properties, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $propertyIds = $properties->pluck('id')->map(fn ($id) => (int) $id)->all();
        $establishmentIds = $properties->pluck('establishment_id')->filter()->unique()->values()->all();
        $periodEndExclusive = $to->addDay();

        $reservations = Reservation::query()
            ->whereIn('property_id', $propertyIds)
            ->whereDate('check_in', '<', $periodEndExclusive->toDateString())
            ->whereDate('check_out', '>', $from->toDateString())
            ->with(['property.establishment', 'guest'])
            ->get();

        $occupiedReservations = $reservations->whereIn('status', self::OCCUPIED_STATUSES);
        $arrivalReservations = $reservations->filter(fn (Reservation $reservation) => $reservation->check_in->betweenIncluded($from, $to));
        $bookedValueReservations = $arrivalReservations->whereIn('status', self::OCCUPIED_STATUSES);
        $outstandingReservations = $arrivalReservations->whereIn('status', self::OUTSTANDING_STATUSES);
        $periodDays = (int) $from->diffInDays($periodEndExclusive);
        $inventoryNights = $properties->count() * $periodDays;
        $blockedNights = $this->blockedNights($properties, $from, $to);
        $sellableNights = max(0, $inventoryNights - $blockedNights);
        $occupiedNights = $this->reservationNights($occupiedReservations, $from, $to);
        $allStayNights = $this->reservationNights($reservations->where('status', '!=', 'cancelled'), $from, $to);

        $receipts = Receipt::query()
            ->whereHas('reservation', fn (Builder $query) => $query->whereIn('property_id', $propertyIds))
            ->whereBetween('issued_at', [$from->startOfDay(), $to->endOfDay()])
            ->with('reservation.property')
            ->get();

        $paymentAttempts = PaymentAttempt::query()
            ->whereHas('reservation', fn (Builder $query) => $query->whereIn('property_id', $propertyIds))
            ->whereBetween('created_at', [$from->startOfDay(), $to->endOfDay()])
            ->get();

        $reviews = SiteReview::query()
            ->active()
            ->whereIn('establishment_id', $establishmentIds)
            ->whereBetween('reviewed_at', [$from->startOfDay(), $to->endOfDay()])
            ->get();

        $statusBreakdown = $reservations
            ->groupBy('status')
            ->map(fn (Collection $items, string $status) => ['status' => $status, 'count' => $items->count()])
            ->sortByDesc('count')
            ->values();

        $activeReservations = $reservations->where('status', '!=', 'cancelled');
        $channelBreakdown = $activeReservations
            ->groupBy(fn (Reservation $reservation) => $reservation->source ?: 'website')
            ->map(fn (Collection $items, string $source) => ['source' => $source, 'count' => $items->count()])
            ->sortByDesc('count')
            ->values();
        $guestOrigins = $activeReservations
            ->groupBy(fn (Reservation $reservation) => $reservation->guest?->country ?: __('messages.reports.unknown_country'))
            ->map(fn (Collection $items, string $country) => ['country' => $country, 'count' => $items->count()])
            ->sortByDesc('count')
            ->take(8)
            ->values();

        $paymentBreakdown = $paymentAttempts
            ->groupBy('provider')
            ->map(function (Collection $attempts, string $provider): array {
                $successful = $attempts->whereIn('status', self::SUCCESSFUL_PAYMENT_STATUSES);

                return [
                    'provider' => $provider,
                    'attempts' => $attempts->count(),
                    'successful' => $successful->count(),
                    'amounts' => $this->moneyByCurrency($successful, 'amount'),
                ];
            })
            ->sortByDesc('attempts')
            ->values();

        $propertyPerformance = $properties->map(function (Property $property) use ($reservations, $occupiedReservations, $bookedValueReservations, $from, $to): array {
            $propertyReservations = $reservations->where('property_id', $property->id);
            $propertyOccupied = $occupiedReservations->where('property_id', $property->id);
            $propertyBookedValue = $bookedValueReservations->where('property_id', $property->id);

            return [
                'property' => $property,
                'reservations' => $propertyReservations->count(),
                'occupied_nights' => $this->reservationNights($propertyOccupied, $from, $to),
                'booked_value' => $this->moneyByCurrency($propertyBookedValue, 'total_amount'),
                'cancelled' => $propertyReservations->where('status', 'cancelled')->count(),
            ];
        })->sortByDesc('occupied_nights')->values();

        $successfulAttempts = $paymentAttempts->whereIn('status', self::SUCCESSFUL_PAYMENT_STATUSES);
        $cancelledCount = $reservations->where('status', 'cancelled')->count();
        $averageStay = $reservations->where('status', '!=', 'cancelled')->count() > 0
            ? round($allStayNights / $reservations->where('status', '!=', 'cancelled')->count(), 1)
            : 0.0;

        return [
            'period' => ['from' => $from, 'to' => $to, 'days' => $periodDays],
            'operations' => [
                'reservations' => $reservations->count(),
                'occupied_nights' => $occupiedNights,
                'sellable_nights' => $sellableNights,
                'blocked_nights' => $blockedNights,
                'occupancy_rate' => $sellableNights > 0 ? min(100.0, round(($occupiedNights / $sellableNights) * 100, 1)) : 0.0,
                'average_stay' => $averageStay,
                'guests' => $reservations->where('status', '!=', 'cancelled')->sum(fn (Reservation $reservation) => $reservation->adults + $reservation->children + $reservation->infants),
                'arrivals' => $reservations->where('status', '!=', 'cancelled')->filter(fn (Reservation $reservation) => $reservation->check_in->betweenIncluded($from, $to))->count(),
                'departures' => $reservations->where('status', '!=', 'cancelled')->filter(fn (Reservation $reservation) => $reservation->check_out->betweenIncluded($from, $to))->count(),
                'cancellations' => $cancelledCount,
                'cancellation_rate' => $reservations->count() > 0 ? round(($cancelledCount / $reservations->count()) * 100, 1) : 0.0,
                'statuses' => $statusBreakdown,
                'channels' => $channelBreakdown,
                'guest_origins' => $guestOrigins,
            ],
            'finance' => [
                'collected' => $this->moneyByCurrency($receipts, 'amount'),
                'booked_value' => $this->moneyByCurrency($bookedValueReservations, 'total_amount'),
                'outstanding' => $this->moneyByCurrency($outstandingReservations, 'total_amount'),
                'subtotal' => $this->moneyByCurrency($bookedValueReservations, 'subtotal'),
                'fees' => $this->moneyByCurrency($bookedValueReservations, 'fees'),
                'taxes' => $this->moneyByCurrency($bookedValueReservations, 'taxes'),
                'average_booking' => $this->averageMoneyByCurrency($bookedValueReservations, 'total_amount'),
                'receipts' => $receipts->count(),
                'payment_attempts' => $paymentAttempts->count(),
                'successful_payments' => $successfulAttempts->count(),
                'payment_success_rate' => $paymentAttempts->count() > 0 ? round(($successfulAttempts->count() / $paymentAttempts->count()) * 100, 1) : 0.0,
                'providers' => $paymentBreakdown,
            ],
            'quality' => [
                'review_count' => $reviews->count(),
                'average_rating' => $reviews->count() > 0 ? round((float) $reviews->avg('rating'), 1) : null,
                'sources' => $reviews->groupBy('source')->map->count()->sortDesc(),
            ],
            'property_performance' => $propertyPerformance,
            'upcoming' => Reservation::query()
                ->whereIn('property_id', $propertyIds)
                ->whereIn('status', ['pending', 'pending_payment', 'confirmed', 'checked_in'])
                ->whereBetween('check_in', [now()->toDateString(), now()->addDays(14)->toDateString()])
                ->with(['property', 'guest'])
                ->orderBy('check_in')
                ->limit(12)
                ->get(),
        ];
    }

    private function blockedNights(Collection $properties, CarbonImmutable $from, CarbonImmutable $to): int
    {
        return $properties->sum(function (Property $property) use ($from, $to): int {
            $dates = [];

            foreach ($property->availabilityBlocks as $block) {
                foreach ($this->dateKeys($block->start_date, $block->end_date, $from, $to) as $date) {
                    $dates[$date] = true;
                }
            }

            foreach ($property->calendarFeeds->where('is_enabled', true) as $feed) {
                foreach ($feed->events as $event) {
                    foreach ($this->dateKeys($event->start_date, $event->end_date, $from, $to) as $date) {
                        $dates[$date] = true;
                    }
                }
            }

            return count($dates);
        });
    }

    private function reservationNights(Collection $reservations, CarbonImmutable $from, CarbonImmutable $to): int
    {
        return $reservations->sum(fn (Reservation $reservation) => count($this->dateKeys($reservation->check_in, $reservation->check_out, $from, $to)));
    }

    private function dateKeys($rangeStart, $rangeEndExclusive, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $start = CarbonImmutable::parse($rangeStart)->startOfDay()->max($from->startOfDay());
        $endExclusive = CarbonImmutable::parse($rangeEndExclusive)->startOfDay()->min($to->addDay()->startOfDay());
        $dates = [];

        for ($cursor = $start; $cursor->lt($endExclusive); $cursor = $cursor->addDay()) {
            $dates[] = $cursor->toDateString();
        }

        return $dates;
    }

    private function moneyByCurrency(Collection $items, string $field): Collection
    {
        return $items
            ->groupBy(fn ($item) => $item->currency ?: 'XOF')
            ->map(fn (Collection $currencyItems, string $currency) => [
                'currency' => $currency,
                'amount' => round((float) $currencyItems->sum(fn ($item) => (float) $item->{$field}), 2),
            ])
            ->values();
    }

    private function averageMoneyByCurrency(Collection $items, string $field): Collection
    {
        return $items
            ->groupBy(fn ($item) => $item->currency ?: 'XOF')
            ->map(fn (Collection $currencyItems, string $currency) => [
                'currency' => $currency,
                'amount' => round((float) $currencyItems->avg(fn ($item) => (float) $item->{$field}), 2),
            ])
            ->values();
    }
}
