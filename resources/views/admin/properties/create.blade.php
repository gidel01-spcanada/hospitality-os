@extends('layouts.admin')

@section('title', __('messages.admin.add_property'))

@section('content')
<div class="admin-page-header">
    <h1 class="admin-page-title">{{ __('messages.admin.add_property') }}</h1>
    <p class="admin-page-description">{{ __('messages.admin.create_property_description') }}</p>
</div>

<x-card>
    <form method="POST" action="{{ route('admin.properties.store') }}">
        @csrf

        <x-form-group title="{{ __('messages.admin.property_details') }}" description="{{ __('messages.admin.property_details_description') }}">
            <div>
                <label for="establishment_id">{{ __('messages.admin.establishment') }} <span class="required">*</span></label>
                <select id="establishment_id" name="establishment_id" class="input-md" required>
                    <option value="">{{ __('messages.admin.select_establishment') }}</option>
                    @foreach ($establishments as $establishment)
                        <option value="{{ $establishment->id }}" @selected((string) old('establishment_id') === (string) $establishment->id)>{{ $establishment->name }}</option>
                    @endforeach
                </select>
            </div>
            <x-input name="name" label="{{ __('messages.admin.property_name') }}" required value="{{ old('name') }}" />
            <x-input name="slug" label="{{ __('messages.admin.slug') }}" required value="{{ old('slug') }}" placeholder="comfortable-apartment" />
            <div>
                <label for="property_type">{{ __('messages.admin.property_type') }} <span class="required">*</span></label>
                <select id="property_type" name="property_type" class="input-md" required>
                    @foreach (['apartment', 'house', 'villa', 'studio', 'room', 'other'] as $value)
                        <option value="{{ $value }}" @selected(old('property_type', 'apartment') === $value)>{{ __('messages.admin.type_' . $value) }}</option>
                    @endforeach
                </select>
            </div>
        </x-form-group>

        <x-form-group title="{{ __('messages.admin.pricing_capacity') }}" description="{{ __('messages.admin.pricing_capacity_description') }}">
            <x-input name="nightly_rate_xof" label="{{ __('messages.admin.nightly_rate_xof') }}" type="number" min="0" step="0.01" required value="{{ old('nightly_rate_xof', 0) }}" />
            <x-input name="minimum_stay" label="{{ __('messages.admin.minimum_stay') }}" type="number" min="1" required value="{{ old('minimum_stay', 1) }}" />
            <x-input name="max_guests" label="{{ __('messages.admin.maximum_guests') }}" type="number" min="1" required value="{{ old('max_guests', 1) }}" />
            <x-input name="bedrooms" label="{{ __('messages.admin.bedrooms') }}" type="number" min="0" required value="{{ old('bedrooms', 0) }}" />
            <x-input name="bathrooms" label="{{ __('messages.admin.bathrooms') }}" type="number" min="0" required value="{{ old('bathrooms', 0) }}" />
            <x-input name="beds" label="{{ __('messages.admin.beds') }}" type="number" min="0" required value="{{ old('beds', 0) }}" />
            <x-input name="area" label="{{ __('messages.admin.area') }}" type="number" min="0" step="0.01" value="{{ old('area') }}" />
            <div>
                <label for="area_unit">{{ __('messages.admin.area_unit') }}</label>
                <select id="area_unit" name="area_unit" class="input-md">
                    <option value="m2" @selected(old('area_unit', 'm2') === 'm2')>m²</option>
                    <option value="ft2" @selected(old('area_unit') === 'ft2')>ft²</option>
                </select>
            </div>
            <x-input name="floor" label="{{ __('messages.admin.floor') }}" type="number" min="0" max="200" value="{{ old('floor') }}" />
            <div>
                <label for="status">{{ __('messages.admin.status') }} <span class="required">*</span></label>
                <select id="status" name="status" class="input-md" required>
                    <option value="draft" @selected(old('status', 'draft') === 'draft')>{{ __('messages.admin.draft') }}</option>
                    <option value="published" @selected(old('status') === 'published')>{{ __('messages.admin.published') }}</option>
                    <option value="archived" @selected(old('status') === 'archived')>{{ __('messages.admin.archived') }}</option>
                </select>
            </div>
        </x-form-group>

        <div class="form-actions">
            <x-button type="submit" variant="primary">{{ __('messages.admin.create_property') }}</x-button>
            <x-button tag="a" href="{{ route('admin.properties.index') }}" variant="secondary">{{ __('messages.admin.cancel') }}</x-button>
        </div>
    </form>
</x-card>
@endsection
