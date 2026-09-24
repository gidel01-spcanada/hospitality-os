@extends('layouts.admin')

@section('title', app()->getLocale() === 'fr' ? $category->name_fr : $category->name_en)

@section('content')
    <div class="admin-page-header">
        <div>
            <a class="btn btn-ghost btn-small" href="{{ route('admin.amenities.index') }}">{{ __('messages.admin.back_list') }}</a>
            <h1 class="admin-page-title">{{ app()->getLocale() === 'fr' ? $category->name_fr : $category->name_en }}</h1>
            <p class="admin-page-description">{{ trans_choice('messages.admin.amenity_count', $category->amenities->count(), ['count' => $category->amenities->count()]) }}</p>
        </div>
        <div class="admin-page-actions">
            <x-button tag="a" href="{{ route('admin.amenities.categories.edit', $category) }}" variant="ghost">{{ __('messages.admin.edit') }}</x-button>
            <x-button tag="a" href="{{ route('admin.amenities.create', $category) }}" variant="primary">{{ __('messages.admin.add_amenity') }}</x-button>
        </div>
    </div>

    @if (session('success'))
        <div class="reservation-success">{{ session('success') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert error">{{ $errors->first() }}</div>
    @endif

    <x-card>
        <div class="admin-table-wrap" tabindex="0" role="region" aria-label="{{ app()->getLocale() === 'fr' ? $category->name_fr : $category->name_en }}">
            <table class="admin-table">
                <thead><tr><th>{{ __('messages.admin.amenity_name_en') }}</th><th>{{ __('messages.admin.amenity_name_fr') }}</th><th>{{ __('messages.admin.actions') }}</th></tr></thead>
                <tbody>
                    @forelse ($category->amenities as $amenity)
                        <tr>
                            <td><strong>{{ $amenity->name_en }}</strong></td>
                            <td>{{ $amenity->name_fr }}</td>
                            <td>
                                <div class="admin-row-actions">
                                    <x-button tag="a" href="{{ route('admin.amenities.edit', $amenity) }}" variant="ghost" size="sm" class="btn-icon" title="{{ __('messages.admin.edit') }}" aria-label="{{ __('messages.admin.edit') }}">
                                        <svg class="icon" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 9l-6.455 6.456M9 9l6 6" />
                                        </svg>
                                    </x-button>
                                    <form method="POST" action="{{ route('admin.amenities.destroy', $amenity) }}" data-confirm-message="{{ __('messages.dialog.delete_item') }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-danger btn-icon btn-small" title="{{ __('messages.admin.delete') }}" aria-label="{{ __('messages.admin.delete') }}">
                                            <span aria-hidden="true">&#10005;</span>
                                            <span class="sr-only">{{ __('messages.admin.delete') }}</span>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="empty-state-inline">{{ __('messages.admin.no_amenities_in_category') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>
@endsection
