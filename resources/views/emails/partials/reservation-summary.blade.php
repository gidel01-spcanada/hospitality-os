@php
    $emailSummary = $reservation_summary;
    $emailMoney = fn ($amount, $currency) => \App\Support\ReservationSummary::money($amount, $currency, $emailSummary['locale']);
    $emailDate = fn ($date) => $date ? \Carbon\Carbon::parse($date)->locale($emailSummary['locale'])->translatedFormat('D d M Y') : '—';
    $emailHasPaid = (float) $emailSummary['paid'] > 0 || ! empty($emailSummary['foreign_payments']);
    $emailLabel = 'display:block;font-size:12px;color:#5b6b70;';
    $emailValidating = $emailSummary['payment_status'] === 'pending_validation' && $emailSummary['due'] !== null;
    $emailValidatingAmount = (float) ($emailSummary['validating'] ?? 0) > 0 ? $emailSummary['validating'] : $emailSummary['due'];
    $emailAfterValidation = $emailValidating ? number_format(max(0, (float) $emailSummary['due'] - (float) $emailValidatingAmount), 2, '.', '') : null;
@endphp

<x-email.payment-status :summary="$emailSummary" />

@if ($emailSummary['establishment'])
    <x-email.card :title="__('messages.checkout.establishment')">
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
            <tr>
                @if ($emailSummary['establishment']['image'])<td class="email-col" width="112" style="padding:0 16px 0 0;vertical-align:top;"><img class="email-media-image" src="{{ $emailSummary['establishment']['image'] }}" alt="{{ $emailSummary['establishment']['name'] }}" width="96" style="display:block;width:96px;height:auto;border-radius:8px;"></td>@endif
                <td class="email-col" style="vertical-align:top;">
                    <p style="margin:0;font-size:18px;font-weight:bold;color:#0a3f35;">{{ $emailSummary['establishment']['name'] }}</p>
                    @if ($emailSummary['establishment']['location'])<p style="margin:2px 0 0;font-size:14px;color:#5b6b70;">{{ $emailSummary['establishment']['location'] }}</p>@endif
                    @if (!empty($emailSummary['establishment']['description']))<p style="margin:8px 0 0;font-size:14px;">{{ \Illuminate\Support\Str::limit($emailSummary['establishment']['description'], 200) }}</p>@endif
                    @if (!empty($emailSummary['establishment']['email']) || !empty($emailSummary['establishment']['phone']))
                        <p style="margin:8px 0 0;font-size:13px;">
                            @if (!empty($emailSummary['establishment']['email']))<a href="mailto:{{ $emailSummary['establishment']['email'] }}" style="color:#0f5b4c;">{{ $emailSummary['establishment']['email'] }}</a>@endif
                            @if (!empty($emailSummary['establishment']['email']) && !empty($emailSummary['establishment']['phone'])) · @endif
                            @if (!empty($emailSummary['establishment']['phone'])){{ $emailSummary['establishment']['phone'] }}@endif
                        </p>
                    @endif
                </td>
            </tr>
        </table>
    </x-email.card>
@endif

<x-email.card :title="__('messages.checkout.property')">
    @if ($emailSummary['property']['image'])<img class="email-media-image" src="{{ $emailSummary['property']['image'] }}" alt="{{ $emailSummary['property']['name'] }}" width="534" style="display:block;width:100%;max-width:534px;height:auto;margin:0 0 14px;border-radius:8px;">@endif
    <p style="margin:0;font-size:18px;font-weight:bold;color:#0a3f35;">{{ $emailSummary['property']['name'] }}</p>
    @if ($emailSummary['property']['location'])<p style="margin:2px 0 0;font-size:14px;color:#5b6b70;">{{ $emailSummary['property']['location'] }}</p>@endif
    @if (!empty($emailSummary['property']['description']))<p style="margin:8px 0 0;font-size:14px;">{{ \Illuminate\Support\Str::limit($emailSummary['property']['description'], 200) }}</p>@endif
    @if ($emailSummary['property']['capacity'])<p style="margin:8px 0 0;font-size:13px;color:#0a3f35;font-weight:bold;">{{ $emailSummary['property']['capacity'] }}</p>@endif
