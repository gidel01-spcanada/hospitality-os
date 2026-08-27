@extends('layouts.admin')

@section('title', __('messages.admin.dashboard'))

@section('content')
<!-- Admin Page Header -->
<div class="admin-page-header">
    <h1 class="admin-page-title">{{ __('messages.admin.dashboard') }}</h1>
    <p class="admin-page-description">{{ __('messages.admin.dashboard_overview') }}</p>
</div>

<!-- Quick Stats Grid -->
<div class="admin-stats-grid">
    <x-card variant="elevated">
        <div class="stat-card">
            <span>{{ __('messages.admin.establishments') }}</span>
            <strong>{{ $stats['establishments'] ?? 0 }}</strong>
        </div>
    </x-card>

    <x-card variant="elevated">
        <div class="stat-card">
            <span>{{ __('messages.admin.total_bookings') }}</span>
            <strong>{{ $stats['bookings'] ?? 0 }}</strong>
        </div>
    </x-card>

    <x-card variant="elevated">
        <div class="stat-card">
            <span>{{ __('messages.admin.pending_approvals') }}</span>
            <strong>{{ $stats['pending'] ?? 0 }}</strong>
        </div>
    </x-card>

    <x-card variant="elevated">
        <div class="stat-card">
            <span>{{ __('messages.admin.active_users') }}</span>
            <strong>{{ $stats['active_users'] ?? 0 }}</strong>
        </div>
    </x-card>

    <x-card variant="elevated">
        <div class="stat-card">
            <span>{{ __('messages.admin.active_properties') }}</span>
            <strong>{{ $stats['properties'] ?? 0 }}</strong>
        </div>
    </x-card>
</div>

<!-- Key Activities -->
@if ($user->isAdmin())
<div style="margin-top: var(--space-8);">
    <x-card>
        <div class="card-header">
            <h3>{{ __('messages.admin.key_activities') }}</h3>
            <p style="margin: var(--space-2) 0 0 0; color: var(--text-secondary); font-size: var(--font-sm);">{{ __('messages.admin.admin_responsibilities') }}</p>
        </div>

        <div class="card-body">
            <ul style="margin: 0; padding: 0; list-style: none;">
                <li style="padding: var(--space-4); border-bottom: 1px solid var(--border-default); display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <strong>{{ __('messages.admin.validate_property_listings') }}</strong>
                        <p style="margin: var(--space-1) 0 0 0; font-size: var(--font-sm); color: var(--text-secondary);">{{ __('messages.admin.validate_property_listings_help') }}</p>
                    </div>
                    <x-button tag="a" href="/admin/properties" variant="secondary" size="sm">{{ __('messages.admin.manage') }}</x-button>
                </li>
                <li style="padding: var(--space-4); border-bottom: 1px solid var(--border-default); display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <strong>{{ __('messages.admin.monitor_bookings') }}</strong>
                        <p style="margin: var(--space-1) 0 0 0; font-size: var(--font-sm); color: var(--text-secondary);">{{ __('messages.admin.monitor_bookings_help') }}</p>
                    </div>
                    <x-button tag="a" href="/admin/bookings" variant="secondary" size="sm">{{ __('messages.admin.view') }}</x-button>
                </li>
                <li style="padding: var(--space-4); display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <strong>{{ __('messages.admin.user_management') }}</strong>
                        <p style="margin: var(--space-1) 0 0 0; font-size: var(--font-sm); color: var(--text-secondary);">{{ __('messages.admin.user_management_help') }}</p>
                    </div>
                    <x-button tag="a" href="/admin/users" variant="secondary" size="sm">{{ __('messages.admin.manage') }}</x-button>
                </li>
            </ul>
        </div>
    </x-card>
</div>
@else
<div style="margin-top: var(--space-8);">
    <x-card variant="outlined">
        <div class="card-body" style="text-align: center;">
            <svg class="icon icon-xl" style="margin-bottom: var(--space-4); color: var(--theme-primary);" fill="currentColor" viewBox="0 0 24 24">
                <path d="M5.85 3.5a.75.75 0 00-1.117-1.007A19.5 19.5 0 005.503 19.5h12.994a19.5 19.5 0 00.617-16.007.75.75 0 00-1.117 1.007A18 18 0 0018.503 19.5H5.503a18 18 0 00.347-15.993z" />
            </svg>
            <h3 style="margin-bottom: var(--space-2);">{{ __('messages.admin.concierge_title') }}</h3>
            <p style="color: var(--text-secondary); margin-bottom: var(--space-4);">{{ __('messages.admin.concierge_message') }}</p>
            <x-button tag="a" href="/admin/reservations" variant="primary">{{ __('messages.admin.open_reservations') }}</x-button>
        </div>
    </x-card>
</div>
@endif

<!-- Quick Actions -->
<div style="margin-top: var(--space-8);">
    <h3 style="margin-bottom: var(--space-4);">{{ __('messages.admin.quick_actions') }}</h3>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: var(--space-4);">
        <x-card clickable href="/admin/bookings/create">
            <div style="padding: var(--space-4); text-align: center;">
                <svg class="icon icon-lg" style="margin-bottom: var(--space-3); color: var(--theme-primary);" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm5 11h-4v4h-2v-4H7v-2h4V7h2v4h4v2z" />
                </svg>
                <h4 style="margin: 0 0 var(--space-2) 0;">{{ __('messages.admin.new_booking') }}</h4>
                <p style="margin: 0; color: var(--text-secondary); font-size: var(--font-sm);">{{ __('messages.admin.new_booking_help') }}</p>
            </div>
        </x-card>

        @if ($user->isAdmin())
        <x-card clickable href="/admin/users/create">
            <div style="padding: var(--space-4); text-align: center;">
                <svg class="icon icon-lg" style="margin-bottom: var(--space-3); color: var(--theme-primary);" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 3c1.66 0 3 1.34 3 3s-1.34 3-3 3-3-1.34-3-3 1.34-3 3-3zm0 14.2c-2.5 0-4.71-1.28-6-3.22.03-1.99 4-3.08 6-3.08 1.99 0 5.97 1.09 6 3.08-1.29 1.94-3.5 3.22-6 3.22z" />
                </svg>
                <h4 style="margin: 0 0 var(--space-2) 0;">{{ __('messages.admin.add_team_member') }}</h4>
                <p style="margin: 0; color: var(--text-secondary); font-size: var(--font-sm);">{{ __('messages.admin.add_team_member_help') }}</p>
            </div>
        </x-card>
        @endif

        @if ($user->isAdmin())
        <x-card clickable href="{{ route('admin.properties.index') }}">
            <div style="padding: var(--space-4); text-align: center;">
                <svg class="icon icon-lg" style="margin-bottom: var(--space-3); color: var(--theme-primary);" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z" />
                </svg>
                <h4 style="margin: 0 0 var(--space-2) 0;">{{ __('messages.admin.properties') }}</h4>
                <p style="margin: 0; color: var(--text-secondary); font-size: var(--font-sm);">{{ __('messages.admin.manage_properties_help') }}</p>
            </div>
        </x-card>
        @endif
    </div>
</div>
@endsection
