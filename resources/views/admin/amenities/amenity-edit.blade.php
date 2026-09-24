@extends('layouts.admin')

@section('title', __('messages.admin.edit') . ' - ' . $amenity->name_en)

@section('content')
<div class="admin-page-header">
    <h1 class="admin-page-title">{{ $amenity->name_en }}</h1>
    <p class="admin-page-description">{{ app()->getLocale() === 'fr' ? $amenity->category?->name_fr : $amenity->category?->name_en }}</p>
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

    <form method="POST" action="{{ route('admin.amenities.update', $amenity) }}">
        @csrf
        @method('PUT')
        <div class="admin-form-grid">
            <label><span>{{ __('messages.admin.amenity_name_en') }}</span><input type="text" name="name_en" value="{{ old('name_en', $amenity->name_en) }}" required></label>
            <label><span>{{ __('messages.admin.amenity_name_fr') }}</span><input type="text" name="name_fr" value="{{ old('name_fr', $amenity->name_fr) }}" required></label>
            <label>
                <span>{{ __('messages.admin.move_to_category') }}</span>
                <select name="category_id" required>
                    @foreach ($categories as $option)
                        <option value="{{ $option->id }}" @selected((int) old('category_id', $amenity->category_id) === $option->id)>
                            {{ app()->getLocale() === 'fr' ? $option->name_fr : $option->name_en }}
                        </option>
                    @endforeach
                </select>
            </label>
        </div>

        <div class="form-actions">
            <x-button type="submit" variant="primary">{{ __('messages.admin.save') }}</x-button>
            <x-button tag="a" href="{{ route('admin.amenities.categories.show', $amenity->category_id) }}" variant="secondary">{{ __('messages.admin.cancel') }}</x-button>
        </div>
    </form>
</x-card>
@endsection
