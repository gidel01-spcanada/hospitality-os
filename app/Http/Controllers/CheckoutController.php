<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use App\Services\Payments\PaymentGatewayManager;
use App\Services\ReservationEmailService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\View\View;

class CheckoutController extends Controller
{
    public function show(Request $request, Reservation $reservation): View
    {
        $token = $this->authorizeCheckout($request, $reservation);
        $reservation->load(['property.establishment', 'guest', 'paymentAttempts']);
        $paymentMethods = $this->paymentMethods($reservation);
        $isDevEnvironment = app()->environment(['local', 'testing']);

        return view('checkout.show', compact('reservation', 'paymentMethods', 'token', 'isDevEnvironment'));
    }

    public function start(Request $request, Reservation $reservation, PaymentGatewayManager $manager): RedirectResponse
    {
        $token = $this->authorizeCheckout($request, $reservation);
        $validated = $request->validate([
            'provider' => ['required', 'string', 'in:pay_later,fedapay,paypal,cinetpay,mpesa'],
            'guarantee_provider' => ['nullable', 'string', 'in:fedapay,paypal,cinetpay,mpesa'],
        ]);

        $provider = $validated['provider'];
        $paymentMethods = $this->paymentMethods($reservation);
        abort_unless(in_array($provider, array_keys($paymentMethods), true), 422, 'This payment method is not available for this establishment.');

        $establishment = $reservation->property?->establishment;
        $cancellationFeePercent = (float) ($establishment?->cancellation_fee_percent ?? 0);
        $cancellationFeeHoldAmount = $establishment?->cancellationFeeHoldAmount($reservation) ?? 0.0;
        $onlineMethods = collect($paymentMethods)->except('pay_later')->all();

        if ($provider === 'pay_later' && $cancellationFeePercent > 0 && $cancellationFeeHoldAmount > 0 && ! empty($onlineMethods)) {
            $guaranteeProvider = $validated['guarantee_provider'] ?? array_key_first($onlineMethods);
            abort_unless(in_array($guaranteeProvider, array_keys($onlineMethods), true), 422, 'An online guarantee payment method is required for on-site payment.');

            $guaranteeGateway = $manager->resolve($guaranteeProvider, $onlineMethods[$guaranteeProvider]['mode']);
            $guaranteeAttempt = $guaranteeGateway->createIntent($reservation, [
                'idempotency_key' => $reservation->reservation_ref . '-guarantee-' . $guaranteeProvider . '-' . time(),
                'mode' => $onlineMethods[$guaranteeProvider]['mode'],
                'is_guarantee' => true,
                'amount' => $cancellationFeeHoldAmount,
                'return_url' => route('checkout.return', ['reservation' => $reservation, 'provider' => $guaranteeProvider, 'token' => $token]),
                'cancel_url' => route('checkout.cancel', ['reservation' => $reservation, 'provider' => $guaranteeProvider, 'token' => $token]),
                'webhook_url' => route('checkout.webhook', ['provider' => $guaranteeProvider]),
            ]);

            $payLaterGateway = $manager->resolve('pay_later');
            $attempt = $payLaterGateway->createIntent($reservation, [
                'idempotency_key' => $reservation->reservation_ref . '-pay_later-' . time(),
                'mode' => 'production',
            ]);

            $attempt->update([
                'payload' => array_merge((array) $attempt->payload, [
                    'guarantee_provider' => $guaranteeProvider,
                    'guarantee_amount' => $cancellationFeeHoldAmount,
                    'guarantee_attempt_id' => $guaranteeAttempt->id,
                    'guarantee_status' => $guaranteeAttempt->status,
                ]),
            ]);

            $reservation->update(['status' => 'pending_payment']);

            if ($redirectUrl = $guaranteeAttempt->payload['redirect_url'] ?? null) {
                return redirect()->away($redirectUrl);
            }

            return redirect()->route('checkout.show', ['reservation' => $reservation, 'token' => $token])->with(
                'status',
                __('messages.checkout.pay_later_guarantee_authorized', [
                    'amount' => number_format($cancellationFeeHoldAmount, 0, ',', ' '),
                    'currency' => $reservation->currency,
                ])
            );
        }

        $gateway = $manager->resolve($provider, $paymentMethods[$provider]['mode']);
        $attempt = $gateway->createIntent($reservation, [
            'idempotency_key' => $reservation->reservation_ref . '-' . $provider,
            'mode' => $paymentMethods[$provider]['mode'],
            'return_url' => route('checkout.return', ['reservation' => $reservation, 'provider' => $provider, 'token' => $token]),
            'cancel_url' => route('checkout.cancel', ['reservation' => $reservation, 'provider' => $provider, 'token' => $token]),
            'webhook_url' => route('checkout.webhook', ['provider' => $provider]),
        ]);

        $reservation->update(['status' => 'pending_payment']);

        if ($redirectUrl = $attempt->payload['redirect_url'] ?? null) {
            return redirect()->away($redirectUrl);
        }

        return redirect()->route('checkout.show', ['reservation' => $reservation, 'token' => $token])->with(
            'status',
            __('messages.flash.payment_started', ['gateway' => $gateway->name()])
        );
    }

