@php
    $checkoutSummary = \App\Support\ReservationSummary::make($reservation);
    $summaryProperty = $reservation->property;
    $summaryEstablishment = $summaryProperty?->establishment;
    $summaryNights = $checkoutSummary['nights'];
    $summaryPropertyImage = $checkoutSummary['property']['image'];
    $summaryEstablishmentImage = $checkoutSummary['establishment']['image'] ?? null;
    $summaryLocation = $checkoutSummary['property']['location'];
    $summarySavedAdjustment = (float) $checkoutSummary['adjustment'];
    $summaryMoney = [\App\Support\ReservationSummary::class, 'money'];
    $summaryCanEdit = auth()->user() && ($reservation->user_id === auth()->id() || strtolower((string) $reservation->email) === strtolower((string) auth()->user()->email));
    $summaryValidating = $checkoutSummary['payment_status'] === 'pending_validation' && $checkoutSummary['due'] !== null
        ? ((float) $checkoutSummary['validating'] > 0 ? $checkoutSummary['validating'] : $checkoutSummary['due'])
        : null;
    $summaryRemaining = $summaryValidating !== null
        ? number_format(max(0, (float) $checkoutSummary['due'] - (float) $summaryValidating), 2, '.', '')
        : $checkoutSummary['due'];
@endphp

