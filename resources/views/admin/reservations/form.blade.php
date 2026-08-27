@extends('layouts.admin')

@section('title', $reservation->exists ? __('messages.admin.edit_booking') : __('messages.admin.new_booking'))

@section('content')
<div class="admin-page-header">
    <h1 class="admin-page-title">{{ $reservation->exists ? __('messages.admin.edit_booking') : __('messages.admin.new_booking') }}</h1>
    <p class="admin-page-description">{{ $reservation->exists ? __('messages.admin.update_reservation_details') : __('messages.admin.create_reservation_for_guest') }}</p>
</div>

<x-card>
    <form method="POST" action="{{ $reservation->exists ? route('admin.reservations.update', $reservation) : route('admin.reservations.store') }}">
        @csrf
        @if ($reservation->exists)
            @method('PUT')
        @endif

        <x-form-group title="{{ __('messages.admin.guest_details') }}" description="{{ __('messages.admin.reservation_guest_contact') }}">
            <x-input name="full_name" label="{{ __('messages.profile.full_name') }}" required value="{{ old('full_name', $reservation->guest?->full_name) }}" />
            <x-input name="email" label="{{ __('messages.common.email') }}" type="email" required value="{{ old('email', $reservation->email ?: $reservation->guest?->email) }}" />
            <x-input name="phone" label="{{ __('messages.admin.phone') }}" value="{{ old('phone', $reservation->guest?->phone) }}" />
            <x-input name="country" label="{{ __('messages.reservation.country') }}" value="{{ old('country', $reservation->guest?->country) }}" />
        </x-form-group>

        <x-form-group title="{{ __('messages.admin.stay_details') }}" description="{{ __('messages.admin.select_property_dates') }}">
            <div>
                <label for="property_id">{{ __('messages.admin.property') }} <span class="required">*</span></label>
                <select id="property_id" name="property_id" class="input-md" required>
                    <option value="">{{ __('messages.admin.select_property') }}</option>
                    @foreach ($properties as $property)
                        <option value="{{ $property->id }}" @selected((string) old('property_id', $reservation->property_id) === (string) $property->id)>
                            {{ $property->name }} ({{ number_format((float) $property->nightly_rate_xof, 0, ',', ' ') }} XOF/night)
                        </option>
                    @endforeach
                </select>
            </div>
            <x-input name="check_in" label="{{ __('messages.admin.check_in') }}" type="date" required value="{{ old('check_in', $reservation->check_in?->format('Y-m-d')) }}" />
            <x-input name="check_out" label="{{ __('messages.admin.check_out') }}" type="date" required value="{{ old('check_out', $reservation->check_out?->format('Y-m-d')) }}" />
            <x-input name="adults" label="{{ __('messages.admin.adults') }}" type="number" min="1" max="8" required value="{{ old('adults', $reservation->adults ?: 1) }}" />
            <x-input name="children" label="{{ __('messages.admin.children') }}" type="number" min="0" max="8" value="{{ old('children', $reservation->children ?: 0) }}" />
            <x-input name="infants" label="{{ __('messages.admin.infants') }}" type="number" min="0" max="4" value="{{ old('infants', $reservation->infants ?: 0) }}" />
        </x-form-group>

        <x-form-group title="{{ __('messages.admin.notes') }}" description="{{ __('messages.admin.optional_internal_notes') }}">
            <div>
                <label for="notes">{{ __('messages.admin.notes') }}</label>
                <textarea id="notes" name="notes" class="input-md" rows="4">{{ old('notes', $reservation->notes) }}</textarea>
            </div>
        </x-form-group>

        <div class="form-actions">
            <x-button type="submit" variant="primary">{{ $reservation->exists ? __('messages.admin.update_booking') : __('messages.admin.create_booking') }}</x-button>
            <x-button tag="a" href="{{ route('admin.bookings.index') }}" variant="secondary">{{ __('messages.admin.cancel') }}</x-button>
        </div>
    </form>
</x-card>
@endsection
