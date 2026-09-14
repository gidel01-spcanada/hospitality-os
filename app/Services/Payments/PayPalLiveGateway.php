<?php

namespace App\Services\Payments;

use App\Contracts\PaymentGateway;
use App\Models\PaymentAttempt;
use App\Models\Reservation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class PayPalLiveGateway implements PaymentGateway
{
    public function name(): string
    {
        return 'paypal';
    }

    public function createIntent(Reservation $reservation, array $context = []): PaymentAttempt
    {
        $token = $this->accessToken();
        $rawAmount = $context['amount'] ?? $reservation->total_amount;
        $paypalCurrency = $context['currency'] ?? (in_array($reservation->currency, ['EUR', 'USD', 'GBP', 'CAD', 'AUD', 'CHF'], true) ? $reservation->currency : 'EUR');
        $paypalAmount = (float) $rawAmount;
        $isGuarantee = (bool) ($context['is_guarantee'] ?? false);
        $intent = $isGuarantee ? 'AUTHORIZE' : 'CAPTURE';

        $response = Http::acceptJson()
            ->withToken($token)
            ->withHeaders(['PayPal-Request-Id' => $context['idempotency_key'] ?? Str::uuid()->toString(), 'Prefer' => 'return=representation'])
            ->post($this->baseUrl().'/v2/checkout/orders', [
                'intent' => $intent,
                'purchase_units' => [[
                    'reference_id' => $reservation->reservation_ref,
                    'amount' => ['currency_code' => $paypalCurrency, 'value' => number_format($paypalAmount, 2, '.', '')],
                ]],
            ]);
        $response->throw();
        $order = $response->json();
        $approvalUrl = collect($order['links'] ?? [])->first(fn ($link) => in_array($link['rel'] ?? '', ['payer-action', 'approve'], true))['href'] ?? null;

        return PaymentAttempt::query()->create([
            'reservation_id' => $reservation->id,
            'provider' => $this->name(),
            'provider_reference' => $order['id'],
            'currency' => $paypalCurrency,
            'amount' => $paypalAmount,
            'status' => $isGuarantee ? 'authorized' : 'pending',
            'idempotency_key' => $context['idempotency_key'] ?? Str::uuid()->toString(),
            'payload' => [
                'mode' => 'production',
                'redirect_url' => $approvalUrl,
                'order_status' => $order['status'] ?? null,
                'is_guarantee' => $isGuarantee,
            ],
        ]);
    }

    public function verifyStatus(PaymentAttempt $attempt): PaymentAttempt
    {
        $response = Http::acceptJson()->withToken($this->accessToken())
            ->post($this->baseUrl().'/v2/checkout/orders/'.$attempt->provider_reference.'/capture', []);

        if ($response->successful()) {
            $order = $response->json();
            $status = ($order['status'] ?? null) === 'COMPLETED' ? 'paid' : 'pending';
            $attempt->update(['status' => $status, 'payload' => array_merge((array) $attempt->payload, ['capture' => $order])]);
        } else {
            $checkResponse = Http::acceptJson()->withToken($this->accessToken())
                ->get($this->baseUrl().'/v2/checkout/orders/'.$attempt->provider_reference);
            if ($checkResponse->successful()) {
                $order = $checkResponse->json();
                $status = ($order['status'] ?? null) === 'COMPLETED' ? 'paid' : $attempt->status;
                $attempt->update(['status' => $status, 'payload' => array_merge((array) $attempt->payload, ['check' => $order])]);
            }
        }

        return $attempt->fresh();
    }

    public function handleWebhook(Request $request): ?PaymentAttempt
    {
        $orderId = data_get($request->json()->all(), 'resource.supplementary_data.related_ids.order_id') ?? data_get($request->json()->all(), 'resource.id');
        $attempt = PaymentAttempt::query()->where('provider', $this->name())->where('provider_reference', $orderId)->first();
        if ($attempt) {
            $attempt->update(['status' => data_get($request->json()->all(), 'event_type') === 'PAYMENT.CAPTURE.COMPLETED' ? 'paid' : $attempt->status]);
        }
        return $attempt;
    }

    public function cancel(PaymentAttempt $attempt, string $reason = 'cancelled'): PaymentAttempt { $attempt->update(['status' => 'cancelled']); return $attempt; }
    public function refund(PaymentAttempt $attempt, string $reason = 'requested'): PaymentAttempt { throw new RuntimeException('Refunds must be initiated from the PayPal dashboard until refund support is configured.'); }

    private function accessToken(): string
    {
        $response = Http::asForm()->withBasicAuth((string) config('services.paypal.client_id'), (string) config('services.paypal.client_secret'))
            ->post($this->baseUrl().'/v1/oauth2/token', ['grant_type' => 'client_credentials']);
        $response->throw();
        return (string) $response->json('access_token');
    }

    private function baseUrl(): string { return rtrim((string) config('services.paypal.base_url'), '/'); }
}
