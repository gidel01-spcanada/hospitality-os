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
        $response = Http::acceptJson()
            ->withToken($token)
            ->withHeaders(['PayPal-Request-Id' => $context['idempotency_key'] ?? Str::uuid()->toString(), 'Prefer' => 'return=representation'])
            ->post($this->baseUrl().'/v2/checkout/orders', [
                'intent' => 'CAPTURE',
                'purchase_units' => [[
                    'reference_id' => $reservation->reservation_ref,
                    'amount' => ['currency_code' => $reservation->currency, 'value' => number_format((float) $reservation->total_amount, 2, '.', '')],
                ]],
                'payment_source' => ['paypal' => ['experience_context' => [
                    'return_url' => $context['return_url'],
                    'cancel_url' => $context['cancel_url'],
                    'user_action' => 'PAY_NOW',
                ]]],
            ]);
        $response->throw();
        $order = $response->json();
        $approvalUrl = collect($order['links'] ?? [])->first(fn ($link) => in_array($link['rel'] ?? '', ['payer-action', 'approve'], true))['href'] ?? null;
        if (! $approvalUrl) {
            throw new RuntimeException('PayPal did not return an approval URL.');
        }

        return PaymentAttempt::query()->create([
            'reservation_id' => $reservation->id,
            'provider' => $this->name(),
            'provider_reference' => $order['id'],
            'currency' => $reservation->currency,
            'amount' => $reservation->total_amount,
            'status' => 'pending',
            'idempotency_key' => $context['idempotency_key'] ?? Str::uuid()->toString(),
            'payload' => ['mode' => 'production', 'redirect_url' => $approvalUrl, 'order_status' => $order['status'] ?? null],
        ]);
    }

    public function verifyStatus(PaymentAttempt $attempt): PaymentAttempt
    {
        $response = Http::acceptJson()->withToken($this->accessToken())
            ->post($this->baseUrl().'/v2/checkout/orders/'.$attempt->provider_reference.'/capture', []);
        $response->throw();
        $order = $response->json();
        $attempt->update(['status' => ($order['status'] ?? null) === 'COMPLETED' ? 'paid' : 'pending', 'payload' => array_merge((array) $attempt->payload, ['capture' => $order])]);
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
