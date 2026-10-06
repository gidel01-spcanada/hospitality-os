@extends('emails.layouts.reservation')

@section('email-preheader', __('messages.payment_link.email_intro', ['reference' => $reservation_ref, 'property' => $property_name]))
@section('email-eyebrow', __('messages.transactional.eyebrow_payment'))
@section('email-title', __('messages.payment_link.email_heading'))

@section('email-content')
    <p style="margin:0 0 10px;">{{ __('messages.payment_link.email_intro', ['reference' => $reservation_ref, 'property' => $property_name]) }}</p>
    <p style="margin:0 0 20px;">{{ __('messages.transactional.payment_link_context', ['brand' => \App\Support\PlatformBrand::name()]) }}</p>

    @if (!empty($reservation_summary))
        @include('emails.partials.reservation-summary')
    @else
        @include('emails.partials.legacy-price-lines')
    @endif

    <x-email.button :href="$checkout_url">{{ __('messages.payment_link.email_action') }}</x-email.button>
    @if (!empty($register_url))
        <x-email.button :href="$register_url" variant="secondary">{{ __('messages.payment_link.create_account') }}</x-email.button>
    @endif
    <p style="margin:16px 0 0;font-size:13px;color:#5b6b70;">{{ __('messages.transactional.security_tip', ['brand' => \App\Support\PlatformBrand::name()]) }}</p>
@endsection