</x-email.card>

<x-email.card :title="__('messages.checkout.order_summary')">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="font-size:15px;">
        <tr>
            <td class="email-col" width="50%" style="padding:0 12px 14px 0;vertical-align:top;"><span style="{{ $emailLabel }}">{{ __('messages.checkout.reference') }}</span><strong>{{ $emailSummary['reference'] }}</strong></td>
            <td class="email-col" width="50%" style="padding:0 0 14px;vertical-align:top;"><span style="{{ $emailLabel }}">{{ __('messages.reservation_summary.booked_at') }}</span>{{ $emailDate($emailSummary['booked_at']) }}</td>
        </tr>
        <tr>
            <td class="email-col" style="padding:0 12px 14px 0;vertical-align:top;"><span style="{{ $emailLabel }}">{{ __('messages.properties.check_in') }}</span><strong>{{ $emailDate($emailSummary['check_in']) }}</strong></td>
            <td class="email-col" style="padding:0 0 14px;vertical-align:top;"><span style="{{ $emailLabel }}">{{ __('messages.properties.check_out') }}</span><strong>{{ $emailDate($emailSummary['check_out']) }}</strong></td>
        </tr>
        <tr>
            <td class="email-col" style="padding:0 12px 14px 0;vertical-align:top;"><span style="{{ $emailLabel }}">{{ __('messages.properties.night_count') }}</span>{{ $emailSummary['nights'] }}</td>
            <td class="email-col" style="padding:0 0 14px;vertical-align:top;"><span style="{{ $emailLabel }}">{{ __('messages.reservation.travelers') }}</span>{{ __('messages.reservation.travelers_summary', ['adults' => $emailSummary['adults'], 'children' => $emailSummary['children'], 'infants' => $emailSummary['infants']]) }}</td>
        </tr>
        <tr>
            <td class="email-col" colspan="2" style="padding:12px 0 0;border-top:1px solid #e3e8e5;vertical-align:top;"><span style="{{ $emailLabel }}">{{ __('messages.checkout.client') }}</span>{{ $emailSummary['guest_name'] }}@if (!empty($emailSummary['guest_email']) && $emailSummary['guest_email'] !== $emailSummary['guest_name'])<br><span style="font-size:13px;color:#5b6b70;">{{ $emailSummary['guest_email'] }}</span>@endif</td>
        </tr>
    </table>
    @if ($emailSummary['customer_note'])<p style="margin:12px 0 0;padding:10px 12px;background:#f7f9f8;border-radius:6px;font-size:13px;"><span style="{{ $emailLabel }}">{{ __('messages.properties.customer_note') }}</span>{{ $emailSummary['customer_note'] }}</p>@endif
</x-email.card>

@if (!empty($emailSummary['stay_notes']))
    <x-email.card :title="__('messages.transactional.important_notes')" tone="warning">
        @foreach ($emailSummary['stay_notes'] as $note)
            <p data-email-stay-note="{{ $note['type'] }}" style="margin:0 0 6px;font-size:14px;color:#1d2a2d;">{{ $note['type'] === 'electricity' ? '⚡ ' : '• ' }}{{ $note['text'] }}</p>
        @endforeach
    </x-email.card>
@endif

