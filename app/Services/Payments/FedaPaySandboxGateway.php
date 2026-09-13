<?php

namespace App\Services\Payments;

use App\Contracts\PaymentGateway;
use App\Models\PaymentAttempt;
use App\Models\Reservation;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class FedaPaySandboxGateway implements PaymentGateway
{
    public function name(): string
    {
        return 'fedapay';
    }

    public function createIntent(Reservation $reservation, array $context = []): PaymentAttempt
    {
        $rawAmount = $context['amount'] ?? $reservation->total_amount;
        $isGuarantee = (bool) ($context['is_guarantee'] ?? false);

        return PaymentAttempt::query()->create([
            'reservation_id' => $reservation->id,
            'provider' => $this->name(),
            'provider_reference' => 'fedapay-sandbox-' . Str::upper(Str::random(12)),
            'currency' => 'XOF',
            'amount' => $rawAmount,
            'status' => $isGuarantee ? 'authorized' : 'created',
            'idempotency_key' => $context['idempotency_key'] ?? Str::uuid()->toString(),
            'payload' => [
                'environment' => config('services.fedapay.environment', 'sandbox'),
                'mode' => $context['mode'] ?? 'sandbox',
                'reservation_ref' => $reservation->reservation_ref,
                'is_guarantee' => $isGuarantee,
            ],
        ]);
    }

    public function verifyStatus(PaymentAttempt $attempt): PaymentAttempt
    {
        $attempt->status = $attempt->status === 'paid' ? 'paid' : 'pending';
        $attempt->save();

        return $attempt;
    }

    public function handleWebhook(Request $request): ?PaymentAttempt
    {
        $reference = (string) ($request->input('reference') ?? $request->input('provider_reference') ?? '');
        if ($reference === '') {
            return null;
        }

        $attempt = PaymentAttempt::query()->where('provider_reference', $reference)->first();
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
