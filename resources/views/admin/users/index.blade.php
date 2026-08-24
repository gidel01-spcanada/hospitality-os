@extends('layouts.app')

@section('title', __('messages.admin.users_title'))

@section('content')
    <section class="page-hero compact-hero">
        <div class="container">
            <span class="badge badge-gold">Administration</span>
            <h1>{{ __('messages.admin.users_title') }}</h1>
            <p>{{ __('messages.admin.users_description') }}</p>
        </div>
    </section>

    <section class="container admin-list-shell">
        @if (session('status'))<div class="reservation-success">{{ session('status') }}</div>@endif
        <div class="form-actions"><a class="btn btn-primary" href="{{ route('admin.users.create') }}">{{ __('messages.admin.add_user') }}</a></div>
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead><tr><th>{{ __('messages.admin.name') }}</th><th>{{ __('messages.common.email') }}</th><th>{{ __('messages.admin.role') }}</th><th>{{ __('messages.admin.language') }}</th><th>{{ __('messages.admin.status') }}</th><th>{{ __('messages.admin.actions') }}</th></tr></thead>
                <tbody>
                    @forelse ($users as $user)
                        <tr>
                            <td><strong>{{ $user->name }}</strong></td>
                            <td>{{ $user->email }}</td>
                            <td>{{ $user->role }}</td>
                            <td>{{ strtoupper($user->locale ?: 'fr') }}</td>
                            <td><span class="badge {{ $user->is_active ? 'badge-emerald' : 'badge-gold' }}">{{ $user->is_active ? __('messages.admin.active') : __('messages.admin.inactive') }}</span></td>
                            <td><a class="btn btn-ghost btn-small" href="{{ route('admin.users.edit', $user) }}">{{ __('messages.admin.edit') }}</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="empty-state-inline">{{ __('messages.admin.no_users') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection