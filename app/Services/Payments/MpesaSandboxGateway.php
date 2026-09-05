<?php

namespace App\Services\Payments;

use App\Contracts\PaymentGateway;
use App\Models\PaymentAttempt;
use App\Models\Reservation;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class MpesaSandboxGateway implements PaymentGateway
{
    public function name(): string { return 'mpesa'; }

    public function createIntent(Reservation $reservation, array $context = []): PaymentAttempt
    {
        return PaymentAttempt::query()->create([
            'reservation_id' => $reservation->id,
            'provider' => $this->name(),
            'provider_reference' => 'mpesa-sandbox-'.Str::upper(Str::random(12)),
            'currency' => $reservation->currency,
            'amount' => $reservation->total_amount,
            'status' => 'created',
            'idempotency_key' => $context['idempotency_key'] ?? Str::uuid()->toString(),
            'payload' => ['environment' => config('services.mpesa.environment', 'sandbox'), 'mode' => 'sandbox', 'reservation_ref' => $reservation->reservation_ref],
        ]);
    }

    public function verifyStatus(PaymentAttempt $attempt): PaymentAttempt { $attempt->status = $attempt->status === 'paid' ? 'paid' : 'pending'; $attempt->save(); return $attempt; }
    public function handleWebhook(Request $request): ?PaymentAttempt { return null; }
    public function cancel(PaymentAttempt $attempt, string $reason = 'cancelled'): PaymentAttempt { $attempt->update(['status' => 'cancelled']); return $attempt; }
    public function refund(PaymentAttempt $attempt, string $reason = 'requested'): PaymentAttempt { $attempt->update(['status' => 'refunded']); return $attempt; }
}
