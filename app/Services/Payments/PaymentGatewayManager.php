<?php

namespace App\Services\Payments;

use App\Contracts\PaymentGateway;
use App\Models\Reservation;
use Illuminate\Support\Str;

class PaymentGatewayManager
{
    public function resolve(string $provider = null, string $mode = 'sandbox'): PaymentGateway
    {
        $provider = $provider ?? env('PAYMENT_PROVIDER', 'pay_later');

        return match ($provider) {
            'fedapay' => $mode === 'production' ? app(FedaPayLiveGateway::class) : app(FedaPaySandboxGateway::class),
            'paypal' => $mode === 'production' ? app(PayPalLiveGateway::class) : app(PayPalSandboxGateway::class),
            'cinetpay' => $mode === 'production' ? app(CinetPayLiveGateway::class) : app(CinetPaySandboxGateway::class),
            'mpesa' => $mode === 'production' ? app(MpesaLiveGateway::class) : app(MpesaSandboxGateway::class),
            'interac' => app(InteracGateway::class),
            'wise' => app(WiseGateway::class),
            'revolut' => app(RevolutGateway::class),
            'pay_later' => app(PayLaterGateway::class),
            default => app(PayLaterGateway::class),
        };
    }

    public function createReservationAttempt(Reservation $reservation, ?string $provider = null): PaymentGateway
    {
        $gateway = $this->resolve($provider);
        $gateway->createIntent($reservation, [
            'idempotency_key' => Str::uuid()->toString(),
            'amount' => (string) $reservation->total_amount,
            'currency' => $reservation->currency,
        ]);

        return $gateway;
    }
}
