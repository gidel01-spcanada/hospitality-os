@extends('layouts.admin')

@section('title', __('messages.admin.reviews_title'))

@section('content')
    <div class="admin-page-header">
        <h1 class="admin-page-title">{{ __('messages.admin.reviews_title') }}</h1>
        <p class="admin-page-description">{{ __('messages.admin.reviews_description') }}</p>
    </div>

    <x-card>
            @if (session('status'))
                <div class="alert success">
                    {{ session('status') }}
                </div>
            @endif

            <h2>{{ __('messages.admin.add_review') }}</h2>
            <form method="POST" action="{{ route('admin.reviews.store') }}">
                @csrf

                <div class="admin-form-grid compact">
                    <label>
                        <span>Source</span>
                        <select name="source">
                            <option value="booking">Booking.com</option>
                            <option value="google">Google</option>
                        </select>
                    </label>
                    <label>
                        <span>{{ __('messages.admin.reviewer') }}</span>
                        <input type="text" name="reviewer_name" required>
                    </label>
                    <label>
                        <span>{{ __('messages.admin.rating') }}</span>
                        <input type="number" name="rating" min="1" max="5" value="5" required>
                    </label>
                    <label>
                        <span>{{ __('messages.admin.review_date') }}</span>
                        <input type="date" name="reviewed_at" value="{{ now()->toDateString() }}">
                    </label>
                    <label class="full-width">
                        <span>{{ __('messages.admin.review_text') }}</span>
                        <textarea name="review_text" rows="3" placeholder="Ex. Appartement propre, bien situé, très bon accueil..."></textarea>
                    </label>
                    <label class="full-width">
                        <span>{{ __('messages.admin.source_url') }}</span>
                        <input type="url" name="source_url" value="{{ old('source_url') }}" placeholder="https://...">
                    </label>
                    <label class="checkbox-field">
                        <input type="checkbox" name="is_active" value="1" checked>
                        <span>{{ __('messages.admin.show_home') }}</span>
                    </label>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">{{ __('messages.admin.save_review') }}</button>
                </div>
            </form>

            <h2 class="stacked-title">{{ __('messages.admin.import_csv') }}</h2>
            <form method="POST" action="{{ route('admin.reviews.import') }}">
                @csrf
                <div>
                    <label for="csv">{{ __('messages.admin.csv_format') }}</label>
                    <textarea id="csv" name="csv" rows="6" placeholder="Amina,booking,5,Très bien situé. ,https://..."></textarea>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-ghost">{{ __('messages.admin.import') }}</button>
                </div>
            </form>

            <h2 class="stacked-title">{{ __('messages.admin.saved_reviews') }}</h2>
            @if($reviews->isEmpty())
                <p>{{ __('messages.admin.no_reviews') }}</p>
            @else
                <div class="feature-grid review-admin-grid">
                    @foreach($reviews as $review)
                        <article class="feature-card review-admin-card">
                            <div class="icon">★</div>
                            <h3>{{ $review->reviewer_name }}</h3>
                            <p>{{ $review->source_label }}</p>
                            <strong>{{ str_repeat('★', $review->rating) }}{{ str_repeat('☆', 5 - $review->rating) }}</strong>
                            @if($review->review_text)
                                <p>{{ $review->review_text }}</p>
                            @endif
                            @if($review->source_url)
                                <a href="{{ $review->source_url }}" target="_blank" rel="noopener noreferrer">Voir la source</a>
                            @endif

                            <form method="POST" action="{{ route('admin.reviews.update', $review) }}" class="review-admin-form">
                                @csrf
                                @method('PUT')

                                <label>
                                    <span>Source</span>
                                    <select name="source">
                                        <option value="booking" {{ $review->source === 'booking' ? 'selected' : '' }}>Booking.com</option>
                                        <option value="google" {{ $review->source === 'google' ? 'selected' : '' }}>Google</option>
                                    </select>
                                </label>

                                <label>
                                    <span>Nom</span>
                                    <input type="text" name="reviewer_name" value="{{ $review->reviewer_name }}" required>
                                </label>

                                <label>
                                    <span>Note</span>
                                    <input type="number" name="rating" min="1" max="5" value="{{ $review->rating }}" required>
                                </label>

                                <label>
                                    <span>Date</span>
                                    <input type="date" name="reviewed_at" value="{{ $review->reviewed_at?->toDateString() ?? now()->toDateString() }}">
                                </label>

                                <label>
                                    <span>Texte</span>
                                    <textarea name="review_text" rows="3">{{ $review->review_text }}</textarea>
                                </label>

                                <label>
                                    <span>URL source</span>
                                    <input type="url" name="source_url" value="{{ $review->source_url }}">
                                </label>

                                <label class="checkbox-field">
                                    <input type="checkbox" name="is_active" value="1" {{ $review->is_active ? 'checked' : '' }}>
                                    <span>{{ __('messages.admin.show') }}</span>
                                </label>

                                <div class="review-admin-actions">
                                    <button type="submit" class="btn btn-ghost">{{ __('messages.admin.save_review') }}</button>
                                </div>
                            </form>

                            <form method="POST" action="{{ route('admin.reviews.destroy', $review) }}" class="review-delete-form">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-danger">{{ __('messages.admin.delete') }}</button>
                            </form>
                        </article>
                    @endforeach
                </div>
            @endif
    </x-card>
@endsection
