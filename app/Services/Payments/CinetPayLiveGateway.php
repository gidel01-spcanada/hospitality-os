<?php

namespace App\Services\Payments;

use App\Contracts\PaymentGateway;
use App\Models\PaymentAttempt;
use App\Models\Reservation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class CinetPayLiveGateway implements PaymentGateway
{
    public function name(): string
    {
        return 'cinetpay';
    }

    public function createIntent(Reservation $reservation, array $context = []): PaymentAttempt
    {
        if (! in_array($reservation->currency, ['XOF', 'XAF'], true)) {
            throw new RuntimeException('CinetPay is available only for XOF and XAF payments.');
        }

        $rawAmount = $context['amount'] ?? $reservation->total_amount;
        $isGuarantee = (bool) ($context['is_guarantee'] ?? false);
        $transactionId = Str::upper($reservation->reservation_ref . '-' . Str::random(10));
        $response = Http::acceptJson()->post($this->baseUrl().'/payment', [
            'apikey' => config('services.cinetpay.api_key'),
            'site_id' => config('services.cinetpay.site_id'),
            'transaction_id' => $transactionId,
            'amount' => (int) round((float) $rawAmount),
            'currency' => $reservation->currency,
            'description' => $isGuarantee ? 'Garantie d\'annulation '.$reservation->reservation_ref : 'Reservation '.$reservation->reservation_ref,
            'return_url' => $context['return_url'],
            'notify_url' => $context['webhook_url'],
            'customer_email' => $reservation->email,
            'customer_name' => $reservation->guest?->full_name,
            'metadata' => $reservation->id,
        ]);
        $response->throw();
        $payload = $response->json();
        $paymentUrl = data_get($payload, 'data.payment_url');

        if (! $paymentUrl) {
            throw new RuntimeException('CinetPay did not return a payment URL.');
        }

        return PaymentAttempt::query()->create([
            'reservation_id' => $reservation->id,
            'provider' => $this->name(),
            'provider_reference' => (string) $transactionId,
            'currency' => $reservation->currency,
            'amount' => $rawAmount,
            'status' => $isGuarantee ? 'authorized' : 'pending',
            'idempotency_key' => $context['idempotency_key'] ?? Str::uuid()->toString(),
            'payload' => ['mode' => 'production', 'redirect_url' => $paymentUrl, 'is_guarantee' => $isGuarantee],
        ]);
    }
            'provider_reference' => $transactionId,
            'currency' => $reservation->currency,
            'amount' => $reservation->total_amount,
            'status' => 'pending',
            'idempotency_key' => $context['idempotency_key'] ?? Str::uuid()->toString(),
            'payload' => ['mode' => 'production', 'redirect_url' => $paymentUrl, 'create_response' => $payload],
        ]);
    }

    public function verifyStatus(PaymentAttempt $attempt): PaymentAttempt
    {
        $response = Http::acceptJson()->post($this->baseUrl().'/payment/check', [
            'apikey' => config('services.cinetpay.api_key'),
            'site_id' => config('services.cinetpay.site_id'),
            'transaction_id' => $attempt->provider_reference,
        ]);
        $response->throw();
        $payload = $response->json();
        $status = data_get($payload, 'data.status');

        $attempt->update([
            'status' => $status === 'ACCEPTED' ? 'paid' : (in_array($status, ['REFUSED', 'CANCELLED'], true) ? 'failed' : 'pending'),
            'payload' => array_merge((array) $attempt->payload, ['check_response' => $payload]),
        ]);

        return $attempt->fresh();
    }

    public function handleWebhook(Request $request): ?PaymentAttempt
    {
        $transactionId = (string) $request->input('transaction_id', '');
        $attempt = PaymentAttempt::query()->where('provider', $this->name())->where('provider_reference', $transactionId)->first();

        return $attempt ? $this->verifyStatus($attempt) : null;
    }

    public function cancel(PaymentAttempt $attempt, string $reason = 'cancelled'): PaymentAttempt
    {
        $attempt->update(['status' => 'cancelled']);

        return $attempt;
    }

    public function refund(PaymentAttempt $attempt, string $reason = 'requested'): PaymentAttempt
    {
        throw new RuntimeException('Refunds must be initiated from the CinetPay dashboard until refund support is configured.');
    }

    private function baseUrl(): string
    {
        return rtrim((string) config('services.cinetpay.base_url'), '/');
    }
}
