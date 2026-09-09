<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<body style="font-family: Arial, sans-serif; color: #1d2a2d; line-height: 1.6;">
    <h1>{{ __('messages.receipts.email_heading') }}</h1>
    <p>{{ __('messages.receipts.email_intro', ['reference' => $reservation_ref, 'property' => $property_name]) }}</p>
    <p>{{ __('messages.receipts.email_amount', ['amount' => number_format((float) $amount, 0, ',', ' '), 'currency' => $currency]) }}</p>
    <p><a href="{{ $receipt_url }}">{{ __('messages.receipts.email_action') }}</a></p>
</body>
</html>
