@extends('layouts.app')

@section('title', __('messages.review_request.title'))

@section('content')
<section class="page-hero"><div class="container narrow-shell"><span class="eyebrow eyebrow-dark">{{ __('messages.review_request.badge') }}</span><h1>{{ __('messages.review_request.title') }}</h1><p>{{ __('messages.review_request.description', ['property' => $reservation->property->name]) }}</p></div></section>
<section class="section"><div class="container narrow-shell review-submit-shell">
    @if(session('review_submitted'))
        <div class="alert success" role="status">{{ __('messages.review_request.thanks') }}</div>
    @elseif($existingReview)
        <div class="alert success" role="status">{{ __('messages.review_request.already_submitted') }}</div>
    @elseif($channel !== 'google')
        <x-card><form method="POST" action="{{ request()->fullUrl() }}">@csrf
            <label><span>{{ __('messages.review_request.rating') }}</span><input type="number" name="rating" min="1" max="5" value="5" required></label>
            <label><span>{{ __('messages.review_request.comment') }}</span><textarea name="review_text" rows="6" placeholder="{{ __('messages.review_request.comment_placeholder') }}"></textarea></label>
            <div class="form-actions"><button class="btn btn-primary" type="submit">{{ __('messages.review_request.submit') }}</button></div>
        </form></x-card>
    @endif
    @if(in_array($channel, ['google', 'both'], true) && $googleUrl)
        <div class="review-google-action"><h2>{{ __('messages.review_request.google_title') }}</h2><p>{{ __('messages.review_request.google_description') }}</p><a class="btn btn-ghost" href="{{ route('reviews.submit.google', ['reservation' => $reservation, 'signature' => request('signature'), 'expires' => request('expires')]) }}">{{ __('messages.review_request.google_action') }}</a></div>
    @endif
</div></section>
@endsection