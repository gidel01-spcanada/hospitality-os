<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head><meta charset="UTF-8"><title>{{ __('messages.receipts.title') }} {{ $receipt->receipt_number }}</title></head>
<body style="font-family: DejaVu Sans, sans-serif; color: #1d2a2d;">
    <h1>{{ \App\Support\PlatformBrand::name() }}</h1>
    <h2>{{ __('messages.receipts.title') }}</h2>
    <p><strong>{{ __('messages.receipts.number') }}:</strong> {{ $receipt->receipt_number }}</p>
    <p><strong>{{ __('messages.checkout.reference') }}:</strong> {{ $reservation->reservation_ref }}</p>
    <p><strong>{{ __('messages.receipts.customer') }}:</strong> {{ $reservation->guest?->full_name ?? $reservation->email }}</p>
    <p><strong>{{ __('messages.receipts.property') }}:</strong> {{ $reservation->property?->name }}</p>
    <p><strong>{{ __('messages.receipts.issued_at') }}:</strong> {{ $receipt->issued_at?->format('d/m/Y H:i') }}</p>
    <hr>
    <p><strong>{{ __('messages.receipts.paid_amount') }}:</strong> {{ number_format((float) $receipt->amount, 0, ',', ' ') }} {{ $receipt->currency }}</p>
</body>
</html>
