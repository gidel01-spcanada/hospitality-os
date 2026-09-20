@forelse ($items as $money)
    <span class="report-money-value">{{ number_format((float) $money['amount'], 2, ',', ' ') }} {{ $money['currency'] }}</span>
@empty
    <span class="report-money-value">—</span>
@endforelse
