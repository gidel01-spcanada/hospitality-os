@props([
    'variant' => 'catalog',
    'action',
    'filters' => [],
    'destinations' => [],
    'establishments' => [],
    'amenities' => [],
    'filterCurrency' => 'XOF',
    'priceCeiling' => 5000000,
    'resultsCount' => null,
    'isCustomer' => false,
    'preserve' => [],
    'anchor' => null,
    'searchLabel' => null,
])
@php
    $isHome = $variant === 'home';
    $isEstablishment = $variant === 'establishment';
    $prefix = 'property-filter-' . ($isEstablishment ? 'establishment' : ($isHome ? 'home' : 'catalog'));
    $selectedAmenities = collect($filters['amenities'] ?? [])->map(fn ($id) => (int) $id)->values();
    $popularSlugs = ['air-conditioning', 'wifi', 'kitchen', 'private-bathroom', 'balcony', 'parking', 'pool', 'laundry'];
    $amenityItems = collect($amenities);
    $popularAmenities = $amenityItems->filter(fn ($amenity) => in_array($amenity->slug, $popularSlugs, true));
    $otherAmenityGroups = $amenityItems->reject(fn ($amenity) => in_array($amenity->slug, $popularSlugs, true))
        ->groupBy(fn ($amenity) => $amenity->category?->slug ?: 'other');
    $categoryOrder = ['comfort-utilities', 'kitchen-dining', 'bathroom-laundry', 'media-technology', 'outdoors-wellness', 'layout-views', 'logistics-accessibility', 'other'];
    $otherAmenityGroups = $otherAmenityGroups->sortBy(fn ($items, $slug) => array_search($slug, $categoryOrder, true) === false ? 999 : array_search($slug, $categoryOrder, true));
    $amenityCount = $selectedAmenities->count();
    $filtersForLinks = $filters;
    if (($filtersForLinks['sort'] ?? 'recommended') === 'recommended') {
        unset($filtersForLinks['sort']);
    }
    $activeCount = collect($filtersForLinks)->reject(fn ($value) => $value === null || $value === '' || $value === false || $value === [])->sum(fn ($value) => is_array($value) ? count($value) : 1);
    $buildUrl = function (array $query) use ($action, $preserve, $anchor): string {
        $query = array_filter(array_merge($preserve, $query), fn ($value) => $value !== null && $value !== '' && $value !== []);
        return $action . ($query ? '?' . http_build_query($query) : '') . ($anchor ? '#' . ltrim($anchor, '#') : '');
    };
    $removeUrl = function (string $key, mixed $value = null) use ($filtersForLinks, $buildUrl): string {
        $next = $filtersForLinks;
        if ($key === 'price') {
            unset($next['min_price'], $next['max_price']);
        } elseif ($key === 'dates') {
            unset($next['check_in'], $next['check_out']);
        } elseif ($key === 'destination') {
            unset($next['destination'], $next['city']);
        } elseif ($key === 'amenities') {
            $next['amenities'] = array_values(array_filter((array) ($next['amenities'] ?? []), fn ($id) => (int) $id !== (int) $value));
            if (! $next['amenities']) unset($next['amenities']);
        } else {
            unset($next[$key]);
        }
        return $buildUrl($next);
    };
    $formatDate = fn ($date) => filled($date) ? \Carbon\Carbon::parse($date)->locale(app()->getLocale())->translatedFormat('d M') : null;
    $numberDecimal = app()->getLocale() === 'en' ? '.' : ',';
    $numberThousands = app()->getLocale() === 'en' ? ',' : ' ';
    $priceMinimumLabel = number_format((float) ($filters['min_price'] ?? 0), 0, $numberDecimal, $numberThousands);
    $priceMaximumLabel = number_format((float) ($filters['max_price'] ?? $priceCeiling), 0, $numberDecimal, $numberThousands);
    $chipItems = [];
    if (filled($filters['destination'] ?? null)) $chipItems[] = ['key' => 'destination', 'label' => $filters['destination']];
    if (filled($filters['establishment'] ?? null) && ! $isEstablishment) {
        $selectedEstablishment = collect($establishments)->firstWhere('id', (int) $filters['establishment']);
        $chipItems[] = ['key' => 'establishment', 'label' => $selectedEstablishment?->localized('name') ?? __('messages.common.establishment')];
    }
    if (filled($filters['check_in'] ?? null) || filled($filters['check_out'] ?? null)) $chipItems[] = ['key' => 'dates', 'label' => trim(($formatDate($filters['check_in'] ?? null) ?? '…') . ' – ' . ($formatDate($filters['check_out'] ?? null) ?? '…'))];
    if (filled($filters['guests'] ?? null)) $chipItems[] = ['key' => 'guests', 'label' => trans_choice('messages.properties.guests_count', (int) $filters['guests'], ['count' => (int) $filters['guests']])];
    if (filled($filters['property_type'] ?? null)) $chipItems[] = ['key' => 'property_type', 'label' => __('messages.admin.type_' . $filters['property_type'])];
    if (filled($filters['bedrooms'] ?? null)) $chipItems[] = ['key' => 'bedrooms', 'label' => __('messages.properties.bedrooms') . ' ≥ ' . $filters['bedrooms']];
    if (filled($filters['beds'] ?? null)) $chipItems[] = ['key' => 'beds', 'label' => __('messages.properties.beds') . ' ≥ ' . $filters['beds']];
    if (filled($filters['min_price'] ?? null) || filled($filters['max_price'] ?? null)) $chipItems[] = ['key' => 'price', 'label' => ($filters['min_price'] ?? '0') . ' – ' . ($filters['max_price'] ?? '∞') . ' ' . $filterCurrency];
    if (! empty($filters['flexible_cancellation'])) $chipItems[] = ['key' => 'flexible_cancellation', 'label' => __('messages.properties.flexible_cancellation_filter')];
    if (! empty($filters['favorites']) && $isCustomer) $chipItems[] = ['key' => 'favorites', 'label' => __('messages.favorites.only')];
    foreach ($selectedAmenities as $amenityId) {
        $amenity = $amenityItems->firstWhere('id', $amenityId);
        if ($amenity) $chipItems[] = ['key' => 'amenities', 'value' => $amenityId, 'label' => $amenity->name];
    }
    if (filled($filters['sort'] ?? null) && $filters['sort'] !== 'recommended') $chipItems[] = ['key' => 'sort', 'label' => __('messages.properties.sort_' . $filters['sort'])];
    $activeCount = count($chipItems);
    $controlAttrs = 'data-date-range-picker data-incomplete-message="' . e(__('messages.home.select_both_dates')) . '" data-past-message="' . e(__('messages.home.past_dates')) . '" data-start-label="' . e(__('messages.home.arrival')) . '" data-end-label="' . e(__('messages.home.departure')) . '" data-placeholder="' . e(__('messages.home.select_dates')) . '"';
