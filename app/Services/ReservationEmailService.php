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

    public function queuePaymentLink(Reservation $reservation): void
    {
        EmailOutbox::query()->create([
            'template' => 'payment_link',
            'recipient_email' => $reservation->email,
            'status' => 'queued',
            'payload' => [
                'reservation_ref' => $reservation->reservation_ref,
                'property_name' => $reservation->property?->name ?? 'Afrik Appart',
                'subject' => __('messages.payment_link.email_subject', ['reference' => $reservation->reservation_ref]),
                'checkout_url' => route('checkout.show', ['reservation' => $reservation, 'token' => $reservation->checkout_token]),
                'total_amount' => (string) $reservation->total_amount,
                'currency' => $reservation->currency,
            ],
        ]);
    }
}
