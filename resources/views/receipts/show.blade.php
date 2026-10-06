<!DOCTYPE html>
@php
    $summary ??= \App\Support\ReservationSummary::make($reservation);
    $transaction ??= app(\App\Services\ReservationEmailService::class)->receiptTransaction($receipt);
    $images ??= ['establishment' => null, 'property' => null];
    $brandName = \App\Support\PlatformBrand::name();
    $brand = \App\Support\BrandSettings::all();
    $brandIcon = $brand['site_icon'] ?? \Illuminate\Support\Str::substr($brandName, 0, 1);
    $contactEmail = filter_var($brand['contact_email'] ?? null, FILTER_VALIDATE_EMAIL) && ! str_ends_with(strtolower($brand['contact_email']), '.example') ? $brand['contact_email'] : null;
    $supportPhone = filled($brand['support_phone'] ?? null) && $brand['support_phone'] !== \App\Support\BrandSettings::DEFAULTS['support_phone'] ? $brand['support_phone'] : null;
    $locale = $summary['locale'];
    $money = fn ($amount, $currency = null) => \App\Support\ReservationSummary::money($amount, $currency ?? $summary['currency'], $locale);
    $date = fn ($value) => $value ? \Carbon\Carbon::parse($value)->locale($locale)->translatedFormat('d M Y') : '—';
    $dateTime = fn ($value) => $value ? \Carbon\Carbon::parse($value)->setTimezone(config('app.timezone'))->locale($locale)->translatedFormat('d M Y, H:i T') : '—';
    $verified = (bool) ($transaction['verified'] ?? false);
    $method = !empty($transaction['method']) ? (\Illuminate\Support\Facades\Lang::has('messages.checkout.' . $transaction['method']) ? __('messages.checkout.' . $transaction['method']) : \Illuminate\Support\Str::headline($transaction['method'])) : '—';
    $fullyPaid = in_array($summary['payment_status'], ['paid', 'confirmed'], true);
    $validating = $summary['payment_status'] === 'pending_validation' && $summary['due'] !== null
        ? ((float) ($summary['validating'] ?? 0) > 0 ? $summary['validating'] : $summary['due'])
        : null;
    $statusLabel = match (true) {
        $fullyPaid => __('messages.transactional.status_paid'),
        $summary['payment_status'] === 'partial' => __('messages.transactional.partial_detail', ['amount' => $money($summary['due'])]),
        default => __('messages.reservation_summary.payment_' . $summary['payment_status']),
    };
