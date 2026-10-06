<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\EmailOutbox;
use App\Models\Message;
use App\Models\PaymentAttempt;
use App\Models\Reservation;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;

/** Read-only view model for the admin reservation detail page, built only from stored records. */
class ReservationAdminOverview
{
    private const PAID_STATUSES = ['paid', 'completed', 'verified'];

    private Collection $users;

    public function __construct(private readonly Reservation $reservation)
    {
    }

    public static function make(Reservation $reservation): array
    {
        return (new self($reservation))->build();
    }

    private function build(): array
    {
        $reservation = $this->reservation;
        $reservation->loadMissing(['property.establishment', 'guest', 'user', 'priceLines', 'paymentAttempts', 'receipts', 'internalNotes.user']);
        $attemptIds = $reservation->paymentAttempts->pluck('id');

        $audits = AuditLog::query()->with('user')
            ->where(fn ($query) => $query->where('model_type', Reservation::class)->where('model_id', $reservation->id))
            ->orWhere(fn ($query) => $query->where('model_type', PaymentAttempt::class)->whereIn('model_id', $attemptIds))
            ->orderBy('id')
            ->get()
            ->map(function (AuditLog $log) {
                $details = json_decode((string) $log->details, true) ?: [];

                return ['log' => $log, 'old' => (array) ($details['old'] ?? []), 'new' => (array) ($details['new'] ?? [])];
            });

        $payloadUserIds = $reservation->paymentAttempts
            ->flatMap(fn ($attempt) => [
                data_get($attempt->payload, 'confirmed_by'),
                data_get($attempt->payload, 'refunded_by'),
                data_get($attempt->payload, 'reference_updated_by'),
                data_get($attempt->payload, 'payment_proof.uploaded_by'),
                data_get($attempt->payload, 'refund_proof.uploaded_by'),
            ])
            ->filter(fn ($id) => is_numeric($id))
            ->unique();
        $this->users = User::query()->whereIn('id', $payloadUserIds)->get()->keyBy('id');

        $statusHistory = $audits
            ->filter(fn ($audit) => $audit['log']->model_type === Reservation::class && array_key_exists('status', $audit['new']))
            ->map(fn ($audit) => [
                'at' => $audit['log']->created_at,
                'from' => $audit['log']->action === 'created' ? null : ($audit['old']['status'] ?? null),
                'to' => $audit['new']['status'],
                'by' => $audit['log']->user?->name,
            ])
            ->filter(fn ($entry) => $entry['from'] !== $entry['to'])
            ->values();

        $attemptTransitions = $audits
            ->filter(fn ($audit) => $audit['log']->model_type === PaymentAttempt::class && array_key_exists('status', $audit['new']))
            ->groupBy(fn ($audit) => $audit['log']->model_id)
            ->map(fn ($group) => $group->map(fn ($audit) => [
                'at' => $audit['log']->created_at,
                'from' => $audit['log']->action === 'created' ? null : ($audit['old']['status'] ?? null),
                'to' => $audit['new']['status'],
                'by' => $audit['log']->user?->name,
            ])->filter(fn ($entry) => $entry['from'] !== $entry['to'])->values());

        $payments = $reservation->paymentAttempts->sortByDesc('id')->values()
            ->map(fn (PaymentAttempt $attempt) => $this->payment($attempt, $attemptTransitions->get($attempt->id, collect())));

        return [
            'summary' => ReservationSummary::make($reservation),
            'statusHistory' => $statusHistory,
            'payments' => $payments,
            'timeline' => $this->timeline($statusHistory, $payments),
            'createdBy' => $audits->first(fn ($audit) => $audit['log']->model_type === Reservation::class && $audit['log']->action === 'created')['log']->user ?? null,
        ];
    }

