<?php

namespace App\Services\Payments;

use App\Contracts\PaymentGateway;
use App\Models\Reservation;
use Illuminate\Support\Str;

class PaymentGatewayManager
{
    public function resolve(string $provider = null): PaymentGateway
    {
        $provider = $provider ?? env('PAYMENT_PROVIDER', 'pay_later');

        return match ($provider) {
            'fedapay' => app(FedaPaySandboxGateway::class),
            'paypal' => app(PayPalSandboxGateway::class),
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
