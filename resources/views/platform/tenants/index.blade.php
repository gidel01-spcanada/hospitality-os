@extends('layouts.admin')

@section('title', __('messages.platform.tenants_title'))

@section('content')
    <div class="admin-page-header">
        <h1 class="admin-page-title">{{ __('messages.platform.tenants_title') }}</h1>
        <p class="admin-page-description">{{ __('messages.platform.tenants_description') }}</p>
    </div>

    <x-card>
        @if ($tenants->isEmpty())
            <p>{{ __('messages.platform.no_tenants') }}</p>
        @else
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>{{ __('messages.platform.tenant_name') }}</th>
                        <th>{{ __('messages.platform.status') }}</th>
                        <th>{{ __('messages.platform.establishments') }}</th>
                        <th>{{ __('messages.platform.properties') }}</th>
                        <th>{{ __('messages.platform.staff') }}</th>
                        <th>{{ __('messages.admin.action') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($tenants as $tenant)
                        <tr>
                            <td>
                                <strong>{{ $tenant->name }}</strong>
                                <div class="form-help">{{ $tenant->slug }} &middot; {{ $tenant->contact_email }}</div>
                            </td>
                            <td>
                                <span class="badge {{ $tenant->status === 'active' ? 'badge-success' : 'badge-danger' }}">
                                    {{ $tenant->status === 'active' ? __('messages.platform.active') : __('messages.platform.suspended') }}
                                </span>
                            </td>
                            <td>{{ $tenant->establishments_count }}</td>
                            <td>{{ $tenant->properties_count }}</td>
                            <td>{{ $tenant->users_count }}</td>
                            <td>
                                @if ($tenant->status === 'active')
                                    <form method="POST" action="{{ route('platform.tenants.suspend', $tenant) }}">
                                        @csrf
                                        <button type="submit" class="btn btn-danger btn-small">{{ __('messages.platform.suspend') }}</button>
                                    </form>
                                @else
                                    <form method="POST" action="{{ route('platform.tenants.activate', $tenant) }}">
                                        @csrf
                                        <button type="submit" class="btn btn-ghost btn-small">{{ __('messages.platform.activate') }}</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </x-card>
@endsection
