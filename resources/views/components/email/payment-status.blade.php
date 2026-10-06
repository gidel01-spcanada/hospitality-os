@props(['summary'])
@php
    $emailStatus = $summary['payment_status'];
    $emailDueAmount = $summary['due'] === null ? __('messages.reservation_summary.balance_unknown') : \App\Support\ReservationSummary::money($summary['due'], $summary['currency'], $summary['locale']);
    $emailValidatingAmount = \App\Support\ReservationSummary::money((float) ($summary['validating'] ?? 0) > 0 ? $summary['validating'] : ($summary['due'] ?? 0), $summary['currency'], $summary['locale']);
    [$emailStatusTone, $emailStatusColor, $emailStatusTitle, $emailStatusDetail] = match ($emailStatus) {
        'paid', 'confirmed' => ['success', '#0a6b3f', __('messages.transactional.status_paid'), __('messages.transactional.paid_detail', ['amount' => $emailDueAmount])],
        'partial' => ['warning', '#8a5a00', __('messages.reservation_summary.payment_partial'), __('messages.transactional.partial_detail', ['amount' => $emailDueAmount])],
        'pending_validation' => ['info', '#1f5f8b', __('messages.reservation_summary.payment_pending_validation'), __('messages.transactional.validation_detail', ['amount' => $emailValidatingAmount])],
        'failed' => ['danger', '#a3322a', __('messages.reservation_summary.payment_failed'), __('messages.transactional.failed_detail')],
        'cancelled' => ['muted', '#5b6b70', __('messages.reservation_summary.payment_cancelled'), __('messages.transactional.cancelled_detail')],
        'not_required' => ['muted', '#0f5b4c', __('messages.reservation_summary.payment_not_required'), __('messages.transactional.not_required_detail')],
        'unreconciled' => ['info', '#1f5f8b', __('messages.reservation_summary.payment_unreconciled'), __('messages.transactional.unreconciled_detail')],
        default => ['warning', '#8a5a00', __('messages.reservation_summary.payment_pending'), __('messages.transactional.pending_detail', ['amount' => $emailDueAmount])],
    };
@endphp
<x-email.card :tone="$emailStatusTone">
    <p style="margin:0 0 4px;font-size:12px;font-weight:bold;letter-spacing:0.08em;text-transform:uppercase;color:#5b6b70;">{{ __('messages.reservation_summary.financial_status') }}</p>
    <p data-email-payment-status="{{ $emailStatus }}" style="margin:0 0 6px;font-size:19px;font-weight:bold;color:{{ $emailStatusColor }};">{{ __('messages.transactional.status_label') }} {{ $emailStatusTitle }}</p>
    <p style="margin:0;font-size:14px;color:#1d2a2d;">{{ $emailStatusDetail }}</p>
</x-email.card>
