@extends('layouts.admin')

@section('title', __('messages.admin.calendar_title'))

@section('content')
    <div class="admin-page-header">
        <h1 class="admin-page-title">{{ $property->name }}</h1>
        <p class="admin-page-description">{{ __('messages.admin.calendar_description') }}</p>
    </div>

    <x-card>
        @if (session('success'))
            <div class="reservation-success">{{ session('success') }}</div>
        @endif

        <div class="admin-two-column">
            <div class="admin-panel">
                <h2>{{ __('messages.admin.add_feed') }}</h2>
                <form method="POST" action="{{ route('admin.properties.calendar.store', $property) }}">
                    @csrf

                    <div class="admin-form-grid">
                        <label class="full-width">
                            <span>{{ __('messages.admin.feed_name') }}</span>
                            <input type="text" name="name" placeholder="Booking or Airbnb feed" required>
                        </label>
                        <label class="full-width">
                            <span>{{ __('messages.admin.calendar_provider') }}</span>
                            <select name="provider" required>
                                <option value="booking">Booking.com</option>
                                <option value="airbnb">Airbnb</option>
                                <option value="vrbo">Vrbo</option>
                                <option value="other">{{ __('messages.admin.other') }}</option>
                            </select>
                        </label>
                        <label class="full-width">
                            <span>URL ICS</span>
                            <input type="url" name="url" placeholder="https://example.com/calendar.ics" required>
                        </label>
                        <label class="checkbox-field full-width">
                            <input type="checkbox" name="is_enabled" value="1" checked>
                            <span>{{ __('messages.admin.enable_automatically') }}</span>
                        </label>
                    </div>

                    <div class="form-actions">
                        <button class="btn btn-primary" type="submit">{{ __('messages.admin.save_feed') }}</button>
                    </div>
                </form>
            </div>

            <div class="admin-panel">
                <h2>{{ __('messages.admin.saved_feeds') }}</h2>
                @if ($feeds->isEmpty())
                    <p class="empty-note">{{ __('messages.admin.no_icalendar') }}</p>
                @else
                    <ul class="feed-list">
                        @foreach ($feeds as $feed)
                            <li>
                                <div>
                                    <strong>{{ $feed->name }} · {{ ucfirst($feed->provider) }}</strong>
                                    <small>{{ $feed->status }} · {{ $feed->last_successful_sync_at?->format('d/m/Y H:i') ?? __('messages.admin.never_synced') }}{{ $feed->last_sync_error ? ' · '.$feed->last_sync_error : '' }}</small>
                                </div>
                                <div class="feed-actions">
                                    <form method="POST" action="{{ route('admin.properties.calendar.sync', [$property, $feed]) }}">
                                        @csrf
                                        <button class="btn btn-ghost btn-small" type="submit">{{ __('messages.admin.sync') }}</button>
                                    </form>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif

                <div class="form-actions">
                    <a class="btn btn-ghost" href="{{ route('admin.properties.calendar.export', $property) }}">{{ __('messages.admin.export_calendar') }}</a>
                </div>
            </div>
        </div>
    </x-card>
@endsection
