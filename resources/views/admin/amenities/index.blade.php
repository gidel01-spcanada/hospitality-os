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
            <div class="amenity-category-grid">
                @foreach ($categories as $category)
                    <article class="feature-card amenity-category-card">
                        <form method="POST" action="{{ route('admin.amenities.categories.update', $category) }}" class="amenity-category-rename">
                            @csrf
                            @method('PUT')
                            <label>
                                <span>{{ __('messages.admin.category_name_en') }}</span>
                                <input type="text" name="name_en" value="{{ $category->name_en }}" required>
                            </label>
                            <label>
                                <span>{{ __('messages.admin.category_name_fr') }}</span>
                                <input type="text" name="name_fr" value="{{ $category->name_fr }}" required>
                            </label>
                            <button type="submit" class="btn btn-ghost btn-small">{{ __('messages.admin.save_category') }}</button>
                        </form>

                        <p>{{ trans_choice('messages.admin.amenity_count', $category->amenities_count, ['count' => $category->amenities_count]) }}</p>

                        @if ($category->amenities->isNotEmpty())
                            <div class="amenity-admin-list">
                                @foreach ($category->amenities as $amenity)
                                    <div class="amenity-admin-row">
                                        <form method="POST" action="{{ route('admin.amenities.update', $amenity) }}">
                                            @csrf
                                            @method('PUT')
                                            <input type="text" name="name_en" value="{{ $amenity->name_en }}" required aria-label="{{ __('messages.admin.amenity_name_en') }}">
                                            <input type="text" name="name_fr" value="{{ $amenity->name_fr }}" required aria-label="{{ __('messages.admin.amenity_name_fr') }}">
                                            <select name="category_id" aria-label="{{ __('messages.admin.move_to_category') }}">
                                                @foreach ($categories as $option)
                                                    <option value="{{ $option->id }}" @selected($option->id === $category->id)>
                                                        {{ app()->getLocale() === 'fr' ? $option->name_fr : $option->name_en }}
                                                    </option>
                                                @endforeach
                                            </select>
                                            <button type="submit" class="btn btn-ghost btn-small">{{ __('messages.admin.save') }}</button>
                                        </form>
                                        <form method="POST" action="{{ route('admin.amenities.destroy', $amenity) }}">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-danger btn-small">{{ __('messages.admin.delete') }}</button>
                                        </form>
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        <form method="POST" action="{{ route('admin.amenities.store', $category) }}" class="amenity-admin-add-row">
                            @csrf
                            <input type="text" name="name_en" placeholder="{{ __('messages.admin.amenity_name_en') }}" required>
                            <input type="text" name="name_fr" placeholder="{{ __('messages.admin.amenity_name_fr') }}" required>
                            <button type="submit" class="btn btn-ghost btn-small">{{ __('messages.admin.add_amenity') }}</button>
                        </form>

                        <form method="POST" action="{{ route('admin.amenities.categories.destroy', $category) }}" class="review-delete-form">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-danger" @disabled($category->amenities_count > 0)>{{ __('messages.admin.delete_category') }}</button>
                        </form>
                    </article>
                @endforeach
            </div>
        @endif
    </x-card>
@endsection
