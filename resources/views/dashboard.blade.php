@extends('layouts.app')

@section('title', __('messages.dashboard.title'))

@section('content')
    <section class="page-hero compact-hero">
        <div class="container">
            <span class="badge badge-emerald">{{ __('messages.dashboard.account_type') }}</span>
            <h1>{{ __('messages.dashboard.welcome', ['name' => $user->name]) }}</h1>
            <p>{{ __('messages.dashboard.subtitle') }}</p>
        </div>
    </section>

    <section class="container dashboard-grid">
        <div class="summary-card">
            <h2>{{ __('messages.dashboard.summary') }}</h2>
            <ul>
                <li><strong>{{ __('messages.dashboard.email') }}</strong><span>{{ $user->email }}</span></li>
                <li><strong>{{ __('messages.dashboard.role') }}</strong><span>{{ $user->role }}</span></li>
                <li><strong>{{ __('messages.dashboard.status') }}</strong><span>{{ $user->email_verified_at ? __('messages.dashboard.verified') : __('messages.dashboard.pending_verification') }}</span></li>
            </ul>
        </div>

        <div class="summary-card">
            <h2>{{ __('messages.dashboard.reservations') }}</h2>
            @if($reservations->isEmpty())
                <p>{{ __('messages.dashboard.empty_history') }}</p>
                <a class="btn btn-ghost" href="{{ route('properties.index') }}">{{ __('messages.dashboard.explore') }}</a>
            @else
                <ul class="reservation-list">
                    @foreach($reservations as $reservation)
                        <li>
                            <div>
                                <strong>{{ $reservation->reservation_ref }}</strong>
                                <span>{{ $reservation->property?->name ?? __('messages.checkout.property') }}</span>
                            </div>
                            <div class="reservation-meta">
                                <span>{{ $reservation->check_in?->format('d/m/Y') }} → {{ $reservation->check_out?->format('d/m/Y') }}</span>
                                <span>{{ __('messages.admin.status_' . $reservation->status) }}</span>
                            </div>
                            <a href="{{ route('dashboard.reservations.show', $reservation) }}">{{ __('messages.dashboard.view') }}</a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        <div class="summary-card">
            <h2>{{ __('messages.favorites.title') }}</h2>
            @if ($favoriteProperties->isEmpty())
                <p>{{ __('messages.favorites.empty') }}</p>
                <a class="btn btn-ghost" href="{{ route('properties.index') }}">{{ __('messages.dashboard.explore') }}</a>
            @else
                <ul class="reservation-list">
                    @foreach ($favoriteProperties as $favoriteProperty)
                        <li>
                            <div><strong>{{ $favoriteProperty->localized('name') }}</strong><span>{{ $favoriteProperty->city }}</span></div>
                            <a href="{{ route('properties.show', $favoriteProperty) }}">{{ __('messages.dashboard.view') }}</a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        @if ($user->canManageReservations())
            <div class="summary-card full-width">
                <h2>{{ __('messages.dashboard.staff_access') }}</h2>
                <p>{{ __('messages.dashboard.staff_message') }}</p>
                <a class="btn btn-primary" href="{{ route('admin.dashboard') }}">{{ __('messages.dashboard.open_admin') }}</a>
            </div>
        @endif
    </section>
@endsection