    private function paymentMethods(Reservation $reservation): array
    {
        $configured = $reservation->property?->establishment?->payment_methods ?? [];
        $defaults = [
            'pay_later' => ['enabled' => true, 'mode' => 'production', 'instructions' => ''],
            'fedapay' => ['enabled' => false, 'mode' => 'sandbox', 'instructions' => ''],
            'paypal' => ['enabled' => false, 'mode' => 'sandbox', 'instructions' => ''],
            'cinetpay' => ['enabled' => false, 'mode' => 'sandbox', 'instructions' => ''],
            'mpesa' => ['enabled' => false, 'mode' => 'sandbox', 'instructions' => ''],
        ];

        return collect($defaults)->mapWithKeys(function (array $default, string $provider) use ($configured, $reservation) {
            $method = array_merge($default, $configured[$provider] ?? []);

            if (! $method['enabled']) {
                return [];
            }

            if ($provider === 'mpesa' && $reservation->currency !== 'KES') {
                return [];
            }

            if ($provider === 'fedapay' && $reservation->currency !== 'XOF') {
                return [];
            }

            return [$provider => $method];
        })->all();
    }

    public function complete(Request $request, Reservation $reservation, PaymentGatewayManager $manager, ReservationEmailService $emailService): RedirectResponse
    {
        $token = $this->authorizeCheckout($request, $reservation);
        $attempt = $reservation->paymentAttempts()->latest()->firstOrFail();
        // Offline (pay_later) has no external status to verify; only an admin-confirmed receipt marks it paid outside dev.
        abort_if($attempt->provider === 'pay_later' && ! app()->environment(['local', 'testing']), 403, __('messages.checkout.simulate_unavailable'));
        $gateway = $manager->resolve($attempt->provider, $attempt->payload['mode'] ?? 'sandbox');
        $attempt = $gateway->verifyStatus($attempt);

        if (! in_array($attempt->status, ['paid', 'completed'], true)) {
            return redirect()->route('checkout.show', ['reservation' => $reservation, 'token' => $token])
                ->with('error', 'Payment is still awaiting verification.');
        }

        $attempt->update([
            'status' => 'paid',
            'payload' => array_merge((array) $attempt->payload, ['verified_at' => now()->toIso8601String()]),
        ]);

        $reservation->update([
            'status' => 'confirmed',
            'notes' => trim(($reservation->notes ?? '') . PHP_EOL . 'Payment verified and reservation confirmed.'),
        ]);

        $emailService->queueStatusUpdate($reservation, 'confirmed');
        $emailService->issueReceiptAndQueueEmail($reservation, $attempt);

        return redirect()->route('checkout.show', ['reservation' => $reservation, 'token' => $token])->with('status', __('messages.flash.payment_verified'));
    }

    public function cancel(Request $request, Reservation $reservation, ReservationEmailService $emailService): RedirectResponse
    {
        $token = $this->authorizeCheckout($request, $reservation);
        abort_unless(in_array($reservation->status, ['pending', 'pending_payment', 'payment_failed'], true), 422, __('messages.checkout.cancellation_unavailable'));

        $reservation->update(['status' => 'cancelled']);

        $cancellationFee = $reservation->property?->establishment?->cancellationFeeFor($reservation) ?? 0.0;

        if ($cancellationFee > 0) {
            $reservation->priceLines()->create([
                'label' => __('messages.checkout.cancellation_fee'),
                'amount' => $cancellationFee,
                'currency' => $reservation->currency,
            ]);
        }

        $emailService->queueStatusUpdate($reservation, 'cancelled');

        return redirect()->route('checkout.show', ['reservation' => $reservation, 'token' => $token])
            ->with('status', $cancellationFee > 0
                ? __('messages.checkout.cancelled_with_fee', ['amount' => number_format($cancellationFee, 0, ',', ' '), 'currency' => $reservation->currency])
                : __('messages.checkout.cancelled'));
    }