<x-email.card :title="__('messages.transactional.section_financial')">
    <table width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;table-layout:fixed;font-size:14px;">
        <thead><tr><th scope="col" style="width:62%;padding:0 8px 8px 0;text-align:left;border-bottom:1px solid #e3e8e5;color:#5b6b70;font-size:12px;font-weight:normal;">{{ __('messages.checkout.product_description') }}</th><th scope="col" style="padding:0 0 8px;text-align:right;border-bottom:1px solid #e3e8e5;color:#5b6b70;font-size:12px;font-weight:normal;">{{ __('messages.checkout.line_amount') }}</th></tr></thead>
        <tbody>
            @forelse ($emailSummary['lines'] as $line)
                <tr><th scope="row" style="padding:10px 8px 10px 0;text-align:left;vertical-align:top;border-bottom:1px solid #eef2f0;font-weight:normal;overflow-wrap:anywhere;">
                    @if ($line['is_room'])<strong>{{ $emailSummary['property']['name'] }}</strong><br><span style="font-size:12px;color:#5b6b70;">{{ __('messages.checkout.room_calculation', ['nights' => $emailSummary['nights'], 'rate' => $emailMoney($line['nightly_rate'], $line['currency'])]) }}</span>@else{{ $line['label'] }}@endif
                </th><td style="padding:10px 0;text-align:right;vertical-align:top;border-bottom:1px solid #eef2f0;overflow-wrap:anywhere;{{ (float) $line['amount'] < 0 ? 'color:#0a6b3f;' : '' }}">{{ $emailMoney($line['amount'], $line['currency']) }}</td></tr>
            @empty
                @foreach ([['label' => $emailSummary['property']['name'], 'amount' => $emailSummary['subtotal']], ['label' => __('messages.properties.fees'), 'amount' => $emailSummary['fees']], ['label' => __('messages.reservation.taxes'), 'amount' => $emailSummary['taxes']], ['label' => __('messages.checkout.saved_adjustment'), 'amount' => $emailSummary['adjustment']]] as $line)
                    @if ((float) $line['amount'] != 0)<tr><th scope="row" style="padding:10px 8px 10px 0;text-align:left;font-weight:normal;border-bottom:1px solid #eef2f0;">{{ $line['label'] }}</th><td style="padding:10px 0;text-align:right;border-bottom:1px solid #eef2f0;">{{ $emailMoney($line['amount'], $emailSummary['currency']) }}</td></tr>@endif
                @endforeach
            @endforelse
        </tbody>
    </table>
    @if ($emailSummary['lines'])<p style="margin:8px 0 0;font-size:12px;color:#5b6b70;">{{ __('messages.checkout.included_tax_note') }}</p>@endif

    <table width="100%" cellspacing="0" cellpadding="0" style="margin-top:14px;border-collapse:collapse;">
        <x-email.row :label="__('messages.reservation_summary.total')" :emphasis="true">{{ $emailMoney($emailSummary['total'], $emailSummary['currency']) }}</x-email.row>
        @if ($emailHasPaid)
            <x-email.row :label="__('messages.reservation_summary.paid')" data-email-paid>{{ $emailMoney($emailSummary['paid'], $emailSummary['currency']) }}</x-email.row>
            @foreach ($emailSummary['foreign_payments'] as $payment)
                <x-email.row :label="__('messages.reservation_summary.foreign_payment')">{{ $emailMoney($payment['amount'], $payment['currency']) }}</x-email.row>
            @endforeach
        @endif
    </table>
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin-top:12px;background:{{ $emailValidating ? '#1f5f8b' : '#0f5b4c' }};border-radius:8px;border-collapse:separate;">
        <tr>
            @if ($emailValidating)
                <th scope="row" style="padding:14px 16px;text-align:left;font-size:16px;color:#ffffff;">{{ __('messages.transactional.validation_in_progress') }}</th>
                <td data-email-validating width="45%" style="padding:14px 16px;text-align:right;font-size:20px;font-weight:bold;color:#ffffff;overflow-wrap:anywhere;">{{ $emailMoney($emailValidatingAmount, $emailSummary['currency']) }}</td>
            @else
                <th scope="row" style="padding:14px 16px;text-align:left;font-size:16px;color:#ffffff;">{{ (float) $emailSummary['paid'] > 0 ? __('messages.reservation_summary.remaining') : __('messages.reservation_summary.due') }}</th>
                <td data-email-due width="45%" style="padding:14px 16px;text-align:right;font-size:20px;font-weight:bold;color:#ffffff;overflow-wrap:anywhere;">{{ $emailSummary['due'] === null ? __('messages.reservation_summary.balance_unknown') : $emailMoney($emailSummary['due'], $emailSummary['currency']) }}</td>
            @endif
        </tr>
    </table>
    @if ($emailValidating && (float) $emailAfterValidation > 0)
        <p style="margin:8px 0 0;font-size:13px;color:#5b6b70;">{{ __('messages.transactional.remaining_after_validation', ['amount' => $emailMoney($emailAfterValidation, $emailSummary['currency'])]) }}</p>
    @endif
</x-email.card>
