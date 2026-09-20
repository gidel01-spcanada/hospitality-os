@extends('layouts.admin')

@section('title', __('messages.admin.amenities_title'))

@section('content')
    <div class="admin-page-header">
        <h1 class="admin-page-title">{{ __('messages.admin.amenities_title') }}</h1>
        <p class="admin-page-description">{{ __('messages.admin.amenities_description') }}</p>
    </div>

    @if (session('success'))
        <div class="alert success">{{ session('success') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert error">{{ $errors->first() }}</div>
    @endif

    <div class="amenity-manager">
        <aside class="amenity-manager-sidebar">
            <x-card>
                <h2>{{ __('messages.admin.amenity_categories') }}</h2>
                <p class="form-help">{{ __('messages.admin.select_category_help') }}</p>

                @if ($categories->isEmpty())
                    <p>{{ __('messages.admin.no_amenity_categories') }}</p>
                @else
                    <form method="GET" action="{{ route('admin.amenities.index') }}" class="amenity-manager-select">
                        <label>
                            <span>{{ __('messages.admin.category_selector') }}</span>
                            <select name="category" onchange="this.form.submit()">
                                @foreach ($categories as $category)
                                    <option value="{{ $category->id }}" @selected($selectedCategory?->id === $category->id)>
                                        {{ app()->getLocale() === 'fr' ? $category->name_fr : $category->name_en }} ({{ $category->amenities_count }})
                                    </option>
                                @endforeach
                            </select>
                        </label>
                        <noscript><button type="submit" class="btn btn-ghost btn-small">{{ __('messages.admin.view') }}</button></noscript>
                    </form>
                @endif

                <h2 class="stacked-title">{{ __('messages.admin.add_amenity_category') }}</h2>
                <form method="POST" action="{{ route('admin.amenities.categories.store') }}" class="amenity-manager-new-category">
                    @csrf
                    <label>
                        <span>{{ __('messages.admin.category_name_en') }}</span>
                        <input type="text" name="name_en" value="{{ old('name_en') }}" required>
                    </label>
                    <label>
                        <span>{{ __('messages.admin.category_name_fr') }}</span>
                        <input type="text" name="name_fr" value="{{ old('name_fr') }}" required>
                    </label>
                    <button type="submit" class="btn btn-primary btn-icon" title="{{ __('messages.admin.add_category') }}">
                        <span aria-hidden="true">&#43;</span>
                        <span class="sr-only">{{ __('messages.admin.add_category') }}</span>
                    </button>
                </form>
            </x-card>
        </aside>

        <section class="amenity-manager-detail">
            @if ($selectedCategory)
                <x-card>
                    <div class="amenity-manager-detail-title">
                        <p class="amenity-manager-detail-eyebrow">{{ __('messages.admin.editing_category') }}</p>
                        <h2>{{ app()->getLocale() === 'fr' ? $selectedCategory->name_fr : $selectedCategory->name_en }}</h2>
                    </div>

                    <div class="amenity-manager-detail-header">
                        <form method="POST" action="{{ route('admin.amenities.categories.update', $selectedCategory) }}" class="amenity-category-rename">
                            @csrf
                            @method('PUT')
                            <label>
                                <span>{{ __('messages.admin.category_name_en') }}</span>
                                <input type="text" name="name_en" value="{{ $selectedCategory->name_en }}" required>
                            </label>
                            <label>
                                <span>{{ __('messages.admin.category_name_fr') }}</span>
                                <input type="text" name="name_fr" value="{{ $selectedCategory->name_fr }}" required>
                            </label>
                            <div class="amenity-category-rename-action">
                                <span aria-hidden="true">&nbsp;</span>
                                <button type="submit" class="btn btn-primary btn-icon" title="{{ __('messages.admin.save_category') }}">
                                    <span aria-hidden="true">&#10003;</span>
                                    <span class="sr-only">{{ __('messages.admin.save_category') }}</span>
                                </button>
                            </div>
                        </form>

                        <form method="POST" action="{{ route('admin.amenities.categories.destroy', $selectedCategory) }}" data-confirm-message="{{ __('messages.dialog.delete_item') }}">
                            @csrf
                            @method('DELETE')
                            <div class="amenity-category-rename-action">
                                <span aria-hidden="true">&nbsp;</span>
                                <button
                                    type="submit"
                                    class="btn btn-danger btn-icon"
                                    @disabled($selectedCategory->amenities->isNotEmpty())
                                    title="{{ $selectedCategory->amenities->isNotEmpty() ? __('messages.errors.amenity_category_in_use') : __('messages.admin.delete_category') }}"
                                >
                                    <span aria-hidden="true">&#10005;</span>
                                    <span class="sr-only">{{ __('messages.admin.delete_category') }}</span>
                                </button>
                            </div>
                        </form>
                    </div>

                    <h2 class="stacked-title">
                        {{ trans_choice('messages.admin.amenity_count', $selectedCategory->amenities->count(), ['count' => $selectedCategory->amenities->count()]) }}
                    </h2>

                    @if ($selectedCategory->amenities->isNotEmpty())
                        <div class="amenity-admin-list-head" aria-hidden="true">
                            <span>{{ __('messages.admin.amenity_name_en') }}</span>
                            <span>{{ __('messages.admin.amenity_name_fr') }}</span>
                            <span>{{ __('messages.admin.move_to_category') }}</span>
                            <span></span>
                            <span></span>
                        </div>
                        <div class="amenity-admin-list">
                            @foreach ($selectedCategory->amenities as $amenity)
                                <div class="amenity-admin-row">
                                    <form method="POST" action="{{ route('admin.amenities.update', $amenity) }}">
                                        @csrf
                                        @method('PUT')
                                        <input type="text" name="name_en" value="{{ $amenity->name_en }}" required aria-label="{{ __('messages.admin.amenity_name_en') }}">
                                        <input type="text" name="name_fr" value="{{ $amenity->name_fr }}" required aria-label="{{ __('messages.admin.amenity_name_fr') }}">
                                        <select name="category_id" aria-label="{{ __('messages.admin.move_to_category') }}">
                                            @foreach ($categories as $option)
                                                <option value="{{ $option->id }}" @selected($option->id === $selectedCategory->id)>
                                                    {{ app()->getLocale() === 'fr' ? $option->name_fr : $option->name_en }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <button type="submit" class="btn btn-ghost btn-icon btn-small" title="{{ __('messages.admin.save') }}">
                                            <span aria-hidden="true">&#10003;</span>
                                            <span class="sr-only">{{ __('messages.admin.save') }}</span>
                                        </button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.amenities.destroy', $amenity) }}" data-confirm-message="{{ __('messages.dialog.delete_item') }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-danger btn-icon btn-small" title="{{ __('messages.admin.delete') }}">
                                            <span aria-hidden="true">&#10005;</span>
                                            <span class="sr-only">{{ __('messages.admin.delete') }}</span>
                                        </button>
                                    </form>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p>{{ __('messages.admin.no_amenities_in_category') }}</p>
                    @endif

                    <form method="POST" action="{{ route('admin.amenities.store', $selectedCategory) }}" class="amenity-admin-add-row">
                        @csrf
                        <input type="text" name="name_en" placeholder="{{ __('messages.admin.amenity_name_en') }}" required>
                        <input type="text" name="name_fr" placeholder="{{ __('messages.admin.amenity_name_fr') }}" required>
                        <button type="submit" class="btn btn-primary btn-icon" title="{{ __('messages.admin.add_amenity') }}">
                            <span aria-hidden="true">&#43;</span>
                            <span class="sr-only">{{ __('messages.admin.add_amenity') }}</span>
                        </button>
                    </form>
                </x-card>
            @else
                <x-card>
                    <p>{{ __('messages.admin.no_amenity_categories') }}</p>
                </x-card>
            @endif
        </section>
    </div>
@endsection
