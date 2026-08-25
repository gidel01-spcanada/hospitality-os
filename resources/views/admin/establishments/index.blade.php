@extends('layouts.admin')

@section('title', __('messages.admin.establishment_management'))

@section('content')
    <div class="admin-page-header">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <div>
                <h1 class="admin-page-title">{{ __('messages.admin.establishments') }}</h1>
                <p class="admin-page-description">{{ __('messages.admin.establishments_description') }}</p>
            </div>
            <x-button tag="a" href="{{ route('admin.establishments.create') }}" variant="primary">{{ __('messages.admin.add_establishment') }}</x-button>
        </div>
    </div>

    <x-card>
        @if (session('success'))
            <div class="reservation-success">{{ session('success') }}</div>
        @endif
        <table class="admin-table">
                <thead><tr><th>{{ __('messages.admin.name') }}</th><th>{{ __('messages.admin.city') }}</th><th>{{ __('messages.admin.properties') }}</th><th>{{ __('messages.admin.actions') }}</th></tr></thead>
                <tbody>
                    @forelse ($establishments as $establishment)
                        <tr>
                            <td><strong>{{ $establishment->name }}</strong><br><small>{{ $establishment->slug }}</small></td>
                            <td>{{ $establishment->city ?: '—' }}</td>
                            <td>{{ $establishment->properties_count }}</td>
                            <td>
                                <x-button tag="a" href="{{ route('admin.properties.index', ['establishment' => $establishment->id]) }}" variant="ghost" size="sm">{{ __('messages.admin.view_properties') }}</x-button>
                                <x-button tag="a" href="{{ route('admin.establishments.edit', $establishment) }}" variant="ghost" size="sm">{{ __('messages.admin.edit') }}</x-button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="empty-state-inline">{{ __('messages.admin.no_establishments') }}</td></tr>
                    @endforelse
                </tbody>
        </table>
    </x-card>
@endsection