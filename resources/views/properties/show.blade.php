@extends('layouts.app')

@section('title', __('messages.seo.property_title', ['name' => $property->localized('name'), 'bedrooms' => $property->bedrooms, 'city' => $property->city, 'brand' => \App\Support\PlatformBrand::name()]))
@section('seo_description', $property->localized('description') ?: $property->localized('summary'))

@push('head')
    @php
        $shareTitle = $property->localized('name');
        $shareDescription = $property->localized('description') ?: $property->localized('summary');
        $shareImage = $property->images->firstWhere('is_cover', true) ?? $property->images->first();
        $shareImageUrl = $shareImage ? asset($shareImage->file_path) : ($property->cover_image ? asset($property->cover_image) : asset('https://images.unsplash.com/photo-1494526585095-c41746248156?auto=format&fit=crop&w=1200&q=80'));
    @endphp
    <meta name="description" content="{{ $shareDescription }}">
    <meta property="og:type" content="website">
    <meta property="og:title" content="{{ $shareTitle }}">
    <meta property="og:description" content="{{ $shareDescription }}">
    <meta property="og:image" content="{{ $shareImageUrl }}">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $shareTitle }}">
    <meta name="twitter:description" content="{{ $shareDescription }}">
    <meta name="twitter:image" content="{{ $shareImageUrl }}">
    <script type="application/ld+json">
        {!! json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'LodgingBusiness',
            'name' => $shareTitle,
            'description' => $shareDescription,
            'url' => url()->current(),
            'image' => [$shareImageUrl],
            'address' => array_filter([
                '@type' => 'PostalAddress',
                'streetAddress' => $property->establishment?->address ?: $property->address,
                'addressLocality' => $property->establishment?->city ?: $property->city,
                'addressCountry' => $property->establishment?->country_code ?: $property->country,
            ]),
            'geo' => $property->establishment?->latitude && $property->establishment?->longitude ? [
                '@type' => 'GeoCoordinates',
                'latitude' => (float) $property->establishment->latitude,
                'longitude' => (float) $property->establishment->longitude,
            ] : null,
            'offers' => [
                '@type' => 'Offer',
                'url' => url()->current(),
                'price' => (float) $property->nightly_rate_xof,
                'priceCurrency' => $property->currency,
                'availability' => 'https://schema.org/InStock',
            ],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
    </script>
@endpush

@section('content')
    @php
        $locationAddress = $property->establishment?->address ?: $property->address;
        $locationCity = $property->establishment?->city ?: $property->city;
        $locationCountryCode = $property->establishment?->country_code ?: $property->country;
        $locationCountry = $locationCountryCode;
        $countryNames = [
            'fr' => ['BJ' => 'Bénin', 'FR' => 'France', 'CI' => 'Côte d’Ivoire', 'SN' => 'Sénégal', 'TG' => 'Togo', 'GH' => 'Ghana', 'NG' => 'Nigeria'],
            'en' => ['BJ' => 'Benin', 'FR' => 'France', 'CI' => 'Ivory Coast', 'SN' => 'Senegal', 'TG' => 'Togo', 'GH' => 'Ghana', 'NG' => 'Nigeria'],
        ];
        if ($locationCountryCode && class_exists(\Locale::class)) {
            $displayCountry = \Locale::getDisplayRegion('und-' . strtoupper($locationCountryCode), app()->getLocale());
            $locationCountry = $displayCountry ?: $locationCountryCode;
        }
        $locationCountry = $countryNames[app()->getLocale()][strtoupper((string) $locationCountryCode)] ?? $locationCountry;
    @endphp
    <section class="property-show-header">
        <div class="container">
            <div class="property-show-topline">
                <span class="badge badge-emerald">{{ $property->localized('name') }}</span>
                <a href="{{ route('properties.index') }}" class="inline-link">← {{ __('messages.properties.back_to_list') }}</a>
            </div>
            @php
                $shareUrl = url()->current();
                $encodedShareUrl = urlencode($shareUrl);
                $encodedShareTitle = urlencode($shareTitle);
            @endphp
            <div class="share-panel" aria-label="{{ __('messages.properties.share') }}">
                <strong>{{ __('messages.properties.share') }}</strong>
                <div class="share-links">
                    <a href="https://wa.me/?text={{ $encodedShareTitle }}%20{{ $encodedShareUrl }}" target="_blank" rel="noopener noreferrer">{{ __('messages.properties.share_whatsapp') }}</a>
                    <a href="https://www.facebook.com/sharer/sharer.php?u={{ $encodedShareUrl }}" target="_blank" rel="noopener noreferrer">{{ __('messages.properties.share_facebook') }}</a>
                    <a href="https://twitter.com/intent/tweet?text={{ $encodedShareTitle }}&url={{ $encodedShareUrl }}" target="_blank" rel="noopener noreferrer">{{ __('messages.properties.share_x') }}</a>
                    <a href="https://www.linkedin.com/sharing/share-offsite/?url={{ $encodedShareUrl }}" target="_blank" rel="noopener noreferrer">{{ __('messages.properties.share_linkedin') }}</a>
                    <button type="button" data-copy-share-link="{{ $shareUrl }}" data-copy-share-link-success="{{ __('messages.properties.share_link_copied') }}" data-copy-share-link-error="{{ __('messages.properties.share_link_copy_failed') }}">{{ __('messages.properties.share_instagram') }}</button>
                    <button type="button" data-copy-share-link="{{ $shareUrl }}" data-copy-share-link-success="{{ __('messages.properties.share_link_copied') }}" data-copy-share-link-error="{{ __('messages.properties.share_link_copy_failed') }}">{{ __('messages.properties.share_tiktok') }}</button>
                </div>
                <p class="form-help" data-copy-share-link-status aria-live="polite" hidden></p>
            </div>
            <div class="property-show-layout">
                <div class="gallery-panel">
                    @php
                        $images = $property->images;
                        $galleryImages = $images->values()->map(fn ($image, $index) => [
                            'url' => asset($image->file_path),
                            'tag' => $image->room_tag,
                            'alt' => __('messages.seo.property_image_alt', ['name' => $property->localized('name'), 'city' => $property->city, 'number' => $index + 1]),
                        ]);
                        $coverImage = $images->firstWhere('is_cover', true) ?? $images->first();
                        $cover = $coverImage ? asset($coverImage->file_path) : ($property->cover_image ? asset($property->cover_image) : asset('https://images.unsplash.com/photo-1494526585095-c41746248156?auto=format&fit=crop&w=1200&q=80'));
                        $coverIndex = $coverImage ? $images->search(fn ($image) => $image->id === $coverImage->id) : 0;
                    @endphp
                    <div class="gallery-viewer" data-gallery='@json($galleryImages)' data-gallery-start="{{ $coverIndex === false ? 0 : $coverIndex }}">
                        @if ($galleryImages->count() > 1)
                            <button type="button" class="gallery-control gallery-prev" data-gallery-prev aria-label="{{ __('messages.properties.previous_photo') }}">&#8592;</button>
                        @endif
                        <img src="{{ $cover }}" alt="{{ __('messages.seo.property_image_alt', ['name' => $property->localized('name'), 'city' => $property->city, 'number' => 1]) }}" class="main-image" data-gallery-image>
                        @if ($coverImage?->room_tag)
                            <span class="gallery-tag" data-gallery-tag>{{ $coverImage->room_tag }}</span>
                        @endif
                        @if ($galleryImages->count() > 1)
                            <button type="button" class="gallery-control gallery-next" data-gallery-next aria-label="{{ __('messages.properties.next_photo') }}">&#8594;</button>
                        @endif
                        <span class="gallery-counter" data-gallery-counter>1 / {{ max(1, $galleryImages->count()) }}</span>
                    </div>
                    <div class="gallery-grid">
                        @foreach ($images as $image)
                            @php $src = $image->file_path ? asset($image->file_path) : $cover; @endphp
                            <button type="button" class="gallery-thumb" data-gallery-thumb="{{ $loop->index }}" aria-label="{{ __('messages.properties.view_photo', ['number' => $loop->iteration]) }}">
                                <img src="{{ $src }}" alt="{{ __('messages.seo.property_image_alt', ['name' => $property->localized('name'), 'city' => $property->city, 'number' => $loop->iteration]) }}" class="thumb-image">
                            </button>
                        @endforeach
                    </div>
                    @if (!empty($property->video_urls))
                        <div class="property-video-list">
                            <h2>{{ __('messages.properties.videos') }}</h2>
                            @foreach ($property->video_urls as $videoUrl)
                                @php
                                    $youtubeId = null;
                                    if (preg_match('/(?:youtube\.com\/(?:watch\?v=|embed\/)|youtu\.be\/)([\w-]+)/', $videoUrl, $matches)) {
                                        $youtubeId = $matches[1];
                                    }
                                    $vimeoId = preg_match('/vimeo\.com\/(\d+)/', $videoUrl, $matches) ? $matches[1] : null;
                                @endphp
                                <div class="property-video-frame">
                                    @if ($youtubeId)
                                        <button type="button" class="property-video-load" data-video-embed="https://www.youtube.com/embed/{{ $youtubeId }}?autoplay=1&rel=0" data-video-title="{{ __('messages.properties.video_title') }}" style="background-image: url('https://img.youtube.com/vi/{{ $youtubeId }}/hqdefault.jpg');">
                                            <span>{{ __('messages.properties.play_video') }}</span>
                                        </button>
                                    @elseif ($vimeoId)
                                        <button type="button" class="property-video-load" data-video-embed="https://player.vimeo.com/video/{{ $vimeoId }}?autoplay=1" data-video-title="{{ __('messages.properties.video_title') }}">
                                            <span>{{ __('messages.properties.play_video') }}</span>
                                        </button>
                                    @else
                                        <video controls preload="none" playsinline src="{{ $videoUrl }}"></video>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                <aside class="booking-panel">
                    <div class="booking-card">
                        <div class="price-summary">
                            <div class="price-row">
                                <span class="price-label">{{ __('messages.properties.from') }}</span>
                                <strong>{{ __('messages.properties.nightly_price', ['price' => number_format($property->nightly_rate_xof, 0, ',', ' ')]) }}</strong>
                            </div>
                            <div class="price-row muted-row">
                                <span>≈</span>
                                <strong>{{ number_format((float) $property->nightly_rate_eur, 2, ',', ' ') }} EUR</strong>
                            </div>
                        </div>

                        @if (auth()->user()?->role === 'customer' && $property->establishment)
                            <div class="message-new-panel">
                                <h2>{{ __('messages.messages.contact_concierge') }}</h2>
                                <form method="POST" action="{{ route('messages.store') }}" class="message-form">
                                    @csrf
                                    <input type="hidden" name="establishment_id" value="{{ $property->establishment->id }}">
                                    <label for="property-message-body">{{ __('messages.messages.message') }}</label>
                                    <textarea id="property-message-body" name="body" rows="4" maxlength="5000" required></textarea>
                                    <button type="submit" class="btn btn-primary btn-full">{{ __('messages.messages.send') }}</button>
                                </form>
                            </div>
                        @endif

                        @if (session('status'))
                            <div class="reservation-success">
                                {{ session('status') }}
                            </div>
                        @endif

                        <form method="POST" action="{{ route('properties.reserve', $property) }}" class="booking-form" data-availability-url="{{ route('properties.availability', $property) }}" data-availability-available="{{ __('messages.properties.availability_available') }}" data-availability-unavailable="{{ __('messages.properties.availability_unavailable') }}">
                            @csrf
                            @if (! auth()->check() || auth()->user()->role !== 'customer')
                                <div class="input-group">
                                    <label for="full_name">{{ __('messages.properties.full_name') }}</label>
                                    <input id="full_name" name="full_name" type="text" value="{{ old('full_name') }}" required>
                                </div>
                                <div class="input-group">
                                    <label for="email">{{ __('messages.common.email') }}</label>
                                    <input id="email" name="email" type="email" value="{{ old('email') }}" required>
                                </div>
                            @endif
                            <div class="grid-two compact-grid">
                                <div class="input-group">
                                    <label for="check_in">{{ __('messages.properties.check_in') }}</label>
                                    <input id="check_in" name="check_in" type="date" value="{{ old('check_in', now()->toDateString()) }}" required>
                                </div>
                                <div class="input-group">
                                    <label for="check_out">{{ __('messages.properties.check_out') }}</label>
                                    <input id="check_out" name="check_out" type="date" value="{{ old('check_out', now()->addDay()->toDateString()) }}" required>
                                </div>
                            </div>
                            <div class="grid-two compact-grid">
                                <div class="input-group">
                                    <label for="adults">{{ __('messages.properties.adults') }}</label>
                                    <select id="adults" name="adults">
                                        @for ($i = 1; $i <= min(6, (int) $property->max_guests); $i++)
                                            <option value="{{ $i }}" @selected(old('adults', 2) == $i)>{{ $i }} {{ $i === 1 ? __('messages.properties.adult') : __('messages.properties.adults') }}</option>
                                        @endfor
                                    </select>
                                </div>
                                <div class="input-group">
                                    <label for="country">{{ __('messages.properties.country') }}</label>
                                    <input id="country" name="country" type="text" value="{{ old('country', 'Bénin') }}">
                                </div>
                            </div>
                            <div class="grid-two compact-grid">
                                <div class="input-group">
                                    <label for="children">{{ __('messages.properties.children') }}</label>
                                    <input id="children" name="children" type="number" min="0" max="4" value="{{ old('children', 0) }}">
                                </div>
                                <div class="input-group">
                                    <label for="infants">{{ __('messages.properties.infants') }}</label>
                                    <input id="infants" name="infants" type="number" min="0" max="2" value="{{ old('infants', 0) }}">
                                </div>
                            </div>
                            <input id="phone" name="phone" type="tel" value="{{ old('phone') }}" placeholder="{{ __('messages.properties.phone_optional') }}">

                            @if ($property->features->isNotEmpty())
                                <div class="feature-choice-box">
                                    <h3>{{ __('messages.properties.extra_options') }}</h3>
                                    @foreach ($property->features as $feature)
                                        <label class="feature-choice-item">
                                            <input type="checkbox" name="selected_features[]" value="{{ $feature->id }}" {{ in_array((string) $feature->id, (array) old('selected_features', []), true) ? 'checked' : '' }}>
                                            <div class="feature-choice-copy">
                                                <strong>{{ $feature->name }}</strong>
                                                @if ($feature->description)
                                                    <span>{{ $feature->description }}</span>
                                                @endif
                                            </div>
                                            <em>{{ number_format((float) $feature->cost_xof, 0, ',', ' ') }} XOF</em>
                                        </label>
                                    @endforeach
                                </div>
                            @endif

                            @if($errors->any())
                                <div class="form-error-box">
                                    <ul>
                                        @foreach ($errors->all() as $error)
                                            <li>{{ $error }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif

                            @php $cancellationPolicy = $property->establishment; @endphp
                            @if ((float) ($cancellationPolicy?->cancellation_fee_percent ?? 0) > 0)
                                <p class="cancellation-policy-note">
                                    {{ __('messages.properties.cancellation_fee_note', [
                                        'percent' => rtrim(rtrim(number_format((float) $cancellationPolicy->cancellation_fee_percent, 2, ',', ' '), '0'), ','),
                                        'days' => (int) $cancellationPolicy->cancellation_fee_days,
                                    ]) }}
                                </p>
                            @endif

                            <div class="form-actions">
                                <button type="button" class="btn btn-ghost btn-full" data-check-availability>{{ __('messages.properties.check_availability') }}</button>
                                <p class="form-help" data-availability-result role="status" aria-live="polite"></p>
                                <button type="submit" class="btn btn-primary btn-full">{{ __('messages.properties.request_reservation') }}</button>
                                @guest
                                    <label class="account-choice">
                                        <input type="checkbox" name="create_account" value="1" @checked(old('create_account', true))>
                                        <span>{{ __('messages.properties.create_account_during_reservation') }}</span>
                                    </label>
                                @endguest
                            </div>
                        </form>
                    </div>
                </aside>
            </div>
        </div>
    </section>

    <section class="section detail-section">
        <div class="container property-details-grid">
            <div class="content-column">
                <div class="detail-card">
                                <h2>{{ __('messages.properties.about') }}</h2>
                            <p>{{ $property->localized('description') ?: $property->localized('summary') }}</p>
                </div>

                <div class="detail-card">
                    <h2>{{ __('messages.properties.characteristics') }}</h2>
                    <div class="stat-list">
                        <div class="stat-item stat-item-text"><span>{{ __('messages.admin.establishment') }}</span><strong>{{ $property->establishment?->localized('name') ?? '—' }}</strong></div>
                        <div class="stat-item stat-item-text"><span>{{ __('messages.admin.property_type') }}</span><strong>{{ __('messages.admin.type_' . ($property->property_type ?: 'apartment')) }}</strong></div>
                        <div class="stat-item"><span>{{ __('messages.home.travelers') }}</span><strong>{{ $property->max_guests }}</strong></div>
                        <div class="stat-item"><span>{{ __('messages.properties.bedrooms_count') }}</span><strong>{{ $property->bedrooms }}</strong></div>
                        <div class="stat-item"><span>{{ __('messages.properties.bathrooms') }}</span><strong>{{ $property->bathrooms }}</strong></div>
                        <div class="stat-item"><span>{{ __('messages.properties.beds') }}</span><strong>{{ $property->beds }}</strong></div>
                    </div>

                    @if ($property->amenities->isNotEmpty())
                        <h3 class="characteristics-subtitle">{{ __('messages.properties.included_services') }}</h3>
                        <ul class="amenity-list">
                            @foreach ($property->amenities as $amenity)
                                <li>{{ $amenity->name }}</li>
                            @endforeach
                        </ul>
                    @endif

                    @php $visibleFeatures = $property->features->where('is_active', true); @endphp
                    @if ($visibleFeatures->isNotEmpty())
                        <h3 class="characteristics-subtitle">{{ __('messages.properties.extra_options') }}</h3>
                        <ul class="feature-list">
                            @foreach ($visibleFeatures as $feature)
                                <li>
                                    <div>
                                        <strong>{{ $feature->name }}</strong>
                                        @if ($feature->description)
                                            <span>{{ $feature->description }}</span>
                                        @endif
                                    </div>
                                    <em>{{ number_format((float) $feature->cost_xof, 0, ',', ' ') }} XOF</em>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>

                @php
                    $availabilityBlockedRanges = $property->availabilityBlocks->map(fn ($block) => [$block->start_date->toDateString(), $block->end_date->toDateString()])->values();
                    $availabilityReservedRanges = collect($property->reservations->where('status', '!=', 'cancelled')->map(fn ($reservation) => [$reservation->check_in->toDateString(), $reservation->check_out->toDateString()])->all());
                    $availabilityReservedRanges = $availabilityReservedRanges->merge($property->calendarFeeds->where('is_enabled', true)->flatMap(fn ($feed) => $feed->events->map(fn ($event) => [$event->start_date->toDateString(), $event->end_date->toDateString()])))->values();
                @endphp
                <div class="detail-card property-availability-card">
                    <div class="detail-card-heading">
                        <h2>{{ __('messages.properties.availability') }}</h2>
                        <button type="button" class="btn btn-ghost btn-small" data-toggle-availability data-show-label="{{ __('messages.properties.show_availability') }}" data-hide-label="{{ __('messages.properties.hide_availability') }}" aria-expanded="false" aria-controls="property-availability-calendar">{{ __('messages.properties.show_availability') }}</button>
                    </div>
                    <div id="property-availability-calendar" class="property-availability-content" data-availability-content hidden>
                        <div class="availability-legend"><span class="availability-key availability-available">{{ __('messages.admin.available') }}</span><span class="availability-key availability-blocked">{{ __('messages.admin.blocked_reserved') }}</span><span class="availability-key availability-past">{{ __('messages.admin.past') }}</span></div>
                        <div class="availability-calendar" data-availability-calendar data-blocked="{{ $availabilityBlockedRanges->toJson() }}" data-reserved="{{ $availabilityReservedRanges->toJson() }}" data-external="[]"></div>
                    </div>
                </div>

                <div class="detail-card">
                    <div class="detail-card-heading">
                        <h2>{{ __('messages.properties.reviews') }}</h2>
                        @if ($reviewStats['count'] > 0)
                            <a class="inline-link" href="{{ route('reviews') }}">{{ __('messages.reviews.view_all') }}</a>
                        @endif
                    </div>
                    @if ($reviews->isNotEmpty())
                        <div class="review-summary property-review-summary">
                            <div class="review-summary-score">
                                <strong>{{ $reviewStats['average'] }}</strong>
                                <span>/ 5</span>
                                <div class="review-summary-stars">★★★★★</div>
                            </div>
                            <div class="review-summary-copy">
                                <h3>{{ __('messages.home.verified_reviews') }}</h3>
                                <p>{{ __('messages.home.review_count', ['count' => $reviewStats['count']]) }}</p>
                            </div>
                        </div>
                        <div class="review-grid property-review-grid">
                            @foreach ($reviews as $review)
                                <article class="review-card">
                                    <div class="review-topline"><strong>{{ $review->reviewer_name }}</strong><span class="review-stars">{{ str_repeat('★', $review->rating) }}{{ str_repeat('☆', 5 - $review->rating) }}</span></div>
                                    @if ($review->reviewed_at)<time class="review-date" datetime="{{ $review->reviewed_at->toDateString() }}">{{ $review->reviewed_at->format('d/m/Y') }}</time>@endif
                                    @if ($review->review_text)<p class="review-quote">{{ $review->review_text }}</p>@endif
                                    <small>{{ $review->source }}</small>
                                </article>
                            @endforeach
                        </div>
                    @else
                        <p>{{ __('messages.properties.no_reviews') }}</p>
                    @endif
                </div>
            </div>

            <div class="content-column">
            <div class="detail-card sticky-card">
                <h2>{{ __('messages.properties.stay_sheet') }}</h2>
                <div class="info-row"><span>{{ __('messages.properties.address') }}</span><strong>{{ $locationAddress }}</strong></div>
                <div class="info-row"><span>{{ __('messages.properties.city') }}</span><strong>{{ $locationCity }}</strong></div>
                <div class="info-row"><span>{{ __('messages.properties.country') }}</span><strong>{{ $locationCountry }}</strong></div>
                <div class="info-row"><span>{{ __('messages.properties.minimum_stay') }}</span><strong>{{ __('messages.properties.nights', ['count' => $property->minimum_stay]) }}</strong></div>
                <div class="info-row"><span>{{ __('messages.properties.currency') }}</span><strong>{{ $property->currency }}</strong></div>
            </div>
            @if ($property->establishment && ($property->establishment->latitude || $property->establishment->longitude || $property->establishment->google_maps_url))
                <div class="detail-card">
                    <h2>{{ __('messages.properties.location') }}</h2>
                    @if ($property->establishment->address || $property->establishment->city)
                        <p>{{ $property->establishment->address ?: $property->establishment->city }}</p>
                    @endif
                    @if ($property->establishment->latitude && $property->establishment->longitude)
                        <iframe class="location-map" title="{{ __('messages.properties.location') }}" src="https://www.google.com/maps?q={{ $property->establishment->latitude }},{{ $property->establishment->longitude }}&output=embed" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
                    @endif
                    @if ($property->establishment->google_maps_url)
                        <a class="inline-link" href="{{ $property->establishment->google_maps_url }}" target="_blank" rel="noopener noreferrer">{{ __('messages.properties.view_map') }} →</a>
                    @endif
                </div>
            @endif
            </div>
        </div>
    </section>
@endsection
