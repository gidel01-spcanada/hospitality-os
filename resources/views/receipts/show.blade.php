<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <title>{{ __('messages.receipts.title') }} {{ $receipt->receipt_number }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #1d2a2d; margin: 20px; font-size: 13px; }
        h1 { color: #0f5b4c; margin-bottom: 5px; }
        h2 { border-bottom: 2px solid #0f5b4c; padding-bottom: 5px; margin-top: 0; }
        .meta-table, .lines-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .meta-table td { padding: 4px 0; }
        .lines-table th { background: #f6f3ec; border-bottom: 2px solid #0f5b4c; padding: 8px; text-align: left; }
        .lines-table td { padding: 8px; border-bottom: 1px solid #e2e8f0; }
        .lines-table tr.total-row td { font-weight: bold; border-top: 2px solid #0f5b4c; border-bottom: none; font-size: 14px; }
    </style>
</head>
<body>
    <h1>{{ \App\Support\PlatformBrand::name() }}</h1>
    <h2>{{ __('messages.receipts.title') }}</h2>

    <table class="meta-table">
        <tr><td><strong>{{ __('messages.receipts.number') }}:</strong> {{ $receipt->receipt_number }}</td><td><strong>{{ __('messages.receipts.issued_at') }}:</strong> {{ $receipt->issued_at?->format('d/m/Y H:i') }}</td></tr>
        <tr><td><strong>{{ __('messages.checkout.reference') }}:</strong> {{ $reservation->reservation_ref }}</td><td><strong>{{ __('messages.receipts.customer') }}:</strong> {{ $reservation->guest?->full_name ?? $reservation->email }}</td></tr>
        <tr><td colspan="2"><strong>{{ __('messages.receipts.property') }}:</strong> {{ $reservation->property?->name }}</td></tr>
    </table>

    @if ($reservation->priceLines->isNotEmpty())
        <table class="lines-table">
            <thead>
                <tr>
                    <th>{{ __('messages.checkout.mode') }}</th>
                    <th style="text-align: right;">{{ __('messages.reservation.total') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($reservation->priceLines as $line)
                    <tr>
                        <td>{{ $line->label }}</td>
                        <td style="text-align: right;">{{ number_format((float) $line->amount, 0, ',', ' ') }} {{ $line->currency }}</td>
                    </tr>
                @endforeach
                <tr class="total-row">
                    <td>{{ __('messages.receipts.paid_amount') }}</td>
                    <td style="text-align: right;">{{ number_format((float) $receipt->amount, 0, ',', ' ') }} {{ $receipt->currency }}</td>
                </tr>
            </tbody>
        </table>
    @else
        <hr>
        <p><strong>{{ __('messages.receipts.paid_amount') }}:</strong> {{ number_format((float) $receipt->amount, 0, ',', ' ') }} {{ $receipt->currency }}</p>
    @endif
</body>
</html>
