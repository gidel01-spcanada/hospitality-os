@extends('layouts.admin')

@section('title', __('messages.admin.amenities_title'))

@section('content')
    <div class="admin-page-header">
        <div>
            <h1 class="admin-page-title">{{ __('messages.admin.amenities_title') }}</h1>
            <p class="admin-page-description">{{ __('messages.admin.amenities_description') }}</p>
        </div>
        <div class="admin-page-actions">
            <x-button tag="a" href="{{ route('admin.amenities.categories.create') }}" variant="primary">{{ __('messages.admin.add_amenity_category') }}</x-button>
        </div>
    </div>

    @if (session('success'))
        <div class="reservation-success">{{ session('success') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert error">{{ $errors->first() }}</div>
    @endif

    <x-card>
        <div class="admin-table-wrap" tabindex="0" role="region" aria-label="{{ __('messages.admin.amenity_categories') }}">
            <table class="admin-table">
                <thead><tr><th>{{ __('messages.admin.category_name_en') }}</th><th>{{ __('messages.admin.category_name_fr') }}</th><th>{{ __('messages.admin.amenities_menu') }}</th><th>{{ __('messages.admin.actions') }}</th></tr></thead>
                <tbody>
                    @forelse ($categories as $category)
                        <tr>
                            <td><strong>{{ $category->name_en }}</strong></td>
                            <td>{{ $category->name_fr }}</td>
                            <td>{{ trans_choice('messages.admin.amenity_count', $category->amenities_count, ['count' => $category->amenities_count]) }}</td>
                            <td>
                                <div class="admin-row-actions">
                                    <x-button tag="a" href="{{ route('admin.amenities.categories.show', $category) }}" variant="ghost" size="sm" title="{{ __('messages.admin.view') }}">{{ __('messages.admin.view') }}</x-button>
                                    <x-button tag="a" href="{{ route('admin.amenities.categories.edit', $category) }}" variant="ghost" size="sm" class="btn-icon" title="{{ __('messages.admin.edit') }}" aria-label="{{ __('messages.admin.edit') }}">
                                        <svg class="icon" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 9l-6.455 6.456M9 9l6 6" />
                                        </svg>
                                    </x-button>
                                    <form method="POST" action="{{ route('admin.amenities.categories.destroy', $category) }}" data-confirm-message="{{ __('messages.dialog.delete_item') }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-danger btn-icon btn-small" @disabled($category->amenities_count > 0) title="{{ $category->amenities_count > 0 ? __('messages.errors.amenity_category_in_use') : __('messages.admin.delete_category') }}" aria-label="{{ __('messages.admin.delete_category') }}">
                                            <span aria-hidden="true">&#10005;</span>
                                            <span class="sr-only">{{ __('messages.admin.delete_category') }}</span>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="empty-state-inline">{{ __('messages.admin.no_amenity_categories') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>
@endsection