    public function return(Request $request, Reservation $reservation, string $provider, PaymentGatewayManager $manager, ReservationEmailService $emailService): RedirectResponse
    {
        $token = $this->authorizeCheckout($request, $reservation);
        $attempt = $reservation->paymentAttempts()->where('provider', $provider)->latest()->firstOrFail();
        $attempt = $manager->resolve($provider, $attempt->payload['mode'] ?? 'sandbox')->verifyStatus($attempt);

        if (in_array($attempt->status, ['paid', 'completed'], true)) {
            $reservation->update(['status' => 'confirmed']);
            $emailService->queueStatusUpdate($reservation, 'confirmed');
            $emailService->issueReceiptAndQueueEmail($reservation, $attempt);

            return redirect()->route('checkout.show', ['reservation' => $reservation, 'token' => $token])->with('status', __('messages.flash.payment_verified'));
        }

        return redirect()->route('checkout.show', ['reservation' => $reservation, 'token' => $token])->with('error', 'Payment is awaiting confirmation.');
    }

    public function createPayPalOrder(Request $request, Reservation $reservation, PaymentGatewayManager $manager)
    {
        $token = $this->authorizeCheckout($request, $reservation);
        $paymentMethods = $this->paymentMethods($reservation);

        abort_unless(isset($paymentMethods['paypal']), 422, 'PayPal is not available for this establishment.');

        $isGuarantee = $request->boolean('is_guarantee');
        $establishment = $reservation->property?->establishment;
        $amount = $isGuarantee ? ($establishment?->cancellationFeeHoldAmount($reservation) ?? $reservation->total_amount) : $reservation->total_amount;

        $gateway = $manager->resolve('paypal', $paymentMethods['paypal']['mode']);
        $attempt = $gateway->createIntent($reservation, [
            'idempotency_key' => $reservation->reservation_ref . '-paypal-' . ($isGuarantee ? 'guarantee-' : '') . time(),
            'mode' => $paymentMethods['paypal']['mode'],
            'is_guarantee' => $isGuarantee,
            'amount' => $amount,
            'return_url' => route('checkout.return', ['reservation' => $reservation, 'provider' => 'paypal', 'token' => $token]),
            'cancel_url' => route('checkout.cancel', ['reservation' => $reservation, 'provider' => 'paypal', 'token' => $token]),
        ]);

        $reservation->update(['status' => 'pending_payment']);

        return response()->json([
            'orderID' => $attempt->provider_reference,
            'attempt_id' => $attempt->id,
            'is_guarantee' => $isGuarantee,
        ]);
    }

    public function capturePayPalOrder(Request $request, Reservation $reservation, PaymentGatewayManager $manager, ReservationEmailService $emailService)
    {
        $token = $this->authorizeCheckout($request, $reservation);
        $paymentMethods = $this->paymentMethods($reservation);

        abort_unless(isset($paymentMethods['paypal']), 422, 'PayPal is not available for this establishment.');

        $orderID = (string) $request->input('orderID');
        $attempt = $reservation->paymentAttempts()
            ->where('provider', 'paypal')
            ->when($orderID !== '', fn ($query) => $query->where('provider_reference', $orderID))
            ->latest()
            ->first();

        if (! $attempt) {
            $gateway = $manager->resolve('paypal', $paymentMethods['paypal']['mode']);
            $attempt = $gateway->createIntent($reservation, [
                'idempotency_key' => $reservation->reservation_ref . '-paypal-' . time(),
                'mode' => $paymentMethods['paypal']['mode'],
            ]);
        }

        $gateway = $manager->resolve('paypal', $paymentMethods['paypal']['mode']);
        $attempt = $gateway->verifyStatus($attempt);

        if (in_array($attempt->status, ['paid', 'completed', 'authorized'], true)) {
            $isGuarantee = (bool) ($attempt->payload['is_guarantee'] ?? false);

            if ($isGuarantee) {
                $payLaterGateway = $manager->resolve('pay_later');
                $payLaterAttempt = $payLaterGateway->createIntent($reservation, [
                    'idempotency_key' => $reservation->reservation_ref . '-pay_later-' . time(),
                    'mode' => 'production',
                ]);
                $payLaterAttempt->update([
                    'payload' => array_merge((array) $payLaterAttempt->payload, [
                        'guarantee_provider' => 'paypal',
                        'guarantee_amount' => $attempt->amount,
                        'guarantee_attempt_id' => $attempt->id,
                        'guarantee_status' => $attempt->status,
                    ]),
                ]);

                $reservation->update([
                    'status' => 'pending_payment',
                    'notes' => trim(($reservation->notes ?? '') . PHP_EOL . 'Cancellation guarantee hold authorized via PayPal Smart Buttons.'),
                ]);
            } else {
                $reservation->update([
                    'status' => 'confirmed',
                    'notes' => trim(($reservation->notes ?? '') . PHP_EOL . 'Payment verified via PayPal Smart Buttons.'),
                ]);

                $emailService->queueStatusUpdate($reservation, 'confirmed');
                $emailService->issueReceiptAndQueueEmail($reservation, $attempt);
            }

            return response()->json([
                'status' => 'COMPLETED',
                'redirect_url' => route('checkout.show', ['reservation' => $reservation, 'token' => $token]),
            ]);
        }

        return response()->json(['status' => 'PENDING', 'message' => 'Payment awaiting confirmation.'], 400);
    }

