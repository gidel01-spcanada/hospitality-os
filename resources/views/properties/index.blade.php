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
            <details class="property-filters" {{ count($filters) ? 'open' : '' }}>
                <summary><span><span class="badge badge-emerald">{{ __('messages.properties.search_badge') }}</span><strong>{{ __('messages.properties.filter_heading') }}</strong></span><a href="{{ route('properties.index') }}" class="inline-link">{{ __('messages.properties.reset') }}</a></summary>
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

                        <div class="filter-group date-range-picker" data-date-range-picker data-incomplete-message="{{ __('messages.home.select_both_dates') }}" data-start-label="{{ __('messages.home.arrival') }}" data-end-label="{{ __('messages.home.departure') }}" data-placeholder="{{ __('messages.home.select_dates') }}">
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

                        <div class="filter-group">
                            <div class="filter-field">
                                <label for="guests">{{ __('messages.properties.guests') }}</label>
                                <input id="guests" name="guests" type="number" min="1" inputmode="numeric" value="{{ $filters['guests'] ?? '' }}">
                            </div>
                            <div class="filter-field">
                                <label for="bedrooms">{{ __('messages.properties.bedrooms') }}</label>
                                <input id="bedrooms" name="bedrooms" type="number" min="1" inputmode="numeric" value="{{ $filters['bedrooms'] ?? '' }}">
                            </div>
                        </div>

                        <div class="filter-group">
                            <div class="filter-field">
                                <label for="min_price">{{ __('messages.properties.min_price') }}</label>
                                <input id="min_price" name="min_price" type="number" min="0" step="1000" inputmode="numeric" value="{{ $filters['min_price'] ?? '' }}">
                            </div>
                            <div class="filter-field">
                                <label for="max_price">{{ __('messages.properties.max_price') }}</label>
                                <input id="max_price" name="max_price" type="number" min="0" step="1000" inputmode="numeric" value="{{ $filters['max_price'] ?? '' }}">
                            </div>
                        </div>

                        @auth
                            @if (auth()->user()->role === 'customer')
                                <div class="filter-field filter-field-checkbox">
                                    <label for="favorites"><input id="favorites" name="favorites" type="checkbox" value="1" @checked(($filters['favorites'] ?? false))> {{ __('messages.favorites.only') }}</label>
                                </div>
                            @endif
                        @endauth
                    </div>
                    <div class="form-actions"><button type="submit" class="btn btn-primary">{{ __('messages.properties.apply') }}</button></div>
                </form>
            </details>
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
                            <img src="{{ $coverUrl }}" alt="{{ __('messages.seo.property_image_alt', ['name' => $property->localized('name'), 'city' => $property->city, 'number' => $coverIndex === false ? 1 : $coverIndex + 1]) }}" class="property-image" data-gallery-image loading="lazy">
                            @if ($coverImage?->room_tag)
                                <span class="gallery-tag" data-gallery-tag>{{ $coverImage->room_tag }}</span>
                            @endif
                            @if ($galleryImages->count() > 1)
                                <button type="button" class="gallery-control gallery-prev" data-gallery-prev aria-label="Previous photo">&#8592;</button>
                                <button type="button" class="gallery-control gallery-next" data-gallery-next aria-label="Next photo">&#8594;</button>
                                <span class="gallery-counter" data-gallery-counter>1 / {{ $galleryImages->count() }}</span>
                            @endif
                        </div>
                        <a href="{{ route('properties.show', $property) }}" class="property-link" aria-label="Voir {{ $property->name }}">
                            <div class="property-copy">
                                <div class="meta-row">
                                    <span class="badge badge-emerald">{{ $property->localized('name') }}</span>
                                    <strong>{{ __('messages.properties.nightly', ['price' => number_format($property->nightly_rate_xof, 0, ',', ' ')]) }}</strong>
                                </div>
                                <h3>{{ $property->localized('summary') ?: $property->localized('name') }}</h3>
                                <p>{{ __('messages.properties.guest_summary', ['guests' => $property->max_guests, 'bedrooms' => $property->bedrooms, 'bathrooms' => $property->bathrooms]) }}</p>
                                <span class="inline-link">{{ __('messages.properties.view_details') }} →</span>
                            </div>
                        </a>
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
