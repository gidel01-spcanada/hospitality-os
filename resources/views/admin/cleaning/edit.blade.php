@extends('layouts.admin')

@section('title', __('messages.admin.edit') . ' — ' . $visit->property->name)

@section('content')
<div class="admin-page-header">
    <h1 class="admin-page-title">{{ __('messages.admin.edit') }} — {{ $visit->property->name }}</h1>
    <p class="admin-page-description">{{ $visit->scheduled_at->translatedFormat('D d M · H:i') }}</p>
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

    <form method="POST" action="{{ route('admin.cleaning.update', $visit) }}">
        @csrf
        @method('PUT')

        <div class="admin-form-grid">
            <label>
                <span>{{ __('messages.admin.property') }}</span>
                <select name="property_id" required>
                    @foreach ($properties as $property)
                        <option value="{{ $property->id }}" @selected($visit->property_id === $property->id)>{{ $property->name }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                <span>{{ __('messages.cleaning.assignee') }}</span>
                <input name="assignee_name" value="{{ old('assignee_name', $visit->assignee_name) }}" required>
            </label>
            <label>
                <span>{{ __('messages.cleaning.scheduled_at') }}</span>
                <input type="datetime-local" name="scheduled_at" value="{{ old('scheduled_at', $visit->scheduled_at->format('Y-m-d\TH:i')) }}" required>
            </label>
            <label>
                <span>{{ __('messages.cleaning.duration') }}</span>
                <input type="number" name="duration_minutes" min="15" max="1440" step="15" value="{{ old('duration_minutes', $visit->duration_minutes) }}" required>
            </label>
            <label>
                <span>{{ __('messages.cleaning.reservation_optional') }}</span>
                <select name="reservation_id">
                    <option value="">{{ __('messages.cleaning.no_reservation') }}</option>
                    @foreach ($reservations as $reservation)
                        <option value="{{ $reservation->id }}" @selected((string) old('reservation_id', $visit->reservation_id) === (string) $reservation->id)>{{ $reservation->property->name }} - {{ $reservation->check_out->format('d/m/Y') }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                <span>{{ __('messages.admin.status') }}</span>
                <select name="status">
                    @foreach (['scheduled', 'in_progress', 'completed', 'cancelled'] as $status)
                        <option value="{{ $status }}" @selected(old('status', $visit->status) === $status)>{{ __('messages.cleaning.status_' . $status) }}</option>
                    @endforeach
                </select>
            </label>
            <label class="full-width">
                <span>{{ __('messages.cleaning.instructions') }}</span>
                <textarea name="instructions" rows="3">{{ old('instructions', $visit->instructions) }}</textarea>
            </label>
        </div>

        <div class="form-actions">
            <x-button type="submit" variant="primary">{{ __('messages.admin.save_changes') }}</x-button>
            <x-button tag="a" href="{{ route('admin.cleaning.index') }}" variant="secondary">{{ __('messages.admin.cancel') }}</x-button>
        </div>
    </form>
</x-card>
@endsection
