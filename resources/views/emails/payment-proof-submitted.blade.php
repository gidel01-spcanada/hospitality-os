<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<body style="font-family: Arial, sans-serif; color: #1d2a2d; line-height: 1.6;">
    <h1>{{ __('messages.receipts.proof_notification_heading') }}</h1>
    <p>{{ __('messages.receipts.proof_notification_intro', ['reference' => $reservation_ref, 'property' => $property_name, 'provider' => __('messages.checkout.' . $provider)]) }}</p>
    <p>{{ __('messages.receipts.email_amount', ['amount' => number_format((float) $amount, 0, ',', ' '), 'currency' => $currency]) }}</p>
    <p><a href="{{ $reservation_url }}">{{ __('messages.receipts.proof_notification_action') }}</a></p>
</body>
</html>
