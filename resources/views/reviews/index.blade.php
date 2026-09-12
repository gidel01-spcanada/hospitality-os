@extends('layouts.app')

@section('title', __('messages.reviews.title'))
@section('seo_description', __('messages.seo.reviews_description', ['brand' => \App\Support\PlatformBrand::name()]))

@section('content')
<section class="page-hero compact-hero">
    <div class="container narrow">
        <span class="badge badge-emerald">{{ __('messages.home.guest_reviews') }}</span>
        <h1>{{ __('messages.reviews.title') }}</h1>
        <p>{{ __('messages.reviews.subtitle') }}</p>
    </div>
</section>

<section class="container auth-panel">
    @if ($reviews->isNotEmpty())
        <div class="review-summary">
            <div class="review-summary-score">
                <strong>{{ $reviewStats['average'] }}</strong>
                <span>/ 5</span>
                <div class="review-summary-stars">★★★★★</div>
            </div>
            <div class="review-summary-copy">
                <h2>{{ __('messages.home.verified_reviews') }}</h2>
                <p>{{ __('messages.home.review_count', ['count' => $reviewStats['count']]) }}</p>
            </div>
        </div>

        <div class="review-grid all-reviews-grid">
            @foreach ($reviews as $review)
                <article class="review-card">
                    <div class="review-topline">
                        <span class="review-source">{{ $review->source_label }}</span>
                        <span class="review-stars" aria-label="{{ __('messages.home.rating_stars', ['rating' => $review->rating]) }}">{{ str_repeat('★', (int) $review->rating) }}{{ str_repeat('☆', 5 - (int) $review->rating) }}</span>
                    </div>
                    <h3>{{ $review->reviewer_name }}</h3>
                    @if ($review->reviewed_at)
                        <time class="review-date" datetime="{{ $review->reviewed_at->toDateString() }}">{{ $review->reviewed_at->format('d/m/Y') }}</time>
                    @endif
                    @if ($review->review_text)
                        <p class="review-quote">“{{ $review->review_text }}”</p>
                    @endif
                </article>
            @endforeach
        </div>

        {{ $reviews->links() }}
    @else
        <div class="summary-card full-width">
            <p>{{ __('messages.properties.no_reviews') }}</p>
        </div>
    @endif
</section>
@endsection
