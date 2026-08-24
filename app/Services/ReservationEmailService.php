<?php

namespace App\Services;

use App\Models\EmailOutbox;
use App\Models\Reservation;

class ReservationEmailService
{
    public function queueForReservation(Reservation $reservation, string $template, ?string $subject = null): void
    {
        EmailOutbox::query()->create([
            'template' => $template,
            'recipient_email' => $reservation->email,
            'status' => 'queued',
            'payload' => [
                'reservation_ref' => $reservation->reservation_ref,
                'property_name' => $reservation->property?->name ?? 'Afrik Appart',
                'subject' => $subject ?? 'Reservation update',
                'check_in' => $reservation->check_in?->toDateString(),
                'check_out' => $reservation->check_out?->toDateString(),
                'total_amount' => (string) $reservation->total_amount,
                'currency' => $reservation->currency,
                'status' => $reservation->status,
            ],
        ]);
    }
}
