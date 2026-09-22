@extends('layouts.admin')

@section('title', __('messages.cleaning.add_visit'))

@section('content')
<div class="admin-page-header">
    <h1 class="admin-page-title">{{ __('messages.cleaning.add_visit') }}</h1>
    <p class="admin-page-description">{{ __('messages.cleaning.title') }}</p>
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

    <form method="POST" action="{{ route('admin.cleaning.store') }}">
        @csrf
        <input type="hidden" name="mode" value="{{ $mode }}">
        <input type="hidden" name="date" value="{{ $reference->toDateString() }}">
        @if ($propertyId)<input type="hidden" name="property" value="{{ $propertyId }}">@endif
        @if ($assignee)<input type="hidden" name="assignee" value="{{ $assignee }}">@endif

        <div class="admin-form-grid">
            <label>
                <span>{{ __('messages.admin.property') }}</span>
                <select name="property_id" required>
                    <option value="">{{ __('messages.admin.select_property') }}</option>
                    @foreach ($properties as $property)
                        <option value="{{ $property->id }}" @selected((string) old('property_id', $propertyId) === (string) $property->id)>{{ $property->name }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                <span>{{ __('messages.cleaning.assignee') }}</span>
                <input name="assignee_name" value="{{ old('assignee_name', $assignee) }}" required placeholder="{{ __('messages.cleaning.assignee_placeholder') }}">
            </label>
            <label>
                <span>{{ __('messages.cleaning.scheduled_at') }}</span>
                <input type="datetime-local" name="scheduled_at" value="{{ old('scheduled_at', $reference->setTime(10, 0)->format('Y-m-d\TH:i')) }}" required>
            </label>
            <label>
                <span>{{ __('messages.cleaning.duration') }}</span>
                <input type="number" name="duration_minutes" min="15" max="1440" step="15" value="{{ old('duration_minutes', 120) }}" required>
            </label>
            <label>
                <span>{{ __('messages.cleaning.reservation_optional') }}</span>
                <select name="reservation_id">
                    <option value="">{{ __('messages.cleaning.no_reservation') }}</option>
                    @foreach ($reservations as $reservation)
                        <option value="{{ $reservation->id }}" @selected((string) old('reservation_id') === (string) $reservation->id)>{{ $reservation->property->name }} - {{ $reservation->check_out->format('d/m/Y') }} ({{ $reservation->reservation_ref }})</option>
                    @endforeach
                </select>
            </label>
            <label>
                <span>{{ __('messages.admin.status') }}</span>
                <select name="status">
                    @foreach (['scheduled', 'in_progress', 'completed', 'cancelled'] as $status)
                        <option value="{{ $status }}" @selected(old('status', 'scheduled') === $status)>{{ __('messages.cleaning.status_' . $status) }}</option>
                    @endforeach
                </select>
            </label>
            <label class="full-width">
                <span>{{ __('messages.cleaning.instructions') }}</span>
                <textarea name="instructions" rows="3">{{ old('instructions') }}</textarea>
            </label>
        </div>

        <div class="form-actions">
            <x-button type="submit" variant="primary">{{ __('messages.cleaning.add_visit') }}</x-button>
            <x-button tag="a" href="{{ route('admin.cleaning.index', array_filter(['mode' => $mode, 'date' => $reference->toDateString(), 'property' => $propertyId, 'assignee' => $assignee])) }}" variant="secondary">{{ __('messages.admin.cancel') }}</x-button>
        </div>
    </form>
</x-card>
@endsection
