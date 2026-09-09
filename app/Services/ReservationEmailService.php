<?php

namespace App\Services;

use App\Models\EmailOutbox;
use App\Models\Receipt;
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
                'checkout_url' => route('checkout.show', ['reservation' => $reservation, 'token' => $reservation->checkout_token]),
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
                'register_url' => route('register', ['email' => $reservation->email]),
                'total_amount' => (string) $reservation->total_amount,
                'currency' => $reservation->currency,
            ],
        ]);
    }

    public function queueStatusUpdate(Reservation $reservation, string $status): void
    {
        $this->queueForReservation($reservation, 'reservation_status_updated', __('messages.reservation_status.email_subject'));
    }

    public function queueReceipt(Receipt $receipt): void
    {
        $reservation = $receipt->reservation()->with('property')->firstOrFail();

        EmailOutbox::query()->create([
            'template' => 'receipt_issued',
            'recipient_email' => $reservation->email,
            'status' => 'queued',
            'payload' => [
                'reservation_ref' => $reservation->reservation_ref,
                'property_name' => $reservation->property?->name ?? 'Afrik Appart',
                'receipt_number' => $receipt->receipt_number,
                'amount' => (string) $receipt->amount,
                'currency' => $receipt->currency,
                'receipt_url' => route('reservations.receipt', ['reservation' => $reservation]),
                'subject' => __('messages.receipts.email_subject', ['reference' => $reservation->reservation_ref]),
            ],
        ]);
    }
}
