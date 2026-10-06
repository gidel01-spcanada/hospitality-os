@extends('emails.layouts.reservation')

@php($emailProvider = \Illuminate\Support\Facades\Lang::has('messages.checkout.' . $provider) ? __('messages.checkout.' . $provider) : \Illuminate\Support\Str::headline($provider))
@section('email-preheader', __('messages.receipts.proof_notification_subject', ['reference' => $reservation_ref]))
@section('email-eyebrow', __('messages.transactional.eyebrow_proof'))
@section('email-title', __('messages.receipts.proof_notification_heading'))

@section('email-content')
    <p style="margin:0 0 10px;">{{ __('messages.transactional.proof_context', ['brand' => \App\Support\PlatformBrand::name()]) }}</p>
    <p style="margin:0 0 20px;">{{ __('messages.receipts.proof_notification_intro', ['reference' => $reservation_ref, 'property' => $property_name, 'provider' => $emailProvider]) }}</p>

    <x-email.card tone="info">
        <p style="margin:0 0 10px;font-size:19px;font-weight:bold;color:#1f5f8b;">{{ __('messages.transactional.status_label') }} {{ __('messages.reservation_summary.payment_pending_validation') }}</p>
        <table width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;">
            <x-email.row :label="__('messages.checkout.reference')">{{ $reservation_ref }}</x-email.row>
            <x-email.row :label="__('messages.checkout.property')">{{ $property_name }}</x-email.row>
            <x-email.row :label="__('messages.transactional.payment_method')">{{ $emailProvider }}</x-email.row>
            <x-email.row :label="__('messages.transactional.transaction_amount')" :emphasis="true">{{ \App\Support\ReservationSummary::money($amount, $currency) }}</x-email.row>
        </table>
    </x-email.card>

    <x-email.button :href="$reservation_url">{{ __('messages.receipts.proof_notification_action') }}</x-email.button>
@endsection
