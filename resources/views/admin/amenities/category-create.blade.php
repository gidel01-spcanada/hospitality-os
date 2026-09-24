@extends('layouts.admin')

@section('title', __('messages.admin.add_amenity_category'))

@section('content')
<div class="admin-page-header">
    <h1 class="admin-page-title">{{ __('messages.admin.add_amenity_category') }}</h1>
    <p class="admin-page-description">{{ __('messages.admin.amenities_description') }}</p>
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

    <form method="POST" action="{{ route('admin.amenities.categories.store') }}">
        @csrf
        <div class="admin-form-grid">
            <label><span>{{ __('messages.admin.category_name_en') }}</span><input type="text" name="name_en" value="{{ old('name_en') }}" required></label>
            <label><span>{{ __('messages.admin.category_name_fr') }}</span><input type="text" name="name_fr" value="{{ old('name_fr') }}" required></label>
        </div>

        <div class="form-actions">
            <x-button type="submit" variant="primary">{{ __('messages.admin.add_category') }}</x-button>
            <x-button tag="a" href="{{ route('admin.amenities.index') }}" variant="secondary">{{ __('messages.admin.cancel') }}</x-button>
        </div>
    </form>
</x-card>
@endsection
