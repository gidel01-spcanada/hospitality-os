@extends('layouts.app')

@section('title', __('messages.dashboard.title'))

@section('content')
    @if ($user->role !== 'customer')
        <section class="page-hero compact-hero portal-page-heading">
            <div class="container">
                <span class="badge badge-emerald">{{ __('messages.dashboard.staff_account_type') }}</span>
                <h1>{{ __('messages.dashboard.welcome', ['name' => $user->name]) }}</h1>
                <p>{{ __('messages.dashboard.subtitle') }}</p>
            </div>
        </section>

        <section class="container portal-staff-landing" aria-labelledby="portal-staff-title">
            <div>
                <span class="portal-section-kicker">{{ __('messages.dashboard.account_type') }}</span>
                <h2 id="portal-staff-title">{{ __('messages.dashboard.staff_access') }}</h2>
                <p>{{ __('messages.dashboard.staff_message') }}</p>
            </div>
            @if ($user->canManageReservations())
                <nav aria-label="{{ __('messages.admin.operations') }}">
                    <a class="portal-quick-link" href="{{ route('admin.dashboard') }}">{{ __('messages.admin.dashboard') }}</a>
                    <a class="portal-quick-link" href="{{ route('admin.reservations.index') }}">{{ __('messages.admin.reservations') }}</a>
                    <a class="portal-quick-link" href="{{ route('admin.messages.index') }}">{{ __('messages.messages.title') }}</a>
                    <a class="portal-quick-link" href="{{ route('admin.cleaning.index') }}">{{ __('messages.cleaning.title') }}</a>
                    @if ($user->isEstablishmentManager())
                        <a class="portal-quick-link" href="{{ route('admin.reports.index') }}">{{ __('messages.reports.title') }}</a>
                        <a class="portal-quick-link" href="{{ route('admin.properties.index') }}">{{ __('messages.admin.properties') }}</a>
                        <a class="portal-quick-link" href="{{ route('admin.establishments.index') }}">{{ __('messages.admin.establishments') }}</a>
                        <a class="portal-quick-link" href="{{ route('admin.preferences') }}">{{ __('messages.profile.tabs.preferences') }}</a>
                    @endif
                </nav>
            @else
                <p>{{ __('messages.dashboard.account_landing_message') }}</p>
            @endif
        </section>
    @else
    <section class="page-hero compact-hero">
        <div class="container">
            <span class="badge badge-emerald">{{ __('messages.dashboard.account_type') }}</span>
            <h1>{{ __('messages.dashboard.welcome', ['name' => $user->name]) }}</h1>
            <p>{{ __('messages.dashboard.subtitle') }}</p>
        </div>
    </section>

    <section class="container">
        <div class="reservation-grid-header">
            <h2>{{ __('messages.dashboard.reservations') }}</h2>
            <nav class="portal-reservation-periods" aria-label="{{ __('messages.dashboard.reservations') }}">
                @foreach ($periods as $period)
                    <a href="{{ route('dashboard', ['period' => $period]) }}" @if ($periodFilter === $period) aria-current="page" @endif>{{ __('messages.dashboard.period_' . $period) }}</a>
                @endforeach
            </nav>
            <form method="GET" action="{{ route('dashboard') }}" class="reservation-filter">
                <input type="hidden" name="period" value="{{ $periodFilter }}">
                <label for="reservation-status-filter">{{ __('messages.dashboard.filter_status') }}</label>
                <select id="reservation-status-filter" name="status" onchange="this.form.submit();">
                    <option value="all" @selected($statusFilter === 'all')>{{ __('messages.dashboard.filter_all') }}</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status }}" @selected($statusFilter === $status)>{{ __('messages.admin.status_' . $status) }}</option>
                    @endforeach
                </select>
                <noscript><button type="submit" class="btn btn-ghost">{{ __('messages.dashboard.filter_apply') }}</button></noscript>
            </form>
            <a class="btn btn-ghost" href="{{ route('properties.index') }}">{{ __('messages.dashboard.explore') }}</a>
        </div>

        @if($reservations->isEmpty())
            <div class="summary-card full-width">
                <p>{{ __('messages.dashboard.empty_history') }}</p>
            </div>
        @else
            <div class="reservation-grid">
                @foreach($reservations as $reservation)
                    @php($reservationSummary = $reservationSummaries[$reservation->id])
                    <article class="reservation-card">
                        @if ($reservationSummary['property']['image'])
                            <img class="portal-reservation-image" src="{{ $reservationSummary['property']['image'] }}" alt="{{ $reservationSummary['property']['name'] }}" loading="lazy">
                        @endif
                        <header class="reservation-card-header">
                            <div>
                                <strong>{{ $reservation->reservation_ref }}</strong>
                                <span>{{ $reservationSummary['property']['name'] }}</span>
                                @if ($reservationSummary['property']['location'])<span>{{ $reservationSummary['property']['location'] }}</span>@endif
                            </div>
                            <span class="portal-status-badge status-{{ $reservation->status }}">{{ __('messages.admin.status_' . $reservation->status) }}</span>
                        </header>

                        <dl class="reservation-card-facts">
                            <div>
                                <dt>{{ __('messages.reservation.dates') }}</dt>
                                <dd>{{ $reservation->check_in?->format('d/m/Y') }} → {{ $reservation->check_out?->format('d/m/Y') }}</dd>
                            </div>
                            <div>
                                <dt>{{ __('messages.dashboard.guests') }}</dt>
                                <dd>{{ __('messages.reservation.travelers_summary', ['adults' => $reservation->adults, 'children' => $reservation->children, 'infants' => $reservation->infants]) }}</dd>
                            </div>
                            <div>
                                <dt>{{ __('messages.dashboard.nights') }}</dt>
                                <dd>{{ $reservationSummary['nights'] }}</dd>
                            </div>
                            <div>
                                <dt>{{ __('messages.reservation.total') }}</dt>
                                <dd>{{ \App\Support\ReservationSummary::money($reservation->total_amount, $reservation->currency) }}</dd>
                            </div>
                            <div>
                                <dt>{{ __('messages.dashboard.payment_status') }}</dt>
                                <dd>{{ __('messages.dashboard.payment_' . $reservationSummary['payment_status']) }}</dd>
                            </div>
                        </dl>

                        <footer class="reservation-card-actions">
                            <a class="btn btn-ghost" href="{{ route('dashboard.reservations.show', $reservation) }}">{{ __('messages.reservation.details') }}</a>

                            @if ($reservation->receipts->isNotEmpty())
                                <a class="btn btn-ghost" href="{{ route('reservations.receipt', $reservation) }}">{{ __('messages.dashboard.receipt') }}</a>
                            @endif

                            @if (in_array($reservation->status, ['pending', 'pending_payment', 'payment_failed'], true))
                                <a class="btn btn-primary" href="{{ route('checkout.show', ['reservation' => $reservation, 'token' => $reservation->checkout_token]) }}">{{ __('messages.checkout.pay_now') }}</a>

                                <form method="POST" action="{{ route('checkout.cancel', ['reservation' => $reservation, 'token' => $reservation->checkout_token]) }}" data-confirm-message="{{ __('messages.checkout.cancel_confirmation') }}">
                                    @csrf
                                    <button type="submit" class="btn btn-ghost">{{ __('messages.checkout.cancel_reservation') }}</button>
                                </form>
                            @endif
                        </footer>
                    </article>
                @endforeach
            </div>
        @endif
    </section>

    <section class="container dashboard-grid">
        <div class="summary-card full-width">
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
    @endif
@endsection
