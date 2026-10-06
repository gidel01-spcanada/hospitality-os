@props(['amount', 'property'])
@php
    $preferredCurrency = session('display_currency');
    $convertedAmount = $preferredCurrency ? \App\Support\DisplayCurrency::amount((float) $amount, $property, $preferredCurrency) : null;
    $originalPrice = \App\Support\ReservationSummary::money($amount, $property->currency);
@endphp
@if ($preferredCurrency && $preferredCurrency !== $property->currency && $convertedAmount !== null)
    <span title="{{ $originalPrice }}" data-display-currency="{{ $preferredCurrency }}">{{ \App\Support\ReservationSummary::money($convertedAmount, $preferredCurrency) }}</span>
    <small class="display-price-note">{{ __('messages.comparison.approximate') }} · {{ $originalPrice }}</small>
@else
    {{ $originalPrice }}
    @if ($preferredCurrency && $preferredCurrency !== $property->currency)<small class="display-price-note">{{ __('messages.comparison.rate_missing') }} ({{ $preferredCurrency }})</small>@endif
@endif