@extends('layouts.admin')

@section('title', $feed ? __('messages.admin.manage_calendar_feed') : __('messages.admin.add_calendar_feed_title'))

@section('content')
<div class="admin-page-header">
    <h1 class="admin-page-title">{{ $feed ? __('messages.admin.manage_calendar_feed') : __('messages.admin.add_calendar_feed_title') }}</h1>
    <p class="admin-page-description">{{ __('messages.admin.add_calendar_feed_help') }}</p>
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

    <form method="POST" action="{{ $feed ? route('admin.properties.calendar.update', [$property, $feed]) : route('admin.properties.calendar.store', $property) }}">
        @csrf
        @if ($feed)
            @method('PUT')
        @endif

        <div class="admin-form-grid">
            <label class="full-width">
                <span>{{ __('messages.admin.calendar_name') }}</span>
                <input name="name" value="{{ old('name', $feed?->name) }}" placeholder="Booking or Airbnb feed" required>
            </label>
            <label class="full-width">
                <span>{{ __('messages.admin.calendar_provider') }}</span>
                <select name="provider" required>
                    <option value="booking" @selected(old('provider', $feed?->provider) === 'booking')>Booking.com</option>
                    <option value="airbnb" @selected(old('provider', $feed?->provider) === 'airbnb')>Airbnb</option>
                    <option value="vrbo" @selected(old('provider', $feed?->provider) === 'vrbo')>Vrbo</option>
                    <option value="other" @selected(old('provider', $feed?->provider) === 'other')>{{ __('messages.admin.other') }}</option>
                </select>
            </label>
            <label class="full-width">
                <span>{{ __('messages.admin.ics_url') }}</span>
                <input type="url" name="url" value="{{ old('url', $feed?->url) }}" placeholder="https://example.com/calendar.ics" required>
            </label>
            <label class="checkbox-field full-width">
                <input type="hidden" name="is_enabled" value="0">
                <input type="checkbox" name="is_enabled" value="1" @checked(old('is_enabled', $feed?->is_enabled ?? true))>
                <span>{{ __('messages.admin.enable_automatically') }}</span>
            </label>
        </div>

        <div class="form-actions">
            <x-button type="submit" variant="primary">{{ $feed ? __('messages.admin.update') : __('messages.admin.save_calendar_feed') }}</x-button>
            <x-button tag="a" href="{{ route('admin.properties.edit', $property) . '#calendars' }}" variant="secondary">{{ __('messages.admin.cancel') }}</x-button>
        </div>
    </form>
</x-card>
@endsection
