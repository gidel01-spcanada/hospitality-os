@extends('layouts.admin')

@section('title', 'Properties')

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
                <label for="status" style="display: block; font-size: var(--font-sm); font-weight: var(--font-semibold); margin-bottom: var(--space-2);">Status</label>
                <select id="status" name="status" class="input-md">
                    <option value="">All statuses</option>
                    @foreach (['draft' => 'Draft', 'published' => 'Published', 'archived' => 'Archived'] as $value => $label)
                        <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div style="display: flex; gap: var(--space-2);">
                <x-button type="submit" variant="primary">Filter</x-button>
                <x-button tag="a" href="{{ route('admin.properties.index') }}" variant="secondary">Reset</x-button>
            </div>
        </form>
    </div>
</x-card>

<div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: var(--space-6);">
    @forelse ($properties as $property)
        <x-card variant="elevated">
            @if ($property->cover_image)
                <img src="{{ asset($property->cover_image) }}" alt="{{ $property->name }}" style="width: 100%; height: 180px; object-fit: cover; border-radius: var(--radius-lg) var(--radius-lg) 0 0; margin: -24px -24px 24px -24px;" />
            @else
                <div style="width: 100%; height: 180px; background: var(--color-slate-100); display: flex; align-items: center; justify-content: center; border-radius: var(--radius-lg) var(--radius-lg) 0 0; margin: -24px -24px 24px -24px;">
                    <span style="color: var(--text-tertiary);">No image</span>
                </div>
            @endif

            <div>
                <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: var(--space-3);">
                    <div>
                        <h3 style="margin: 0 0 var(--space-1);">{{ $property->name }}</h3>
                        <p style="margin: 0; color: var(--text-secondary); font-size: var(--font-sm);">{{ $property->establishment?->name ?? 'No establishment' }}</p>
                    </div>
                    @if ($property->status === 'published')
                        <x-badge variant="success" size="sm" dot>Published</x-badge>
                    @elseif ($property->status === 'draft')
                        <x-badge variant="warning" size="sm" dot>Draft</x-badge>
                    @else
                        <x-badge variant="neutral" size="sm" dot>Archived</x-badge>
                    @endif
                </div>

                <div style="display: flex; gap: var(--space-2); margin: var(--space-4) 0; flex-wrap: wrap;">
                    <x-badge variant="primary" size="sm">{{ ucfirst($property->property_type ?: 'apartment') }}</x-badge>
                    <x-badge variant="secondary" size="sm">{{ number_format((float) $property->nightly_rate_xof, 0, ',', ' ') }} XOF/night</x-badge>
                </div>

                <div style="display: flex; justify-content: space-between; align-items: center; padding-top: var(--space-4); border-top: 1px solid var(--border-default);">
                    <span style="font-size: var(--font-sm); color: var(--text-secondary);">
                        {{ $property->availabilityBlocks()->count() + $property->rateRules()->count() }} rules
                    </span>
                    <x-button tag="a" href="{{ route('admin.properties.edit', $property) }}" variant="ghost" size="sm">Edit</x-button>
                </div>
            </div>
        </x-card>
    @empty
        <div style="grid-column: 1 / -1;">
            <x-card>
                <div class="card-body" style="text-align: center;">
                    <h3>No properties found</h3>
                    <p style="color: var(--text-secondary);">Create a property or adjust your filters.</p>
                    <x-button tag="a" href="{{ route('admin.properties.create') }}" variant="primary">Add Property</x-button>
                </div>
            </x-card>
        </div>
    @endforelse
</div>
@endsection