@endphp

@if (! $isHome && $chipItems)
    <div class="selected-filter-chips" aria-label="{{ __('messages.properties.active_filters_label') }}">
        @foreach ($chipItems as $chip)
            <a class="selected-filter-chip" href="{{ $removeUrl($chip['key'], $chip['value'] ?? null) }}">
                <span>{{ $chip['label'] }}</span><span aria-hidden="true">×</span>
                <span class="sr-only">{{ __('messages.properties.remove_filter', ['filter' => $chip['label']]) }}</span>
            </a>
        @endforeach
        <a class="selected-filter-clear" href="{{ $buildUrl([]) }}">{{ __('messages.properties.reset') }}</a>
    </div>
@endif

@if ($isHome)
    <form method="GET" action="{{ $action }}" class="search-form property-filter-home" data-property-filter-home data-property-filter-form data-share-search-form>
        <div class="property-filter-primary">
            <div class="filter-field">
                <label for="{{ $prefix }}-destination"><x-filter-icon name="pin" />{{ __('messages.home.destination') }}</label>
                <select id="{{ $prefix }}-destination" name="destination">
                    <option value="">{{ __('messages.properties.all_destinations') }}</option>
                    @foreach ($destinations as $destination)
                        <option value="{{ $destination }}" @selected(($filters['destination'] ?? null) === $destination)>{{ $destination }}</option>
                    @endforeach
                </select>
            </div>
            <div class="date-range-picker filter-field" {!! $controlAttrs !!}>
                <label for="{{ $prefix }}-date-range-trigger"><x-filter-icon name="calendar" />{{ __('messages.home.dates') }}</label>
                <button type="button" id="{{ $prefix }}-date-range-trigger" class="date-range-trigger" data-date-range-trigger aria-expanded="false"><span data-date-range-label>{{ ($filters['check_in'] ?? null) && ($filters['check_out'] ?? null) ? $filters['check_in'] . ' → ' . $filters['check_out'] : __('messages.home.select_dates') }}</span></button>
                <input type="hidden" name="check_in" value="{{ $filters['check_in'] ?? '' }}" data-date-range-start>
                <input type="hidden" name="check_out" value="{{ $filters['check_out'] ?? '' }}" data-date-range-end>
                <div class="date-range-popover" data-date-range-popover hidden></div>
            </div>
            <div class="filter-field" data-number-stepper>
                <label for="{{ $prefix }}-guests"><x-filter-icon name="users" />{{ __('messages.properties.guests') }}</label>
                <div class="filter-stepper"><button type="button" data-step="-1" aria-label="{{ __('messages.properties.decrease', ['field' => __('messages.properties.guests')]) }}">−</button><input id="{{ $prefix }}-guests" name="guests" type="number" min="1" max="100" inputmode="numeric" value="{{ $filters['guests'] ?? '1' }}" data-step-input><button type="button" data-step="1" aria-label="{{ __('messages.properties.increase', ['field' => __('messages.properties.guests')]) }}">+</button></div>
            </div>
            <div class="filter-field">
                <label for="{{ $prefix }}-type"><x-filter-icon name="home" />{{ __('messages.properties.property_type') }}</label>
                <select id="{{ $prefix }}-type" name="property_type"><option value="">{{ __('messages.properties.all_types') }}</option>@foreach (['apartment', 'house', 'villa', 'studio', 'room', 'other'] as $type)<option value="{{ $type }}" @selected(($filters['property_type'] ?? null) === $type)>{{ __('messages.admin.type_' . $type) }}</option>@endforeach</select>
            </div>
        </div>
        <button type="submit" class="btn btn-primary btn-full">{{ $searchLabel ?? __('messages.home.search') }}</button>
    </form>
