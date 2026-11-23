<?php

namespace App\Services;

use App\Models\Reservation;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class ReservationLifecycle
{
    private const TRANSITIONS = [
        'pending' => ['pending_payment', 'pending_validation', 'confirmed', 'cancelled'],
        'pending_payment' => ['pending_validation', 'confirmed', 'cancelled'],
        'pending_validation' => ['pending_payment', 'confirmed', 'cancelled'],
        'payment_failed' => ['pending_payment', 'cancelled'],
        'confirmed' => ['checked_in', 'completed', 'cancelled'],
        'checked_in' => ['completed', 'cancelled'],
        'completed' => [],
        'cancelled' => ['pending_payment', 'confirmed'],
    ];

    public function assertTransition(Reservation $reservation, string $nextStatus, AvailabilityService $availability): void
    {
        $currentStatus = (string) $reservation->status;
        if ($currentStatus === $nextStatus) {
            return;
        }

        if (! in_array($nextStatus, self::TRANSITIONS[$currentStatus] ?? [], true)) {
            throw ValidationException::withMessages([
                'status' => __('messages.errors.reservation_invalid_transition'),
            ]);
        }

        if ($currentStatus === 'cancelled') {
            $property = $reservation->property()->with('establishment')->firstOrFail();
            if (! $availability->isAvailable(
                $property,
                Carbon::parse($reservation->check_in),
                Carbon::parse($reservation->check_out),
                $reservation->id,
            )) {
                throw ValidationException::withMessages([
                    'status' => __('messages.errors.property_unavailable'),
                ]);
            }
        }
    }
}
