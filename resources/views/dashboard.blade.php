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

    <section class="container">
        <div class="reservation-grid-header">
            <h2>{{ __('messages.dashboard.reservations') }}</h2>
            <form method="GET" action="{{ route('dashboard') }}" class="reservation-filter">
                <label for="reservation-status-filter">{{ __('messages.dashboard.filter_status') }}</label>
                <select id="reservation-status-filter" name="status" onchange="this.form.submit();">
                    <option value="active" @selected($statusFilter === 'active')>{{ __('messages.dashboard.filter_active') }}</option>
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
                    <article class="reservation-card">
                        <header class="reservation-card-header">
                            <div>
                                <strong>{{ $reservation->reservation_ref }}</strong>
                                <span>{{ $reservation->property?->name ?? __('messages.checkout.property') }}</span>
                            </div>
                            <span class="badge badge-emerald">{{ __('messages.admin.status_' . $reservation->status) }}</span>
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
                                <dt>{{ __('messages.reservation.total') }}</dt>
                                <dd>{{ number_format((float) $reservation->total_amount, 0, ',', ' ') }} {{ $reservation->currency }}</dd>
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
@endsection