@else
    <section class="property-filter-shell" data-property-filter data-active-count="{{ $activeCount }}">
        <details class="property-filters" data-filter-drawer data-filter-count-template="{{ __('messages.properties.filters_count', ['count' => '__COUNT__']) }}" @if ($isEstablishment) data-establishment-filters @endif>
            <summary aria-controls="{{ $prefix }}-form">
                <span class="filter-summary-label"><x-filter-icon name="sliders"/><strong>{{ __('messages.properties.filter_heading') }}</strong><span class="filter-count-badge">{{ __('messages.properties.filters_count', ['count' => $activeCount]) }}</span></span>
                <span class="filter-summary-chevron" aria-hidden="true">⌄</span>
            </summary>
            <form id="{{ $prefix }}-form" method="GET" action="{{ $action }}" class="property-filter-form" data-property-filter-form @if ($isEstablishment) data-share-establishment-form @else data-share-search-form @endif>
                @foreach ($preserve as $key => $value)
                    @if (filled($value))<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endif
                @endforeach
                <section class="property-filter-primary" aria-labelledby="{{ $prefix }}-primary-heading">
                    <h2 id="{{ $prefix }}-primary-heading" class="filter-section-heading">{{ __('messages.properties.primary_filters') }}</h2>
                    <div class="filter-primary-grid">
                        @if (! $isEstablishment)
                            <div class="filter-field">
                                <label for="{{ $prefix }}-destination"><x-filter-icon name="pin" />{{ __('messages.home.destination') }}</label>
                                <select id="{{ $prefix }}-destination" name="destination"><option value="">{{ __('messages.properties.all_destinations') }}</option>@foreach ($destinations as $destination)<option value="{{ $destination }}" @selected(($filters['destination'] ?? null) === $destination)>{{ $destination }}</option>@endforeach</select>
                            </div>
                        @endif
                        <div class="date-range-picker filter-field" {!! $controlAttrs !!}>
                            <label for="{{ $prefix }}-date-range-trigger"><x-filter-icon name="calendar" />{{ __('messages.home.dates') }}</label>
                            <button type="button" id="{{ $prefix }}-date-range-trigger" class="date-range-trigger" data-date-range-trigger aria-expanded="false"><span data-date-range-label>{{ ($filters['check_in'] ?? null) && ($filters['check_out'] ?? null) ? $filters['check_in'] . ' → ' . $filters['check_out'] : __('messages.home.select_dates') }}</span></button>
                            <input type="hidden" name="check_in" value="{{ $filters['check_in'] ?? '' }}" data-date-range-start><input type="hidden" name="check_out" value="{{ $filters['check_out'] ?? '' }}" data-date-range-end><div class="date-range-popover" data-date-range-popover hidden></div>
                        </div>
                        <div class="filter-field" data-number-stepper>
                            <label for="{{ $prefix }}-guests"><x-filter-icon name="users" />{{ __('messages.properties.guests') }}</label>
                            <div class="filter-stepper"><button type="button" data-step="-1" aria-label="{{ __('messages.properties.decrease', ['field' => __('messages.properties.guests')]) }}">−</button><input id="{{ $prefix }}-guests" name="guests" type="number" min="1" max="100" inputmode="numeric" value="{{ $filters['guests'] ?? '' }}" data-step-input><button type="button" data-step="1" aria-label="{{ __('messages.properties.increase', ['field' => __('messages.properties.guests')]) }}">+</button></div>
                        </div>
                        <div class="filter-field">
                            <label for="{{ $prefix }}-type"><x-filter-icon name="home" />{{ __('messages.properties.property_type') }}</label>
                            <select id="{{ $prefix }}-type" name="property_type"><option value="">{{ __('messages.properties.all_types') }}</option>@foreach (['apartment', 'house', 'villa', 'studio', 'room', 'other'] as $type)<option value="{{ $type }}" @selected(($filters['property_type'] ?? null) === $type)>{{ __('messages.admin.type_' . $type) }}</option>@endforeach</select>
                        </div>
                    </div>
                </section>

                <section class="filter-section" aria-labelledby="{{ $prefix }}-accommodation-heading">
                    <h2 id="{{ $prefix }}-accommodation-heading" class="filter-section-heading">{{ __('messages.properties.accommodation_configuration') }}</h2>
                    <div class="filter-secondary-grid">
                        @if (! $isEstablishment)
                            <div class="filter-field">
                                <label for="{{ $prefix }}-establishment"><x-filter-icon name="building" />{{ __('messages.common.establishment') }}</label>
                                <select id="{{ $prefix }}-establishment" name="establishment"><option value="">{{ __('messages.properties.all_establishments') }}</option>@foreach ($establishments as $establishment)<option value="{{ $establishment->id }}" @selected(($filters['establishment'] ?? null) == $establishment->id)>{{ $establishment->localized('name') }}</option>@endforeach</select>
                            </div>
                        @endif
                        @foreach ([['bedrooms', __('messages.properties.bedrooms'), 'home', 50], ['beds', __('messages.properties.beds'), 'bed', 100]] as [$field, $label, $icon, $maximum])
                            <div class="filter-field" data-number-stepper>
                                <label for="{{ $prefix }}-{{ $field }}"><x-filter-icon :name="$icon" />{{ $label }}</label>
                                <div class="filter-stepper"><button type="button" data-step="-1" aria-label="{{ __('messages.properties.decrease', ['field' => $label]) }}">−</button><input id="{{ $prefix }}-{{ $field }}" name="{{ $field }}" type="number" min="1" max="{{ $maximum }}" inputmode="numeric" value="{{ $filters[$field] ?? '' }}" data-step-input><button type="button" data-step="1" aria-label="{{ __('messages.properties.increase', ['field' => $label]) }}">+</button></div>
                            </div>
                        @endforeach
                    </div>
                </section>

                <section class="filter-section" aria-labelledby="{{ $prefix }}-conditions-heading">
                    <h2 id="{{ $prefix }}-conditions-heading" class="filter-section-heading">{{ __('messages.properties.price_and_conditions') }}</h2>
                    <div class="filter-price-header"><span><x-filter-icon name="wallet"/>{{ __('messages.properties.price_per_night') }}</span><strong>{{ $filterCurrency }}</strong></div>
                    <div class="filter-price-range" data-price-range data-price-ceiling="{{ max(1000, (int) $priceCeiling) }}">
                        <label class="sr-only" for="{{ $prefix }}-min-price-range">{{ __('messages.properties.min_price', ['currency' => $filterCurrency]) }}</label>
                        <input id="{{ $prefix }}-min-price-range" type="range" min="0" max="{{ max(1000, (int) $priceCeiling) }}" step="5000" value="{{ $filters['min_price'] ?? 0 }}" data-price-range-min>
                        <label class="sr-only" for="{{ $prefix }}-max-price-range">{{ __('messages.properties.max_price', ['currency' => $filterCurrency]) }}</label>
                        <input id="{{ $prefix }}-max-price-range" type="range" min="0" max="{{ max(1000, (int) $priceCeiling) }}" step="5000" value="{{ $filters['max_price'] ?? max(1000, (int) $priceCeiling) }}" data-price-range-max>
                    </div>
                    <div class="filter-price-inputs">
                        <div class="filter-field"><label for="{{ $prefix }}-min-price">{{ __('messages.properties.min_price', ['currency' => $filterCurrency]) }}</label><input id="{{ $prefix }}-min-price" name="min_price" type="number" min="0" max="{{ max(1000, (int) $priceCeiling) }}" step="1000" inputmode="numeric" value="{{ $filters['min_price'] ?? '' }}" data-price-number-min data-range-error="{{ __('messages.properties.price_range_invalid') }}"></div>
                        <div class="filter-field"><label for="{{ $prefix }}-max-price">{{ __('messages.properties.max_price', ['currency' => $filterCurrency]) }}</label><input id="{{ $prefix }}-max-price" name="max_price" type="number" min="0" max="{{ max(1000, (int) $priceCeiling) }}" step="1000" inputmode="numeric" value="{{ $filters['max_price'] ?? '' }}" data-price-number-max data-range-error="{{ __('messages.properties.price_range_invalid') }}"></div>
                    </div>
                    <output class="filter-price-preview" data-price-summary data-currency="{{ $filterCurrency }}" data-template="{{ __('messages.properties.price_range_summary', ['min' => '__MIN__', 'max' => '__MAX__', 'currency' => $filterCurrency]) }}" aria-live="polite">{{ __('messages.properties.price_range_summary', ['min' => $priceMinimumLabel, 'max' => $priceMaximumLabel, 'currency' => $filterCurrency]) }}</output>
                    <label class="filter-toggle-card" for="{{ $prefix }}-flexible"><input id="{{ $prefix }}-flexible" name="flexible_cancellation" type="checkbox" value="1" @checked($filters['flexible_cancellation'] ?? false)><span class="filter-toggle-icon"><x-filter-icon name="shield"/></span><span>{{ __('messages.properties.flexible_cancellation_filter') }}</span></label>
                    @if ($isCustomer)
                        <label class="filter-toggle-card" for="{{ $prefix }}-favorites"><input id="{{ $prefix }}-favorites" name="favorites" type="checkbox" value="1" @checked($filters['favorites'] ?? false)><span>{{ __('messages.favorites.only') }}</span></label>
                    @endif
                </section>

                <details class="filter-amenities" data-amenities-section>
                    <summary><x-filter-icon name="sliders"/><span>{{ __('messages.properties.amenities') }}</span><span class="filter-count-badge" data-amenity-count data-count-template="{{ __('messages.properties.selected_count', ['count' => '__COUNT__']) }}" @if (! $amenityCount) hidden @endif>{{ __('messages.properties.selected_count', ['count' => $amenityCount]) }}</span><span class="filter-summary-chevron" aria-hidden="true">⌄</span></summary>
                    <div class="filter-amenity-content">
                        @if ($amenityItems->count() > 8)
                            <label class="filter-amenity-search"><span class="sr-only">{{ __('messages.properties.search_amenities') }}</span><input type="search" placeholder="{{ __('messages.properties.search_amenities') }}" data-amenity-search></label>
                        @endif
                        @if ($popularAmenities->isNotEmpty())
                            <section class="amenity-category amenity-popular" aria-labelledby="{{ $prefix }}-popular-heading">
                                <h3 id="{{ $prefix }}-popular-heading">{{ __('messages.properties.popular_amenities') }}</h3>
                                <div class="amenity-options">
                                    @foreach ($popularAmenities as $amenity)
                                        <label class="amenity-option amenity-option-popular" data-amenity-option data-amenity-search-text="{{ \Illuminate\Support\Str::lower($amenity->name) }}"><input type="checkbox" name="amenities[]" value="{{ $amenity->id }}" @checked($selectedAmenities->contains((int) $amenity->id))><span>{{ $amenity->name }}</span><small>{{ __('messages.properties.popular') }}</small></label>
                                    @endforeach
                                </div>
                            </section>
                        @endif
                        @foreach ($otherAmenityGroups as $categorySlug => $group)
                            <details class="amenity-category" data-amenity-category>
                                <summary>{{ $group->first()->category?->name ?? __('messages.properties.other_amenities') }} <span>{{ $group->count() }}</span></summary>
                                <div class="amenity-options">
                                    @foreach ($group as $index => $amenity)
                                        <label class="amenity-option {{ $index >= 6 ? 'is-extra' : '' }}" data-amenity-option data-amenity-search-text="{{ \Illuminate\Support\Str::lower($amenity->name) }}" @if ($index >= 6) hidden data-amenity-extra @endif><input type="checkbox" name="amenities[]" value="{{ $amenity->id }}" @checked($selectedAmenities->contains((int) $amenity->id))><span>{{ $amenity->name }}</span></label>
                                    @endforeach
                                </div>
                                @if ($group->count() > 6)<button type="button" class="amenity-show-more" data-amenity-more data-less-label="{{ __('messages.properties.show_less') }}">{{ __('messages.properties.show_more') }}</button>@endif
                            </details>
                        @endforeach
                    </div>
                </details>

                <section class="filter-section filter-sort-section" aria-labelledby="{{ $prefix }}-sort-heading">
                    <h2 id="{{ $prefix }}-sort-heading" class="filter-section-heading"><x-filter-icon name="sliders"/>{{ __('messages.properties.sort') }}</h2>
                    <select id="{{ $prefix }}-sort" name="sort" aria-label="{{ __('messages.properties.sort') }}">
                        @foreach (['recommended', 'price_asc', 'price_desc', 'rating', 'newest'] as $sort)
                            <option value="{{ $sort }}" @selected(($filters['sort'] ?? 'recommended') === $sort)>{{ __('messages.properties.sort_' . $sort) }}</option>
                        @endforeach
                    </select>
                </section>

                <footer class="filter-action-footer">
                    @if ($activeCount)<a href="{{ $buildUrl([]) }}" class="filter-clear-all">{{ __('messages.properties.reset') }}</a>@endif
                    <span class="filter-result-count" data-filter-result-count data-stale-label="{{ __('messages.properties.apply_hint') }}">{{ $resultsCount !== null ? trans_choice('messages.properties.result_count', $resultsCount, ['count' => $resultsCount]) : __('messages.properties.apply_hint') }}</span>
                    <button type="submit" class="btn btn-primary" data-filter-apply>{{ __('messages.properties.apply') }}</button>
                </footer>
            </form>
        </details>
    </section>
@endif
