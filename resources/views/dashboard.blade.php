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

        <div class="summary-card full-width">
            <h2>{{ __('messages.dashboard.preferences') }}</h2>
            <form method="POST" action="{{ route('dashboard.preferences.update') }}">
                @csrf
                <div>
                    <label for="locale">{{ __('messages.profile.language') }}</label>
                    <select id="locale" name="locale">
                        <option value="fr" @selected(old('locale', $user->locale ?? 'fr') === 'fr')>Français</option>
                        <option value="en" @selected(old('locale', $user->locale ?? 'fr') === 'en')>English</option>
                    </select>
                </div>
                <div>
                    <label><input type="checkbox" name="email_booking_updates" value="1" @checked(old('email_booking_updates', (bool) ($user->email_booking_updates ?? true)))> {{ __('messages.profile.booking_updates') }}</label>
                    <label><input type="checkbox" name="email_marketing" value="1" @checked(old('email_marketing', (bool) ($user->email_marketing ?? false)))> {{ __('messages.profile.marketing') }}</label>
                    <label><input type="checkbox" name="email_newsletter" value="1" @checked(old('email_newsletter', (bool) ($user->email_newsletter ?? false)))> {{ __('messages.profile.newsletter') }}</label>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">{{ __('messages.profile.save') }}</button>
                </div>
            </form>
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
