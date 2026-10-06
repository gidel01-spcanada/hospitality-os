<?php

namespace App\Services;

use App\Models\EmailOutbox;
use App\Models\Receipt;
use App\Models\Reservation;
use App\Models\PaymentAttempt;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\URL;
use App\Support\ReservationSummary;

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
        $summary = ReservationSummary::make($reservation, $locale);

        EmailOutbox::query()->create([
            'template' => $template,
            'recipient_email' => $reservation->email,
            'status' => 'queued',
            'payload' => [
                'locale' => $locale,
                'reservation_id' => $reservation->id,
                'reservation_summary' => $summary,
                'reservation_ref' => $reservation->reservation_ref,
                'property_name' => $summary['property']['name'],
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
        $summary = ReservationSummary::make($reservation, $locale);
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
                'reservation_id' => $reservation->id,
                'reservation_summary' => $summary,
                'reservation_ref' => $reservation->reservation_ref,
                'property_name' => $summary['property']['name'],
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
        $summary = ReservationSummary::make($reservation, $locale);
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
                'reservation_id' => $reservation->id,
                'reservation_summary' => $summary,
                'receipt_id' => $receipt->id,
                'transaction' => $this->receiptTransaction($receipt),
                'reservation_ref' => $reservation->reservation_ref,
                'property_name' => $summary['property']['name'],
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

    public function receiptTransaction(Receipt $receipt): array
    {
        $receipt->load('paymentAttempt');
        $attempt = $receipt->paymentAttempt;
        $verified = $attempt
            && $attempt->reservation_id === $receipt->reservation_id
            && $attempt->currency === $receipt->currency
            && (string) $attempt->amount === (string) $receipt->amount
            && in_array($attempt->status, ['paid', 'completed'], true)
            && ! (bool) data_get($attempt->payload, 'is_guarantee', false);

        return [
            'verified' => (bool) $verified,
            'transaction_number' => $attempt ? 'TX-' . str_pad((string) $attempt->id, 6, '0', STR_PAD_LEFT) : null,
            'paid_at' => $verified ? $attempt->updated_at?->toIso8601String() : null,
            'status' => $attempt?->status ?? 'unknown',
            'is_guarantee' => (bool) data_get($attempt?->payload, 'is_guarantee', false),
            'reference' => $attempt?->provider_reference,
            'method' => $attempt?->provider,
            'recorded_at' => $receipt->issued_at?->toIso8601String(),
            'amount' => (string) $receipt->amount,
            'currency' => $receipt->currency,
        ];
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
            ->where(fn ($query) => $query->where('role', '!=', 'host')->orWhereHas('establishments', fn ($establishmentQuery) => $establishmentQuery->where('establishments.id', $reservation->property->establishment_id)))
            ->get();

        foreach ($recipients as $recipient) {
            $locale = $recipient->locale ?: 'fr';

            EmailOutbox::query()->create([
                'template' => 'payment_proof_submitted',
                'recipient_email' => $recipient->email,
                'status' => 'queued',
                'payload' => [
                    'locale' => $locale,
                    'recipient_name' => $recipient->name,
                    'subject' => __('messages.receipts.proof_notification_subject', ['reference' => $reservation->reservation_ref], $locale),
                    'reservation_ref' => $reservation->reservation_ref,
                    'property_name' => $reservation->property?->localized('name', $locale) ?? __('messages.checkout.property', [], $locale),
                    'provider' => $attempt->provider,
                    'amount' => (string) $attempt->amount,
                    'currency' => $attempt->currency,
                    'reservation_url' => route('admin.reservations.show', $reservation),
                ],
            ]);
        }
    }
}
