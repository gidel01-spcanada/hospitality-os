<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use App\Services\Payments\PaymentGatewayManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CheckoutController extends Controller
{
    public function show(Reservation $reservation): View
    {
        $reservation->load(['property.establishment', 'guest', 'paymentAttempts']);
        $paymentMethods = $this->paymentMethods($reservation);

        return view('checkout.show', compact('reservation', 'paymentMethods'));
    }

    public function start(Request $request, Reservation $reservation, PaymentGatewayManager $manager): RedirectResponse
    {
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

        return redirect()->route('checkout.show', $reservation)->with(
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

    public function complete(Reservation $reservation, PaymentGatewayManager $manager): RedirectResponse
    {
        $attempt = $reservation->paymentAttempts()->latest()->firstOrFail();
        $gateway = $manager->resolve($attempt->provider);
        $gateway->verifyStatus($attempt);

        $attempt->update([
            'status' => 'paid',
            'payload' => array_merge((array) $attempt->payload, ['verified_at' => now()->toIso8601String()]),
        ]);

        $reservation->update([
            'status' => 'confirmed',
            'notes' => trim(($reservation->notes ?? '') . PHP_EOL . 'Payment verified and reservation confirmed.'),
        ]);

        return redirect()->route('checkout.show', $reservation)->with('status', __('messages.flash.payment_verified'));
    }

    public function webhook(Request $request, string $provider, PaymentGatewayManager $manager)
    {
        $attempt = $manager->resolve($provider)->handleWebhook($request);

        if ($attempt && $attempt->reservation) {
            $attempt->reservation->update([
                'status' => in_array($attempt->status, ['paid', 'completed']) ? 'confirmed' : 'payment_failed',
            ]);
        }

        return response()->json(['ok' => true, 'provider' => $provider, 'status' => $attempt?->status ?? 'ignored']);
    }
}
