@extends('layouts.admin')

@section('title', __('messages.admin.block_dates'))

@section('content')
<div class="admin-page-header">
    <h1 class="admin-page-title">{{ __('messages.admin.block_dates') }}</h1>
    <p class="admin-page-description">{{ $property->name }}</p>
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

    <form method="POST" action="{{ route('admin.properties.availability.store', $property) }}">
        @csrf
        <div class="admin-form-grid">
            <div class="date-range-picker admin-date-range-picker" data-date-range-picker data-incomplete-message="{{ __('messages.home.select_both_dates') }}" data-past-message="{{ __('messages.home.past_dates') }}" data-start-label="{{ __('messages.admin.blocked_to') }}" data-end-label="{{ __('messages.admin.blocked_to') }}" data-placeholder="{{ __('messages.admin.blocked_from') }}">
                <label for="availability-date-range-trigger"><span>{{ __('messages.admin.blocked_from') }} / {{ __('messages.admin.blocked_to') }}</span><button type="button" id="availability-date-range-trigger" class="date-range-trigger" data-date-range-trigger aria-expanded="false"><span data-date-range-label>{{ __('messages.admin.blocked_from') }} / {{ __('messages.admin.blocked_to') }}</span></button></label>
                <input type="hidden" name="start_date" value="{{ old('start_date') }}" data-date-range-start required>
                <input type="hidden" name="end_date" value="{{ old('end_date') }}" data-date-range-end required>
                <div class="date-range-popover" data-date-range-popover hidden></div>
            </div>
            <label class="full-width"><span>{{ __('messages.admin.reason') }}</span><input name="reason" value="{{ old('reason') }}" placeholder="Maintenance, renovation..." required></label>
        </div>

        <div class="form-actions">
            <x-button type="submit" variant="primary">{{ __('messages.admin.block_dates') }}</x-button>
            <x-button tag="a" href="{{ route('admin.properties.edit', $property) . '#availability' }}" variant="secondary">{{ __('messages.admin.cancel') }}</x-button>
        </div>
    </form>
</x-card>
@endsection
