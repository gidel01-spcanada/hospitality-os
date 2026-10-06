@extends('emails.layouts.reservation')

@php
    $emailBrandName = \App\Support\PlatformBrand::name();
    $emailFromEstablishment = !empty($from_establishment);
    $emailMessageBody = $message_body ?? (is_string($message ?? null) ? $message : '');
@endphp
@section('email-preheader', $emailFromEstablishment ? __('messages.transactional.message_from_establishment', ['establishment' => $establishment_name]) : __('messages.messages.email_heading'))
@section('email-eyebrow', __('messages.transactional.eyebrow_message', ['brand' => $emailBrandName]))
@section('email-title', $emailFromEstablishment ? __('messages.transactional.message_from_establishment', ['establishment' => $establishment_name]) : __('messages.messages.email_heading'))

@section('email-content')
    @if ($emailFromEstablishment && !empty($reservation_context))
        <p style="margin:0 0 10px;">{{ __('messages.transactional.message_via_establishment', ['establishment' => $establishment_name, 'brand' => $emailBrandName, 'reference' => $reservation_context['reference']]) }}</p>
    @endif
    <p style="margin:0 0 20px;color:#5b6b70;font-size:14px;">{{ __('messages.transactional.message_context', ['sender' => $sender_name ?? '', 'establishment' => $establishment_name ?? '', 'brand' => $emailBrandName]) }}</p>

    <x-email.card>
        <p style="margin:0 0 10px;font-size:12px;font-weight:bold;letter-spacing:0.08em;text-transform:uppercase;color:#5b6b70;">{{ $sender_name ?? ($establishment_name ?? '') }}</p>
        <div style="padding:2px 0 2px 14px;border-left:3px solid #c9a24a;font-size:15px;white-space:pre-wrap;">{{ $emailMessageBody }}</div>
    </x-email.card>

    @if (!empty($thread_url))
        <x-email.button :href="$thread_url">{{ __('messages.messages.email_action') }}</x-email.button>
    @endif
    @if (!empty($reservation_context) && !empty($reservation_url))
        <x-email.button :href="$reservation_url" variant="secondary">{{ __('messages.messages.return_to_reservation', ['reference' => $reservation_context['reference']]) }}</x-email.button>
    @endif

    @if (!empty($reservation_context))
        <div style="height:16px;line-height:16px;">&nbsp;</div>
        @include('emails.partials.reservation-brief')
    @endif

    <x-email.card tone="success">
        <p style="margin:0;font-size:14px;font-weight:bold;color:#0a3f35;">{{ !empty($reservation_context) ? __('messages.transactional.message_forwarded_notice', ['brand' => $emailBrandName]) : __('messages.transactional.message_forwarded_generic', ['brand' => $emailBrandName]) }}</p>
        @if ($emailFromEstablishment)<p style="margin:6px 0 0;font-size:13px;">{{ __('messages.transactional.security_tip', ['brand' => $emailBrandName]) }}</p>@endif
    </x-email.card>
@endsection
