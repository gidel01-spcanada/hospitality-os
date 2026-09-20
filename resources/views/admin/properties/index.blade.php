@extends('layouts.admin')

@section('title', __('messages.admin.properties'))

@section('content')
<div class="admin-page-header">
    <div class="admin-page-actions" style="justify-content: space-between; gap: var(--space-4);">
        <div>
            <h1 class="admin-page-title">{{ __('messages.admin.properties') }}</h1>
            <p class="admin-page-description">{{ __('messages.admin.properties_description') }}</p>
        </div>
        <x-button tag="a" href="{{ route('admin.properties.create') }}" variant="primary">{{ __('messages.admin.add_property') }}</x-button>
    </div>
</div>

<x-card style="margin-bottom: var(--space-6);">
    <div class="card-body">
        <form method="GET" action="{{ route('admin.properties.index') }}" style="display: flex; gap: var(--space-4); align-items: flex-end; flex-wrap: wrap;">
            <div style="flex: 1; min-width: 220px;">
                <label for="establishment" style="display: block; font-size: var(--font-sm); font-weight: var(--font-semibold); margin-bottom: var(--space-2);">{{ __('messages.common.establishment') }}</label>
                <select id="establishment" name="establishment" class="input-md">
                    <option value="">{{ __('messages.properties.all_establishments') }}</option>
                    @foreach ($establishments as $establishment)
                        <option value="{{ $establishment->id }}" @selected($establishmentId == $establishment->id)>{{ $establishment->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="property-index-filter-field">
                <label for="status">{{ __('messages.admin.status') }}</label>
                <select id="status" name="status" class="input-md">
                    <option value="">{{ __('messages.admin.all_status') }}</option>
                    @foreach (['draft' => __('messages.admin.draft'), 'published' => __('messages.admin.published'), 'archived' => __('messages.admin.archived')] as $value => $label)
                        <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="admin-filter-actions property-index-filter-actions">
                <x-button type="submit" variant="primary">{{ __('messages.admin.filter') }}</x-button>
                <x-button tag="a" href="{{ route('admin.properties.index') }}" variant="secondary">{{ __('messages.admin.reset') }}</x-button>
            </div>
        </form>
    </div>
</x-card>

<div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: var(--space-6);">
    @forelse ($properties as $property)
        <x-card variant="elevated">
            @if ($property->cover_image)
                <img src="{{ asset($property->cover_image) }}" alt="{{ $property->name }}" class="admin-card-media" />
            @else
                <div class="admin-card-media admin-card-media-empty">
                    <span>No image</span>
                </div>
            @endif

            <div>
                <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: var(--space-3);">
                    <div style="min-width: 0;">
                        <h3 style="margin: 0 0 var(--space-1); font-size: var(--font-base); line-height: 1.35;">{{ $property->name }}</h3>
                        <p style="margin: 0; color: var(--text-secondary); font-size: var(--font-sm);">{{ $property->establishment?->name ?? __('messages.admin.no_establishment') }}</p>
                    </div>
                    @if ($property->status === 'published')
                        <x-badge variant="success" size="sm" dot style="flex-shrink: 0;">{{ __('messages.admin.published') }}</x-badge>
                    @elseif ($property->status === 'draft')
                        <x-badge variant="warning" size="sm" dot style="flex-shrink: 0;">{{ __('messages.admin.draft') }}</x-badge>
                    @else
                        <x-badge variant="neutral" size="sm" dot style="flex-shrink: 0;">{{ __('messages.admin.archived') }}</x-badge>
                    @endif
                </div>

                <div style="display: flex; gap: var(--space-2); margin: var(--space-4) 0; flex-wrap: wrap;">
                    <x-badge variant="primary" size="sm">{{ ucfirst($property->property_type ?: 'apartment') }}</x-badge>
                    <x-badge variant="secondary" size="sm">{{ number_format((float) $property->nightly_rate_xof, 0, ',', ' ') }} XOF/night</x-badge>
                </div>

                <div class="property-index-card-footer">
                    <span>
                        {{ $property->availabilityBlocks()->count() + $property->rateRules()->count() }} {{ __('messages.admin.rules') }}
                    </span>
                    <x-button tag="a" href="{{ route('admin.properties.edit', $property) }}" variant="ghost" size="sm" class="btn-icon" title="{{ __('messages.admin.edit') }}" aria-label="{{ __('messages.admin.edit') }}">
                        <svg class="icon" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 9l-6.455 6.456M9 9l6 6" />
                        </svg>
                    </x-button>
                </div>
            </div>
        </x-card>
    @empty
        <div style="grid-column: 1 / -1;">
            <x-card>
                <div class="card-body property-index-empty-content">
                    <h3>{{ __('messages.admin.no_properties_found') }}</h3>
                    <p style="color: var(--text-secondary);">{{ __('messages.admin.no_properties_found_help') }}</p>
                    <x-button tag="a" href="{{ route('admin.properties.create') }}" variant="primary">{{ __('messages.admin.add_property') }}</x-button>
                </div>
            </x-card>
        </div>
    @endforelse
</div>
@endsection
