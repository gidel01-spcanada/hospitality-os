<?php

namespace App\Services\Payments;

use App\Contracts\PaymentGateway;
use App\Models\PaymentAttempt;
use App\Models\Reservation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class MpesaLiveGateway implements PaymentGateway
{
    public function name(): string
    {
        return 'mpesa';
    }

    public function createIntent(Reservation $reservation, array $context = []): PaymentAttempt
    {
        if ($reservation->currency !== 'KES') {
            throw new RuntimeException('M-Pesa is available only for KES payments.');
        }

        $phone = $this->normalizePhone((string) ($reservation->guest?->phone ?? ''));
        if (! $phone) {
            throw new RuntimeException('A valid Kenyan mobile number is required for M-Pesa.');
        }

        $rawAmount = $context['amount'] ?? $reservation->total_amount;
        $isGuarantee = (bool) ($context['is_guarantee'] ?? false);
        $timestamp = now()->format('YmdHis');
        $shortcode = (string) config('services.mpesa.shortcode');
        $password = base64_encode($shortcode . config('services.mpesa.passkey') . $timestamp);
        $response = Http::acceptJson()->withToken($this->accessToken())
            ->post($this->baseUrl().'/mpesa/stkpush/v1/processrequest', [
                'BusinessShortCode' => $shortcode,
                'Password' => $password,
                'Timestamp' => $timestamp,
                'TransactionType' => 'CustomerPayBillOnline',
                'Amount' => (int) round((float) $rawAmount),
                'PartyA' => $phone,
                'PartyB' => $shortcode,
                'PhoneNumber' => $phone,
                'CallBackURL' => $context['webhook_url'],
                'AccountReference' => $reservation->reservation_ref,
                'TransactionDesc' => $isGuarantee ? 'Garantie d\'annulation '.$reservation->reservation_ref : 'Reservation '.$reservation->reservation_ref,
            ]);
        $response->throw();
        $payload = $response->json();
        $checkoutRequestId = $payload['CheckoutRequestID'] ?? null;
        if (! $checkoutRequestId) {
            throw new RuntimeException((string) ($payload['errorMessage'] ?? 'M-Pesa did not return a checkout request ID.'));
        }

        return PaymentAttempt::query()->create([
            'reservation_id' => $reservation->id,
            'provider' => $this->name(),
            'provider_reference' => $checkoutRequestId,
            'currency' => 'KES',
            'amount' => $rawAmount,
            'status' => $isGuarantee ? 'authorized' : 'pending',
            'idempotency_key' => $context['idempotency_key'] ?? Str::uuid()->toString(),
            'payload' => ['mode' => 'production', 'is_guarantee' => $isGuarantee],
        ]);
    }
            'status' => 'pending',
            'idempotency_key' => $context['idempotency_key'] ?? Str::uuid()->toString(),
            'payload' => ['mode' => 'production', 'merchant_request_id' => $payload['MerchantRequestID'] ?? null, 'phone' => $phone],
        ]);
    }

    public function verifyStatus(PaymentAttempt $attempt): PaymentAttempt
    {
        $timestamp = now()->format('YmdHis');
        $shortcode = (string) config('services.mpesa.shortcode');
        $response = Http::acceptJson()->withToken($this->accessToken())
            ->post($this->baseUrl().'/mpesa/stkpushquery/v1/query', [
                'BusinessShortCode' => $shortcode,
                'Password' => base64_encode($shortcode . config('services.mpesa.passkey') . $timestamp),
                'Timestamp' => $timestamp,
                'CheckoutRequestID' => $attempt->provider_reference,
            ]);
        $response->throw();
        $payload = $response->json();
        $resultCode = (int) ($payload['ResultCode'] ?? -1);
        $status = $resultCode === 0 ? 'paid' : (in_array($resultCode, [1032, 1037], true) ? 'cancelled' : 'pending');
        $attempt->update(['status' => $status, 'payload' => array_merge((array) $attempt->payload, ['query_response' => $payload])]);

        return $attempt->fresh();
    }

    public function handleWebhook(Request $request): ?PaymentAttempt
    {
        $callback = data_get($request->all(), 'Body.stkCallback', []);
        $checkoutRequestId = $callback['CheckoutRequestID'] ?? null;
        $attempt = PaymentAttempt::query()->where('provider', $this->name())->where('provider_reference', $checkoutRequestId)->first();
        if (! $attempt) {
            return null;
        }

        $attempt->update(['payload' => array_merge((array) $attempt->payload, ['callback' => $callback])]);

        return $this->verifyStatus($attempt->fresh());
    }

    public function cancel(PaymentAttempt $attempt, string $reason = 'cancelled'): PaymentAttempt
    {
        $attempt->update(['status' => 'cancelled']);
        return $attempt;
    }

    public function refund(PaymentAttempt $attempt, string $reason = 'requested'): PaymentAttempt
    {
        throw new RuntimeException('Refunds must be initiated from the M-Pesa business portal until reversal support is configured.');
    }

    private function accessToken(): string
    {
        $response = Http::withBasicAuth((string) config('services.mpesa.consumer_key'), (string) config('services.mpesa.consumer_secret'))
            ->get($this->baseUrl().'/oauth/v1/generate?grant_type=client_credentials');
        $response->throw();
        return (string) $response->json('access_token');
    }

    private function normalizePhone(string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', $phone);
        if (str_starts_with($digits, '0')) {
            $digits = '254' . substr($digits, 1);
        } elseif (str_starts_with($digits, '7') || str_starts_with($digits, '1')) {
            $digits = '254' . $digits;
        }

        return preg_match('/^254[17]\d{8}$/', $digits) ? $digits : null;
    }

    private function baseUrl(): string
    {
        return rtrim((string) config('services.mpesa.base_url'), '/');
    }
}