@endphp
<html lang="{{ str_replace('_', '-', $locale) }}">
<head>
    <meta charset="UTF-8">
    <title>{{ __('messages.receipts.title') }} {{ $receipt->receipt_number }}</title>
    <style>
        @page { margin: 28px 34px 70px; }
        body { font-family: DejaVu Sans, sans-serif; color: #1d2a2d; font-size: 11px; line-height: 1.45; margin: 0; }
        table { width: 100%; border-collapse: collapse; }
        td, th { vertical-align: top; }
        .header { background: #0f5b4c; color: #ffffff; }
        .header td { padding: 16px 18px; }
        .logo { display: inline-block; width: 34px; height: 34px; line-height: 34px; text-align: center; background: #c9a24a; color: #ffffff; font-size: 18px; font-weight: bold; border-radius: 6px; }
        .brand { font-size: 18px; font-weight: bold; }
        .tagline { color: #cfe5dd; font-size: 9px; }
        .doc-title { font-size: 16px; font-weight: bold; letter-spacing: 1px; text-align: right; }
        .doc-meta { color: #cfe5dd; text-align: right; font-size: 10px; }
        .banner { margin-top: 16px; border-radius: 8px; }
        .banner td { padding: 14px 16px; }
        .banner-success { background: #ecf8f1; border: 1px solid #9fd4b5; }
        .banner-warning { background: #fff8e8; border: 1px solid #efd28d; }
        .banner-info { background: #eef5fb; border: 1px solid #b7d3ea; }
        .banner-title { font-size: 15px; font-weight: bold; }
        .amount-big { font-size: 22px; font-weight: bold; color: #0a3f35; text-align: right; }
        .muted { color: #5b6b70; }
        .label { color: #5b6b70; font-size: 9px; text-transform: uppercase; letter-spacing: 0.8px; font-weight: bold; }
        .section { margin-top: 14px; border: 1px solid #e3e8e5; border-radius: 8px; }
        .section > tbody > tr > td, .section > tr > td { padding: 12px 14px; }
        .section-title { font-size: 9px; font-weight: bold; text-transform: uppercase; letter-spacing: 1px; color: #5b6b70; margin: 0 0 8px; }
        .name { font-size: 13px; font-weight: bold; color: #0a3f35; }
        .kv td { padding: 4px 0; }
        .kv td.v { text-align: right; font-weight: bold; }
        .lines th { text-align: left; color: #5b6b70; font-size: 9px; font-weight: normal; border-bottom: 1px solid #e3e8e5; padding: 0 0 6px; }
        .lines td { padding: 7px 0; border-bottom: 1px solid #eef2f0; }
        .lines .amt { text-align: right; white-space: nowrap; }
        .totals td { padding: 5px 0; }
        .totals .amt { text-align: right; }
        .balance { margin-top: 8px; border-radius: 6px; color: #ffffff; }
        .balance td { padding: 10px 12px; font-size: 13px; font-weight: bold; }
        .footer { position: fixed; bottom: -50px; left: 0; right: 0; font-size: 8.5px; color: #5b6b70; border-top: 1px solid #e3e8e5; padding-top: 8px; }
    </style>
</head>
<body>
    <div class="footer">
        <strong style="color:#0a3f35;">{{ $brandName }}</strong> — {{ __('messages.transactional.footer_managed', ['brand' => $brandName]) }}
        @if ($contactEmail || $supportPhone)<br>{{ __('messages.transactional.footer_support') }} : {{ collect([$contactEmail, $supportPhone])->filter()->implode(' · ') }}@endif
        <br>{{ __('messages.transactional.receipt_electronic', ['brand' => $brandName]) }} &copy; {{ now()->year }} {{ $brandName }}. {{ __('messages.transactional.footer_rights') }}
    </div>

    <table class="header">
        <tr>
            <td style="width:55%;">
                <table><tr>
                    <td style="width:44px;padding:0;"><span class="logo">{{ $brandIcon }}</span></td>
                    <td style="padding:0 0 0 8px;"><div class="brand">{{ $brandName }}</div><div class="tagline">{{ __('messages.transactional.platform_role', ['brand' => $brandName]) }}</div></td>
                </tr></table>
            </td>
            <td>
                <div class="doc-title">{{ mb_strtoupper(__('messages.receipts.title')) }}</div>
                <div class="doc-meta">{{ __('messages.transactional.receipt_number') }} : {{ $receipt->receipt_number }}</div>
                <div class="doc-meta">{{ __('messages.transactional.payment_recorded_at') }} : {{ $dateTime($receipt->issued_at) }}</div>
            </td>
        </tr>
    </table>

    <table class="banner {{ $verified ? 'banner-success' : ($validating !== null ? 'banner-info' : 'banner-warning') }}">
        <tr>
            <td>
                <div class="banner-title" style="color:{{ $verified ? '#0a6b3f' : '#8a5a00' }};">{{ $verified ? __('messages.transactional.paid_confirmed') : __('messages.transactional.transaction_not_confirmed') }}</div>
                <div style="margin-top:4px;"><strong>{{ __('messages.transactional.status_label') }}</strong> {{ $statusLabel }}</div>
                <div class="muted" style="margin-top:2px;">{{ $transaction['is_guarantee'] ? __('messages.transactional.guarantee_notice') : ($verified ? __('messages.transactional.payment_received') : __('messages.transactional.payment_unverified')) }}</div>
            </td>
            <td style="width:38%;">
                <div class="label" style="text-align:right;">{{ $verified ? __('messages.transactional.amount_paid') : __('messages.transactional.transaction_amount') }}</div>
                <div class="amount-big">{{ $money($receipt->amount, $receipt->currency) }}</div>
            </td>
        </tr>
    </table>

    <table style="margin-top:14px;">
        <tr>
            <td style="width:49%;padding-right:1%;">
                <table class="section"><tr><td>
                    <p class="section-title">{{ __('messages.receipts.customer') }}</p>
                    <div class="name">{{ $summary['guest_name'] }}</div>
                    @if ($summary['guest_email'] && $summary['guest_email'] !== $summary['guest_name'])<div class="muted">{{ $summary['guest_email'] }}</div>@endif
                </td></tr></table>
            </td>
            <td style="width:49%;padding-left:1%;">
                <table class="section"><tr><td>
                    <p class="section-title">{{ __('messages.checkout.establishment') }}</p>
                    @if ($summary['establishment'])
                        <table><tr>
                            @if ($images['establishment'])<td style="width:52px;padding:0 8px 0 0;"><img src="{{ $images['establishment'] }}" style="width:48px;border-radius:4px;" alt=""></td>@endif
                            <td style="padding:0;">
                                <div class="name">{{ $summary['establishment']['name'] }}</div>
                                @if ($summary['establishment']['location'])<div class="muted">{{ $summary['establishment']['location'] }}</div>@endif
                                @if (!empty($summary['establishment']['email']) || !empty($summary['establishment']['phone']))<div class="muted">{{ collect([$summary['establishment']['email'] ?? null, $summary['establishment']['phone'] ?? null])->filter()->implode(' · ') }}</div>@endif
                            </td>
                        </tr></table>
                    @else
                        <div class="muted">—</div>
                    @endif
                </td></tr></table>
            </td>
        </tr>
    </table>

    <table class="section"><tr><td>
        <p class="section-title">{{ __('messages.checkout.property') }}</p>
        <table><tr>
            @if ($images['property'])<td style="width:130px;padding:0 12px 0 0;"><img src="{{ $images['property'] }}" style="width:126px;border-radius:6px;" alt=""></td>@endif
            <td style="padding:0;">
                <div class="name">{{ $summary['property']['name'] }}</div>
                @if ($summary['property']['location'])<div class="muted">{{ $summary['property']['location'] }}</div>@endif
                @if (!empty($summary['property']['description']))<div style="margin-top:4px;">{{ \Illuminate\Support\Str::limit($summary['property']['description'], 220) }}</div>@endif
                @if ($summary['property']['capacity'])<div style="margin-top:4px;font-weight:bold;color:#0a3f35;">{{ $summary['property']['capacity'] }}</div>@endif
            </td>
        </tr></table>
    </td></tr></table>

    <table class="section"><tr><td>
        <p class="section-title">{{ __('messages.checkout.order_summary') }}</p>
        <table>
            <tr>
                <td style="width:33%;"><div class="label">{{ __('messages.checkout.reference') }}</div><strong>{{ $summary['reference'] }}</strong></td>
                <td style="width:33%;"><div class="label">{{ __('messages.properties.check_in') }}</div><strong>{{ $date($summary['check_in']) }}</strong></td>
                <td style="width:34%;"><div class="label">{{ __('messages.properties.check_out') }}</div><strong>{{ $date($summary['check_out']) }}</strong></td>
            </tr>
            <tr>
                <td style="padding-top:8px;"><div class="label">{{ __('messages.reservation_summary.booked_at') }}</div>{{ $date($summary['booked_at']) }}</td>
                <td style="padding-top:8px;"><div class="label">{{ __('messages.properties.night_count') }}</div>{{ $summary['nights'] }}</td>
                <td style="padding-top:8px;"><div class="label">{{ __('messages.reservation.travelers') }}</div>{{ __('messages.reservation.travelers_summary', ['adults' => $summary['adults'], 'children' => $summary['children'], 'infants' => $summary['infants']]) }}</td>
            </tr>
        </table>
    </td></tr></table>

    <table style="margin-top:14px;">
        <tr>
            <td style="width:58%;padding-right:1%;">
                <table class="section"><tr><td>
                    <p class="section-title">{{ __('messages.transactional.section_financial') }}</p>
                    <table class="lines">
                        <thead><tr><th>{{ __('messages.checkout.product_description') }}</th><th class="amt" style="text-align:right;">{{ __('messages.checkout.line_amount') }}</th></tr></thead>
                        <tbody>
                            @forelse ($summary['lines'] as $line)
                                <tr>
                                    <td>@if ($line['is_room'])<strong>{{ $summary['property']['name'] }}</strong><br><span class="muted">{{ __('messages.checkout.room_calculation', ['nights' => $summary['nights'], 'rate' => $money($line['nightly_rate'], $line['currency'])]) }}</span>@else{{ $line['label'] }}@endif</td>
                                    <td class="amt">{{ $money($line['amount'], $line['currency']) }}</td>
                                </tr>
                            @empty
                                @foreach ([[$summary['property']['name'], $summary['subtotal']], [__('messages.properties.fees'), $summary['fees']], [__('messages.reservation.taxes'), $summary['taxes']], [__('messages.checkout.saved_adjustment'), $summary['adjustment']]] as [$label, $amount])
                                    @if ((float) $amount != 0)<tr><td>{{ $label }}</td><td class="amt">{{ $money($amount) }}</td></tr>@endif
                                @endforeach
                            @endforelse
                        </tbody>
                    </table>
                    @if ($summary['lines'])<div class="muted" style="margin-top:6px;font-size:8.5px;">{{ __('messages.checkout.included_tax_note') }}</div>@endif
                    <table class="totals" style="margin-top:8px;">
                        <tr><td><strong>{{ __('messages.reservation_summary.total') }}</strong></td><td class="amt"><strong>{{ $money($summary['total']) }}</strong></td></tr>
                        <tr><td>{{ __('messages.reservation_summary.paid') }}</td><td class="amt">{{ $money($summary['paid']) }}</td></tr>
                        @foreach ($summary['foreign_payments'] as $payment)
                            <tr><td>{{ __('messages.reservation_summary.foreign_payment') }}</td><td class="amt">{{ $money($payment['amount'], $payment['currency']) }}</td></tr>
                        @endforeach
                        @if ($validating !== null)
                            <tr><td>{{ __('messages.transactional.validation_in_progress') }}</td><td class="amt">{{ $money($validating) }}</td></tr>
                        @endif
                    </table>
                    <table class="balance" style="background:{{ $fullyPaid ? '#0a6b3f' : '#0f5b4c' }};">
                        <tr>
                            <td>{{ $validating !== null ? __('messages.reservation_summary.remaining_after_validation') : __('messages.reservation_summary.remaining') }}</td>
                            <td style="text-align:right;">{{ $summary['due'] === null ? __('messages.reservation_summary.balance_unknown') : $money($validating !== null ? max(0, (float) $summary['due'] - (float) $validating) : $summary['due']) }}</td>
                        </tr>
                    </table>
                </td></tr></table>
            </td>
            <td style="width:41%;padding-left:1%;">
                <table class="section"><tr><td>
                    <p class="section-title">{{ __('messages.transactional.section_payment') }}</p>
                    <table class="kv">
                        <tr><td class="muted">{{ __('messages.transactional.status_label') }}</td><td class="v" style="color:{{ $verified ? '#0a6b3f' : '#8a5a00' }};">{{ $verified ? __('messages.transactional.paid_confirmed') : __('messages.reservation_summary.payment_' . $summary['payment_status']) }}</td></tr>
                        <tr><td class="muted">{{ __('messages.transactional.payment_method') }}</td><td class="v">{{ $method }}</td></tr>
                        @if ($verified && !empty($transaction['paid_at']))<tr><td class="muted">{{ __('messages.transactional.paid_at') }}</td><td class="v">{{ $dateTime($transaction['paid_at']) }}</td></tr>@endif
                        @if (!empty($transaction['transaction_number']))<tr><td class="muted">{{ __('messages.transactional.transaction_number') }}</td><td class="v">{{ $transaction['transaction_number'] }}</td></tr>@endif
                        @if (!empty($transaction['reference']))<tr><td class="muted">{{ __('messages.transactional.payment_reference') }}</td><td class="v">{{ $transaction['reference'] }}</td></tr>@endif
                        <tr><td class="muted">{{ __('messages.transactional.currency') }}</td><td class="v">{{ $receipt->currency }}</td></tr>
                        <tr><td class="muted">{{ $verified ? __('messages.transactional.amount_paid') : __('messages.transactional.transaction_amount') }}</td><td class="v" style="font-size:13px;">{{ $money($receipt->amount, $receipt->currency) }}</td></tr>
                    </table>
                </td></tr></table>
            </td>
        </tr>
    </table>

    <p class="muted" style="margin-top:14px;font-size:9.5px;">{{ __('messages.transactional.receipt_pdf_keep') }}</p>
</body>
</html>