<section class="checkout-order-summary" aria-labelledby="checkout-order-title">
    <header class="checkout-order-header">
        <div>
            <p class="checkout-order-eyebrow">{{ __('messages.checkout.reference') }} · {{ $reservation->reservation_ref }}</p>
            <h2 id="checkout-order-title">{{ __('messages.checkout.order_summary') }}</h2>
        </div>
        <span class="checkout-order-status">{{ __('messages.admin.status_' . $reservation->status) }}</span>
    </header>

    @if ($summaryEstablishment)
        <section class="checkout-order-section checkout-establishment-summary" aria-labelledby="checkout-establishment-title">
            @if ($summaryEstablishmentImage)<img class="checkout-establishment-image" src="{{ $summaryEstablishmentImage }}" alt="{{ $checkoutSummary['establishment']['name'] }}">@endif
            <div>
                <p class="checkout-order-eyebrow">{{ __('messages.checkout.establishment') }}</p>
                <h3 id="checkout-establishment-title">{{ $checkoutSummary['establishment']['name'] }}</h3>
                <p class="checkout-order-muted">{{ $checkoutSummary['establishment']['location'] }}</p>
                @if ($checkoutSummary['establishment']['email'])
                    <a class="inline-link" href="mailto:{{ $checkoutSummary['establishment']['email'] }}">{{ $checkoutSummary['establishment']['email'] }}</a>
                @endif
            </div>
        </section>
    @endif

    <section class="checkout-order-section checkout-property-summary" aria-labelledby="checkout-property-title">
        @if ($summaryPropertyImage)<img class="checkout-property-image" src="{{ $summaryPropertyImage }}" alt="{{ $checkoutSummary['property']['name'] }}">@endif
        <div>
            <p class="checkout-order-eyebrow">{{ __('messages.checkout.property') }}</p>
            <h3 id="checkout-property-title">{{ $checkoutSummary['property']['name'] }}</h3>
            @if ($summaryLocation)<p class="checkout-order-muted">{{ $summaryLocation }}</p>@endif
            @if ($checkoutSummary['property']['capacity'])
                <p class="checkout-property-capacity">{{ $checkoutSummary['property']['capacity'] }}</p>
            @endif
        </div>
    </section>

    <section class="checkout-order-section" aria-labelledby="checkout-stay-title">
        <h3 id="checkout-stay-title">{{ __('messages.checkout.stay_details') }}</h3>
        <dl class="checkout-stay-details">
            <div><dt>{{ __('messages.reservation_summary.booked_at') }}</dt><dd>{{ $reservation->created_at?->translatedFormat('d M Y') ?? '—' }}</dd></div>
            <div><dt>{{ __('messages.properties.check_in') }}</dt><dd>{{ $reservation->check_in?->translatedFormat('d M Y') ?? '—' }}</dd></div>
            <div><dt>{{ __('messages.properties.check_out') }}</dt><dd>{{ $reservation->check_out?->translatedFormat('d M Y') ?? '—' }}</dd></div>
            <div><dt>{{ __('messages.properties.night_count') }}</dt><dd>{{ $summaryNights }}</dd></div>
            <div><dt>{{ __('messages.reservation.travelers') }}</dt><dd>{{ __('messages.reservation.travelers_summary', ['adults' => $reservation->adults, 'children' => $reservation->children, 'infants' => $reservation->infants]) }}</dd></div>
            <div><dt>{{ __('messages.checkout.client') }}</dt><dd>{{ $reservation->guest?->full_name ?: $reservation->user?->name ?: $reservation->email }}</dd></div>
            @if ($reservation->customer_note)<div class="checkout-stay-note"><dt>{{ __('messages.properties.customer_note') }}</dt><dd>{{ $reservation->customer_note }}</dd></div>@endif
        </dl>
    </section>

    @if (!empty($checkoutSummary['stay_notes']))
        <section class="checkout-order-section" aria-labelledby="checkout-stay-notes-title" data-stay-notes>
            <h3 id="checkout-stay-notes-title">{{ __('messages.transactional.important_notes') }}</h3>
            @foreach ($checkoutSummary['stay_notes'] as $note)
                <p class="cancellation-policy-note {{ $note['type'] === 'electricity' ? 'electricity-policy-note' : '' }}">{{ $note['type'] === 'electricity' ? '⚡ ' : '' }}{{ $note['text'] }}</p>
            @endforeach
        </section>
    @endif

    <section class="checkout-order-section checkout-invoice" aria-labelledby="checkout-invoice-title">
        <div class="checkout-invoice-heading">
            <h3 id="checkout-invoice-title">{{ __('messages.checkout.cost_breakdown') }}</h3>
            <span>{{ __('messages.properties.currency_used') }} {{ $reservation->currency }}</span>
        </div>
        <table class="checkout-invoice-table">
            <caption class="sr-only">{{ __('messages.checkout.cost_breakdown') }} · {{ $summaryProperty?->localized('name') }}</caption>
            <thead><tr><th scope="col">{{ __('messages.checkout.product_description') }}</th><th scope="col">{{ __('messages.checkout.line_amount') }}</th></tr></thead>
            <tbody>
                @forelse ($checkoutSummary['lines'] as $line)
                    <tr data-invoice-line>
                        <th scope="row">
                            @if ($line['is_room'])
                                    <strong>{{ $checkoutSummary['property']['name'] }}</strong>
                                <small>{{ __('messages.checkout.room_calculation', ['nights' => $summaryNights, 'rate' => $summaryMoney($line['nightly_rate'], $line['currency'])]) }}</small>
                            @else
                                {{ $line['label'] }}
                            @endif
                        </th>
                        <td>{{ $summaryMoney($line['amount'], $line['currency']) }}</td>
                    </tr>
                @empty
                    <tr><th scope="row"><strong>{{ $checkoutSummary['property']['name'] }}</strong><small>{{ __('messages.checkout.saved_subtotal') }}</small></th><td>{{ $summaryMoney($checkoutSummary['subtotal'], $checkoutSummary['currency']) }}</td></tr>
                    @if ((float) $checkoutSummary['fees'] > 0)<tr><th scope="row">{{ __('messages.properties.fees') }}</th><td>{{ $summaryMoney($checkoutSummary['fees'], $checkoutSummary['currency']) }}</td></tr>@endif
                    @if ((float) $checkoutSummary['taxes'] > 0)<tr><th scope="row">{{ __('messages.reservation.taxes') }}</th><td>{{ $summaryMoney($checkoutSummary['taxes'], $checkoutSummary['currency']) }}</td></tr>@endif
                    @if ($summarySavedAdjustment != 0)<tr><th scope="row">{{ __('messages.checkout.saved_adjustment') }}</th><td>{{ $summaryMoney($summarySavedAdjustment, $reservation->currency) }}</td></tr>@endif
                @endforelse
            </tbody>
        </table>
        @if ($reservation->priceLines->isNotEmpty())<p class="checkout-tax-note">{{ __('messages.checkout.included_tax_note') }}</p>@endif
        <div class="checkout-order-total">
            <span>{{ __('messages.reservation_summary.total') }}</span>
            <strong data-checkout-order-total data-amount="{{ $reservation->total_amount }}" data-currency="{{ $reservation->currency }}">{{ $summaryMoney($reservation->total_amount, $reservation->currency) }}</strong>
        </div>
        @if ($summaryEstablishment?->secondary_currency && $summaryEstablishment->secondary_currency !== $reservation->currency && $summaryEstablishment->secondaryDisplayAmount((float) $reservation->total_amount) !== null)
            <p class="checkout-order-conversion">≈ {{ number_format($summaryEstablishment->secondaryDisplayAmount((float) $reservation->total_amount), 2, ',', ' ') }} {{ $summaryEstablishment->secondary_currency }}</p>
        @endif
        <div class="checkout-payment-balance" data-payment-summary data-payment-status="{{ $checkoutSummary['payment_status'] }}">
            <p>{{ __('messages.reservation_summary.payment_' . $checkoutSummary['payment_status']) }}</p>
            <dl>
                <div><dt>{{ __('messages.reservation_summary.paid') }}</dt><dd data-paid-amount>{{ $summaryMoney($checkoutSummary['paid'], $checkoutSummary['currency']) }}</dd></div>
                @foreach ($checkoutSummary['foreign_payments'] as $payment)
                    <div><dt>{{ __('messages.reservation_summary.foreign_payment') }}</dt><dd>{{ $summaryMoney($payment['amount'], $payment['currency']) }}</dd></div>
                @endforeach
                @if ($summaryValidating !== null)
                    <div class="checkout-payment-validating"><dt>{{ __('messages.transactional.validation_in_progress') }}</dt><dd data-validating-amount>{{ $summaryMoney($summaryValidating, $checkoutSummary['currency']) }}</dd></div>
                @endif
                <div class="{{ $summaryValidating !== null ? 'checkout-payment-after-validation' : 'checkout-payment-remaining' }}"><dt>{{ $summaryValidating !== null ? __('messages.reservation_summary.remaining_after_validation') : ((float) $checkoutSummary['paid'] > 0 ? __('messages.reservation_summary.remaining') : __('messages.reservation_summary.due')) }}</dt><dd data-remaining-amount>{{ $summaryRemaining === null ? __('messages.reservation_summary.balance_unknown') : $summaryMoney($summaryRemaining, $checkoutSummary['currency']) }}</dd></div>
            </dl>
            @if ($summaryValidating !== null)
                <p data-validating-note>{{ __('messages.transactional.validation_detail', ['amount' => $summaryMoney($summaryValidating, $checkoutSummary['currency'])]) }}</p>
            @endif
        </div>
    </section>

    <footer class="checkout-order-review">
        <p>{{ __('messages.checkout.review_before_payment') }}</p>
        @if ($summaryCanEdit)
            <div class="checkout-order-review-links">
                <a class="inline-link" href="{{ route('dashboard.reservations.show', $reservation) }}">{{ __('messages.reservation.details') }}</a>
            </div>
        @endif
    </footer>
</section>