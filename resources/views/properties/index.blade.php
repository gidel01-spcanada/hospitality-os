@extends('layouts.app')

@section('title', __('messages.properties.title'))
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
                    <div class="form-grid">
                    <div>
                        <label for="establishment">{{ __('messages.common.establishment') }}</label>
                        <select id="establishment" name="establishment">
                            <option value="">{{ __('messages.properties.all_establishments') }}</option>
                            @foreach ($establishments as $establishment)
                                <option value="{{ $establishment->id }}" @selected(($filters['establishment'] ?? null) == $establishment->id)>{{ $establishment->localized('name') }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="city">{{ __('messages.properties.city') }}</label>
                        <input id="city" name="city" value="{{ $filters['city'] ?? '' }}" placeholder="Cotonou">
                    </div>
                    <div>
                        <label for="guests">{{ __('messages.properties.guests') }}</label>
                        <input id="guests" name="guests" type="number" min="1" value="{{ $filters['guests'] ?? '' }}">
                    </div>
                    <div>
                        <label for="bedrooms">{{ __('messages.properties.bedrooms') }}</label>
                        <input id="bedrooms" name="bedrooms" type="number" min="1" value="{{ $filters['bedrooms'] ?? '' }}">
                    </div>
                    <div>
                        <label for="min_price">{{ __('messages.properties.min_price') }}</label>
                        <input id="min_price" name="min_price" type="number" min="0" step="1000" value="{{ $filters['min_price'] ?? '' }}">
                    </div>
                    <div>
                        <label for="max_price">{{ __('messages.properties.max_price') }}</label>
                        <input id="max_price" name="max_price" type="number" min="0" step="1000" value="{{ $filters['max_price'] ?? '' }}">
                    </div>
                    </div>
                    <div class="form-actions"><button type="submit" class="btn btn-primary">{{ __('messages.properties.apply') }}</button></div>
                </form>
            </details>
            <div class="property-grid property-grid-large">
                @forelse ($properties as $property)
                    @php
                        $galleryImages = $property->images->map(fn ($image) => ['url' => asset($image->file_path), 'tag' => $image->room_tag])->values();
                        $coverImage = $property->images->firstWhere('is_cover', true) ?? $property->images->first();
                        $coverUrl = $coverImage ? asset($coverImage->file_path) : ($property->cover_image ? asset($property->cover_image) : asset('https://images.unsplash.com/photo-1494526585095-c41746248156?auto=format&fit=crop&w=1200&q=80'));
                        $coverIndex = $coverImage ? $property->images->search(fn ($image) => $image->id === $coverImage->id) : 0;
                    @endphp
                    <article class="property-card property-card-link">
                        <div class="property-gallery" data-gallery='@json($galleryImages)' data-gallery-start="{{ $coverIndex === false ? 0 : $coverIndex }}">
                            <div class="property-image" style="background-image: linear-gradient(rgba(0,0,0,0.15), rgba(0,0,0,0.15)), url("{{ $coverUrl }}");"></div>
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
