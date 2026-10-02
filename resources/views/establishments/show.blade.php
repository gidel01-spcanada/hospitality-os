@extends('layouts.app')

@section('title', $establishment->localized('name') . ' | ' . \App\Support\PlatformBrand::name())
@section('seo_description', \Illuminate\Support\Str::limit(strip_tags((string) $establishment->localized('description')), 160))

@push('head')
    @php
        $establishmentImage = $establishment->cover_image
            ? asset($establishment->cover_image)
            : asset('https://images.unsplash.com/photo-1505693416388-ac5ce068fe85?auto=format&fit=crop&w=1600&q=80');
    @endphp
    <meta property="og:type" content="website">
    <meta property="og:title" content="{{ $establishment->localized('name') }}">
    <meta property="og:description" content="{{ $establishment->localized('description') }}">
    <meta property="og:image" content="{{ $establishmentImage }}">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:image" content="{{ $establishmentImage }}">
@endpush

@section('content')
    <section class="property-show-header establishment-show-header">
        <div class="container">
            <div class="property-show-topline">
                <a href="{{ route('properties.index') }}" class="inline-link">← {{ __('messages.nav.properties') }}</a>
                <span class="badge badge-emerald">{{ $establishment->city }}{{ $establishment->country_code ? ', ' . $establishment->country_code : '' }}</span>
            </div>
            <div class="property-title-block">
                <h1>{{ $establishment->localized('name') }}</h1>
                @if ($establishment->localized('description'))
                    <p>{{ $establishment->localized('description') }}</p>
                @endif
            </div>
            <details class="share-panel">
                <summary>{{ __('messages.properties.share') }}</summary>
                <div class="share-links">
                    <button type="button" data-copy-share-link="{{ url()->current() }}" data-copy-share-link-success="{{ __('messages.properties.share_link_copied') }}" data-copy-share-link-error="{{ __('messages.properties.share_link_copy_failed') }}">{{ __('messages.properties.copy_link') }}</button>
                </div>
                <p class="form-help" data-copy-share-link-status aria-live="polite" hidden></p>
            </details>
        </div>
    </section>

    <section class="section">
        <div class="container establishment-landing-grid">
            <div>
                <div class="section-head">
                    <div>
                        <span class="badge badge-emerald">{{ __('messages.nav.properties') }}</span>
                        <h2>{{ __('messages.home.premium_homes') }}</h2>
                    </div>
                    <span class="establishment-property-count">{{ $properties->count() }}</span>
                </div>

                <div class="property-grid">
                    @forelse ($properties as $property)
                        @php
                            $coverImage = $property->images->firstWhere('is_cover', true) ?? $property->images->first();
                            $coverUrl = $coverImage ? asset($coverImage->file_path) : ($property->cover_image ? asset($property->cover_image) : $establishmentImage);
                            $propertyReviews = $property->reviews;
                            $reviewAverage = $propertyReviews->isNotEmpty() ? number_format((float) $propertyReviews->avg('rating'), 1, ',', ' ') : null;
                        @endphp
                        <article class="property-card property-card-link">
                            <a class="property-gallery-link" href="{{ route('properties.show', $property) }}" aria-label="{{ __('messages.properties.view_details') }}: {{ $property->localized('name') }}">
                                <img src="{{ $coverUrl }}" alt="{{ $property->localized('name') }}" class="property-image" loading="lazy">
                            </a>
                            <a href="{{ route('properties.show', $property) }}" class="property-link">
                                <div class="property-copy">
                                    <div class="property-card-type-row">
                                        <span class="badge badge-emerald">{{ __('messages.admin.type_' . $property->property_type) }}</span>
                                        @if ($reviewAverage)<span class="property-card-rating">★ {{ $reviewAverage }} · {{ __('messages.properties.review_count_short', ['count' => $propertyReviews->count()]) }}</span>@endif
                                    </div>
                                    <h3>{{ $property->localized('name') }}</h3>
                                    <p class="property-card-location">{{ implode(', ', array_filter([$property->city, $property->country])) }}</p>
                                    <p class="property-card-summary">{{ $property->displaySummary() }}</p>
                                    <div class="property-card-footer">
                                        <div class="property-price-footer">
                                            <strong>{{ __('messages.properties.nightly', ['price' => number_format((float) $property->nightly_rate_xof, 0, ',', ' '), 'currency' => $property->currency]) }}</strong>
                                        </div>
                                    </div>
                                </div>
                            </a>
                        </article>
                    @empty
                        <p class="empty-state">{{ __('messages.properties.none') }}</p>
                    @endforelse
                </div>
            </div>

            <aside class="establishment-information">
                <h2>{{ __('messages.properties.location') }}</h2>
                @if ($establishment->address || $establishment->city)
                    <p>{{ implode(', ', array_filter([$establishment->address, $establishment->city, $establishment->country_code])) }}</p>
                @endif
                @if ($establishment->email)
                    <p><a class="inline-link" href="mailto:{{ $establishment->email }}">{{ $establishment->email }}</a></p>
                @endif
                @if ($establishment->phone)
                    <p><a class="inline-link" href="tel:{{ preg_replace('/[^+\d]/', '', $establishment->phone) }}">{{ $establishment->phone }}</a></p>
                @endif
                @if ($establishment->google_maps_url)
                    <a class="btn btn-ghost" href="{{ $establishment->google_maps_url }}" target="_blank" rel="noopener noreferrer">{{ __('messages.properties.view_map') }}</a>
                @elseif ($establishment->latitude && $establishment->longitude)
                    <iframe class="location-map" title="{{ __('messages.properties.location') }}" src="https://www.google.com/maps?q={{ $establishment->latitude }},{{ $establishment->longitude }}&output=embed" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
                @endif
            </aside>
        </div>
    </section>

    @if ($reviews->isNotEmpty())
        <section class="section" id="reviews">
            <div class="container">
                <div class="section-head">
                    <div>
                        <span class="badge badge-emerald">{{ __('messages.home.guest_reviews') }}</span>
                        <h2>{{ __('messages.home.verified_reviews') }}</h2>
                    </div>
                    <span>{{ $reviewStats['average'] }} / 5 · {{ __('messages.home.review_count', ['count' => $reviewStats['count']]) }}</span>
                </div>
                <div class="review-grid">
                    @foreach ($reviews as $review)
                        <article class="review-card">
                            <div class="review-topline">
                                <span class="review-source">{{ $review->source_label }}</span>
                                <span class="review-stars">{{ str_repeat('★', (int) $review->rating) }}</span>
                            </div>
                            <h3>{{ $review->reviewer_name }}</h3>
                            @if ($review->review_text)<p class="review-quote">{{ $review->review_text }}</p>@endif
                        </article>
                    @endforeach
                </div>
            </div>
        </section>
    @endif
@endsection