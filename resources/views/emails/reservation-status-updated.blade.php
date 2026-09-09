<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<body style="font-family: Arial, sans-serif; color: #1d2a2d; line-height: 1.6;">
    <h1>{{ __('messages.reservation_status.email_heading') }}</h1>
    <p>{{ __('messages.reservation_status.email_intro', ['reference' => $reservation_ref, 'property' => $property_name]) }}</p>
    <p><strong>{{ __('messages.reservation_status.email_status') }}:</strong> {{ $status }}</p>
    @if (!empty($checkout_url))
        <p><a href="{{ $checkout_url }}">{{ __('messages.reservation_status.email_action') }}</a></p>
    @endif
</body>
</html>