    private function payment(PaymentAttempt $attempt, Collection $transitions): array
    {
        $payload = (array) $attempt->payload;
        $paidTransition = $transitions->first(fn ($entry) => in_array($entry['to'], self::PAID_STATUSES, true));
        $validatedAt = data_get($payload, 'confirmed_at') ? Carbon::parse(data_get($payload, 'confirmed_at')) : $paidTransition['at'] ?? null;
        $validatedBy = $this->userName(data_get($payload, 'confirmed_by')) ?? ($paidTransition['by'] ?? null);
        $receipt = $this->reservation->receipts->firstWhere('payment_attempt_id', $attempt->id);
        $reference = $attempt->provider_reference && ! str_starts_with($attempt->provider_reference, $attempt->provider . '-') ? $attempt->provider_reference : null;
        $proofUrl = data_get($payload, 'payment_proof.path')
            ? route('reservations.payment-proof.download', ['reservation' => $this->reservation, 'attempt' => $attempt]) : null;
        $refundUrl = data_get($payload, 'refund_proof.path')
            ? route('reservations.payment-proof.download', ['reservation' => $this->reservation, 'attempt' => $attempt, 'type' => 'refund']) : null;

        return [
            'attempt' => $attempt,
            'provider' => self::providerLabel($attempt->provider),
            'status' => $attempt->status,
            'status_label' => self::attemptStatusLabel($attempt->status),
            'status_tone' => self::attemptStatusTone($attempt->status),
            'is_guarantee' => (bool) data_get($payload, 'is_guarantee', false),
            'amount' => ReservationSummary::money($attempt->amount, $attempt->currency),
            'currency' => $attempt->currency,
            'transaction_number' => 'TX-' . str_pad((string) $attempt->id, 6, '0', STR_PAD_LEFT),
            'reference' => $reference,
            'initiated_at' => $attempt->created_at,
            'paid_at' => in_array($attempt->status, [...self::PAID_STATUSES, 'refunded'], true) ? ($paidTransition['at'] ?? $validatedAt ?? $attempt->updated_at) : null,
            'validated_at' => in_array($attempt->status, [...self::PAID_STATUSES, 'refunded'], true) ? $validatedAt : null,
            'validated_by' => in_array($attempt->status, [...self::PAID_STATUSES, 'refunded'], true) ? $validatedBy : null,
            'proof_url' => $proofUrl,
            'proof_name' => data_get($payload, 'payment_proof.original_name'),
            'proof_uploaded_at' => data_get($payload, 'payment_proof.uploaded_at') ? Carbon::parse(data_get($payload, 'payment_proof.uploaded_at')) : null,
            'proof_uploaded_by' => $this->uploaderName(data_get($payload, 'payment_proof.uploaded_by')),
            'refund_url' => $refundUrl,
            'refund_name' => data_get($payload, 'refund_proof.original_name'),
            'refund_reference' => data_get($payload, 'refund_reference'),
            'refunded_at' => data_get($payload, 'refunded_at') ? Carbon::parse(data_get($payload, 'refunded_at')) : null,
            'refunded_by' => $this->userName(data_get($payload, 'refunded_by')),
            'receipt' => $receipt,
            'needs_validation' => in_array($attempt->status, ['awaiting_validation', 'pending_validation'], true) && $proofUrl && ! data_get($payload, 'is_guarantee'),
            'transitions' => $transitions,
        ];
    }