    public function webhook(Request $request, string $provider, PaymentGatewayManager $manager, ReservationEmailService $emailService)
    {
        abort_unless(in_array($provider, ['pay_later', 'fedapay', 'paypal', 'cinetpay', 'mpesa'], true), 404, 'Unsupported payment provider.');

        abort_unless($this->validWebhookSignature($request, $provider), 403, 'Invalid webhook signature.');

        $mode = match ($provider) {
            'fedapay' => config('services.fedapay.environment') === 'production' ? 'production' : 'sandbox',
            'paypal' => config('services.paypal.environment') === 'production' ? 'production' : 'sandbox',
            'cinetpay' => config('services.cinetpay.environment') === 'production' ? 'production' : 'sandbox',
            'mpesa' => config('services.mpesa.environment') === 'production' ? 'production' : 'sandbox',
            default => 'sandbox',
        };
        $attempt = $manager->resolve($provider, $mode)->handleWebhook($request);

        if ($attempt && $attempt->reservation) {
            if (in_array($attempt->status, ['paid', 'completed'], true)) {
                $attempt->reservation->update(['status' => 'confirmed']);
                $emailService->queueStatusUpdate($attempt->reservation, 'confirmed');
                $emailService->issueReceiptAndQueueEmail($attempt->reservation, $attempt);
            } elseif (in_array($attempt->status, ['failed', 'cancelled'], true) && $attempt->reservation->status !== 'confirmed') {
                $attempt->reservation->update(['status' => 'payment_failed']);
                $emailService->queueStatusUpdate($attempt->reservation, 'payment_failed');
            }
        }

        return response()->json(['ok' => true, 'provider' => $provider, 'status' => $attempt?->status ?? 'ignored']);
    }

    private function authorizeCheckout(Request $request, Reservation $reservation): string
    {
        $token = (string) $request->query('token', $request->input('token', ''));
        $owner = $request->user();

        abort_unless(
            ($owner && $reservation->user_id === $owner->id)
                || ($reservation->checkout_token && $token && hash_equals($reservation->checkout_token, $token)),
            403,
            'Checkout access denied.'
        );

        return $token ?: (string) $reservation->checkout_token;
    }

    private function validWebhookSignature(Request $request, string $provider): bool
    {
        if ($provider === 'mpesa' && config('services.mpesa.environment') === 'production') {
            return filled(data_get($request->all(), 'Body.stkCallback.CheckoutRequestID'));
        }

        if ($provider === 'cinetpay' && config('services.cinetpay.environment') === 'production') {
            return filled($request->input('transaction_id'));
        }

        if ($provider === 'paypal' && config('services.paypal.environment') === 'production') {
            $accessToken = Http::asForm()
                ->withBasicAuth((string) config('services.paypal.client_id'), (string) config('services.paypal.client_secret'))
                ->post(rtrim((string) config('services.paypal.base_url'), '/') . '/v1/oauth2/token', ['grant_type' => 'client_credentials']);

            if (! $accessToken->successful() || ! $accessToken->json('access_token')) {
                return false;
            }

            $verification = Http::acceptJson()
                ->withToken($accessToken->json('access_token'))
                ->post(rtrim((string) config('services.paypal.base_url'), '/') . '/v1/notifications/verify-webhook-signature', [
                    'auth_algo' => $request->header('Paypal-Auth-Algo'),
                    'cert_url' => $request->header('Paypal-Cert-Url'),
                    'transmission_id' => $request->header('Paypal-Transmission-Id'),
                    'transmission_sig' => $request->header('Paypal-Transmission-Sig'),
                    'transmission_time' => $request->header('Paypal-Transmission-Time'),
                    'webhook_id' => config('services.paypal.webhook_id'),
                    'webhook_event' => $request->json()->all(),
                ]);

            return $verification->successful() && $verification->json('verification_status') === 'SUCCESS';
        }

        $secret = config('services.payments.webhook_secret');
        $signature = $request->header('X-Webhook-Signature');

        return filled($secret)
            && filled($signature)
            && hash_equals(hash_hmac('sha256', $request->getContent(), $secret), $signature);
    }
}
