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
        @include('emails.partials.legacy-price-lines')
    @endif

    @if (!empty($checkout_url))
        <x-email.button :href="$checkout_url">{{ __('messages.reservation_created.email_action') }}</x-email.button>
    @endif
    @if ($emailEstablishmentName)<p style="margin:20px 0 0;">{{ __('messages.transactional.closing_stay', ['establishment' => $emailEstablishmentName]) }}</p>@endif
@endsection
