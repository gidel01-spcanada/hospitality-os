@extends('layouts.admin')

@section('title', __('messages.admin.amenities_title'))

@section('content')
    <div class="admin-page-header">
        <h1 class="admin-page-title">{{ __('messages.admin.amenities_title') }}</h1>
        <p class="admin-page-description">{{ __('messages.admin.amenities_description') }}</p>
    </div>

    <x-card>
        @if (session('success'))
            <div class="alert success">{{ session('success') }}</div>
        @endif

        @if ($errors->any())
            <div class="alert error">{{ $errors->first() }}</div>
        @endif

        <h2>{{ __('messages.admin.add_amenity_category') }}</h2>
        <form method="POST" action="{{ route('admin.amenities.categories.store') }}">
            @csrf

            <div class="admin-form-grid compact">
                <label>
                    <span>{{ __('messages.admin.category_name_en') }}</span>
                    <input type="text" name="name_en" value="{{ old('name_en') }}" required>
                </label>
                <label>
                    <span>{{ __('messages.admin.category_name_fr') }}</span>
                    <input type="text" name="name_fr" value="{{ old('name_fr') }}" required>
                </label>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">{{ __('messages.admin.save_category') }}</button>
            </div>
        </form>

        <h2 class="stacked-title">{{ __('messages.admin.amenity_categories') }}</h2>
        @if ($categories->isEmpty())
            <p>{{ __('messages.admin.no_amenity_categories') }}</p>
        @else
            <div class="feature-grid">
                @foreach ($categories as $category)
                    <article class="feature-card">
                        <h3>{{ app()->getLocale() === 'fr' ? $category->name_fr : $category->name_en }}</h3>
                        <p>{{ trans_choice('messages.admin.amenity_count', $category->amenities_count, ['count' => $category->amenities_count]) }}</p>

                        @if ($category->amenities->isNotEmpty())
                            <ul class="amenity-list">
                                @foreach ($category->amenities as $amenity)
                                    <li>{{ app()->getLocale() === 'fr' ? $amenity->name_fr : $amenity->name_en }}</li>
                                @endforeach
                            </ul>
                        @endif

                        <form method="POST" action="{{ route('admin.amenities.categories.destroy', $category) }}" class="review-delete-form">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-danger" @disabled($category->amenities_count > 0)>{{ __('messages.admin.delete') }}</button>
                        </form>
                    </article>
                @endforeach
            </div>
        @endif
    </x-card>
@endsection
