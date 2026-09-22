@extends('layouts.admin')

@section('title', __('messages.cleaning.generate_from_departures'))

@section('content')
<div class="admin-page-header">
    <h1 class="admin-page-title">{{ __('messages.cleaning.generate_from_departures') }}</h1>
    <p class="admin-page-description">{{ __('messages.cleaning.generate_help') }}</p>
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

    <form method="POST" action="{{ route('admin.cleaning.generate') }}">
        @csrf
        <input type="hidden" name="mode" value="{{ $mode }}">
        <input type="hidden" name="date" value="{{ $reference->toDateString() }}">
        @if ($propertyId)<input type="hidden" name="property" value="{{ $propertyId }}">@endif
        @if ($assignee)<input type="hidden" name="assignee" value="{{ $assignee }}">@endif

        <div class="admin-form-grid">
            <label>
                <span>{{ __('messages.cleaning.assignee') }}</span>
                <input name="assignee_name" value="{{ old('assignee_name') }}" required placeholder="{{ __('messages.cleaning.assignee_placeholder') }}">
            </label>
            <label>
                <span>{{ __('messages.cleaning.start_time') }}</span>
                <input type="time" name="scheduled_time" value="{{ old('scheduled_time', '11:00') }}" required>
            </label>
            <label>
                <span>{{ __('messages.cleaning.duration') }}</span>
                <input type="number" name="duration_minutes" min="15" max="1440" step="15" value="{{ old('duration_minutes', 120) }}" required>
            </label>
            <label class="full-width">
                <span>{{ __('messages.cleaning.instructions') }}</span>
                <textarea name="instructions" rows="3" placeholder="{{ __('messages.cleaning.default_instructions_placeholder') }}">{{ old('instructions') }}</textarea>
            </label>
        </div>

        <div class="form-actions">
            <x-button type="submit" variant="primary">{{ __('messages.cleaning.generate') }}</x-button>
            <x-button tag="a" href="{{ route('admin.cleaning.index', array_filter(['mode' => $mode, 'date' => $reference->toDateString(), 'property' => $propertyId, 'assignee' => $assignee])) }}" variant="secondary">{{ __('messages.admin.cancel') }}</x-button>
        </div>
    </form>
</x-card>
@endsection
