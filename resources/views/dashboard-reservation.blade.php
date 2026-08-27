@extends('layouts.app')

@section('title', __('messages.reservation.title'))

@section('content')
    <section class="page-hero compact-hero">
        <div class="container">
            <span class="badge badge-emerald">{{ __('messages.reservation.badge') }}</span>
            <h1>{{ $reservation->reservation_ref }}</h1>
            <p>{{ $reservation->property?->name ?? __('messages.checkout.property') }}</p>
        </div>
    </section>

    <section class="container dashboard-grid">
        <div class="summary-card full-width">
            <h2>{{ __('messages.reservation.details') }}</h2>
            <ul>
                <li><strong>{{ __('messages.reservation.status') }}</strong><span>{{ __('messages.admin.status_' . $reservation->status) }}</span></li>
                <li><strong>{{ __('messages.reservation.dates') }}</strong><span>{{ $reservation->check_in?->format('d/m/Y') }} → {{ $reservation->check_out?->format('d/m/Y') }}</span></li>
                <li><strong>{{ __('messages.reservation.travelers') }}</strong><span>{{ __('messages.reservation.travelers_summary', ['adults' => $reservation->adults, 'children' => $reservation->children, 'infants' => $reservation->infants]) }}</span></li>
                <li><strong>{{ __('messages.reservation.total') }}</strong><span>{{ number_format((float) $reservation->total_amount, 0, ',', ' ') }} {{ $reservation->currency }}</span></li>
            </ul>
        </div>

        <div class="summary-card">
            <h2>{{ __('messages.reservation.contact') }}</h2>
            <ul>
                <li><strong>{{ __('messages.common.email') }}</strong><span>{{ $reservation->email }}</span></li>
                <li><strong>{{ __('messages.reservation.name') }}</strong><span>{{ $reservation->guest?->full_name ?? '—' }}</span></li>
                <li><strong>{{ __('messages.reservation.country') }}</strong><span>{{ $reservation->guest?->country ?? '—' }}</span></li>
            </ul>
        </div>

        <div class="summary-card">
            <h2>{{ __('messages.reservation.amounts') }}</h2>
            <ul>
                @foreach($reservation->priceLines as $line)
                    <li><strong>{{ $line->label }}</strong><span>{{ number_format((float) $line->amount, 0, ',', ' ') }} XOF</span></li>
                @endforeach
            </ul>
        </div>

        @if (auth()->user()?->role === 'customer' && $reservation->property?->establishment)
            <div class="summary-card full-width">
                <h2>{{ __('messages.messages.contact_concierge') }}</h2>
                <form method="POST" action="{{ route('messages.store') }}" class="message-form">
                    @csrf
                    <input type="hidden" name="establishment_id" value="{{ $reservation->property->establishment->id }}">
                    <label for="reservation-message-body">{{ __('messages.messages.message') }}</label>
                    <textarea id="reservation-message-body" name="body" rows="4" maxlength="5000" required></textarea>
                    <button type="submit" class="btn btn-primary">{{ __('messages.messages.send') }}</button>
                </form>
            </div>
        @endif
    </section>
@endsection
