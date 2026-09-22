<?php

namespace App\Services;

use App\Models\EmailOutbox;
use App\Models\Receipt;
use App\Models\Reservation;
use App\Models\PaymentAttempt;
use App\Models\User;
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
        $locale = $reservation->locale ?: config('app.locale');

        EmailOutbox::query()->create([
            'template' => $template,
            'recipient_email' => $reservation->email,
            'status' => 'queued',
            'payload' => [
                'locale' => $locale,
                'reservation_ref' => $reservation->reservation_ref,
                'property_name' => $reservation->property?->name ?? 'Afrik Appart',
                'subject' => $subject ?? $this->subjectFor($template, $reservation, $locale),
                'checkout_url' => $this->localizedUrl(route('checkout.show', ['reservation' => $reservation, 'token' => $reservation->checkout_token]), $locale),
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

    private function subjectFor(string $template, Reservation $reservation, string $locale): string
    {
        $key = match ($template) {
            'reservation_created' => 'messages.reservation_created.email_subject',
            'reservation_received' => 'messages.reservation_received.email_subject',
            'reservation_status_updated' => 'messages.reservation_status.email_subject',
            default => null,
        };

        if (! $key) {
            return 'Reservation update';
        }

        $previousLocale = app()->getLocale();
        app()->setLocale($locale);
        $subject = __($key, ['reference' => $reservation->reservation_ref]);
        app()->setLocale($previousLocale);

        return $subject;
    }

    private function localizedUrl(string $url, string $locale): string
    {
        return $url . (str_contains($url, '?') ? '&' : '?') . 'lang=' . $locale;
    }


    public function queuePaymentLink(Reservation $reservation): void
    {
        $reservation->loadMissing('priceLines');
        $locale = $reservation->locale ?: config('app.locale');
        $previousLocale = app()->getLocale();
        app()->setLocale($locale);
        $subject = __('messages.payment_link.email_subject', ['reference' => $reservation->reservation_ref]);
        app()->setLocale($previousLocale);

        EmailOutbox::query()->create([
            'template' => 'payment_link',
            'recipient_email' => $reservation->email,
            'status' => 'queued',
            'payload' => [
                'locale' => $locale,
                'reservation_ref' => $reservation->reservation_ref,
                'property_name' => $reservation->property?->name ?? 'Afrik Appart',
                'subject' => $subject,
                'checkout_url' => $this->localizedUrl(route('checkout.show', ['reservation' => $reservation, 'token' => $reservation->checkout_token]), $locale),
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
        $this->queueForReservation($reservation, 'reservation_status_updated');
    }

    public function queueReceipt(Receipt $receipt): void
    {
        $reservation = $receipt->reservation()->with(['property', 'priceLines'])->firstOrFail();
        $locale = $reservation->locale ?: config('app.locale');
        $previousLocale = app()->getLocale();
        app()->setLocale($locale);
        $subject = __('messages.receipts.email_subject', ['reference' => $reservation->reservation_ref]);
        app()->setLocale($previousLocale);

        EmailOutbox::query()->create([
            'template' => 'receipt_issued',
            'recipient_email' => $reservation->email,
            'status' => 'queued',
            'payload' => [
                'locale' => $locale,
                'reservation_ref' => $reservation->reservation_ref,
                'property_name' => $reservation->property?->name ?? 'Afrik Appart',
                'receipt_number' => $receipt->receipt_number,
                'amount' => (string) $receipt->amount,
                'currency' => $receipt->currency,
                'receipt_url' => $this->localizedUrl(route('reservations.receipt', ['reservation' => $reservation]), $locale),
                'subject' => $subject,
                'price_lines' => $reservation->priceLines->map(fn ($line) => [
                    'label' => $line->label,
                    'amount' => (string) $line->amount,
                    'currency' => $line->currency,
                ])->toArray(),
            ],
        ]);
    }

    public function queueOfflineProofNotification(Reservation $reservation, PaymentAttempt $attempt): void
    {
        $reservation->loadMissing('property.establishment');
        $tenantId = $reservation->property?->establishment?->tenant_id;

        if (! $tenantId) {
            return;
        }

        $recipients = User::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('role', ['admin', 'concierge', 'host'])
            ->get();

        foreach ($recipients as $recipient) {
            $locale = $recipient->locale ?: 'fr';

            EmailOutbox::query()->create([
                'template' => 'payment_proof_submitted',
                'recipient_email' => $recipient->email,
                'status' => 'queued',
                'payload' => [
                    'locale' => $locale,
                    'subject' => __('messages.receipts.proof_notification_subject', ['reference' => $reservation->reservation_ref], $locale),
                    'reservation_ref' => $reservation->reservation_ref,
                    'property_name' => $reservation->property?->name ?? 'Afrik Appart',
                    'provider' => $attempt->provider,
                    'amount' => (string) $attempt->amount,
                    'currency' => $attempt->currency,
                    'reservation_url' => route('admin.reservations.show', $reservation),
                ],
            ]);
        }
    }
}
