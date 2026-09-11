<?php

namespace App\Services;

use App\Models\AdminAvailabilityBlock;
use App\Models\ExternalCalendarEvent;
use App\Models\Property;
use App\Models\Reservation;
use Carbon\Carbon;

class AvailabilityService
{
    public function isAvailable(Property $property, Carbon $checkIn, Carbon $checkOut, ?int $ignoreReservationId = null): bool
    {
        if ($checkOut->lte($checkIn)) {
            return false;
        }

        $conflicts = Reservation::query()
            ->where('property_id', $property->id)
            ->where('status', '!=', 'cancelled')
            ->when($ignoreReservationId, fn ($query) => $query->where('id', '!=', $ignoreReservationId))
            ->whereDate('check_in', '<', $checkOut->toDateString())
            ->whereDate('check_out', '>', $checkIn->toDateString())
            ->exists();

        if ($conflicts) {
            return false;
        }

        $externalConflicts = ExternalCalendarEvent::query()
            ->whereHas('feed', fn ($query) => $query->where('property_id', $property->id)->where('is_enabled', true))
            ->whereDate('start_date', '<', $checkOut->toDateString())
            ->whereDate('end_date', '>', $checkIn->toDateString())
            ->exists();

        if ($externalConflicts) {
            return false;
        }

        return ! AdminAvailabilityBlock::query()
            ->where('property_id', $property->id)
            ->whereDate('start_date', '<', $checkOut->toDateString())
            ->whereDate('end_date', '>', $checkIn->toDateString())
            ->exists();
    }
}
