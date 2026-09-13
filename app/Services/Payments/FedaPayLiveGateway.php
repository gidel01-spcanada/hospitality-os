<?php

namespace App\Services\Payments;

use App\Contracts\PaymentGateway;
use App\Models\PaymentAttempt;
use App\Models\Reservation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class FedaPayLiveGateway implements PaymentGateway
{
    public function name(): string { return 'fedapay'; }

    public function createIntent(Reservation $reservation, array $context = []): PaymentAttempt
    {
        $rawAmount = $context['amount'] ?? $reservation->total_amount;
        $isGuarantee = (bool) ($context['is_guarantee'] ?? false);

        $transactionResponse = Http::acceptJson()->withToken((string) config('services.fedapay.secret_key'))
            ->post($this->baseUrl().'/transactions', [
                'description' => $isGuarantee ? 'Garantie d\'annulation '.$reservation->reservation_ref : 'Reservation '.$reservation->reservation_ref,
                'amount' => (int) round((float) $rawAmount),
                'currency' => ['iso' => 'XOF'],
                'callback_url' => $context['webhook_url'],
                'custom_metadata' => ['reservation_id' => $reservation->id, 'reservation_ref' => $reservation->reservation_ref, 'is_guarantee' => $isGuarantee],
            ]);
        $transactionResponse->throw();
        $transaction = $transactionResponse->json();
        $transactionId = $transaction['id'] ?? null;
        if (! $transactionId) { throw new RuntimeException('FedaPay did not return a transaction ID.'); }

        $tokenResponse = Http::acceptJson()->withToken((string) config('services.fedapay.secret_key'))
            ->post($this->baseUrl().'/transactions/'.$transactionId.'/token');
        $tokenResponse->throw();
        $paymentUrl = $tokenResponse->json('url');
        if (! $paymentUrl) { throw new RuntimeException('FedaPay did not return a payment URL.'); }

        return PaymentAttempt::query()->create([
            'reservation_id' => $reservation->id,
            'provider' => $this->name(),
            'provider_reference' => (string) ($transaction['reference'] ?? $transactionId),
            'currency' => 'XOF', 'amount' => $rawAmount, 'status' => $isGuarantee ? 'authorized' : 'pending',
            'idempotency_key' => $context['idempotency_key'] ?? Str::uuid()->toString(),
            'payload' => ['mode' => 'production', 'transaction_id' => $transactionId, 'redirect_url' => $paymentUrl, 'is_guarantee' => $isGuarantee],
        ]);
    }

    public function verifyStatus(PaymentAttempt $attempt): PaymentAttempt
    {
        $transactionId = $attempt->payload['transaction_id'] ?? null;
        if (! $transactionId) { throw new RuntimeException('Missing FedaPay transaction ID.'); }
        $response = Http::acceptJson()->withToken((string) config('services.fedapay.secret_key'))->get($this->baseUrl().'/transactions/'.$transactionId);
        $response->throw();
        $transaction = $response->json();
        $status = in_array($transaction['status'] ?? '', ['approved', 'completed'], true) ? 'paid' : 'pending';
        $attempt->update(['status' => $status, 'payload' => array_merge((array) $attempt->payload, ['transaction' => $transaction])]);
        return $attempt->fresh();
    }

    public function handleWebhook(Request $request): ?PaymentAttempt
    {
        $payload = $request->all();
        $reference = data_get($payload, 'reference') ?? data_get($payload, 'transaction.reference');
        $attempt = PaymentAttempt::query()->where('provider', $this->name())->where('provider_reference', $reference)->first();
        if ($attempt && in_array(data_get($payload, 'status', data_get($payload, 'transaction.status')), ['approved', 'completed'], true)) { $attempt->update(['status' => 'paid']); }
        return $attempt;
    }

    public function cancel(PaymentAttempt $attempt, string $reason = 'cancelled'): PaymentAttempt { $attempt->update(['status' => 'cancelled']); return $attempt; }
    public function refund(PaymentAttempt $attempt, string $reason = 'requested'): PaymentAttempt { throw new RuntimeException('Refunds must be initiated from the FedaPay dashboard until refund support is configured.'); }
    private function baseUrl(): string { return rtrim((string) config('services.fedapay.base_url'), '/'); }
}
