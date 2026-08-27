@extends('layouts.admin')

@section('title', __('messages.admin.properties'))

@section('content')
<div class="admin-page-header">
    <div style="display: flex; justify-content: space-between; align-items: center; gap: var(--space-4);">
        <div>
            <h1 class="admin-page-title">{{ __('messages.admin.properties') }}</h1>
            <p class="admin-page-description">{{ __('messages.admin.properties_description') }}</p>
        </div>
        <x-button tag="a" href="{{ route('admin.properties.create') }}" variant="primary">Add Property</x-button>
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
            <div>
                <label for="status" style="display: block; font-size: var(--font-sm); font-weight: var(--font-semibold); margin-bottom: var(--space-2);">{{ __('messages.admin.status') }}</label>
                <select id="status" name="status" class="input-md">
                    <option value="">{{ __('messages.admin.all_status') }}</option>
                    @foreach (['draft' => __('messages.admin.draft'), 'published' => __('messages.admin.published'), 'archived' => __('messages.admin.archived')] as $value => $label)
                        <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div style="display: flex; gap: var(--space-2);">
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
                        <p style="margin: 0; color: var(--text-secondary); font-size: var(--font-sm);">{{ $property->establishment?->name ?? 'No establishment' }}</p>
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

                <div style="display: flex; justify-content: space-between; align-items: center; padding-top: var(--space-4); border-top: 1px solid var(--border-default);">
                    <span style="font-size: var(--font-sm); color: var(--text-secondary);">
                        {{ $property->availabilityBlocks()->count() + $property->rateRules()->count() }} {{ __('messages.admin.rules') }}
                    </span>
                    <x-button tag="a" href="{{ route('admin.properties.edit', $property) }}" variant="ghost" size="sm">{{ __('messages.admin.edit') }}</x-button>
                </div>
            </div>
        </x-card>
    @empty
        <div style="grid-column: 1 / -1;">
            <x-card>
                <div class="card-body" style="text-align: center;">
                    <h3>{{ __('messages.admin.no_properties_found') }}</h3>
                    <p style="color: var(--text-secondary);">{{ __('messages.admin.no_properties_found_help') }}</p>
                    <x-button tag="a" href="{{ route('admin.properties.create') }}" variant="primary">{{ __('messages.admin.add_property') }}</x-button>
                </div>
            </x-card>
        </div>
    @endforelse
</div>
@endsection
