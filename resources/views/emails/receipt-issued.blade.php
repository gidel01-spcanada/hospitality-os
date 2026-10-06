@extends('emails.layouts.reservation')

@php
    $emailTx = $transaction ?? null;
    $emailTxVerified = !empty($emailTx['verified']);
    $emailMethod = !empty($emailTx['method']) ? (\Illuminate\Support\Facades\Lang::has('messages.checkout.' . $emailTx['method']) ? __('messages.checkout.' . $emailTx['method']) : \Illuminate\Support\Str::headline($emailTx['method'])) : null;
    $emailDateTime = fn ($value) => \Carbon\Carbon::parse($value)->setTimezone(config('app.timezone'))->locale(app()->getLocale())->translatedFormat('d M Y, H:i T');
@endphp
@section('email-preheader', __('messages.receipts.email_subject', ['reference' => $reservation_ref]))
@section('email-eyebrow', __('messages.transactional.eyebrow_receipt'))
@section('email-title', __('messages.receipts.email_heading'))

@section('email-content')
    <p style="margin:0 0 20px;">{{ __('messages.transactional.receipt_context', ['brand' => \App\Support\PlatformBrand::name(), 'reference' => $reservation_ref]) }}</p>

    @if ($emailTx)
        <x-email.card :tone="$emailTxVerified ? 'success' : ($emailTx['is_guarantee'] ? 'info' : 'warning')">
            <p data-receipt-status style="margin:0 0 6px;font-size:21px;font-weight:bold;color:{{ $emailTxVerified ? '#0a6b3f' : '#8a5a00' }};">{{ $emailTxVerified ? __('messages.transactional.paid_confirmed') : __('messages.transactional.transaction_not_confirmed') }}</p>
            @if ($emailTxVerified)<p style="margin:0 0 10px;font-size:14px;">{{ __('messages.receipts.email_intro', ['reference' => $reservation_ref, 'property' => $property_name]) }}</p>@endif
            <p style="margin:0;font-size:12px;font-weight:bold;letter-spacing:0.08em;text-transform:uppercase;color:#5b6b70;">{{ $emailTxVerified ? __('messages.transactional.amount_paid') : __('messages.transactional.transaction_amount') }}</p>
            <p style="margin:0;font-size:30px;font-weight:bold;color:#0a3f35;">{{ \App\Support\ReservationSummary::money($emailTx['amount'], $emailTx['currency']) }}</p>
            <p style="margin:8px 0 0;font-size:13px;">{{ $emailTx['is_guarantee'] ? __('messages.transactional.guarantee_notice') : ($emailTxVerified ? __('messages.transactional.payment_received') : __('messages.transactional.payment_unverified')) }}</p>
        </x-email.card>

        <x-email.card :title="__('messages.transactional.section_transaction')">
            <table width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;">
                @if (!empty($emailTx['transaction_number']))<x-email.row :label="__('messages.transactional.transaction_number')">{{ $emailTx['transaction_number'] }}</x-email.row>@endif
                <x-email.row :label="__('messages.transactional.receipt_number')">{{ $receipt_number }}</x-email.row>
                <x-email.row :label="__('messages.checkout.reference')">{{ $reservation_ref }}</x-email.row>
                @if ($emailTxVerified && !empty($emailTx['paid_at']))<x-email.row :label="__('messages.transactional.paid_at')">{{ $emailDateTime($emailTx['paid_at']) }}</x-email.row>@endif
                @if ($emailTx['recorded_at'])<x-email.row :label="__('messages.transactional.payment_recorded_at')">{{ $emailDateTime($emailTx['recorded_at']) }}</x-email.row>@endif
                @if ($emailMethod)<x-email.row :label="__('messages.transactional.payment_method')">{{ $emailMethod }}</x-email.row>@endif
                <x-email.row :label="__('messages.transactional.currency')">{{ $emailTx['currency'] }}</x-email.row>
                @if ($emailTx['reference'])<x-email.row :label="__('messages.transactional.payment_reference')">{{ $emailTx['reference'] }}</x-email.row>@endif
                <x-email.row :label="$emailTxVerified ? __('messages.transactional.amount_paid') : __('messages.transactional.transaction_amount')" :emphasis="true">{{ \App\Support\ReservationSummary::money($emailTx['amount'], $emailTx['currency']) }}</x-email.row>
            </table>
        </x-email.card>
    @else
        <x-email.card :title="__('messages.transactional.section_transaction')">
            <table width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;">
                <x-email.row :label="__('messages.transactional.receipt_number')">{{ $receipt_number }}</x-email.row>
                <x-email.row :label="__('messages.checkout.reference')">{{ $reservation_ref }}</x-email.row>
                <x-email.row :label="__('messages.transactional.transaction_amount')" :emphasis="true">{{ \App\Support\ReservationSummary::money($amount, $currency) }}</x-email.row>
            </table>
        </x-email.card>
    @endif

    @if (!empty($reservation_summary))
        @include('emails.partials.reservation-summary')
    @else
        @include('emails.partials.legacy-price-lines')
    @endif

    <x-email.button :href="$receipt_url">{{ __('messages.receipts.email_action') }}</x-email.button>
    <p style="margin:16px 0 0;font-size:13px;color:#5b6b70;">{{ __('messages.transactional.receipt_keep') }}</p>
@endsection
