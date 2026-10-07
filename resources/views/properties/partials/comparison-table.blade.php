@php
    $money = fn ($amount, $property) => \App\Support\DisplayCurrency::label((float) $amount, $property, $currency);
    $unknown = __('messages.comparison.unknown');
@endphp
@if ($missingCount)
    <p class="comparison-warning" role="alert">{{ __('messages.comparison.missing', ['count' => $missingCount]) }}</p>
@endif
@if ($columns->count() < 2)
    <div class="comparison-empty">
        <h2>{{ __('messages.comparison.choose_two') }}</h2>
        @foreach ($columns as $column)
            <p>{{ $column['property']->localized('name') }} <a href="{{ $column['url'] }}">{{ __('messages.properties.view_details') }}</a>
                <button type="button" class="btn btn-ghost" data-comparison-remove="{{ $column['property']->id }}" aria-label="{{ __('messages.comparison.remove', ['name' => $column['property']->localized('name')]) }}">&times;</button>
            </p>
        @endforeach
    </div>
@else
    <div class="comparison-table-scroll" tabindex="0" aria-label="{{ __('messages.properties.compare_title') }}">
        <table class="comparison-matrix" style="--comparison-columns:{{ $columns->count() }}">
            <caption class="sr-only">{{ __('messages.properties.compare_title') }}</caption>
            <thead><tr><th scope="col" class="comparison-criteria-heading">{{ __('messages.properties.compare_criteria') }}</th>
                @foreach ($columns as $column)
                    @php
                        $property = $column['property'];
                    @endphp
                    <th scope="col" data-comparison-column="{{ $property->id }}">
                        <article class="comparison-product">
                            <button type="button" class="comparison-remove" data-comparison-remove="{{ $property->id }}" title="{{ __('messages.comparison.remove', ['name' => $property->localized('name')]) }}" aria-label="{{ __('messages.comparison.remove', ['name' => $property->localized('name')]) }}">&times;</button>
                            @if ($column['image'])<img src="{{ asset($column['image']) }}" alt="{{ $property->localized('name') }}" width="320" height="180">@else<div class="comparison-no-image">{{ __('messages.comparison.no_image') }}</div>@endif
                            <h2><a href="{{ $column['url'] }}">{{ $property->localized('name') }}</a></h2>
                            <p class="comparison-location">{{ $property->city ?: $property->establishment?->city ?: $unknown }}</p>
                            <p class="comparison-rating">{{ $column['rating'] === null ? __('messages.properties.new_listing') : number_format($column['rating'], 1, app()->getLocale() === 'fr' ? ',' : '.', ' ') . ' / 5' }} · {{ trans_choice('messages.properties.review_count_short', $column['review_count'], ['count' => $column['review_count']]) }}</p>
                            <strong class="comparison-price" title="{{ (float) $property->nightly_rate_xof > 0 ? \App\Support\ReservationSummary::money($property->nightly_rate_xof, $property->currency) : __('messages.comparison.price_missing') }}">{{ (float) $property->nightly_rate_xof > 0 ? $money($property->nightly_rate_xof, $property) : __('messages.comparison.price_missing') }}</strong>
                            <small>{{ __('messages.properties.night_short') }}@if ($currency !== $property->currency) · {{ __('messages.comparison.approximate') }}@endif</small>
                            @if ($currency === $property->currency && $property->establishment?->secondary_currency && $property->establishment->secondary_currency !== $currency && (float) $property->establishment->secondary_currency_rate > 0 && (float) $property->nightly_rate_xof > 0)
                                <small>≈ {{ \App\Support\DisplayCurrency::label((float) $property->nightly_rate_xof, $property, $property->establishment->secondary_currency) }} · {{ __('messages.comparison.approximate') }}</small>
                            @endif
                            @if ($column['pricing'])
                                <p class="comparison-total">{{ __('messages.reservation_summary.total') }} : <strong>{{ $money($column['pricing']['total_amount'], $property) }}</strong></p>
                                <small>{{ __('messages.properties.for_nights', ['nights' => $column['pricing']['nights']]) }} · {{ __('messages.properties.taxes_fees_included') }}</small>
                                @if ($column['available'] === false)<small>{{ __('messages.comparison.estimate') }}</small>@endif
                            @endif
                            <p class="comparison-availability {{ $column['available'] === false ? 'is-unavailable' : '' }}">{{ $column['available'] === null ? __('messages.comparison.select_dates') : ($column['available'] ? __('messages.comparison.available') : __('messages.comparison.unavailable')) }}</p>
                            <a class="btn btn-primary" href="{{ $column['url'] }}">{{ __('messages.properties.view_details') }}</a>
                        </article>
                    </th>
                @endforeach
            </tr></thead>
            @foreach ($groups as $group)
                <tbody data-comparison-group="{{ $group['key'] }}">
                    <tr class="comparison-group-heading"><th colspan="{{ $columns->count() + 1 }}"><button type="button" data-comparison-collapse aria-expanded="{{ $group['open'] ? 'true' : 'false' }}" aria-controls="comparison-group-{{ $group['key'] }}"><span>{{ $group['label'] }}</span><span aria-hidden="true">{{ $group['open'] ? '−' : '+' }}</span></button></th></tr>
                </tbody>
                <tbody id="comparison-group-{{ $group['key'] }}" data-comparison-rows="{{ $group['key'] }}" @if (! $group['open']) hidden @endif>
                    @foreach ($group['rows'] as $row)
                        @php
                            $different = $row['different'];
                            $moneyValues = $row['type'] === 'money' ? $columns->map(fn ($column, $index) => $row['values'][$index] === null ? null : \App\Support\DisplayCurrency::amount((float) $row['values'][$index], $column['property'], $currency)) : collect();
                            $missingRate = $moneyValues->contains(fn ($amount) => $amount === null);
                            if ($row['type'] === 'money') $different = $missingRate || $moneyValues->unique()->count() > 1;
                            $bestMoney = $row['best'] !== null && ! $missingRate ? $moneyValues->min() : null;
                        @endphp
                        <tr data-comparison-row data-comparison-key="{{ $row['key'] }}" data-different="{{ $different ? 'true' : 'false' }}" @if (! $different) hidden @endif>
                            <th scope="row">{{ $row['label'] }}</th>
                            @foreach ($columns as $index => $column)
                                @php
                                    $property = $column['property'];
                                    $value = $row['values'][$index];
                                    $best = $different && ($row['type'] === 'money' ? ($bestMoney !== null && $moneyValues[$index] === $bestMoney && $column['available'] !== false) : ($row['best'] !== null && $value === $row['best']));
                                @endphp
                                <td class="{{ $different ? 'comparison-cell-different' : '' }} {{ $best || ($different && $row['type'] === 'amenity' && $value === true) ? 'comparison-cell-highlight' : '' }}">
                                    @if ($row['type'] === 'availability' && $value === null)
                                        {{ __('messages.comparison.select_dates') }}
                                    @elseif ($value === null || $value === '')
                                        <span class="comparison-unknown">{{ $unknown }}</span>
                                    @elseif ($row['type'] === 'money')
                                        <span title="{{ \App\Support\ReservationSummary::money($value, $property->currency) }}">{{ $money($value, $property) }}</span>
                                        @if ($currency !== $property->currency)<small>{{ __('messages.comparison.approximate') }}</small>@endif
                                    @elseif ($row['type'] === 'amenity')
                                        <span class="comparison-amenity" aria-label="{{ $value ? __('messages.comparison.present') : __('messages.comparison.not_listed') }}">{{ $value ? '✓' : '—' }}</span><small>{{ $value ? __('messages.comparison.present') : __('messages.comparison.not_listed') }}</small>
                                    @elseif ($row['type'] === 'availability')
                                        {{ $value ? __('messages.comparison.available') : __('messages.comparison.unavailable') }}
                                    @elseif ($row['type'] === 'map')
                                        <a class="inline-link" href="{{ $value }}" target="_blank" rel="noopener noreferrer">{{ __('messages.properties.location') }}</a>
                                    @elseif ($row['type'] === 'type')
                                        {{ __('messages.admin.type_' . $value) }}
                                    @elseif ($row['type'] === 'rating')
                                        {{ number_format((float) $value, 1, app()->getLocale() === 'fr' ? ',' : '.', ' ') }} / 5
                                    @else
                                        {{ $value }}
                                    @endif
                                    @if ($row['key'] === 'room_subtotal' && $column['pricing'])
                                        <small>{{ __('messages.checkout.room_calculation', ['nights' => $column['pricing']['nights'], 'rate' => $money($column['pricing']['nightly_rate'], $property)]) }}</small>
                                    @endif
                                    @if ($best)<small class="comparison-highlight-label">{{ __('messages.comparison.highlight_' . $row['key']) }}</small>@endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                    <tr data-comparison-no-difference><td colspan="{{ $columns->count() + 1 }}" class="comparison-same">{{ __('messages.comparison.same') }}</td></tr>
                </tbody>
            @endforeach
        </table>
    </div>
    @if ($hasDates)<p class="comparison-limit">{{ __('messages.comparison.tax_note') }}</p>@endif
@endif