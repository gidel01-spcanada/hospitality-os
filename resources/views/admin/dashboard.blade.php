@extends('layouts.app')

@section('title', __('messages.admin.title'))

@section('content')
    <section class="page-hero compact-hero">
        <div class="container">
            <span class="badge badge-gold">{{ __('messages.admin.title') }}</span>
            <h1>{{ __('messages.admin.dashboard') }}</h1>
            <p>{{ __('messages.admin.overview') }}</p>
        </div>
    </section>

    <section class="container stats-grid">
        <div class="stat-card">
            <span>{{ __('messages.admin.establishments') }}</span>
            <strong>{{ $stats['establishments'] }}</strong>
        </div>
        <div class="stat-card">
            <span>{{ __('messages.nav.properties') }}</span>
            <strong>{{ $stats['properties'] }}</strong>
        </div>
        <div class="stat-card">
            <span>{{ __('messages.dashboard.reservations') }}</span>
            <strong>{{ $stats['bookings'] }}</strong>
        </div>
        <div class="stat-card">
            <span>{{ __('messages.admin.users') }}</span>
            <strong>{{ $stats['active_users'] }}</strong>
        </div>
        <div class="stat-card">
            <span>{{ __('messages.admin.pending') }}</span>
            <strong>{{ $stats['pending'] }}</strong>
        </div>
    </section>

    <section class="container dashboard-grid">
        <div class="summary-card full-width">
            @if (! $user->isAdmin())
                <h2>{{ __('messages.admin.concierge_title') }}</h2>
                <p>{{ __('messages.admin.concierge_message') }}</p>
                <a class="btn btn-primary" href="{{ route('admin.reservations.index') }}">{{ __('messages.admin.open_reservations') }}</a>
            @else
            <h2>{{ __('messages.admin.key_activities') }}</h2>
            <ul>
                <li>{{ __('messages.admin.check_properties') }}</li>
                <li>{{ __('messages.admin.validate_bookings') }}</li>
                <li>{{ __('messages.admin.monitor_content') }}</li>
            </ul>
            @endif
        </div>
    </section>
@endsection
