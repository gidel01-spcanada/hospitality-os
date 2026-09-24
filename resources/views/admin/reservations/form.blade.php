@extends('layouts.admin')

@section('title', $reservation->exists ? __('messages.admin.edit_booking') : __('messages.admin.new_booking'))

@section('content')
<div class="admin-page-header">
    <h1 class="admin-page-title">{{ $reservation->exists ? __('messages.admin.edit_booking') : __('messages.admin.new_booking') }}</h1>
    <p class="admin-page-description">{{ $reservation->exists ? __('messages.admin.update_reservation_details') : __('messages.admin.create_reservation_for_guest') }}</p>
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
            <div>
                <label for="locale">{{ __('messages.profile.language') }}</label>
                <select id="locale" name="locale" class="input-md">
                    <option value="">{{ __('messages.admin.reservation_locale_unspecified') }}</option>
                    <option value="fr" @selected(old('locale', $reservation->locale) === 'fr')>Français</option>
                    <option value="en" @selected(old('locale', $reservation->locale) === 'en')>English</option>
                </select>
                <p class="form-help">{{ __('messages.admin.reservation_locale_help') }}</p>
            </div>
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
            <div class="date-range-picker admin-date-range-picker" data-date-range-picker data-disable-past-dates data-incomplete-message="{{ __('messages.home.select_both_dates') }}" data-past-message="{{ __('messages.home.past_dates') }}" data-start-label="{{ __('messages.admin.check_out') }}" data-end-label="{{ __('messages.admin.check_out') }}" data-placeholder="{{ __('messages.admin.check_in') }} / {{ __('messages.admin.check_out') }}">
                <label for="reservation-date-range-trigger"><span>{{ __('messages.admin.check_in') }} / {{ __('messages.admin.check_out') }}</span><button type="button" id="reservation-date-range-trigger" class="date-range-trigger" data-date-range-trigger aria-expanded="false" @if($errors->has('check_in') || $errors->has('check_out')) aria-invalid="true" @endif><span data-date-range-label>{{ old('check_in', $reservation->check_in?->format('Y-m-d')) && old('check_out', $reservation->check_out?->format('Y-m-d')) ? old('check_in', $reservation->check_in?->format('Y-m-d')) . ' → ' . old('check_out', $reservation->check_out?->format('Y-m-d')) : __('messages.admin.check_in') . ' / ' . __('messages.admin.check_out') }}</span></button></label>
                <input type="hidden" name="check_in" value="{{ old('check_in', $reservation->check_in?->format('Y-m-d')) }}" data-date-range-start required>
                <input type="hidden" name="check_out" value="{{ old('check_out', $reservation->check_out?->format('Y-m-d')) }}" data-date-range-end required>
                <div class="date-range-popover" data-date-range-popover hidden></div>
                @if($errors->has('check_in') || $errors->has('check_out'))<p class="form-error-box" data-date-range-error>{{ $errors->first('check_in') ?: $errors->first('check_out') }}</p>@endif
            </div>
            <x-input name="adults" label="{{ __('messages.admin.adults') }}" type="number" min="1" max="8" required value="{{ old('adults', $reservation->adults ?: 1) }}" />
            <x-input name="children" label="{{ __('messages.admin.children') }}" type="number" min="0" max="8" value="{{ old('children', $reservation->children ?: 0) }}" />
            <x-input name="infants" label="{{ __('messages.admin.infants') }}" type="number" min="0" max="4" value="{{ old('infants', $reservation->infants ?: 0) }}" />
            @if (auth()->user()->isEstablishmentManager())
                <div class="full-width reservation-calendar-override">
                    <label class="checkbox-field">
                        <input type="hidden" name="ignore_external_calendar_conflicts" value="0">
                        <input type="checkbox" name="ignore_external_calendar_conflicts" value="1" @checked(old('ignore_external_calendar_conflicts'))>
                        <span>{{ __('messages.admin.ignore_external_calendar_conflicts') }}</span>
                    </label>
                    <p class="form-help">{{ __('messages.admin.ignore_external_calendar_conflicts_help') }}</p>
                </div>
            @endif
        </x-form-group>

        <x-form-group title="{{ __('messages.admin.notes') }}" description="{{ __('messages.admin.optional_internal_notes') }}">
            <div>
                <label for="notes">{{ __('messages.admin.notes') }}</label>
                <textarea id="notes" name="notes" class="input-md" rows="4">{{ old('notes', $reservation->notes) }}</textarea>
            </div>
            <div>
                <label for="customer_note">{{ __('messages.admin.customer_note') }}</label>
                <textarea id="customer_note" name="customer_note" class="input-md" rows="4">{{ old('customer_note', $reservation->customer_note) }}</textarea>
            </div>
        </x-form-group>

        <div class="form-actions">
            <x-button type="submit" variant="primary">{{ $reservation->exists ? __('messages.admin.update_booking') : __('messages.admin.create_booking') }}</x-button>
            <x-button tag="a" href="{{ route('admin.bookings.index') }}" variant="secondary">{{ __('messages.admin.cancel') }}</x-button>
        </div>
    </form>
</x-card>
@endsection
