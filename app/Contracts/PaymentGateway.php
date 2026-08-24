<?php

namespace App\Contracts;

use App\Models\PaymentAttempt;
use App\Models\Reservation;
use Illuminate\Http\Request;

interface PaymentGateway
{
    public function name(): string;

    public function createIntent(Reservation $reservation, array $context = []): PaymentAttempt;

    public function verifyStatus(PaymentAttempt $attempt): PaymentAttempt;

    public function handleWebhook(Request $request): ?PaymentAttempt;

    public function cancel(PaymentAttempt $attempt, string $reason = 'cancelled'): PaymentAttempt;

    public function refund(PaymentAttempt $attempt, string $reason = 'requested'): PaymentAttempt;
}
