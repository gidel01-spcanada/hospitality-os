<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use App\Services\Payments\PaymentGatewayManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CheckoutController extends Controller
{
    public function show(Request $request, Reservation $reservation): View
    {
        $token = $this->authorizeCheckout($request, $reservation);
        $reservation->load(['property.establishment', 'guest', 'paymentAttempts']);
        $paymentMethods = $this->paymentMethods($reservation);

        return view('checkout.show', compact('reservation', 'paymentMethods', 'token'));
    }

    public function start(Request $request, Reservation $reservation, PaymentGatewayManager $manager): RedirectResponse
    {
        $token = $this->authorizeCheckout($request, $reservation);
        $validated = $request->validate([
            'provider' => ['required', 'string', 'in:pay_later,fedapay,paypal'],
        ]);

        $provider = $validated['provider'];
        abort_unless(in_array($provider, array_keys($this->paymentMethods($reservation)), true), 422, 'This payment method is not available for this establishment.');
        $gateway = $manager->resolve($provider);
        $attempt = $gateway->createIntent($reservation, [
            'idempotency_key' => $reservation->reservation_ref . '-' . $provider,
        ]);

        $reservation->update(['status' => 'pending_payment']);

        return redirect()->route('checkout.show', ['reservation' => $reservation, 'token' => $token])->with(
            'status',
            __('messages.flash.payment_started', ['gateway' => $gateway->name()])
        );
    }

    private function paymentMethods(Reservation $reservation): array
    {
        $configured = $reservation->property?->establishment?->payment_methods ?? [];
        $defaults = [
            'pay_later' => ['enabled' => true, 'instructions' => ''],
            'fedapay' => ['enabled' => false, 'instructions' => ''],
            'paypal' => ['enabled' => false, 'instructions' => ''],
        ];

        return collect($defaults)->mapWithKeys(function (array $default, string $provider) use ($configured) {
            $method = array_merge($default, $configured[$provider] ?? []);

            return $method['enabled'] ? [$provider => $method] : [];
        })->all();
    }

    public function complete(Request $request, Reservation $reservation, PaymentGatewayManager $manager): RedirectResponse
    {
        $token = $this->authorizeCheckout($request, $reservation);
        $attempt = $reservation->paymentAttempts()->latest()->firstOrFail();
        $gateway = $manager->resolve($attempt->provider);
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

        return redirect()->route('checkout.show', ['reservation' => $reservation, 'token' => $token])->with('status', __('messages.flash.payment_verified'));
    }

    public function webhook(Request $request, string $provider, PaymentGatewayManager $manager)
    {
        abort_unless(in_array($provider, ['pay_later', 'fedapay', 'paypal'], true), 404, 'Unsupported payment provider.');

        $secret = config('services.payments.webhook_secret');
        $signature = $request->header('X-Webhook-Signature');
        $expectedSignature = $secret ? hash_hmac('sha256', $request->getContent(), $secret) : null;

        abort_unless($expectedSignature && $signature && hash_equals($expectedSignature, $signature), 403, 'Invalid webhook signature.');

        $attempt = $manager->resolve($provider)->handleWebhook($request);

        if ($attempt && $attempt->reservation) {
            if (in_array($attempt->status, ['paid', 'completed'], true)) {
                $attempt->reservation->update(['status' => 'confirmed']);
            } elseif (in_array($attempt->status, ['failed', 'cancelled'], true) && $attempt->reservation->status !== 'confirmed') {
                $attempt->reservation->update(['status' => 'payment_failed']);
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
}
