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
        </div>

        @if (in_array($reservation->status, ['pending', 'pending_payment', 'payment_failed'], true))
            <div class="summary-card full-width">
                <h2>{{ __('messages.reservation.actions') }}</h2>
                @php $cancellationFee = $reservation->property?->establishment?->cancellationFeeFor($reservation) ?? 0.0; @endphp
                @if ($cancellationFee > 0)
                    <p class="cancellation-policy-note">{{ __('messages.checkout.cancellation_fee_warning', ['amount' => number_format($cancellationFee, 0, ',', ' '), 'currency' => $reservation->currency]) }}</p>
                @endif
                <div class="form-actions reservation-detail-actions">
                    <a class="btn btn-primary" href="{{ route('checkout.show', ['reservation' => $reservation, 'token' => $reservation->checkout_token]) }}">{{ __('messages.checkout.pay_now') }}</a>

                    <form action="{{ route('checkout.cancel', ['reservation' => $reservation, 'token' => $reservation->checkout_token]) }}" method="POST" onsubmit="return confirm('{{ __('messages.checkout.cancel_confirmation') }}');">
                        @csrf
                        <button type="submit" class="btn btn-ghost">{{ __('messages.checkout.cancel_reservation') }}</button>
                    </form>
                </div>
            </div>
        @endif

        @if (! in_array($reservation->status, ['cancelled', 'completed'], true))
            <div class="summary-card full-width">
                <h2>{{ __('messages.reservation.modify') }}</h2>
                <form method="POST" action="{{ route('dashboard.reservations.update', $reservation) }}" class="reservation-modification-form">
                    @csrf
                    @method('PUT')
                    <div class="form-grid">
                        <div>
                            <label for="modification_check_in">{{ __('messages.properties.check_in') }}</label>
                            <input id="modification_check_in" name="check_in" type="date" value="{{ old('check_in', $reservation->check_in?->toDateString()) }}" min="{{ now()->toDateString() }}" required>
                        </div>
                        <div>
                            <label for="modification_check_out">{{ __('messages.properties.check_out') }}</label>
                            <input id="modification_check_out" name="check_out" type="date" value="{{ old('check_out', $reservation->check_out?->toDateString()) }}" min="{{ now()->addDay()->toDateString() }}" required>
                        </div>
                        <div>
                            <label for="modification_adults">{{ __('messages.properties.adults') }}</label>
                            <select id="modification_adults" name="adults" required>
                                @for ($count = 1; $count <= min(8, (int) $reservation->property->max_guests); $count++)
                                    <option value="{{ $count }}" @selected(old('adults', $reservation->adults) == $count)>{{ $count }}</option>
                                @endfor
                            </select>
                        </div>
                        <div>
                            <label for="modification_children">{{ __('messages.properties.children') }}</label>
                            <input id="modification_children" name="children" type="number" min="0" max="8" value="{{ old('children', $reservation->children) }}">
                        </div>
                        <div>
                            <label for="modification_infants">{{ __('messages.properties.infants') }}</label>
                            <input id="modification_infants" name="infants" type="number" min="0" max="4" value="{{ old('infants', $reservation->infants) }}">
                        </div>
                    </div>

                    @if ($reservation->property->features->isNotEmpty())
                        <fieldset class="feature-choice-box">
                            <legend>{{ __('messages.properties.extra_options') }}</legend>
                            @foreach ($reservation->property->features->where('is_active', true) as $feature)
                                <label class="feature-choice-item">
                                    <input type="checkbox" name="selected_features[]" value="{{ $feature->id }}" @checked(in_array($feature->id, (array) old('selected_features', $selectedFeatureIds), true))>
                                    <div class="feature-choice-copy">
                                        <strong>{{ $feature->name }}</strong>
                                        @if ($feature->description)<span>{{ $feature->description }}</span>@endif
                                    </div>
                                    <em>{{ number_format((float) $feature->cost_xof, 0, ',', ' ') }} XOF</em>
                                </label>
                            @endforeach
                        </fieldset>
                    @endif

                    <p class="form-help">{{ __('messages.reservation.modification_help') }}</p>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">{{ __('messages.reservation.save_modification') }}</button>
                    </div>
                </form>
            </div>
        @endif


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
