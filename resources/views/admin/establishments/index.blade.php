@extends('layouts.admin')

@section('title', __('messages.admin.establishment_management'))

@section('content')
    <div class="admin-page-header">
        <div class="admin-page-actions" style="justify-content: space-between;">
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
                                <div class="admin-row-actions">
                                    <x-button tag="a" href="{{ route('admin.properties.index', ['establishment' => $establishment->id]) }}" variant="ghost" size="sm" class="btn-icon" title="{{ __('messages.admin.view_properties') }}" aria-label="{{ __('messages.admin.view_properties') }}">
                                        <svg class="icon" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path d="M4.5 5.25A2.25 2.25 0 016.75 3h10.5a2.25 2.25 0 012.25 2.25v13.5A2.25 2.25 0 0117.25 21H6.75a2.25 2.25 0 01-2.25-2.25V5.25zm3 3.75h9m-9 3h9m-9 3h5.25" />
                                        </svg>
                                    </x-button>
                                    <x-button tag="a" href="{{ route('admin.establishments.edit', $establishment) }}" variant="ghost" size="sm" class="btn-icon" title="{{ __('messages.admin.edit') }}" aria-label="{{ __('messages.admin.edit') }}">
                                        <svg class="icon" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 9l-6.455 6.456M9 9l6 6" />
                                        </svg>
                                    </x-button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="empty-state-inline">{{ __('messages.admin.no_establishments') }}</td></tr>
                    @endforelse
                </tbody>
        </table>
    </x-card>
@endsection