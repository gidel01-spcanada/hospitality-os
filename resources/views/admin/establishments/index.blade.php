@extends('layouts.app')

@section('title', __('messages.admin.establishment_management'))

@section('content')
    <section class="page-hero compact-hero">
        <div class="container">
            <span class="badge badge-gold">{{ __('messages.admin.title') }}</span>
            <h1>{{ __('messages.admin.establishments') }}</h1>
            <p>{{ __('messages.admin.establishments_description') }}</p>
        </div>
    </section>

    <section class="container admin-list-shell">
        @if (session('success'))
            <div class="reservation-success">{{ session('success') }}</div>
        @endif
        <div class="form-actions"><a class="btn btn-primary" href="{{ route('admin.establishments.create') }}">{{ __('messages.admin.add_establishment') }}</a></div>
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead><tr><th>{{ __('messages.admin.name') }}</th><th>{{ __('messages.admin.city') }}</th><th>{{ __('messages.admin.properties') }}</th><th>{{ __('messages.admin.actions') }}</th></tr></thead>
                <tbody>
                    @forelse ($establishments as $establishment)
                        <tr>
                            <td><strong>{{ $establishment->name }}</strong><br><small>{{ $establishment->slug }}</small></td>
                            <td>{{ $establishment->city ?: '—' }}</td>
                            <td>{{ $establishment->properties_count }}</td>
                            <td>
                                <a class="btn btn-ghost btn-small" href="{{ route('admin.properties.index', ['establishment' => $establishment->id]) }}">{{ __('messages.admin.view_properties') }}</a>
                                <a class="btn btn-ghost btn-small" href="{{ route('admin.establishments.edit', $establishment) }}">{{ __('messages.admin.edit') }}</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="empty-state-inline">{{ __('messages.admin.no_establishments') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection