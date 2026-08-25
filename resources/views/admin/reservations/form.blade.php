@extends('layouts.admin')

@section('title', $reservation->exists ? 'Edit Booking' : 'New Booking')

@section('content')
<div class="admin-page-header">
    <h1 class="admin-page-title">{{ $reservation->exists ? 'Edit Booking' : 'New Booking' }}</h1>
    <p class="admin-page-description">{{ $reservation->exists ? 'Update reservation details' : 'Create a reservation for a guest' }}</p>
</div>

<x-card>
    <form method="POST" action="{{ $reservation->exists ? route('admin.reservations.update', $reservation) : route('admin.reservations.store') }}">
        @csrf
        @if ($reservation->exists)
            @method('PUT')
        @endif

        <x-form-group title="Guest Details" description="Contact information for the reservation guest">
            <x-input name="full_name" label="Full Name" required value="{{ old('full_name', $reservation->guest?->full_name) }}" />
            <x-input name="email" label="Email" type="email" required value="{{ old('email', $reservation->email ?: $reservation->guest?->email) }}" />
            <x-input name="phone" label="Phone" value="{{ old('phone', $reservation->guest?->phone) }}" />
            <x-input name="country" label="Country" value="{{ old('country', $reservation->guest?->country) }}" />
        </x-form-group>

        <x-form-group title="Stay Details" description="Select the property and reservation dates">
            <div>
                <label for="property_id">Property <span class="required">*</span></label>
                <select id="property_id" name="property_id" class="input-md" required>
                    <option value="">Select a property</option>
                    @foreach ($properties as $property)
                        <option value="{{ $property->id }}" @selected((string) old('property_id', $reservation->property_id) === (string) $property->id)>
                            {{ $property->name }} ({{ number_format((float) $property->nightly_rate_xof, 0, ',', ' ') }} XOF/night)
                        </option>
                    @endforeach
                </select>
            </div>
            <x-input name="check_in" label="Check-in" type="date" required value="{{ old('check_in', $reservation->check_in?->format('Y-m-d')) }}" />
            <x-input name="check_out" label="Check-out" type="date" required value="{{ old('check_out', $reservation->check_out?->format('Y-m-d')) }}" />
            <x-input name="adults" label="Adults" type="number" min="1" max="8" required value="{{ old('adults', $reservation->adults ?: 1) }}" />
            <x-input name="children" label="Children" type="number" min="0" max="8" value="{{ old('children', $reservation->children ?: 0) }}" />
            <x-input name="infants" label="Infants" type="number" min="0" max="4" value="{{ old('infants', $reservation->infants ?: 0) }}" />
        </x-form-group>

        <x-form-group title="Notes" description="Optional internal notes for staff">
            <div>
                <label for="notes">Notes</label>
                <textarea id="notes" name="notes" class="input-md" rows="4">{{ old('notes', $reservation->notes) }}</textarea>
            </div>
        </x-form-group>

        <div class="form-actions">
            <x-button type="submit" variant="primary">{{ $reservation->exists ? 'Update Booking' : 'Create Booking' }}</x-button>
            <x-button tag="a" href="{{ route('admin.bookings.index') }}" variant="secondary">Cancel</x-button>
        </div>
    </form>
</x-card>
@endsection