    private function timeline(Collection $statusHistory, Collection $payments): Collection
    {
        $reservation = $this->reservation;
        $events = collect([[
            'at' => $reservation->created_at,
            'type' => 'created',
            'title' => __('messages.admin.timeline_created'),
            'detail' => __('messages.admin.timeline_source', ['source' => $reservation->source ?: '—']),
            'by' => null,
        ]]);

        foreach ($statusHistory->filter(fn ($entry) => $entry['from'] !== null) as $entry) {
            $events->push([
                'at' => $entry['at'], 'type' => 'status',
                'title' => __('messages.admin.timeline_status_changed'),
                'detail' => __('messages.admin.status_' . $entry['from']) . ' → ' . __('messages.admin.status_' . $entry['to']),
                'by' => $entry['by'],
            ]);
        }

        foreach ($payments as $payment) {
            $events->push([
                'at' => $payment['initiated_at'], 'type' => 'payment',
                'title' => __('messages.admin.timeline_payment_initiated'),
                'detail' => $payment['provider'] . ' · ' . $payment['amount'] . ($payment['is_guarantee'] ? ' · ' . __('messages.admin.payment_guarantee') : ''),
                'by' => null,
            ]);
            if ($payment['proof_uploaded_at']) {
                $events->push(['at' => $payment['proof_uploaded_at'], 'type' => 'proof', 'title' => __('messages.admin.timeline_proof_uploaded'), 'detail' => $payment['provider'] . ' · ' . $payment['proof_name'], 'by' => $payment['proof_uploaded_by']]);
            }
            foreach ($payment['transitions']->filter(fn ($entry) => $entry['from'] !== null) as $entry) {
                $events->push([
                    'at' => $entry['at'], 'type' => in_array($entry['to'], self::PAID_STATUSES, true) ? 'paid' : 'payment',
                    'title' => in_array($entry['to'], self::PAID_STATUSES, true) ? __('messages.admin.timeline_payment_validated') : __('messages.admin.timeline_payment_status'),
                    'detail' => $payment['provider'] . ' · ' . $payment['amount'] . ' · ' . self::attemptStatusLabel($entry['from']) . ' → ' . self::attemptStatusLabel($entry['to']),
                    'by' => $entry['by'],
                ]);
            }
            if ($payment['refunded_at']) {
                $events->push(['at' => $payment['refunded_at'], 'type' => 'refund', 'title' => __('messages.admin.timeline_refunded'), 'detail' => $payment['provider'] . ' · ' . $payment['amount'], 'by' => $payment['refunded_by']]);
            }
        }

        foreach ($reservation->receipts as $receipt) {
            $events->push(['at' => $receipt->issued_at ?? $receipt->created_at, 'type' => 'receipt', 'title' => __('messages.admin.timeline_receipt_issued'), 'detail' => $receipt->receipt_number . ' · ' . ReservationSummary::money($receipt->amount, $receipt->currency), 'by' => $receipt->issued_by]);
        }

        EmailOutbox::query()
            ->where('payload', 'like', '%"' . $reservation->reservation_ref . '"%')
            ->orderBy('id')
            ->get()
            ->each(fn (EmailOutbox $email) => $events->push([
                'at' => $email->created_at, 'type' => 'email',
                'title' => __('messages.admin.timeline_email', ['status' => Lang::has('messages.admin.email_status_' . $email->status) ? __('messages.admin.email_status_' . $email->status) : $email->status]),
                'detail' => ($email->payload['subject'] ?? $email->template) . ' → ' . $email->recipient_email,
                'by' => null,
            ]));

        Message::query()->with('sender')->where('reservation_id', $reservation->id)->orderBy('id')->get()
            ->each(fn (Message $message) => $events->push([
                'at' => $message->created_at, 'type' => 'message',
                'title' => __('messages.admin.timeline_message'),
                'detail' => Str::limit($message->body, 140),
                'by' => $message->sender?->name,
            ]));

        foreach ($reservation->internalNotes as $note) {
            $events->push(['at' => $note->created_at, 'type' => 'note', 'title' => __('messages.admin.timeline_note'), 'detail' => Str::limit($note->body, 140), 'by' => $note->user?->name]);
        }

        return $events->filter(fn ($event) => $event['at'])->sortByDesc(fn ($event) => $event['at']->getTimestamp())->values();
    }

    private function userName(mixed $id): ?string
    {
        return is_numeric($id) ? $this->users->get((int) $id)?->name : null;
    }

    private function uploaderName(mixed $value): ?string
    {
        return $value === 'guest' ? __('messages.admin.timeline_customer') : $this->userName($value);
    }

    public static function providerLabel(?string $provider): string
    {
        return $provider && Lang::has('messages.checkout.' . $provider) ? __('messages.checkout.' . $provider) : Str::headline((string) $provider);
    }

    public static function attemptStatusLabel(?string $status): string
    {
        return $status && Lang::has('messages.admin.payment_attempt_status_' . $status) ? __('messages.admin.payment_attempt_status_' . $status) : Str::headline((string) $status);
    }

    public static function attemptStatusTone(?string $status): string
    {
        return match ($status) {
            'paid', 'completed', 'verified' => 'success',
            'awaiting_validation', 'pending_validation', 'authorized', 'pending', 'created', 'processing' => 'warning',
            'failed', 'cancelled' => 'error',
            'refunded' => 'info',
            default => 'neutral',
        };
    }
}
