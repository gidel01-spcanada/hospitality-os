<?php

namespace App\Services\Payments;

use App\Contracts\PaymentGateway;
use App\Models\PaymentAttempt;
use App\Models\Reservation;
use App\Support\BrandSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PayPalSandboxGateway implements PaymentGateway
{
    public function name(): string
    {
        return 'paypal';
    }

    public function createIntent(Reservation $reservation, array $context = []): PaymentAttempt
    {
        $xofPerEur = (float) BrandSettings::get('eur_to_xof_rate', 655.957);
        $paypalCurrency = in_array($reservation->currency, ['EUR', 'USD', 'GBP', 'CAD', 'AUD', 'CHF'], true) ? $reservation->currency : 'EUR';
        $paypalAmount = $reservation->currency === 'XOF' ? round((float) $reservation->total_amount / $xofPerEur, 2) : (float) $reservation->total_amount;

        return PaymentAttempt::query()->create([
            'reservation_id' => $reservation->id,
            'provider' => $this->name(),
            'provider_reference' => 'paypal-sandbox-' . Str::upper(Str::random(12)),
            'currency' => $paypalCurrency,
            'amount' => $paypalAmount,
            'status' => 'created',
            'idempotency_key' => $context['idempotency_key'] ?? Str::uuid()->toString(),
            'payload' => [
                'environment' => config('services.paypal.environment', 'sandbox'),
                'mode' => $context['mode'] ?? 'sandbox',
                'reservation_ref' => $reservation->reservation_ref,
            ],
        ]);
    }

    public function verifyStatus(PaymentAttempt $attempt): PaymentAttempt
    {
        $attempt->status = 'paid';
        $attempt->save();

        return $attempt;
    }

    public function handleWebhook(Request $request): ?PaymentAttempt
    {
        $reference = (string) ($request->input('resource.id') ?? $request->input('provider_reference') ?? $request->input('reference') ?? '');
        if ($reference === '') {
            return null;
        }

        $attempt = PaymentAttempt::query()->where('provider_reference', $reference)->first();
        if (! $attempt) {
            return null;
        }

        $status = $request->input('event', 'paid');
        if (! in_array($status, ['paid', 'completed', 'failed', 'cancelled', 'pending'], true)) {
            return null;
        }

        if (in_array($attempt->status, ['paid', 'completed'], true) && $status !== 'paid' && $status !== 'completed') {
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
