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
                        <div class="date-range-picker" data-date-range-picker data-incomplete-message="{{ __('messages.home.select_both_dates') }}" data-start-label="{{ __('messages.home.arrival') }}" data-end-label="{{ __('messages.home.departure') }}" data-placeholder="{{ __('messages.home.select_dates') }}">
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
                            <select name="guests" aria-label="{{ __('messages.home.travelers') }}">
                                <option value="1" @selected(request('guests', '1') == 1)>1 voyageur</option>
                                <option value="2" @selected(request('guests', '2') == 2)>2 voyageurs</option>
                                <option value="3" @selected(request('guests') == 3)>3 voyageurs</option>
                                <option value="4" @selected(request('guests') == 4)>4 voyageurs</option>
                            </select>
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
                            <a href="{{ route('reviews') }}">{{ __('messages.reviews.view_all') }}</a>
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
                    @php($isFavorite = in_array($property->id, $favoritePropertyIds ?? [], true))
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
                        <a href="{{ route('properties.show', $property) }}" class="property-link" aria-label="Voir {{ $property->localized('name') }}">
                            <img src="{{ $property->cover_image ? asset($property->cover_image) : 'https://images.unsplash.com/photo-1494526585095-c41746248156?auto=format&fit=crop&w=1200&q=80' }}" alt="{{ __('messages.seo.property_image_alt', ['name' => $property->localized('name'), 'city' => $property->city, 'number' => 1]) }}" class="property-image" loading="lazy">
                            <div class="property-copy">
                                <div class="meta-row">
                                    <span class="badge badge-emerald">{{ $property->localized('name') }}</span>
                                    <strong>{{ number_format($property->nightly_rate_xof, 0, ',', ' ') }} XOF / nuit</strong>
                                </div>
                                <h3>{{ $property->localized('summary') ?: $property->localized('name') }}</h3>
                                <p>{{ $property->max_guests }} voyageurs · {{ $property->bedrooms }} chambre(s) · {{ $property->bathrooms }} salle(s) de bain</p>
                            </div>
                        </a>
                    </article>
                @endforeach
            </div>
        </div>
    </section>
@endsection
