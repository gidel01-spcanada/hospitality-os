<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<body style="font-family: Arial, sans-serif; color: #1d2a2d; line-height: 1.6;">
    <h1>{{ __('messages.reservation_status.email_heading') }}</h1>
    <p>{{ __('messages.reservation_status.email_intro', ['reference' => $reservation_ref, 'property' => $property_name]) }}</p>
    <p><strong>{{ __('messages.reservation_status.email_status') }}:</strong> {{ $status }}</p>
    @if (!empty($checkout_url))
        <p><a href="{{ $checkout_url }}">{{ __('messages.reservation_status.email_action') }}</a></p>
    @endif
    @if (($review_channel ?? null) !== 'google' && !empty($review_url))
        <p><a href="{{ $review_url }}">{{ __('messages.review_request.email_internal_action') }}</a></p>
    @endif
    @if (in_array($review_channel ?? null, ['google', 'both'], true) && !empty($google_review_url))
        <p><a href="{{ $google_review_url }}">{{ __('messages.review_request.email_google_action') }}</a></p>
    @endif
</body>
</html>
