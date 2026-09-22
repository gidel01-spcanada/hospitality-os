@extends('layouts.admin')

@section('title', __('messages.admin.add_review'))

@section('content')
<div class="admin-page-header">
    <h1 class="admin-page-title">{{ __('messages.admin.add_review') }}</h1>
    <p class="admin-page-description">{{ $establishment->name }}</p>
</div>

<x-card>
    @if ($errors->any())
        <div class="form-alert form-alert-error" role="alert" tabindex="-1">
            <strong>{{ __('messages.common.error') }}</strong>
            <ul>
                @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.establishments.reviews.store', $establishment) }}">
        @csrf
        <div class="admin-form-grid">
            <label>
                <span>{{ __('messages.admin.source') }}</span>
                <select name="source">
                    <option value="booking" @selected(old('source') === 'booking')>Booking.com</option>
                    <option value="airbnb" @selected(old('source') === 'airbnb')>Airbnb</option>
                    <option value="google" @selected(old('source') === 'google')>Google</option>
                </select>
            </label>
            <label>
                <span>{{ __('messages.admin.reviewer') }}</span>
                <input type="text" name="reviewer_name" value="{{ old('reviewer_name') }}" required>
            </label>
            <label>
                <span>{{ __('messages.admin.review_property') }}</span>
                <select name="property_id">
                    <option value="">{{ __('messages.admin.review_establishment_wide') }}</option>
                    @foreach ($establishment->properties as $property)
                        <option value="{{ $property->id }}" @selected((string) old('property_id') === (string) $property->id)>{{ $property->localized('name') }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                <span>{{ __('messages.admin.rating') }}</span>
                <input type="number" name="rating" min="1" max="5" value="{{ old('rating', 5) }}" required>
            </label>
            <label>
                <span>{{ __('messages.admin.review_date') }}</span>
                <input type="date" name="reviewed_at" value="{{ old('reviewed_at', now()->toDateString()) }}">
            </label>
            <label class="full-width">
                <span>{{ __('messages.admin.review_text') }}</span>
                <textarea name="review_text" rows="3">{{ old('review_text') }}</textarea>
            </label>
            <label class="full-width">
                <span>{{ __('messages.admin.source_url') }}</span>
                <input type="url" name="source_url" value="{{ old('source_url') }}" placeholder="https://...">
            </label>
            <label class="checkbox-field">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', true))>
                <span>{{ __('messages.admin.show_home') }}</span>
            </label>
        </div>

        <div class="form-actions">
            <x-button type="submit" variant="primary">{{ __('messages.admin.save_review') }}</x-button>
            <x-button tag="a" href="{{ route('admin.establishments.edit', $establishment) . '#reviews' }}" variant="secondary">{{ __('messages.admin.cancel') }}</x-button>
        </div>
    </form>
</x-card>
@endsection
