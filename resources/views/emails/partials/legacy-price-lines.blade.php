@if (!empty($price_lines) || isset($total_amount))
    <x-email.card :title="__('messages.transactional.section_financial')">
        <table width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;">
            @foreach ($price_lines ?? [] as $line)
                <x-email.row :label="$line['label']">{{ \App\Support\ReservationSummary::money($line['amount'], $line['currency']) }}</x-email.row>
            @endforeach
            @isset($total_amount)
                <x-email.row :label="__('messages.reservation_summary.total')" :emphasis="true">{{ \App\Support\ReservationSummary::money($total_amount, $currency) }}</x-email.row>
            @endisset
        </table>
    </x-email.card>
@endif
