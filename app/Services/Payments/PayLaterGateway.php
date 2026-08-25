<?php

namespace App\Services\Payments;

use App\Contracts\PaymentGateway;
use App\Models\PaymentAttempt;
use App\Models\Reservation;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PayLaterGateway implements PaymentGateway
{
    public function name(): string
    {
        return 'pay_later';
    }

    public function createIntent(Reservation $reservation, array $context = []): PaymentAttempt
    {
        return PaymentAttempt::query()->create([
            'reservation_id' => $reservation->id,
            'provider' => $this->name(),
            'provider_reference' => 'pay-later-' . Str::upper(Str::random(12)),
            'currency' => $reservation->currency,
            'amount' => $reservation->total_amount,
            'status' => 'created',
            'idempotency_key' => $context['idempotency_key'] ?? Str::uuid()->toString(),
            'payload' => [
                'provider' => $this->name(),
                'mode' => 'manual',
                'reservation_ref' => $reservation->reservation_ref,
                'hold_minutes' => (int) env('PAYMENT_HOLD_MINUTES', 20),
            ],
        ]);
    }

    public function verifyStatus(PaymentAttempt $attempt): PaymentAttempt
    {
        $attempt->status = $attempt->status === 'paid' ? 'paid' : 'created';
        $attempt->save();

        return $attempt;
    }

    public function handleWebhook(Request $request): ?PaymentAttempt
    {
        $providerReference = (string) ($request->input('provider_reference') ?? $request->input('reference') ?? '');
        if ($providerReference === '') {
            return null;
        }

        $attempt = PaymentAttempt::query()->where('provider_reference', $providerReference)->first();
        if (! $attempt) {
            return null;
        }

        $status = $request->input('status', 'paid');
        if (! in_array($status, ['paid', 'failed', 'cancelled', 'pending'], true)) {
            return null;
        }

        if (in_array($attempt->status, ['paid', 'completed'], true) && $status !== 'paid') {
            return $attempt;
        }

        $attempt->status = $status;
        $attempt->payload = array_merge((array) $attempt->payload, $request->all());
        $attempt->save();

        return $attempt;
    }

    public function cancel(PaymentAttempt $attempt, string $reason = 'cancelled'): PaymentAttempt
    {
        $attempt->status = 'cancelled';
        $attempt->payload = array_merge((array) $attempt->payload, ['cancel_reason' => $reason]);
        $attempt->save();

        return $attempt;
    }

    public function refund(PaymentAttempt $attempt, string $reason = 'requested'): PaymentAttempt
    {
        $attempt->status = 'refunded';
        $attempt->payload = array_merge((array) $attempt->payload, ['refund_reason' => $reason]);
        $attempt->save();

        return $attempt;
    }
}
