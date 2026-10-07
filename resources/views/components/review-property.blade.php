@props(['review'])
@if ($review->property)
    <p class="review-date" data-review-property="{{ $review->property_id }}">
        {{ __('messages.checkout.property') }} :
        @if ($review->property->status === 'published' && $review->property->is_active && $review->property->establishment?->is_active && $review->property->establishment?->is_published)
            <a class="inline-link" href="{{ route('properties.show', $review->property) }}">{{ $review->property->localized('name') }}</a>
        @else
            {{ $review->property->localized('name') }}
        @endif
    </p>
@endif