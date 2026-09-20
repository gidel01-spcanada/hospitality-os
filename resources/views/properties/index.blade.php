@extends('layouts.app')

@section('title', __('messages.properties.title', ['brand' => \App\Support\PlatformBrand::name()]))
@section('seo_description', __('messages.seo.properties_description'))

@section('content')
    <section class="page-hero">
        <div class="container narrow-shell">
            <span class="eyebrow eyebrow-dark">{{ __('messages.properties.badge') }}</span>
            <h1>{{ __('messages.properties.heading') }}</h1>
            <p>{{ __('messages.properties.subtitle') }}</p>
        </div>
    </section>

    <section class="section">
        <div class="container">
            @php $activeFilterCount = collect($filters)->sum(fn ($value) => is_array($value) ? count($value) : 1); @endphp
            <details class="property-filters" {{ $activeFilterCount ? 'open' : '' }}>
                <summary><span><span class="badge badge-emerald">{{ __('messages.properties.search_badge') }}</span><strong>{{ __('messages.properties.filter_heading') }}</strong>@if($activeFilterCount)<small class="active-filter-count">{{ trans_choice('messages.properties.active_filters', $activeFilterCount, ['count' => $activeFilterCount]) }}</small>@endif</span><a href="{{ route('properties.index') }}" class="inline-link">{{ __('messages.properties.reset') }}</a></summary>
                <form method="GET" action="{{ route('properties.index') }}">
                    <div class="filter-grid">
                        <div class="filter-group">
                            <div class="filter-field">
                                <label for="destination">{{ __('messages.home.destination') }}</label>
                                <select id="destination" name="destination">
                                    <option value="">{{ __('messages.properties.all_destinations') }}</option>
                                    @foreach ($destinations as $destination)
                                        <option value="{{ $destination }}" @selected(($filters['destination'] ?? null) === $destination)>{{ $destination }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="filter-field">
                                <label for="establishment">{{ __('messages.common.establishment') }}</label>
                                <select id="establishment" name="establishment">
                                    <option value="">{{ __('messages.properties.all_establishments') }}</option>
                                    @foreach ($establishments as $establishment)
                                        <option value="{{ $establishment->id }}" @selected(($filters['establishment'] ?? null) == $establishment->id)>{{ $establishment->localized('name') }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="filter-group date-range-picker" data-date-range-picker data-incomplete-message="{{ __('messages.home.select_both_dates') }}" data-past-message="{{ __('messages.home.past_dates') }}" data-start-label="{{ __('messages.home.arrival') }}" data-end-label="{{ __('messages.home.departure') }}" data-placeholder="{{ __('messages.home.select_dates') }}">
                            <div class="filter-field">
                                <label for="property-date-range-trigger">{{ __('messages.home.dates') }}</label>
                                <button type="button" id="property-date-range-trigger" class="date-range-trigger" data-date-range-trigger aria-expanded="false">
                                    <span data-date-range-label>{{ ($filters['check_in'] ?? null) && ($filters['check_out'] ?? null) ? $filters['check_in'] . ' → ' . $filters['check_out'] : __('messages.home.select_dates') }}</span>
                                </button>
                                <input type="hidden" name="check_in" value="{{ $filters['check_in'] ?? '' }}" data-date-range-start>
                                <input type="hidden" name="check_out" value="{{ $filters['check_out'] ?? '' }}" data-date-range-end>
                                <div class="date-range-popover" data-date-range-popover hidden></div>
                            </div>
                        </div>

                        <div class="filter-group capacity-filter-group">
                            <div class="filter-field">
                                <label for="guests">{{ __('messages.properties.guests') }}</label>
                                <input id="guests" name="guests" type="number" min="1" inputmode="numeric" value="{{ $filters['guests'] ?? '' }}">
                            </div>
                            <div class="filter-field">
                                <label for="bedrooms">{{ __('messages.properties.bedrooms') }}</label>
                                <input id="bedrooms" name="bedrooms" type="number" min="1" inputmode="numeric" value="{{ $filters['bedrooms'] ?? '' }}">
                            </div>
                            <div class="filter-field">
                                <label for="beds">{{ __('messages.properties.beds') }}</label>
                                <input id="beds" name="beds" type="number" min="1" inputmode="numeric" value="{{ $filters['beds'] ?? '' }}">
                            </div>
                        </div>

                        <div class="filter-group">
                            <div class="filter-field">
                                <label for="property_type">{{ __('messages.properties.property_type') }}</label>
                                <select id="property_type" name="property_type">
                                    <option value="">{{ __('messages.properties.all_types') }}</option>
                                    @foreach (['apartment', 'house', 'villa', 'studio', 'room', 'other'] as $type)
                                        <option value="{{ $type }}" @selected(($filters['property_type'] ?? null) === $type)>{{ __('messages.admin.type_' . $type) }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="filter-group">
                            <div class="filter-field">
                                <label for="min_price">{{ __('messages.properties.min_price', ['currency' => $filterCurrency]) }}</label>
                                <input id="min_price" name="min_price" type="number" min="0" step="1000" inputmode="numeric" value="{{ $filters['min_price'] ?? '' }}">
                            </div>
                            <div class="filter-field">
                                <label for="max_price">{{ __('messages.properties.max_price', ['currency' => $filterCurrency]) }}</label>
                                <input id="max_price" name="max_price" type="number" min="0" step="1000" inputmode="numeric" value="{{ $filters['max_price'] ?? '' }}">
                            </div>
                        </div>

                        <div class="filter-field">
                            <label for="sort">{{ __('messages.properties.sort') }}</label>
                            <select id="sort" name="sort">
                                <option value="recommended" @selected(($filters['sort'] ?? 'recommended') === 'recommended')>{{ __('messages.properties.sort_recommended') }}</option>
                                <option value="price_asc" @selected(($filters['sort'] ?? null) === 'price_asc')>{{ __('messages.properties.sort_price_asc') }}</option>
                                <option value="price_desc" @selected(($filters['sort'] ?? null) === 'price_desc')>{{ __('messages.properties.sort_price_desc') }}</option>
                            </select>
                        </div>

                        <div class="filter-field filter-field-checkbox">
                            <label for="flexible_cancellation"><input id="flexible_cancellation" name="flexible_cancellation" type="checkbox" value="1" @checked(($filters['flexible_cancellation'] ?? false))> {{ __('messages.properties.flexible_cancellation_filter') }}</label>
                        </div>

                        @auth
                            @if (auth()->user()->role === 'customer')
                                <div class="filter-field filter-field-checkbox">
                                    <label for="favorites"><input id="favorites" name="favorites" type="checkbox" value="1" @checked(($filters['favorites'] ?? false))> {{ __('messages.favorites.only') }}</label>
                                </div>
                            @endif
                        @endauth

                        <details class="amenities-filter-panel full-width" {{ !empty($filters['amenities']) ? 'open' : '' }}>
                            <summary>{{ __('messages.properties.amenities') }}@if(!empty($filters['amenities'])) <small class="active-filter-count">{{ count((array) $filters['amenities']) }}</small>@endif</summary>
                            <div id="amenities" class="amenities-filter-options" role="group" aria-label="{{ __('messages.properties.amenities') }}">
                                @foreach ($amenities as $amenity)
                                    <label class="amenity-filter-option"><input type="checkbox" name="amenities[]" value="{{ $amenity->id }}" @checked(in_array($amenity->id, array_map('intval', (array) ($filters['amenities'] ?? [])), true))><span>{{ $amenity->name }}</span></label>
                                @endforeach
                            </div>
                            <small class="form-help">{{ __('messages.properties.amenities_filter_help') }}</small>
                        </details>
                    </div>
                    <div class="form-actions"><button type="submit" class="btn btn-primary">{{ __('messages.properties.apply') }}</button></div>
                </form>
            </details>
            <form id="compare-form" method="GET" action="{{ route('properties.compare') }}" class="compare-toolbar">
                <strong>{{ __('messages.properties.compare_select') }}</strong>
                <span data-compare-count>{{ __('messages.properties.compare_selected', ['count' => 0]) }}</span>
                <small>{{ __('messages.properties.compare_limit') }}</small>
                @if(($filters['check_in'] ?? null) && ($filters['check_out'] ?? null))
                    <input type="hidden" name="check_in" value="{{ $filters['check_in'] }}">
                    <input type="hidden" name="check_out" value="{{ $filters['check_out'] }}">
                    <input type="hidden" name="guests" value="{{ $filters['guests'] ?? 1 }}">
                @endif
                <button class="btn btn-ghost" type="submit" disabled>{{ __('messages.properties.compare_action') }}</button>
            </form>
            <div class="property-grid property-grid-large">
                @forelse ($properties as $property)
                    @php
                        $galleryImages = $property->images->values()->map(fn ($image, $index) => [
                            'url' => asset($image->file_path),
                            'tag' => $image->room_tag,
                            'alt' => __('messages.seo.property_image_alt', ['name' => $property->localized('name'), 'city' => $property->city, 'number' => $index + 1]),
                        ]);
                        $coverImage = $property->images->firstWhere('is_cover', true) ?? $property->images->first();
                        $coverUrl = $coverImage ? asset($coverImage->file_path) : ($property->cover_image ? asset($property->cover_image) : asset('https://images.unsplash.com/photo-1494526585095-c41746248156?auto=format&fit=crop&w=1200&q=80'));
                        $coverIndex = $coverImage ? $property->images->search(fn ($image) => $image->id === $coverImage->id) : 0;
                        $isFavorite = in_array($property->id, $favoritePropertyIds ?? [], true);
                        $propertyReviews = $property->reviews?->where('is_active', true) ?? collect();
                        if ($propertyReviews->isEmpty()) $propertyReviews = $property->establishment?->reviews?->where('is_active', true)->whereNull('property_id') ?? collect();
                        $reviewCount = $propertyReviews->count();
                        $reviewAverage = $reviewCount ? number_format((float) $propertyReviews->avg('rating'), 1, ',', ' ') : null;
                        $nights = ($filters['check_in'] ?? null) && ($filters['check_out'] ?? null)
                            ? max(1, \Carbon\Carbon::parse($filters['check_in'])->diffInDays(\Carbon\Carbon::parse($filters['check_out'])))
                            : null;
                        $amenityNames = $property->amenities->pluck('name')->filter()->take(3);
                        $flexibleCancellation = (float) ($property->establishment?->cancellation_fee_percent ?? 0) <= 0;
                        $hasDateSearch = filled($filters['check_in'] ?? null) && filled($filters['check_out'] ?? null);
                        $propertyUrl = route('properties.show', $property) . (($hasDateSearch || filled($filters['guests'] ?? null)) ? '?' . http_build_query(array_filter(['check_in' => $filters['check_in'] ?? null, 'check_out' => $filters['check_out'] ?? null, 'guests' => $filters['guests'] ?? null], fn ($value) => filled($value))) : '');
                    @endphp
                    <article class="property-card property-card-link">
                        @auth
                            <form method="POST" action="{{ route('properties.favorite.toggle', $property) }}" class="favorite-form">
                                @csrf
                                <button type="submit" class="favorite-button {{ $isFavorite ? 'is-favorite' : '' }}" aria-label="{{ $isFavorite ? __('messages.favorites.remove') : __('messages.favorites.add') }}" title="{{ $isFavorite ? __('messages.favorites.remove') : __('messages.favorites.add') }}">
                                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78L12 21.23l8.84-8.84a5.5 5.5 0 0 0 0-7.78Z" /></svg>
                                </button>
                            </form>
                        @else
                            <a class="favorite-button" href="{{ route('login') }}" aria-label="{{ __('messages.favorites.login') }}" title="{{ __('messages.favorites.login') }}">
                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78L12 21.23l8.84-8.84a5.5 5.5 0 0 0 0-7.78Z" /></svg>
                            </a>
                        @endauth
                        <div class="property-gallery" data-gallery='@json($galleryImages)' data-gallery-start="{{ $coverIndex === false ? 0 : $coverIndex }}">
                            <a href="{{ $propertyUrl }}" class="property-gallery-link" aria-label="{{ __('messages.properties.view_details') }}: {{ $property->localized('name') }}"><img src="{{ $coverUrl }}" alt="{{ __('messages.seo.property_image_alt', ['name' => $property->localized('name'), 'city' => $property->city, 'number' => $coverIndex === false ? 1 : $coverIndex + 1]) }}" class="property-image" data-gallery-image loading="lazy"></a>
                            @if ($coverImage?->room_tag)
                                <span class="gallery-tag" data-gallery-tag>{{ $coverImage->room_tag }}</span>
                            @endif
                            @if ($galleryImages->count() > 1)
                                <button type="button" class="gallery-control gallery-prev" data-gallery-prev aria-label="Previous photo">&#8592;</button>
                                <button type="button" class="gallery-control gallery-next" data-gallery-next aria-label="Next photo">&#8594;</button>
                                <span class="gallery-counter" data-gallery-counter>1 / {{ $galleryImages->count() }}</span>
                            @endif
                        </div>
                        <div class="property-card-body">
                        <a href="{{ $propertyUrl }}" class="property-link" aria-label="Voir {{ $property->name }}">
                            <div class="property-copy">
                                <div class="property-card-type-row">
                                    <span class="badge badge-emerald">{{ __('messages.admin.type_' . $property->property_type) }}</span>
                                    @if ($reviewAverage)<span class="property-card-rating">★ {{ $reviewAverage }} · {{ __('messages.properties.review_count_short', ['count' => $reviewCount]) }}</span>@else<span class="property-card-rating property-card-new">{{ __('messages.properties.new_listing') }}</span>@endif
                                </div>
                                <h3>{{ $property->localized('name') }}</h3>
                                <p class="property-card-location">{{ implode(', ', array_filter([$property->city, $property->country])) ?: __('messages.properties.location_on_request') }}</p>
                                <p class="property-card-summary">{{ $property->displaySummary() }}</p>
                                <p class="property-card-meta">{{ __('messages.properties.guest_summary_full', ['guests' => $property->max_guests, 'bedrooms' => $property->bedrooms, 'beds' => $property->beds, 'bathrooms' => $property->bathrooms]) }}</p>
                                @if ($amenityNames->isNotEmpty())<p class="property-card-amenities">{{ $amenityNames->implode(' · ') }}@if($property->amenities->count() > $amenityNames->count()) · +{{ $property->amenities->count() - $amenityNames->count() }}@endif</p>@endif
                                @if ($flexibleCancellation)<p class="property-card-policy">{{ __('messages.properties.flexible_cancellation') }}</p>@endif
                                @if ($hasDateSearch)<p class="property-card-availability">{{ __('messages.properties.available_for_dates') }}</p>@endif
                                <div class="property-card-footer">
                                    <div class="property-price-footer">
                                        @php
                                            $displayAmount = (float) $property->nightly_rate_xof * ($nights ?: 1);
                                            $internationalAmount = $property->establishment?->secondaryDisplayAmount($displayAmount);
                                        @endphp
                                        @if ($nights)
                                            <strong>{{ number_format($displayAmount, 0, ',', ' ') }} {{ $property->currency }} {{ __('messages.properties.for_nights', ['nights' => $nights]) }}</strong>
                                        @else
                                            <strong>{{ __('messages.properties.nightly', ['price' => number_format($displayAmount, 0, ',', ' '), 'currency' => $property->currency]) }}</strong>
                                        @endif
                                        @if ($property->establishment?->secondary_currency && $internationalAmount !== null)
                                            <span>≈ {{ number_format($internationalAmount, 2, ',', ' ') }} {{ $property->establishment->secondary_currency }}</span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </a>
                        <label class="compare-toggle">
                            <input type="checkbox" name="properties[]" value="{{ $property->id }}" form="compare-form" data-compare-option>
                            <span>{{ __('messages.properties.compare_select_one') }}</span>
                        </label>
                        </div>
                    </article>
                @empty
                    <div class="empty-state">
                        <p>{{ __('messages.properties.none') }}</p>
                    </div>
                @endforelse
            </div>
        </div>
    </section>
@endsection
