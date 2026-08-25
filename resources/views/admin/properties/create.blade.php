@extends('layouts.admin')

@section('title', 'Add Property')

@section('content')
<div class="admin-page-header">
    <h1 class="admin-page-title">Add Property</h1>
    <p class="admin-page-description">Create a rental property listing</p>
</div>

<x-card>
    <form method="POST" action="{{ route('admin.properties.store') }}">
        @csrf

        <x-form-group title="Property Details" description="Basic information about the property">
            <div>
                <label for="establishment_id">Establishment <span class="required">*</span></label>
                <select id="establishment_id" name="establishment_id" class="input-md" required>
                    <option value="">Select an establishment</option>
                    @foreach ($establishments as $establishment)
                        <option value="{{ $establishment->id }}" @selected((string) old('establishment_id') === (string) $establishment->id)>{{ $establishment->name }}</option>
                    @endforeach
                </select>
            </div>
            <x-input name="name" label="Property Name" required value="{{ old('name') }}" />
            <x-input name="slug" label="URL Slug" required value="{{ old('slug') }}" placeholder="comfortable-apartment" />
            <div>
                <label for="property_type">Property Type <span class="required">*</span></label>
                <select id="property_type" name="property_type" class="input-md" required>
                    @foreach (['apartment' => 'Apartment', 'house' => 'House', 'villa' => 'Villa', 'studio' => 'Studio', 'room' => 'Room', 'other' => 'Other'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('property_type', 'apartment') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </x-form-group>

        <x-form-group title="Pricing and Capacity" description="Set rates and guest limits">
            <x-input name="nightly_rate_xof" label="Nightly Rate (XOF)" type="number" min="0" step="0.01" required value="{{ old('nightly_rate_xof', 0) }}" />
            <x-input name="nightly_rate_eur" label="Nightly Rate (EUR)" type="number" min="0" step="0.01" required value="{{ old('nightly_rate_eur', 0) }}" />
            <x-input name="minimum_stay" label="Minimum Stay (nights)" type="number" min="1" required value="{{ old('minimum_stay', 1) }}" />
            <x-input name="max_guests" label="Maximum Guests" type="number" min="1" required value="{{ old('max_guests', 1) }}" />
            <div>
                <label for="status">Status <span class="required">*</span></label>
                <select id="status" name="status" class="input-md" required>
                    <option value="draft" @selected(old('status', 'draft') === 'draft')>Draft</option>
                    <option value="published" @selected(old('status') === 'published')>Published</option>
                    <option value="archived" @selected(old('status') === 'archived')>Archived</option>
                </select>
            </div>
        </x-form-group>

        <div class="form-actions">
            <x-button type="submit" variant="primary">Create Property</x-button>
            <x-button tag="a" href="{{ route('admin.properties.index') }}" variant="secondary">Cancel</x-button>
        </div>
    </form>
</x-card>
@endsection
