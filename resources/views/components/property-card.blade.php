@props(['property', 'filters' => [], 'favoritePropertyIds' => [], 'compareEnabled' => false, 'pricing' => null])

@php
    $galleryImages = $property->images->values()->map(fn ($image, $index) => [
        'url' => asset($image->file_path),
        'tag' => $image->room_tag,
        'alt' => __('messages.seo.property_image_alt', ['name' => $property->localized('name'), 'city' => $property->city, 'number' => $index + 1]),
    ]);
    $coverImage = $property->images->firstWhere('is_cover', true) ?? $property->images->first();
    $coverUrl = $coverImage ? asset($coverImage->file_path) : ($property->cover_image ? asset($property->cover_image) : asset('https://images.unsplash.com/photo-1494526585095-c41746248156?auto=format&fit=crop&w=1200&q=80'));
    $coverIndex = $coverImage ? $property->images->search(fn ($image) => $image->id === $coverImage->id) : 0;
    $isFavorite = in_array($property->id, $favoritePropertyIds, true);
    $propertyReviews = $property->reviews?->where('is_active', true) ?? collect();
    $reviewCount = $propertyReviews->count();
    $reviewAverage = $reviewCount ? number_format((float) $propertyReviews->avg('rating'), 1, ',', ' ') : null;
    $hasDateSearch = filled($filters['check_in'] ?? null) && filled($filters['check_out'] ?? null);
    $nights = $hasDateSearch ? max(1, \Carbon\Carbon::parse($filters['check_in'])->diffInDays(\Carbon\Carbon::parse($filters['check_out']))) : null;
    $amenityNames = $property->amenities->pluck('name')->filter()->take(3);
    $flexibleCancellation = (float) ($property->establishment?->cancellation_fee_percent ?? 0) <= 0;
    $propertyUrlFilters = array_filter($filters, fn ($value) => $value !== null && $value !== '' && $value !== [] && $value !== false);
    $propertyUrl = route('properties.show', $property) . ($propertyUrlFilters ? '?' . http_build_query($propertyUrlFilters) : '');
    // Every catalog page prices date searches the same way (fees and taxes included).
    $pricing ??= $hasDateSearch
        ? \App\Services\PricingCalculator::calculate($property, \Carbon\Carbon::parse($filters['check_in']), \Carbon\Carbon::parse($filters['check_out']), (int) ($filters['guests'] ?? 1))
        : null;
    $displayAmount = $pricing['total_amount'] ?? (float) $property->nightly_rate_xof * ($nights ?: 1);
    $internationalAmount = $property->establishment?->secondaryDisplayAmount($displayAmount);
@endphp

<article class="property-card property-card-link" data-property-id="{{ $property->id }}">
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
            <button type="button" class="gallery-control gallery-prev" data-gallery-prev aria-label="{{ __('messages.properties.previous_photo') }}">&#8592;</button>
            <button type="button" class="gallery-control gallery-next" data-gallery-next aria-label="{{ __('messages.properties.next_photo') }}">&#8594;</button>
            <span class="gallery-counter" data-gallery-counter>1 / {{ $galleryImages->count() }}</span>
        @endif
    </div>
    <div class="property-card-body">
        <a href="{{ $propertyUrl }}" class="property-link" aria-label="{{ __('messages.properties.view_details') }}: {{ $property->localized('name') }}">
            <div class="property-copy">
                <div class="property-card-type-row">
                    <span class="badge badge-emerald">{{ __('messages.admin.type_' . $property->property_type) }}</span>
                    @if ($reviewAverage)<span class="property-card-rating">★ {{ $reviewAverage }} · {{ __('messages.properties.review_count_short', ['count' => $reviewCount]) }}</span>@else<span class="property-card-rating property-card-new">{{ __('messages.properties.new_listing') }}</span>@endif
                </div>
                <h3>{{ $property->localized('name') }}</h3>
                <div class="property-card-detail-stack">
                    <p class="property-card-location">{{ implode(', ', array_filter([$property->city, $property->country])) ?: __('messages.properties.location_on_request') }}</p>
                    <p class="property-card-summary">{{ $property->displaySummary() }}</p>
                    <p class="property-card-meta">{{ __('messages.properties.guest_summary_full', ['guests' => $property->max_guests, 'bedrooms' => $property->bedrooms, 'beds' => $property->beds, 'bathrooms' => $property->bathrooms]) }}</p>
                    @if ($amenityNames->isNotEmpty())<p class="property-card-amenities">{{ $amenityNames->implode(' · ') }}@if($property->amenities->count() > $amenityNames->count()) · +{{ $property->amenities->count() - $amenityNames->count() }}@endif</p>@endif
                    @if ($flexibleCancellation)<p class="property-card-policy">{{ __('messages.properties.flexible_cancellation') }}</p>@endif
                    @if ($hasDateSearch)<p class="property-card-availability">{{ __('messages.properties.available_for_dates') }}</p>@endif
                </div>
                <div class="property-card-footer">
                    <div class="property-price-footer">
                        @if ($nights)
                            <strong><x-display-price :amount="$displayAmount" :property="$property" /> {{ __('messages.properties.for_nights', ['nights' => $nights]) }}</strong>
                        @else
                            <strong><x-display-price :amount="$displayAmount" :property="$property" /> / {{ __('messages.properties.night_short') }}</strong>
                        @endif
                        @php
                            $priceNotes = array_filter([
                                $pricing ? __('messages.properties.taxes_fees_included') : null,
                                $property->establishment?->secondary_currency && $internationalAmount !== null ? '≈ ' . number_format($internationalAmount, 2, ',', ' ') . ' ' . $property->establishment->secondary_currency : null,
                            ]);
                        @endphp
                        @if ($priceNotes)<span class="property-price-note">{{ implode(' · ', $priceNotes) }}</span>@endif
                    </div>
                </div>
            </div>
        </a>
        @if ($compareEnabled)
            <label class="compare-toggle">
                <input type="checkbox" name="properties[]" value="{{ $property->id }}" form="compare-form" data-compare-option>
                <span>{{ __('messages.properties.compare_select_one') }}</span>
            </label>
        @endif
    </div>
</article>