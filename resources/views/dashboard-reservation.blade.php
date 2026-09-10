@extends('layouts.app')

@section('title', __('messages.reservation.title'))

@section('content')
    <section class="page-hero compact-hero">
        <div class="container">
            <span class="badge badge-emerald">{{ __('messages.reservation.badge') }}</span>
            <h1>{{ $reservation->reservation_ref }}</h1>
            <p>{{ $reservation->property?->name ?? __('messages.checkout.property') }}</p>
            <a href="{{ route('dashboard') }}" class="inline-link">← {{ __('messages.dashboard.reservations') }}</a>
        </div>
    </section>

    <section class="container dashboard-grid">
        <div class="summary-card full-width">
            <h2>{{ __('messages.reservation.details') }}</h2>
            <ul>
                <li><strong>{{ __('messages.reservation.status') }}</strong><span>{{ __('messages.admin.status_' . $reservation->status) }}</span></li>
                <li><strong>{{ __('messages.reservation.dates') }}</strong><span>{{ $reservation->check_in?->format('d/m/Y') }} → {{ $reservation->check_out?->format('d/m/Y') }}</span></li>
                <li><strong>{{ __('messages.reservation.travelers') }}</strong><span>{{ __('messages.reservation.travelers_summary', ['adults' => $reservation->adults, 'children' => $reservation->children, 'infants' => $reservation->infants]) }}</span></li>
                <li><strong>{{ __('messages.reservation.name') }}</strong><span>{{ $reservation->guest?->full_name ?? '—' }}</span></li>
                <li><strong>{{ __('messages.reservation.total') }}</strong><span>{{ number_format((float) $reservation->total_amount, 0, ',', ' ') }} {{ $reservation->currency }}</span></li>
            </ul>

            @if (in_array($reservation->status, ['pending', 'pending_payment', 'payment_failed'], true))
                <div class="form-actions">
                    <a class="btn btn-primary" href="{{ route('checkout.show', ['reservation' => $reservation, 'token' => $reservation->checkout_token]) }}">{{ __('messages.checkout.pay_now') }}</a>
                </div>

                <form action="{{ route('checkout.cancel', ['reservation' => $reservation, 'token' => $reservation->checkout_token]) }}" method="POST" onsubmit="return confirm('{{ __('messages.checkout.cancel_confirmation') }}');">
                    @csrf
                    <div class="form-actions">
                        <button type="submit" class="btn btn-ghost">{{ __('messages.checkout.cancel_reservation') }}</button>
                    </div>
                </form>
            @endif
        </div>


        @if ($reservation->receipts->isNotEmpty())
            <div class="summary-card full-width">
                <h2>{{ __('messages.receipts.title') }}</h2>
                @foreach ($reservation->receipts as $receipt)
                    <a class="btn btn-primary" href="{{ route('reservations.receipt', $reservation) }}">{{ __('messages.receipts.download', ['number' => $receipt->receipt_number]) }}</a>
                @endforeach
            </div>
        @endif

        <div class="summary-card full-width">
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
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">{{ __('messages.messages.send') }}</button>
                    </div>
                </form>
            </div>
        @endif
    </section>
@endsection
