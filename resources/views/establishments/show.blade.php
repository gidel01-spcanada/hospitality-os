@extends('layouts.app')

@section('title', $establishment->localized('name') . ' | ' . \App\Support\PlatformBrand::name())
@section('seo_description', \Illuminate\Support\Str::limit($description, 160))
@if ($gallery->isNotEmpty())
    @section('seo_image', $gallery->first()['url'])
@endif

@push('head')
    <script type="application/ld+json">{!! json_encode($structuredData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
@endpush

@section('content')
    <div class="container establishment-share-wrap">
        <x-share-panel :title="$establishment->localized('name') . ' | ' . \App\Support\PlatformBrand::name()" :label="__('messages.establishments.share')" state="[data-share-establishment-form]" />
    </div>
    @if ($gallery->isNotEmpty())
        <div class="container establishment-gallery-wrap">
            <section class="establishment-hero gallery-panel" aria-label="{{ __('messages.establishments.photos') }}">
                <div class="gallery-viewer" data-gallery='@json($gallery)' data-gallery-start="0">
                    @if ($gallery->count() > 1)
                        <button type="button" class="gallery-control gallery-prev" data-gallery-prev aria-label="{{ __('messages.properties.previous_photo') }}">&#8592;</button>
                    @endif
                    <img src="{{ $gallery->first()['url'] }}" alt="{{ $gallery->first()['alt'] }}" class="main-image" data-gallery-image fetchpriority="high">
                    <span class="gallery-tag establishment-gallery-caption" data-gallery-caption aria-live="polite">{{ $gallery->first()['alt'] }}</span>
                    @if ($gallery->count() > 1)
                        <button type="button" class="gallery-control gallery-next" data-gallery-next aria-label="{{ __('messages.properties.next_photo') }}">&#8594;</button>
                    @endif
                    <span class="gallery-counter" data-gallery-counter>1 / {{ $gallery->count() }}</span>
                </div>
                @if ($gallery->count() > 1)
                    <div class="gallery-grid">
                        @foreach ($gallery as $image)
                            <button type="button" class="gallery-thumb" data-gallery-thumb="{{ $loop->index }}" aria-label="{{ __('messages.properties.view_photo', ['number' => $loop->iteration]) }}: {{ $image['alt'] }}" aria-pressed="{{ $loop->first ? 'true' : 'false' }}">
                                <img src="{{ $image['url'] }}" alt="{{ $image['alt'] }}" class="thumb-image" loading="lazy">
                            </button>
                        @endforeach
                    </div>
                @endif
            </section>
        </div>
    @endif
    <section class="establishment-introduction">
        <div class="container establishment-hero-copy">
            <a href="{{ route('establishments.index') }}" class="inline-link">← {{ __('messages.establishments.badge') }}</a>
            <div class="establishment-intro-layout">
                <div>
                    <p class="establishment-location">{{ implode(', ', array_filter([$establishment->city, $establishment->country_code])) }}</p>
                    <h1>{{ $establishment->localized('name') }}</h1>
                    @if (! empty($establishment->localized('features')))
                        <ul class="establishment-essential-features">
                            @foreach (collect($establishment->localized('features'))->filter()->take(4) as $feature)<li>{{ $feature }}</li>@endforeach
                        </ul>
                    @endif
                </div>
                <div class="establishment-intro-actions">
                    <span>{{ __('messages.establishments.property_count', ['count' => $allProperties->count()]) }}</span>
                    @if ($reviewStats['count'])
                        <a class="establishment-rating" href="#establishment-reviews">★ {{ $reviewStats['average'] }} / 5 · {{ __('messages.home.review_count', ['count' => $reviewStats['count']]) }}</a>
                    @endif
                    <a class="btn btn-primary" href="#establishment-properties">{{ __('messages.establishments.search_stay') }}</a>
                </div>
            </div>
            <div class="establishment-description-block">
                <h2>{{ __('messages.establishments.about') }}</h2>
                <p class="establishment-description">{{ $description }}</p>
            </div>
        </div>
    </section>

    <section class="section" id="establishment-properties">
        <div class="container">
            <div class="section-head">
                <div>
                    <span class="badge badge-emerald">{{ __('messages.nav.properties') }}</span>
                    <h2>{{ __('messages.establishments.available_homes') }}</h2>
                </div>
                <span>{{ __('messages.establishments.property_count', ['count' => $properties->count()]) }}</span>
            </div>
            <x-property-filters
                variant="establishment"
                :action="route('establishments.show', $establishment)"
                :filters="$filters"
                :destinations="$destinations"
                :establishments="$establishments"
                :amenities="$amenities"
                :filter-currency="$filterCurrency"
                :price-ceiling="$priceCeiling"
                :results-count="$properties->count()"
                :is-customer="auth()->user()?->role === 'customer'"
                :preserve="$reviewFilters"
                anchor="establishment-properties"
            />

            <x-property-comparison :properties="$properties" :filters="$filters" />
            <div class="property-grid property-grid-large">
                @forelse ($properties as $property)
                    <x-property-card :property="$property" :filters="$filters" :favorite-property-ids="$favoritePropertyIds" :compare-enabled="$properties->count() >= 2" />
                @empty
                    <div class="empty-state establishment-empty-state">
                        <h3>{{ __('messages.establishments.no_homes') }}</h3>
                        <p>{{ $allProperties->isEmpty() ? __('messages.establishments.no_homes_description') : __('messages.establishments.no_matching_homes') }}</p>
                        @if ($allProperties->isNotEmpty())<a class="btn btn-ghost" href="{{ route('establishments.show', $establishment) }}#establishment-properties">{{ __('messages.properties.reset') }}</a>@endif
                    </div>
                @endforelse
            </div>
        </div>
    </section>

    @if ($establishmentAmenities->isNotEmpty() || $services->isNotEmpty())
        <section class="section establishment-services-band">
            <div class="container">
                <h2>{{ __('messages.establishments.amenities_services') }}</h2>
                <p>{{ __('messages.establishments.amenities_scope') }}</p>
                <ul class="establishment-service-list">
                    @foreach ($establishmentAmenities as $amenity)<li>{{ $amenity->name }}</li>@endforeach
                    @foreach ($services as $service)<li>{{ $service }}</li>@endforeach
                </ul>
            </div>
        </section>
    @endif

    @if ($mapQuery || $contactEmail || $contactPhone || $nearbyPlaces->isNotEmpty())
        <section class="section">
            <div class="container establishment-location-grid">
                <div>
                <h2>{{ __('messages.properties.location') }}</h2>
                @if ($mapQuery)
                    <p>{{ implode(', ', array_filter([$address, $establishment->city, $establishment->country_code])) }}</p>
                @endif
                @if ($contactEmail || $contactPhone)
                    <h3>{{ __('messages.brand.contact') }}</h3>
                    @if ($contactEmail)<p><a class="inline-link" href="mailto:{{ $contactEmail }}">{{ $contactEmail }}</a></p>@endif
                    @if ($contactPhone)<p><a class="inline-link" href="tel:{{ $contactPhone }}">{{ $establishment->phone }}</a></p>@endif
                @endif
                @if ($nearbyPlaces->isNotEmpty())
                    <h3>{{ __('messages.establishments.nearby') }}</h3>
                    <ul>
                        @foreach ($nearbyPlaces as $place)<li><strong>{{ $place['name'] }}</strong>@if (filled($place['description'] ?? null)) · {{ $place['description'] }}@endif</li>@endforeach
                    </ul>
                @endif
                @if ($establishment->google_maps_url)
                    <a class="btn btn-ghost" href="{{ $establishment->google_maps_url }}" target="_blank" rel="noopener noreferrer">{{ __('messages.properties.view_map') }}</a>
                @endif
                </div>
                @if ($mapQuery)<iframe class="location-map" title="{{ __('messages.properties.location') }}: {{ $establishment->localized('name') }}" src="https://www.google.com/maps?q={{ urlencode($mapQuery) }}&output=embed" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>@endif
            </div>
        </section>
    @endif

        <section class="section" id="establishment-reviews">
            <div class="container">
                <div class="section-head">
                    <div>
                        <span class="badge badge-emerald">{{ __('messages.home.guest_reviews') }}</span>
                        <h2>{{ __('messages.home.verified_reviews') }}</h2>
                    </div>
                    @if ($reviewStats['count'])<span>{{ $reviewStats['average'] }} / 5 · {{ __('messages.home.review_count', ['count' => $reviewStats['count']]) }}</span>@endif
                </div>
                <form class="establishment-review-filters" data-establishment-review-filters method="GET" action="{{ route('establishments.show', $establishment) }}#establishment-reviews">
                    @foreach (collect($filters)->except('establishment') as $key => $value)
                        @if (is_array($value))
                            @foreach ($value as $item)<input type="hidden" name="{{ $key }}[]" value="{{ $item }}">@endforeach
                        @else
                            <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                        @endif
                    @endforeach
                    <div class="filter-field">
                        <label for="establishment-review-scope">{{ __('messages.establishments.review_origin') }}</label>
                        <select id="establishment-review-scope" name="review_scope" data-review-origin>
                            @foreach (['all', 'establishment', 'properties'] as $scope)
                                <option value="{{ $scope }}" @selected(($reviewFilters['review_scope'] ?? 'all') === $scope)>{{ __('messages.establishments.reviews_' . $scope) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="filter-field">
                        <label for="establishment-review-property">{{ __('messages.checkout.property') }}</label>
                        <select id="establishment-review-property" name="review_property" data-review-home @disabled(($reviewFilters['review_scope'] ?? 'all') === 'establishment')>
                            <option value="">{{ __('messages.establishments.reviews_all_homes') }}</option>
                            @foreach ($reviewProperties as $reviewProperty)
                                <option value="{{ $reviewProperty->id }}" @selected(($reviewFilters['review_property'] ?? null) == $reviewProperty->id)>{{ $reviewProperty->localized('name') }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit" class="btn btn-ghost">{{ __('messages.properties.apply') }}</button>
                </form>
                @if ($reviews->total())<p class="establishment-reviews-result-count">{{ __('messages.establishments.reviews_matching', ['count' => $reviews->total()]) }}</p>@endif
                <div class="review-grid">
                    @forelse ($reviews as $review)
                        <article class="review-card" data-review-id="{{ $review->id }}">
                            <div class="review-topline">
                                <span class="review-source">{{ $review->source_label }}</span>
                                <span class="review-stars" aria-label="{{ __('messages.home.rating_stars', ['rating' => $review->rating]) }}">{{ str_repeat('★', (int) $review->rating) }}</span>
                            </div>
                            <h3>{{ $review->reviewer_name }}</h3>
                            <p class="establishment-review-origin">
                                @if ($review->property)
                                    @if ($review->property->status === 'published' && $review->property->is_active)
                                        <a class="inline-link" href="{{ route('properties.show', $review->property) }}">{{ $review->property->localized('name') }}</a>
                                    @else
                                        {{ $review->property->localized('name') }}
                                    @endif
                                @else
                                    {{ __('messages.establishments.review_establishment_label') }}
                                @endif
                            </p>
                            @if ($review->reviewed_at)<time datetime="{{ $review->reviewed_at->toDateString() }}">{{ $review->reviewed_at->translatedFormat('d M Y') }}</time>@endif
                            <p class="review-quote">{{ $review->review_text ?: __('messages.reviews.no_comment') }}</p>
                        </article>
                    @empty
                        <p class="empty-state">{{ $reviewStats['count'] ? __('messages.establishments.reviews_no_match') : __('messages.properties.no_reviews') }}</p>
                    @endforelse
                </div>
                @if ($reviews->hasPages())
                    <nav class="establishment-review-pagination" aria-label="{{ __('messages.establishments.review_navigation') }}">
                        @if ($reviews->previousPageUrl())
                            <a class="btn btn-ghost btn-small" href="{{ $reviews->previousPageUrl() }}" rel="prev">{{ __('messages.establishments.reviews_previous') }}</a>
                        @endif
                        <span>{{ __('messages.establishments.reviews_page', ['page' => $reviews->currentPage(), 'pages' => $reviews->lastPage()]) }}</span>
                        @if ($reviews->nextPageUrl())
                            <a class="btn btn-ghost btn-small" href="{{ $reviews->nextPageUrl() }}" rel="next">{{ __('messages.establishments.reviews_next') }}</a>
                        @endif
                    </nav>
                @endif
            </div>
        </section>
@endsection