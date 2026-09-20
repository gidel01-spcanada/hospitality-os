@extends('layouts.app')

@section('title', __('messages.seo.home_title', ['brand' => \App\Support\PlatformBrand::name()]))
@section('seo_description', __('messages.seo.home_description'))

@section('content')
    <section class="hero">
        <div class="container hero-card">
            <div class="hero-content">
                <div class="hero-copy">
                    <span class="eyebrow">{{ __('messages.home.eyebrow') }}</span>
                    <h1>{{ __('messages.home.hero_title') }}</h1>
                    <p>{{ __('messages.home.hero_description', ['brand' => \App\Support\PlatformBrand::name()]) }}</p>
                    <div class="hero-stats" aria-label="Statistiques de l'expérience">
                        <div>
                            <strong>4</strong>
                            <span>{{ __('messages.home.property_count') }}</span>
                        </div>
                        <div>
                            <strong>2</strong>
                            <span>{{ __('messages.home.currencies') }}</span>
                        </div>
                        <div>
                            <strong>24/7</strong>
                            <span>{{ __('messages.home.support') }}</span>
                        </div>
                    </div>
                </div>

                <aside class="search-panel" id="booking" aria-label="Recherche de disponibilité">
                    <div class="panel-header">
                        <div>
                            <p class="kicker">{{ __('messages.home.availability') }}</p>
                            <h2>{{ __('messages.home.book_stay') }}</h2>
                        </div>
                        <span class="badge badge-gold">{{ __('messages.home.instant_confirmation') }}</span>
                    </div>
                    <form class="search-form" method="GET" action="{{ route('properties.index') }}">
                        <label>
                            <span>{{ __('messages.home.destination') }}</span>
                            <select name="destination" aria-label="{{ __('messages.home.destination') }}">
                                <option value="">{{ __('messages.properties.all_destinations') }}</option>
                                @foreach ($destinations as $destination)
                                    <option value="{{ $destination }}" @selected(request('destination') === $destination)>{{ $destination }}</option>
                                @endforeach
                            </select>
                        </label>
                        <div class="date-range-picker" data-date-range-picker data-incomplete-message="{{ __('messages.home.select_both_dates') }}" data-past-message="{{ __('messages.home.past_dates') }}" data-start-label="{{ __('messages.home.arrival') }}" data-end-label="{{ __('messages.home.departure') }}" data-placeholder="{{ __('messages.home.select_dates') }}">
                            <span>{{ __('messages.home.dates') }}</span>
                            <button type="button" class="date-range-trigger" data-date-range-trigger aria-expanded="false">
                                <span data-date-range-label>{{ request('check_in') && request('check_out') ? request('check_in') . ' → ' . request('check_out') : __('messages.home.select_dates') }}</span>
                            </button>
                            <input type="hidden" name="check_in" value="{{ request('check_in') }}" data-date-range-start>
                            <input type="hidden" name="check_out" value="{{ request('check_out') }}" data-date-range-end>
                            <div class="date-range-popover" data-date-range-popover hidden></div>
                        </div>
                        <label>
                            <span>{{ __('messages.home.travelers') }}</span>
                            <input name="guests" type="number" min="1" max="100" step="1" inputmode="numeric" list="traveler-suggestions" aria-label="{{ __('messages.home.travelers') }}" value="{{ request('guests', '1') }}">
                            <datalist id="traveler-suggestions"><option value="1">1 voyageur</option><option value="2">2 voyageurs</option><option value="3">3 voyageurs</option><option value="4">4 voyageurs</option></datalist>
                        </label>
                        <button type="submit" class="btn btn-primary btn-full">{{ __('messages.home.search') }}</button>
                    </form>
                </aside>
            </div>
        </div>
    </section>

    <section class="section" id="experience">
        <div class="container">
            <div class="section-head">
                <div>
                    <span class="badge badge-emerald">{{ __('messages.home.why_us') }}</span>
                    <h2>{{ __('messages.home.simple_experience') }}</h2>
                </div>
            </div>
            <div class="feature-grid">
                <article class="feature-card">
                    <div class="icon">⌂</div>
                    <h3>{{ __('messages.home.premium_homes') }}</h3>
                    <p>{{ __('messages.home.premium_homes_text') }}</p>
                </article>
                <article class="feature-card">
                    <div class="icon">✓</div>
                    <h3>{{ __('messages.home.clear_availability') }}</h3>
                    <p>{{ __('messages.home.clear_availability_text') }}</p>
                </article>
                <article class="feature-card">
                    <div class="icon">€</div>
                    <h3>{{ __('messages.home.transparent_prices') }}</h3>
                    <p>{{ __('messages.home.transparent_prices_text') }}</p>
                </article>
                <article class="feature-card">
                    <div class="icon">◎</div>
                    <h3>{{ __('messages.home.local_service') }}</h3>
                    <p>{{ __('messages.home.local_service_text') }}</p>
                </article>
            </div>
        </div>
    </section>

        @if ($reviews->isNotEmpty())
            <section class="section" id="reviews">
                <div class="container">
                    <div class="section-head">
                        <div>
                            <span class="badge badge-emerald">{{ __('messages.home.guest_reviews') }}</span>
                            <h2>{{ __('messages.home.satisfied_travelers') }}</h2>
                        </div>
                    </div>

                    <div class="review-summary">
                        <div class="review-summary-score">
                            <strong>{{ $reviewStats['average'] }}</strong>
                            <span>/ 5</span>
                            <div class="review-summary-stars">★★★★★</div>
                        </div>
                        <div class="review-summary-copy">
                            <h3>{{ __('messages.home.verified_reviews') }}</h3>
                            <p>{{ __('messages.home.review_count', ['count' => $reviewStats['count']]) }}</p>
                        </div>
                        <div class="review-summary-links">
                            <a href="{{ route('reviews', ['from' => 'home']) }}">{{ __('messages.reviews.view_all') }}</a>
                        </div>
                    </div>

                    <div class="review-grid">
                        @foreach($reviews as $review)
                            <article class="review-card">
                                <div class="review-topline">
                                    <span class="review-source">{{ $review->source_label }}</span>
                                    <span class="review-stars" aria-label="{{ __('messages.home.rating_stars', ['rating' => $review->rating]) }}">{{ str_repeat('★', (int) $review->rating) }}{{ str_repeat('☆', 5 - (int) $review->rating) }}</span>
                                </div>
                                <h3>{{ $review->reviewer_name }}</h3>
                                @if($review->reviewed_at)
                                    <time class="review-date" datetime="{{ $review->reviewed_at->toDateString() }}">{{ $review->reviewed_at->format('d/m/Y') }}</time>
                                @endif
                                @if($review->review_text)
                                    <p class="review-quote">“{{ $review->review_text }}”</p>
                                @else
                                    <p class="review-quote review-no-comment">{{ __('messages.reviews.no_comment') }}</p>
                                @endif
                            </article>
                        @endforeach
                    </div>
                </div>
            </section>
        @endif

    @if ($establishments->count() === 1 && ($establishments->first()->latitude && $establishments->first()->longitude || $establishments->first()->address || $establishments->first()->city))
        @php
            $homeEstablishment = $establishments->first();
            $homeMapQuery = $homeEstablishment->latitude && $homeEstablishment->longitude
                ? $homeEstablishment->latitude . ',' . $homeEstablishment->longitude
                : trim(implode(', ', array_filter([$homeEstablishment->address, $homeEstablishment->city, $homeEstablishment->country_code])));
        @endphp
        <section class="section home-location" id="home-location">
            <div class="container">
                <div class="section-head">
                    <div>
                        <span class="badge badge-emerald">{{ __('messages.properties.location') }}</span>
                        <h2>{{ $homeEstablishment->localized('name') }}</h2>
                    </div>
                </div>
                @if ($homeEstablishment->address || $homeEstablishment->city)
                    <p class="location-copy">{{ $homeEstablishment->address ?: $homeEstablishment->city }}</p>
                @endif
                <iframe class="location-map home-location-map" title="{{ __('messages.properties.location') }}" src="https://www.google.com/maps?q={{ urlencode($homeMapQuery) }}&output=embed" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
                @if ($homeEstablishment->google_maps_url)
                    <a class="inline-link" href="{{ $homeEstablishment->google_maps_url }}" target="_blank" rel="noopener noreferrer">{{ __('messages.properties.view_map') }} →</a>
                @endif
            </div>
        </section>
    @endif

    <section class="section" id="services">
        <div class="container">
            <div class="section-head">
                <div>
                    <span class="badge badge-gold">{{ __('messages.nav.properties') }}</span>
                        <h2>{{ __('messages.home.properties_heading') }}</h2>
                </div>
                <a href="{{ route('properties.index') }}" class="btn btn-ghost">{{ __('messages.home.view_all') }}</a>
            </div>
            @if ($establishments->count() > 1)
                <form method="GET" action="{{ route('home') }}" class="establishment-filter">
                    <label for="establishment">{{ __('messages.home.establishment') }}</label>
                    <select id="establishment" name="establishment" onchange="this.form.submit()">
                        <option value="">{{ __('messages.home.all_establishments') }}</option>
                        @foreach ($establishments as $establishment)
                            <option value="{{ $establishment->id }}" @selected(request('establishment') == $establishment->id)>{{ $establishment->localized('name') }} ({{ $establishment->properties_count }})</option>
                        @endforeach
                    </select>
                </form>
            @endif
            <div class="property-grid">
                @foreach ($properties as $property)
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
                        $amenityNames = $property->amenities->pluck('name')->filter()->take(3);
                        $flexibleCancellation = (float) ($property->establishment?->cancellation_fee_percent ?? 0) <= 0;
                        $hasDateSearch = filled($homeFilters['check_in'] ?? null) && filled($homeFilters['check_out'] ?? null);
                        $propertyUrl = route('properties.show', $property) . (($hasDateSearch || filled($homeFilters['guests'] ?? null)) ? '?' . http_build_query(array_filter(['check_in' => $homeFilters['check_in'] ?? null, 'check_out' => $homeFilters['check_out'] ?? null, 'guests' => $homeFilters['guests'] ?? null], fn ($value) => filled($value))) : '');
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
                        <a href="{{ $propertyUrl }}" class="property-link" aria-label="Voir {{ $property->localized('name') }}">
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
                                <div class="property-card-footer">
                                    <div class="property-price-footer">
                                        @if (isset($cardPricing[$property->id]))
                                            <strong>{{ number_format($cardPricing[$property->id]['total_amount'], 0, ',', ' ') }} {{ $cardPricing[$property->id]['currency'] }} {{ __('messages.properties.for_nights', ['nights' => $cardPricing[$property->id]['nights']]) }}</strong>
                                            <span>{{ number_format($property->nightly_rate_xof, 0, ',', ' ') }} {{ $property->currency }} / {{ __('messages.properties.night_short') }}</span>
                                        @else
                                            <strong>{{ number_format($property->nightly_rate_xof, 0, ',', ' ') }} {{ $property->currency }} / {{ __('messages.properties.night_short') }}</strong>
                                        @endif
                                        @if ($property->establishment?->secondary_currency && $property->establishment?->secondaryDisplayAmount((float) $property->nightly_rate_xof) !== null)
                                            <span>≈ {{ number_format($property->establishment->secondaryDisplayAmount((float) $property->nightly_rate_xof), 2, ',', ' ') }} {{ $property->establishment->secondary_currency }}</span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </a>
                    </article>
                @endforeach
            </div>
        </div>
    </section>
@endsection
