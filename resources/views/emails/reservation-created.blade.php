<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<body style="font-family: Arial, sans-serif; color: #1d2a2d; line-height: 1.6;">
    <h1>{{ __('messages.reservation_created.email_heading') }}</h1>
    <p>{{ __('messages.reservation_created.email_intro', ['reference' => $reservation_ref, 'property' => $property_name]) }}</p>

    @if (!empty($price_lines))
        <table style="width: 100%; max-width: 500px; border-collapse: collapse; margin: 15px 0; font-size: 14px;">
            <thead>
                <tr style="background-color: #f6f3ec; border-bottom: 2px solid #0f5b4c;">
                    <th style="text-align: left; padding: 8px;">{{ __('messages.checkout.mode') }}</th>
                    <th style="text-align: right; padding: 8px;">{{ __('messages.reservation.total') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($price_lines as $line)
                    <tr style="border-bottom: 1px solid #e2e8f0;">
                        <td style="padding: 8px;">{{ $line['label'] }}</td>
                        <td style="text-align: right; padding: 8px;">{{ number_format((float) $line['amount'], 0, ',', ' ') }} {{ $line['currency'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <p>{{ __('messages.reservation_created.email_amount', ['amount' => number_format((float) $total_amount, 0, ',', ' '), 'currency' => $currency]) }}</p>
    @if (!empty($checkout_url))
        <p><a href="{{ $checkout_url }}">{{ __('messages.reservation_created.email_action') }}</a></p>
    @endif
</body>
</html>
