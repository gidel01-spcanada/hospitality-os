@extends('layouts.app')

@section('title', __('messages.seo.home_title'))
@section('seo_description', __('messages.seo.home_description'))

@section('content')
    <section class="hero">
        <div class="container hero-card">
            <div class="hero-content">
                <div class="hero-copy">
                    <span class="eyebrow">{{ __('messages.home.eyebrow') }}</span>
                    <h1>{{ __('messages.home.hero_title') }}</h1>
                    <p>{{ __('messages.home.hero_description') }}</p>
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
                            <input type="text" name="city" value="Cotonou" aria-label="{{ __('messages.home.destination') }}" />
                        </label>
                        <div class="grid-two">
                            <label>
                                <span>{{ __('messages.home.arrival') }}</span>
                                <input type="date" name="check_in" aria-label="{{ __('messages.home.arrival') }}" />
                            </label>
                            <label>
                                <span>{{ __('messages.home.departure') }}</span>
                                <input type="date" name="check_out" aria-label="{{ __('messages.home.departure') }}" />
                            </label>
                        </div>
                        <label>
                            <span>{{ __('messages.home.travelers') }}</span>
                            <select name="guests" aria-label="{{ __('messages.home.travelers') }}">
                                <option value="2">2 voyageurs</option>
                                <option value="3">3 voyageurs</option>
                                <option value="4">4 voyageurs</option>
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

    @php
        $reviewSources = [
            'booking' => \App\Support\BrandSettings::get('review_source_booking_url'),
            'google' => \App\Support\BrandSettings::get('review_source_google_url'),
        ];
        $reviewLinks = array_filter($reviewSources, fn ($url) => ! empty(trim((string) $url)));
        @endphp

        @if ($reviews->isNotEmpty() || ! empty($reviewLinks))
            <section class="section" id="reviews">
                <div class="container">
                    <div class="section-head">
                        <div>
                            <span class="badge badge-emerald">{{ __('messages.home.guest_reviews') }}</span>
                            <h2>{{ __('messages.home.satisfied_travelers') }}</h2>
                        </div>
                    </div>

                    @php
                        $reviewCount = $reviews->count();
                        $averageRating = $reviewCount > 0 ? round((float) $reviews->avg('rating'), 1) : null;
                    @endphp

                    @if ($reviewCount > 0 || ! empty($reviewLinks))
                        <div class="review-summary">
                            <div class="review-summary-score">
                                <strong>{{ $averageRating ?? '4.9' }}</strong>
                                <span>/ 5</span>
                                <div class="review-summary-stars">★★★★★</div>
                            </div>
                            <div class="review-summary-copy">
                                <h3>{{ $reviewCount > 0 ? __('messages.home.verified_reviews') : __('messages.home.platform_reviews') }}</h3>
                                <p>{{ $reviewCount > 0 ? __('messages.home.review_count', ['count' => $reviewCount]) : __('messages.home.review_platforms') }}</p>
                            </div>
                            @if (! empty($reviewLinks))
                                <div class="review-summary-links">
                                    @foreach ($reviewLinks as $platform => $url)
                                        <a href="{{ $url }}" target="_blank" rel="noopener noreferrer">
                                            {{ $platform === 'booking' ? 'Booking.com' : 'Google' }}
                                        </a>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endif

                    @if($reviews->isNotEmpty())
                        <div class="review-grid">
                            @foreach($reviews as $review)
                                <article class="review-card">
                                    <div class="review-topline">
                                        <span class="review-source">{{ ucfirst($review->source) }}</span>
                                        <span class="review-stars" aria-label="{{ __('messages.home.rating_stars', ['rating' => $review->rating]) }}">{{ str_repeat('★', (int) $review->rating) }}{{ str_repeat('☆', 5 - (int) $review->rating) }}</span>
                                    </div>
                                    <h3>{{ $review->reviewer_name }}</h3>
                                    @if($review->review_text)
                                        <p class="review-quote">“{{ $review->review_text }}”</p>
                                    @endif
                                    @if($review->source_url)
                                        <a class="review-link" href="{{ $review->source_url }}" target="_blank" rel="noopener noreferrer">{{ __('messages.home.view_source') }}</a>
                                    @endif
                                </article>
                            @endforeach
                        </div>
                    @endif

                    @if (! empty($reviewLinks))
                        <div class="review-grid review-grid-secondary" style="margin-top: 1.5rem;">
                            @if (! empty($reviewLinks['booking']))
                                <article class="review-card review-card-alt">
                                    <div class="review-topline">
                                        <span class="review-source">Booking.com</span>
                                        <span class="review-stars">★★★★★</span>
                                    </div>
                                    <h3>{{ __('messages.home.happy_guests') }}</h3>
                                    <p class="review-quote">{{ __('messages.home.booking_reviews') }}</p>
                                    <a class="review-link" href="{{ $reviewLinks['booking'] }}" target="_blank" rel="noopener noreferrer">{{ __('messages.home.read_reviews') }}</a>
                                </article>
                            @endif

                            @if (! empty($reviewLinks['google']))
                                <article class="review-card review-card-alt">
                                    <div class="review-topline">
                                        <span class="review-source">Google</span>
                                        <span class="review-stars">★★★★★</span>
                                    </div>
                                    <h3>{{ __('messages.home.verified_experience') }}</h3>
                                    <p class="review-quote">{{ __('messages.home.google_reviews') }}</p>
                                    <a class="review-link" href="{{ $reviewLinks['google'] }}" target="_blank" rel="noopener noreferrer">{{ __('messages.home.read_reviews') }}</a>
                                </article>
                            @endif
                        </div>
                    @endif
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
                    <article class="property-card property-card-link">
                        <a href="{{ route('properties.show', $property) }}" class="property-link" aria-label="Voir {{ $property->localized('name') }}">
                            <div class="property-image" style="background-image: linear-gradient(rgba(0,0,0,0.15), rgba(0,0,0,0.15)), url('{{ $property->cover_image ? asset($property->cover_image) : 'https://images.unsplash.com/photo-1494526585095-c41746248156?auto=format&fit=crop&w=1200&q=80' }}');"></div>
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
