@extends('emails.layouts.reservation')

@php($emailEstablishmentName = $reservation_summary['establishment']['name'] ?? null)
@section('email-preheader', __('messages.reservation_status.email_intro', ['reference' => $reservation_ref, 'property' => $property_name]))
@section('email-eyebrow', __('messages.transactional.eyebrow_status'))
@section('email-title', __('messages.reservation_status.email_heading'))

@section('email-content')
    <p style="margin:0 0 20px;">{{ $emailEstablishmentName ? __('messages.transactional.status_context', ['brand' => \App\Support\PlatformBrand::name(), 'establishment' => $emailEstablishmentName]) : __('messages.reservation_status.email_intro', ['reference' => $reservation_ref, 'property' => $property_name]) }}</p>

    <x-email.card tone="info">
        <p style="margin:0 0 4px;font-size:12px;font-weight:bold;letter-spacing:0.08em;text-transform:uppercase;color:#5b6b70;">{{ __('messages.reservation_status.email_status') }}</p>
        <p style="margin:0;font-size:19px;font-weight:bold;color:#0a3f35;">{{ __('messages.admin.status_' . $status) }}</p>
        <p style="margin:4px 0 0;font-size:14px;">{{ __('messages.reservation_status.email_intro', ['reference' => $reservation_ref, 'property' => $property_name]) }}</p>
    </x-email.card>

    @if ($status === 'cancelled' && (float) ($reservation_summary['refunded'] ?? 0) > 0)
        <x-email.card tone="success">
            <p data-email-refunded style="margin:0 0 4px;font-size:17px;font-weight:bold;color:#0a6b3f;">{{ __('messages.transactional.refund_done', ['amount' => \App\Support\ReservationSummary::money($reservation_summary['refunded'], $reservation_summary['currency'], $reservation_summary['locale'])]) }}</p>
            <p style="margin:0;font-size:14px;">{{ __('messages.transactional.refund_proof_available') }}</p>
        </x-email.card>
    @endif

    @if (!empty($reservation_summary))
        @include('emails.partials.reservation-summary')
    @endif

    @if (!empty($checkout_url))
        <x-email.button :href="$checkout_url">{{ __('messages.reservation_status.email_action') }}</x-email.button>
    @endif
    @if (($review_channel ?? null) !== 'google' && !empty($review_url))
        <x-email.button :href="$review_url" variant="secondary">{{ __('messages.review_request.email_internal_action') }}</x-email.button>
    @endif
    @if (in_array($review_channel ?? null, ['google', 'both'], true) && !empty($google_review_url))
        <x-email.button :href="$google_review_url" variant="secondary">{{ __('messages.review_request.email_google_action') }}</x-email.button>
    @endif
    @if ($emailEstablishmentName && !in_array($status, ['cancelled', 'completed'], true))<p style="margin:20px 0 0;">{{ __('messages.transactional.closing_stay', ['establishment' => $emailEstablishmentName]) }}</p>@endif
@endsection
