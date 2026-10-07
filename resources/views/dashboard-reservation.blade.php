@extends('layouts.app')

@section('title', __('messages.reservation.title'))

@section('content')
    <section class="container portal-reservation-detail-head">
        <a href="{{ route('dashboard') }}" class="inline-link">← {{ __('messages.dashboard.reservations') }}</a>
        <div class="portal-reservation-property">
            @if ($reservationSummary['property']['image'])
                <img src="{{ $reservationSummary['property']['image'] }}" alt="{{ $reservationSummary['property']['name'] }}">
            @endif
            <div>
                <span class="portal-status-badge status-{{ $reservation->status }}">{{ __('messages.admin.status_' . $reservation->status) }}</span>
                <h1>{{ $reservationSummary['property']['name'] }}</h1>
                <p>{{ $reservation->reservation_ref }}@if ($reservationSummary['property']['location']) · {{ $reservationSummary['property']['location'] }}@endif</p>
            </div>
        </div>
    </section>

    <section class="container dashboard-grid portal-reservation-detail">
        @php
            $canModifyReservation = ! in_array($reservation->status, ['cancelled', 'completed'], true);
            $activeReservationView = $canModifyReservation && request()->query('view') === 'edit' ? 'edit' : 'details';
        @endphp
        <div class="section-tabs-shell full-width">
            <nav class="section-tabs" aria-label="{{ __('messages.reservation.view_navigation') }}">
                <a href="{{ route('dashboard.reservations.show', ['reservation' => $reservation, 'view' => 'details']) }}" class="{{ $activeReservationView === 'details' ? 'is-active' : '' }}" @if ($activeReservationView === 'details') aria-current="page" @endif>{{ __('messages.reservation.details') }}</a>
                @if ($canModifyReservation)
                    <a href="{{ route('dashboard.reservations.show', ['reservation' => $reservation, 'view' => 'edit']) }}" class="{{ $activeReservationView === 'edit' ? 'is-active' : '' }}" @if ($activeReservationView === 'edit') aria-current="page" @endif>{{ __('messages.reservation.modify') }}</a>
                @endif
            </nav>
        </div>

        @if ($activeReservationView === 'details')
        <div class="summary-card full-width">
            <div class="reservation-card-heading">
                <h2>{{ __('messages.reservation.details') }}</h2>
                @if ($canModifyReservation)
                    <a class="btn btn-ghost btn-small" href="{{ route('dashboard.reservations.show', ['reservation' => $reservation, 'view' => 'edit']) }}">{{ __('messages.reservation.modify') }}</a>
                @endif
            </div>
            <ul>
                <li><strong>{{ __('messages.reservation.dates') }}</strong><span>{{ $reservation->check_in?->format('d/m/Y') }} → {{ $reservation->check_out?->format('d/m/Y') }}</span></li>
                <li><strong>{{ __('messages.dashboard.nights') }}</strong><span>{{ $reservationSummary['nights'] }}</span></li>
                <li><strong>{{ __('messages.reservation.travelers') }}</strong><span>{{ __('messages.reservation.travelers_summary', ['adults' => $reservation->adults, 'children' => $reservation->children, 'infants' => $reservation->infants]) }}</span></li>
                <li><strong>{{ __('messages.reservation.name') }}</strong><span>{{ $reservation->guest?->full_name ?? '—' }}</span></li>
                <li><strong>{{ __('messages.reservation.total') }}</strong><span>{{ \App\Support\ReservationSummary::money($reservation->total_amount, $reservation->currency) }}</span></li>
                <li><strong>{{ __('messages.dashboard.payment_status') }}</strong><span>{{ __('messages.dashboard.payment_' . $reservationSummary['payment_status']) }}</span></li>
                <li><strong>{{ __('messages.reservation.paid') }}</strong><span>{{ \App\Support\ReservationSummary::money($reservationSummary['paid'], $reservation->currency) }}</span></li>
                <li><strong>{{ __('messages.reservation.due') }}</strong><span>{{ $reservationSummary['due'] === null ? __('messages.reservation_summary.balance_unknown') : \App\Support\ReservationSummary::money($reservationSummary['due'], $reservation->currency) }}</span></li>
            </ul>
        </div>

        @php
            $stayNotes = \App\Support\ReservationSummary::stayNotes($reservation->property?->establishment, app()->getLocale());
        @endphp
        @if ($stayNotes)
            <div class="summary-card full-width" data-stay-notes>
                <h2>{{ __('messages.transactional.important_notes') }}</h2>
                @foreach ($stayNotes as $note)
                    <p class="cancellation-policy-note {{ $note['type'] === 'electricity' ? 'electricity-policy-note' : '' }}">{{ $note['type'] === 'electricity' ? '⚡ ' : '' }}{{ $note['text'] }}</p>
                @endforeach
            </div>
        @endif

        <div class="summary-card full-width">
            <h2>{{ __('messages.reservation.amounts') }}</h2>
            <ul>
                @foreach($reservation->priceLines as $line)
                    <li><strong>{{ $line->label }}</strong><span>{{ number_format((float) $line->amount, 0, ',', ' ') }} {{ $line->currency ?: $reservation->currency }}</span></li>
                @endforeach
                @if ((float) $reservation->taxes > 0 && ! $reservation->priceLines->contains(fn ($line) =>
                    in_array($line->label, [__('messages.pricing.city_tax', [], 'fr'), __('messages.pricing.city_tax', [], 'en')], true)
                    || str_starts_with((string) $line->label, 'TVA (')
                    || str_starts_with((string) $line->label, 'VAT (')
                ))
                    <li><strong>{{ __('messages.reservation.taxes') }}</strong><span>{{ number_format((float) $reservation->taxes, 0, ',', ' ') }} {{ $reservation->currency }}</span></li>
                @endif
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

                    <form action="{{ route('checkout.cancel', ['reservation' => $reservation, 'token' => $reservation->checkout_token]) }}" method="POST" data-confirm-message="{{ __('messages.checkout.cancel_confirmation') }}">
                        @csrf
                        <button type="submit" class="btn btn-ghost">{{ __('messages.checkout.cancel_reservation') }}</button>
                    </form>
                </div>
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

        @php($paymentProofAttempts = $reservation->paymentAttempts->filter(fn ($attempt) => data_get($attempt->payload, 'payment_proof.path')))
        @if ($paymentProofAttempts->isNotEmpty())
            <div class="summary-card full-width">
                <h2>{{ __('messages.receipts.payment_proof') }}</h2>
                @foreach ($paymentProofAttempts as $attempt)
                    <a class="btn btn-ghost" href="{{ route('reservations.payment-proof.download', ['reservation' => $reservation, 'attempt' => $attempt]) }}">{{ data_get($attempt->payload, 'payment_proof.original_name') ?: __('messages.receipts.download_proof') }}</a>
                @endforeach
            </div>
        @endif

        @php($refundProofAttempts = $reservation->paymentAttempts->filter(fn ($attempt) => data_get($attempt->payload, 'refund_proof.path')))
        @if ($refundProofAttempts->isNotEmpty())
            <div class="summary-card full-width">
                <h2>{{ __('messages.receipts.refund_proof') }}</h2>
                @foreach ($refundProofAttempts as $attempt)
                    <a class="btn btn-ghost" href="{{ route('reservations.payment-proof.download', ['reservation' => $reservation, 'attempt' => $attempt, 'type' => 'refund']) }}">{{ data_get($attempt->payload, 'refund_proof.original_name') ?: __('messages.receipts.refund_proof') }}</a>
                    @if (data_get($attempt->payload, 'refund_reference'))<p>{{ __('messages.receipts.refund_reference') }} : {{ data_get($attempt->payload, 'refund_reference') }}</p>@endif
                @endforeach
            </div>
        @endif

        @if (auth()->user()?->role === 'customer' && $reservation->property?->establishment)
            <div class="summary-card full-width">
                <h2>{{ __('messages.messages.contact_concierge') }}</h2>
                <form method="POST" action="{{ route('messages.store') }}" class="message-form">
                    @csrf
                    <input type="hidden" name="establishment_id" value="{{ $reservation->property->establishment->id }}">
                    <input type="hidden" name="reservation_id" value="{{ $reservation->id }}">
                    <label for="reservation-message-body-details">{{ __('messages.messages.message') }}</label>
                    <textarea id="reservation-message-body-details" name="body" rows="4" maxlength="5000" required></textarea>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">{{ __('messages.messages.send') }}</button>
                    </div>
                </form>
            </div>
        @endif
        @else
        @if ($canModifyReservation)
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

                    @if ($reservation->property->features->where('is_active', true)->isNotEmpty())
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

        @if (auth()->user()?->role === 'customer' && $reservation->property?->establishment)
            <div class="summary-card full-width">
                <h2>{{ __('messages.messages.contact_concierge') }}</h2>
                <form method="POST" action="{{ route('messages.store') }}" class="message-form">
                    @csrf
                    <input type="hidden" name="establishment_id" value="{{ $reservation->property->establishment->id }}">
                    <input type="hidden" name="reservation_id" value="{{ $reservation->id }}">
                    <label for="reservation-message-body-edit">{{ __('messages.messages.message') }}</label>
                    <textarea id="reservation-message-body-edit" name="body" rows="4" maxlength="5000" required></textarea>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">{{ __('messages.messages.send') }}</button>
                    </div>
                </form>
            </div>
        @endif
        @endif
    </section>
@endsection
