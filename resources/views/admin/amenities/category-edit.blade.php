@extends('layouts.admin')

@section('title', __('messages.admin.editing_category'))

@section('content')
<div class="admin-page-header">
    <h1 class="admin-page-title">{{ __('messages.admin.editing_category') }}</h1>
    <p class="admin-page-description">{{ app()->getLocale() === 'fr' ? $category->name_fr : $category->name_en }}</p>
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

    <form method="POST" action="{{ route('admin.amenities.categories.update', $category) }}">
        @csrf
        @method('PUT')
        <div class="admin-form-grid">
            <label><span>{{ __('messages.admin.category_name_en') }}</span><input type="text" name="name_en" value="{{ old('name_en', $category->name_en) }}" required></label>
            <label><span>{{ __('messages.admin.category_name_fr') }}</span><input type="text" name="name_fr" value="{{ old('name_fr', $category->name_fr) }}" required></label>
        </div>

        <div class="form-actions">
            <x-button type="submit" variant="primary">{{ __('messages.admin.save_category') }}</x-button>
            <x-button tag="a" href="{{ route('admin.amenities.categories.show', $category) }}" variant="secondary">{{ __('messages.admin.cancel') }}</x-button>
        </div>
    </form>
</x-card>
@endsection
