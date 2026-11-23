@extends('emails.layouts.reservation')

@php($emailEstablishmentName = $reservation_summary['establishment']['name'] ?? null)
@section('email-preheader', __('messages.reservation_created.email_subject', ['reference' => $reservation_ref]))
@section('email-eyebrow', __('messages.transactional.eyebrow_booking'))
@section('email-title', __('messages.reservation_created.email_heading'))

@section('email-content')
    <p style="margin:0 0 10px;font-size:17px;font-weight:bold;color:#0a3f35;">{{ __('messages.transactional.welcome', ['brand' => \App\Support\PlatformBrand::name()]) }}</p>
    <p style="margin:0 0 20px;">{{ $emailEstablishmentName ? __('messages.transactional.booking_context', ['brand' => \App\Support\PlatformBrand::name(), 'establishment' => $emailEstablishmentName]) : __('messages.transactional.booking_context_generic', ['brand' => \App\Support\PlatformBrand::name()]) }}</p>

    @if (!empty($reservation_summary))
        @include('emails.partials.reservation-summary')
    @else
        <p>{{ __('messages.reservation_created.email_intro', ['reference' => $reservation_ref, 'property' => $property_name]) }}</p>
        @if (!empty($check_in) || !empty($check_out))
            <x-email.card :title="__('messages.checkout.order_summary')">
                <table width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;">
                    @if (!empty($check_in))<x-email.row :label="__('messages.properties.check_in')">{{ \Carbon\Carbon::parse($check_in)->locale(app()->getLocale())->translatedFormat('D d M Y') }}</x-email.row>@endif
                    @if (!empty($check_out))<x-email.row :label="__('messages.properties.check_out')">{{ \Carbon\Carbon::parse($check_out)->locale(app()->getLocale())->translatedFormat('D d M Y') }}</x-email.row>@endif
                </table>
            </x-email.card>
        @endif
        @include('emails.partials.legacy-price-lines')
    @endif

    @if (!empty($checkout_url))
        <x-email.button :href="$checkout_url">{{ __('messages.reservation_created.email_action') }}</x-email.button>
    @endif
    @if ($emailEstablishmentName)<p style="margin:20px 0 0;">{{ __('messages.transactional.closing_stay', ['establishment' => $emailEstablishmentName]) }}</p>@endif
@endsection
