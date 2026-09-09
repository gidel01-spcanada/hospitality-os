<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<body style="font-family: Arial, sans-serif; color: #1d2a2d; line-height: 1.6;">
    <h1>{{ __('messages.reservation_received.email_heading') }}</h1>
    <p>{{ __('messages.reservation_received.email_intro', ['reference' => $reservation_ref, 'property' => $property_name]) }}</p>
    <p>{{ __('messages.reservation_received.email_amount', ['amount' => number_format((float) $total_amount, 0, ',', ' '), 'currency' => $currency]) }}</p>
    @if (!empty($checkout_url))
        <p><a href="{{ $checkout_url }}">{{ __('messages.reservation_received.email_action') }}</a></p>
    @endif
</body>
</html>
