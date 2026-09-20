<?php

namespace App\Services;

use App\Models\EmailOutbox;
use App\Models\Receipt;
use App\Models\Reservation;
use App\Models\PaymentAttempt;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\URL;

class ReservationEmailService
{
    public function issueReceiptAndQueueEmail(Reservation $reservation, PaymentAttempt $attempt, ?string $issuedBy = null): Receipt
    {
        $receipt = $reservation->receipts()->firstOrCreate(
            ['payment_attempt_id' => $attempt->id],
            [
                'receipt_number' => 'RC-' . now()->format('Ymd') . '-' . strtoupper(Str::random(8)),
                'amount' => $attempt->amount,
                'currency' => $attempt->currency,
                'issued_at' => now(),
                'issued_by' => $issuedBy,
            ]
        );

        if ($receipt->wasRecentlyCreated) {
            $this->queueReceipt($receipt);
        }

        return $receipt;
    }

    public function queueForReservation(Reservation $reservation, string $template, ?string $subject = null): void
    {
        $reservation->loadMissing('priceLines');
        $reservation->loadMissing('property.establishment');
        $reviewPayload = [];
        if ($reservation->status === 'completed') {
            $reviewPayload = [
                'review_url' => URL::temporarySignedRoute('reviews.submit', now()->addDays(30), ['reservation' => $reservation]),
                'google_review_url' => $reservation->property?->establishment?->google_review_url,
                'review_channel' => $reservation->property?->establishment?->review_channel ?: 'internal',
            ];
        }

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
                'price_lines' => $reservation->priceLines->map(fn ($line) => [
                    'label' => $line->label,
                    'amount' => (string) $line->amount,
                    'currency' => $line->currency,
                ])->toArray(),
                ...$reviewPayload,
            ],
        ]);
    }

    public function queuePaymentLink(Reservation $reservation): void
    {
        $reservation->loadMissing('priceLines');

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
                'price_lines' => $reservation->priceLines->map(fn ($line) => [
                    'label' => $line->label,
                    'amount' => (string) $line->amount,
                    'currency' => $line->currency,
                ])->toArray(),
            ],
        ]);
    }

    public function queueStatusUpdate(Reservation $reservation, string $status): void
    {
        $this->queueForReservation($reservation, 'reservation_status_updated', __('messages.reservation_status.email_subject'));
    }

    public function queueReceipt(Receipt $receipt): void
    {
        $reservation = $receipt->reservation()->with(['property', 'priceLines'])->firstOrFail();

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
                'price_lines' => $reservation->priceLines->map(fn ($line) => [
                    'label' => $line->label,
                    'amount' => (string) $line->amount,
                    'currency' => $line->currency,
                ])->toArray(),
            ],
        ]);
    }
}
