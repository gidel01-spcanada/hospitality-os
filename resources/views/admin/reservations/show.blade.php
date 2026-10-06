@extends('layouts.admin')

@section('title', $reservation->reservation_ref . ' | ' . __('messages.admin.reservation'))

@php
    $locale = app()->getLocale();
    $money = fn ($amount, $currency = null) => \App\Support\ReservationSummary::money($amount, $currency ?? $summary['currency'], $locale);
    $date = fn ($value) => $value ? \Illuminate\Support\Carbon::parse($value)->locale($locale)->translatedFormat('d M Y') : '—';
    $dateTime = fn ($value) => $value ? \Illuminate\Support\Carbon::parse($value)->locale($locale)->translatedFormat('d M Y · H:i') : '—';
    $statusTones = ['pending' => 'warning', 'pending_payment' => 'warning', 'pending_validation' => 'info', 'payment_failed' => 'error', 'confirmed' => 'success', 'checked_in' => 'success', 'completed' => 'neutral', 'cancelled' => 'error'];
    $paymentTones = ['paid' => 'success', 'confirmed' => 'success', 'partial' => 'warning', 'pending' => 'warning', 'pending_validation' => 'info', 'failed' => 'error', 'cancelled' => 'neutral', 'not_required' => 'neutral', 'unreconciled' => 'info'];
    $statuses = ['pending', 'pending_payment', 'pending_validation', 'confirmed', 'checked_in', 'completed', 'cancelled'];
    $establishment = $reservation->property?->establishment;
    $authUser = auth()->user();
    $canOpenEstablishment = $establishment && $authUser->isEstablishmentManager() && $authUser->managesEstablishment($establishment->id);
    $validating = $summary['payment_status'] === 'pending_validation' && $summary['due'] !== null
        ? ((float) $summary['validating'] > 0 ? $summary['validating'] : $summary['due'])
        : null;
    $remaining = $validating !== null ? number_format(max(0, (float) $summary['due'] - (float) $validating), 2, '.', '') : $summary['due'];
    $pendingValidation = $payments->where('needs_validation', true);
    $refundRequired = $reservation->status !== 'cancelled' && $reservation->paymentAttempts
        ->filter(fn ($attempt) => in_array($attempt->status, ['paid', 'completed'], true) && in_array($attempt->provider, \App\Services\PaymentProofService::REFUND_PROOF_PROVIDERS, true) && ! data_get($attempt->payload, 'is_guarantee'))
        ->isNotEmpty();
    $customerName = $summary['guest_name'];
    $customerAccount = $reservation->user;
@endphp

@section('content')
<div class="rsv">
    <header class="rsv-header">
        <div class="rsv-header-main">
            <a class="rsv-back" href="{{ route('admin.reservations.index') }}">← {{ __('messages.admin.rsv_back') }}</a>
            <p class="rsv-eyebrow">{{ __('messages.admin.rsv_eyebrow') }}</p>
            <h1 class="rsv-title">{{ $reservation->reservation_ref }}</h1>
            <div class="rsv-badges">
                <x-badge :variant="$statusTones[$reservation->status] ?? 'neutral'" data-rsv-status="{{ $reservation->status }}">{{ __('messages.admin.status_' . $reservation->status) }}</x-badge>
                <x-badge :variant="$paymentTones[$summary['payment_status']] ?? 'neutral'" data-rsv-payment-status="{{ $summary['payment_status'] }}">{{ in_array($summary['payment_status'], ['paid', 'confirmed'], true) ? __('messages.transactional.status_paid') : __('messages.reservation_summary.payment_' . $summary['payment_status']) }}</x-badge>
            </div>
            <p class="rsv-meta">
                {{ __('messages.admin.rsv_created_at', ['date' => $dateTime($reservation->created_at)]) }}@if ($createdBy) {{ __('messages.admin.rsv_created_by', ['name' => $createdBy->name]) }}@endif
                · {{ __('messages.admin.rsv_updated_at', ['date' => $dateTime($reservation->updated_at)]) }}
            </p>
        </div>
        <div class="rsv-actions">
            <a class="btn btn-ghost" href="{{ route('admin.reservations.edit', $reservation) }}">{{ __('messages.admin.edit') }}</a>
            @if ($customerThread)
                <a href="{{ route('admin.messages.show', $customerThread) }}" class="btn btn-ghost">{{ __('messages.admin.message_customer') }}</a>
            @endif
            @if (!empty($summary['establishment']['email']))
                <a href="mailto:{{ $summary['establishment']['email'] }}?subject={{ rawurlencode($reservation->reservation_ref) }}" class="btn btn-ghost">{{ __('messages.admin.rsv_contact_establishment') }}</a>
            @endif
            @if ($reservation->canSendPaymentLink())
                <form action="{{ route('admin.reservations.payment-link.send', $reservation) }}" method="POST">
                    @csrf
                    <button type="submit" class="btn btn-ghost">{{ __('messages.admin.send_payment_link') }}</button>
                </form>
                <span class="rsv-copy-link">
                    <button type="button" class="btn btn-ghost" data-copy-payment-link="{{ route('checkout.show', ['reservation' => $reservation, 'token' => $reservation->checkout_token]) }}" data-copy-payment-link-success="{{ __('messages.admin.payment_link_copied') }}" data-copy-payment-link-error="{{ __('messages.admin.payment_link_copy_failed') }}">{{ __('messages.admin.copy_payment_link') }}</button>
                    <span class="form-help" data-copy-payment-link-status aria-live="polite" hidden></span>
                </span>
            @endif
            @if ($authUser->isAdmin())
                <form action="{{ route('admin.reservations.destroy', $reservation) }}" method="POST" data-confirm-message="{{ __('messages.admin.delete_reservation_confirmation') }}">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn btn-danger">{{ __('messages.admin.delete_reservation') }}</button>
                </form>
            @endif
        </div>
    </header>

    @if (session('status'))
        <div class="rsv-alert rsv-alert-success" role="status">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="rsv-alert rsv-alert-error" role="alert">
            <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif
    @if ($pendingValidation->isNotEmpty())
        <div class="rsv-alert rsv-alert-info" data-rsv-validation-alert>
            <span>{{ __('messages.admin.rsv_validation_alert', ['count' => $pendingValidation->count()]) }}</span>
            <a href="#reservation-payments" class="btn btn-small btn-primary">{{ __('messages.admin.rsv_review_payments') }}</a>
        </div>
    @endif

    <section class="rsv-kpis" aria-label="{{ __('messages.admin.rsv_eyebrow') }}">
        <div class="rsv-kpi">
            <span class="rsv-kpi-label">{{ __('messages.admin.guest') }}</span>
            <strong class="rsv-kpi-value">{{ $customerName }}</strong>
            <span class="rsv-kpi-sub">{{ $reservation->email }}</span>
        </div>
        <div class="rsv-kpi rsv-kpi-wide">
            <span class="rsv-kpi-label">{{ __('messages.admin.rsv_stay') }}</span>
            <strong class="rsv-kpi-value">{{ $date($summary['check_in']) }} → {{ $date($summary['check_out']) }}</strong>
            <span class="rsv-kpi-sub">{{ __('messages.admin.rsv_nights', ['count' => $summary['nights']]) }} · {{ __('messages.reservation.travelers_summary', ['adults' => $summary['adults'], 'children' => $summary['children'], 'infants' => $summary['infants']]) }}</span>
            <span class="rsv-kpi-sub">{{ $summary['property']['name'] }}@if ($summary['establishment']) · {{ $summary['establishment']['name'] }}@endif</span>
        </div>
        <div class="rsv-kpi">
            <span class="rsv-kpi-label">{{ __('messages.reservation_summary.total') }}</span>
            <strong class="rsv-kpi-value rsv-kpi-money" data-rsv-total>{{ $money($summary['total']) }}</strong>
        </div>
        <div class="rsv-kpi">
            <span class="rsv-kpi-label">{{ __('messages.reservation_summary.paid') }}</span>
            <strong class="rsv-kpi-value rsv-kpi-money" data-rsv-paid>{{ $money($summary['paid']) }}</strong>
            @if ((float) $summary['refunded'] > 0)<span class="rsv-kpi-sub">{{ __('messages.transactional.refund_done', ['amount' => $money($summary['refunded'])]) }}</span>@endif
        </div>
        <div class="rsv-kpi {{ $validating !== null ? 'rsv-kpi-info' : ((float) $remaining > 0 ? 'rsv-kpi-warning' : 'rsv-kpi-success') }}">
            @if ($validating !== null)
                <span class="rsv-kpi-label">{{ __('messages.transactional.validation_in_progress') }}</span>
                <strong class="rsv-kpi-value rsv-kpi-money" data-rsv-validating>{{ $money($validating) }}</strong>
                <span class="rsv-kpi-sub">{{ __('messages.reservation_summary.remaining_after_validation') }} : {{ $money($remaining) }}</span>
            @else
                <span class="rsv-kpi-label">{{ __('messages.reservation_summary.remaining') }}</span>
                <strong class="rsv-kpi-value rsv-kpi-money" data-rsv-due>{{ $remaining === null ? __('messages.reservation_summary.balance_unknown') : $money($remaining) }}</strong>
            @endif
        </div>
    </section>

    <nav class="rsv-nav" aria-label="{{ __('messages.admin.rsv_eyebrow') }}">
        <a href="#reservation-details">{{ __('messages.admin.rsv_nav_details') }}</a>
        <a href="#reservation-customer">{{ __('messages.admin.rsv_nav_customer') }}</a>
        <a href="#reservation-finance">{{ __('messages.admin.rsv_nav_finance') }}</a>
        <a href="#reservation-payments">{{ __('messages.admin.rsv_nav_payments') }} @if ($pendingValidation->isNotEmpty())<span class="rsv-dot" aria-hidden="true"></span>@endif</a>
        <a href="#reservation-status">{{ __('messages.admin.rsv_nav_status') }}</a>
        <a href="#reservation-notes">{{ __('messages.admin.rsv_nav_notes') }} ({{ $reservation->internalNotes->count() }})</a>
        <a href="#reservation-timeline">{{ __('messages.admin.rsv_nav_timeline') }}</a>
    </nav>

    <div class="rsv-grid">
        <div class="rsv-main">
            <section id="reservation-details" class="rsv-card">
                <h2 class="rsv-card-title">{{ __('messages.admin.rsv_details_title') }}</h2>
                <dl class="rsv-dl">
                    <div><dt>{{ __('messages.checkout.reference') }}</dt><dd><strong>{{ $reservation->reservation_ref }}</strong></dd></div>
                    <div><dt>{{ __('messages.reservation_summary.booked_at') }}</dt><dd>{{ $dateTime($reservation->created_at) }}</dd></div>
                    <div><dt>{{ __('messages.admin.establishment') }}</dt><dd>{{ $summary['establishment']['name'] ?? '—' }}@if (!empty($summary['establishment']['location']))<span class="rsv-muted">{{ $summary['establishment']['location'] }}</span>@endif</dd></div>
                    <div><dt>{{ __('messages.admin.property') }}</dt><dd>{{ $summary['property']['name'] }}@if ($summary['property']['capacity'])<span class="rsv-muted">{{ $summary['property']['capacity'] }}</span>@endif</dd></div>
                    <div><dt>{{ __('messages.admin.arrival') }}</dt><dd><strong>{{ $date($summary['check_in']) }}</strong></dd></div>
                    <div><dt>{{ __('messages.admin.departure') }}</dt><dd><strong>{{ $date($summary['check_out']) }}</strong></dd></div>
                    <div><dt>{{ __('messages.properties.night_count') }}</dt><dd>{{ $summary['nights'] }}</dd></div>
                    <div><dt>{{ __('messages.admin.travelers') }}</dt><dd>{{ __('messages.admin.travelers_summary', ['adults' => $reservation->adults, 'children' => $reservation->children, 'infants' => $reservation->infants]) }}</dd></div>
                    <div><dt>{{ __('messages.admin.rsv_source') }}</dt><dd>{{ $reservation->source ?: '—' }}</dd></div>
                    <div><dt>{{ __('messages.admin.rsv_language') }}</dt><dd>{{ strtoupper($reservation->locale ?: config('app.locale')) }}</dd></div>
                </dl>
                @if ($reservation->customer_note)
                    <div class="rsv-callout">
                        <p class="rsv-callout-title">{{ __('messages.admin.customer_note') }}</p>
                        <p>{{ $reservation->customer_note }}</p>
                    </div>
                @endif
            </section>

            <section id="reservation-customer" class="rsv-card">
                <h2 class="rsv-card-title">{{ __('messages.admin.rsv_customer_title') }}</h2>
                <div class="rsv-split">
                    <dl class="rsv-dl rsv-dl-stack">
                        <div><dt>{{ __('messages.admin.guest') }}</dt><dd><strong>{{ $customerName }}</strong></dd></div>
                        <div><dt>{{ __('messages.common.email') }}</dt><dd><a class="inline-link" href="mailto:{{ $reservation->email }}">{{ $reservation->email }}</a></dd></div>
                        <div><dt>{{ __('messages.admin.phone') }}</dt><dd>@if ($reservation->guest?->phone)<a class="inline-link" href="tel:{{ preg_replace('/[^0-9+]/', '', $reservation->guest->phone) }}">{{ $reservation->guest->phone }}</a>@else — @endif</dd></div>
                        <div><dt>{{ __('messages.admin.rsv_country') }}</dt><dd>{{ $reservation->guest?->country ?: '—' }}</dd></div>
                    </dl>
                    <div class="rsv-subcard">
                        <p class="rsv-subcard-title">{{ __('messages.admin.rsv_account') }}</p>
                        @if ($customerAccount)
                            <p>{{ __('messages.admin.rsv_account_since', ['date' => $date($customerAccount->created_at)]) }}</p>
                            <p>
                                @if ($customerAccount->email_verified_at)
                                    <x-badge variant="success" size="sm">{{ __('messages.admin.rsv_account_verified') }}</x-badge>
                                @else
                                    <x-badge variant="warning" size="sm">{{ __('messages.admin.rsv_account_unverified') }}</x-badge>
                                @endif
                            </p>
                            <p class="rsv-muted">{{ $customerAccount->last_login_at ? __('messages.admin.rsv_last_login', ['date' => $dateTime($customerAccount->last_login_at)]) : __('messages.admin.rsv_never_logged_in') }}</p>
                            @if ($authUser->isAdmin())
                                <a class="btn btn-ghost btn-small" href="{{ route('admin.users.edit', $customerAccount) }}">{{ __('messages.admin.rsv_open_profile') }}</a>
                            @endif
                        @else
                            <p class="rsv-muted">{{ __('messages.admin.rsv_account_none') }}</p>
                        @endif
                    </div>
                </div>
                <div class="rsv-history">
                    <p class="rsv-subcard-title">{{ __('messages.admin.rsv_customer_history') }} ({{ $customerReservations->count() }})</p>
                    @forelse ($customerReservations->take(5) as $other)
                        <a class="rsv-history-row" href="{{ route('admin.reservations.show', $other) }}">
                            <strong>{{ $other->reservation_ref }}</strong>
                            <span>{{ $other->property?->name }}</span>
                            <span>{{ $date($other->check_in) }} → {{ $date($other->check_out) }}</span>
                            <x-badge :variant="$statusTones[$other->status] ?? 'neutral'" size="sm">{{ __('messages.admin.status_' . $other->status) }}</x-badge>
                        </a>
                    @empty
                        <p class="rsv-muted">{{ __('messages.admin.rsv_customer_history_empty') }}</p>
                    @endforelse
                </div>
            </section>

            <section id="reservation-finance" class="rsv-card">
                <h2 class="rsv-card-title">{{ __('messages.admin.rsv_finance_title') }} <span class="rsv-muted">· {{ $summary['currency'] }}</span></h2>
                <table class="rsv-table">
                    <thead><tr><th scope="col">{{ __('messages.checkout.product_description') }}</th><th scope="col" class="rsv-num">{{ __('messages.checkout.line_amount') }}</th></tr></thead>
                    <tbody>
                        @forelse ($summary['lines'] as $line)
                            <tr>
                                <td>@if ($line['is_room'])<strong>{{ $summary['property']['name'] }}</strong><br><span class="rsv-muted">{{ __('messages.checkout.room_calculation', ['nights' => $summary['nights'], 'rate' => $money($line['nightly_rate'], $line['currency'])]) }}</span>@else{{ $line['label'] }}@endif</td>
                                <td class="rsv-num {{ (float) $line['amount'] < 0 ? 'rsv-discount' : '' }}">{{ $money($line['amount'], $line['currency']) }}</td>
                            </tr>
                        @empty
                            @foreach ([[$summary['property']['name'], $summary['subtotal']], [__('messages.properties.fees'), $summary['fees']], [__('messages.reservation.taxes'), $summary['taxes']], [__('messages.checkout.saved_adjustment'), $summary['adjustment']]] as [$label, $amount])
                                @if ((float) $amount != 0)<tr><td>{{ $label }}</td><td class="rsv-num">{{ $money($amount) }}</td></tr>@endif
                            @endforeach
                        @endforelse
                    </tbody>
                    <tfoot>
                        <tr class="rsv-total"><th scope="row">{{ __('messages.reservation_summary.total') }}</th><td class="rsv-num">{{ $money($summary['total']) }}</td></tr>
                        <tr><th scope="row">{{ __('messages.reservation_summary.paid') }}</th><td class="rsv-num">{{ $money($summary['paid']) }}</td></tr>
                        @foreach ($summary['foreign_payments'] as $payment)
                            <tr><th scope="row">{{ __('messages.reservation_summary.foreign_payment') }}</th><td class="rsv-num">{{ $money($payment['amount'], $payment['currency']) }}</td></tr>
                        @endforeach
                        @if ($validating !== null)
                            <tr><th scope="row">{{ __('messages.transactional.validation_in_progress') }}</th><td class="rsv-num">{{ $money($validating) }}</td></tr>
                        @endif
                        @if ((float) $summary['refunded'] > 0)
                            <tr><th scope="row">{{ __('messages.admin.payment_attempt_status_refunded') }}</th><td class="rsv-num">{{ $money($summary['refunded']) }}</td></tr>
                        @endif
                        <tr class="rsv-balance"><th scope="row">{{ $validating !== null ? __('messages.reservation_summary.remaining_after_validation') : ((float) $summary['paid'] > 0 ? __('messages.reservation_summary.remaining') : __('messages.reservation_summary.due')) }}</th><td class="rsv-num">{{ $remaining === null ? __('messages.reservation_summary.balance_unknown') : $money($remaining) }}</td></tr>
                    </tfoot>
                </table>
                @if ($summary['lines'])<p class="rsv-muted rsv-small">{{ __('messages.checkout.included_tax_note') }}</p>@endif
            </section>

            <section id="reservation-payments" class="rsv-card">
                <h2 class="rsv-card-title">{{ __('messages.admin.rsv_payments_title') }}</h2>
                <div class="rsv-payment-summary">
                    <div><span>{{ __('messages.admin.rsv_payment_status') }}</span><x-badge :variant="$paymentTones[$summary['payment_status']] ?? 'neutral'" size="sm">{{ in_array($summary['payment_status'], ['paid', 'confirmed'], true) ? __('messages.transactional.status_paid') : __('messages.reservation_summary.payment_' . $summary['payment_status']) }}</x-badge></div>
                    <div><span>{{ __('messages.reservation_summary.total') }}</span><strong>{{ $money($summary['total']) }}</strong></div>
                    <div><span>{{ __('messages.reservation_summary.paid') }}</span><strong>{{ $money($summary['paid']) }}</strong></div>
                    <div><span>{{ $validating !== null ? __('messages.reservation_summary.remaining_after_validation') : __('messages.reservation_summary.remaining') }}</span><strong>{{ $remaining === null ? __('messages.reservation_summary.balance_unknown') : $money($remaining) }}</strong></div>
                </div>

                @forelse ($payments as $payment)
                    @php($attempt = $payment['attempt'])
                    <article class="rsv-payment {{ $payment['needs_validation'] ? 'rsv-payment-attention' : '' }}" data-rsv-payment="{{ $attempt->id }}">
                        <header class="rsv-payment-head">
                            <div>
                                <strong>{{ $payment['provider'] }}</strong>
                                <span class="rsv-muted">{{ $payment['transaction_number'] }}</span>
                                @if ($payment['is_guarantee'])<x-badge variant="neutral" size="sm">{{ __('messages.admin.payment_guarantee') }}</x-badge>@endif
                            </div>
                            <div class="rsv-payment-amount">
                                <strong>{{ $payment['amount'] }}</strong>
                                <x-badge :variant="$payment['status_tone']" size="sm">{{ $payment['status_label'] }}</x-badge>
                            </div>
                        </header>
                        <dl class="rsv-dl rsv-dl-compact">
                            <div><dt>{{ __('messages.admin.rsv_initiated_at') }}</dt><dd>{{ $dateTime($payment['initiated_at']) }}</dd></div>
                            <div><dt>{{ __('messages.admin.rsv_paid_at') }}</dt><dd>{{ $payment['paid_at'] ? $dateTime($payment['paid_at']) : '—' }}</dd></div>
                            <div><dt>{{ __('messages.transactional.currency') }}</dt><dd>{{ $payment['currency'] }}</dd></div>
                            <div><dt>{{ __('messages.admin.rsv_receipt') }}</dt><dd>@if ($payment['receipt'])<a class="inline-link" href="{{ route('reservations.receipt', $reservation) }}">{{ $payment['receipt']->receipt_number }}</a>@else — @endif</dd></div>
                            @if ($payment['validated_at'] || $payment['validated_by'])
                                <div class="rsv-dl-full"><dt>{{ __('messages.admin.rsv_nav_status') }}</dt><dd>{{ $payment['validated_at'] ? __('messages.admin.rsv_validated', ['date' => $dateTime($payment['validated_at'])]) : '' }} @if ($payment['validated_by']){{ __('messages.admin.rsv_validated_by', ['name' => $payment['validated_by']]) }}@endif</dd></div>
                            @endif
                        </dl>

                        @if ($payment['proof_url'])
                            <div class="rsv-proof">
                                <span>{{ __('messages.admin.rsv_proof') }} :</span>
                                <a class="inline-link" href="{{ $payment['proof_url'] }}" target="_blank" rel="noopener">{{ $payment['proof_name'] ?: __('messages.receipts.download_proof') }}</a>
                                @if ($payment['proof_uploaded_at'])<span class="rsv-muted">{{ __('messages.admin.rsv_proof_uploaded', ['date' => $dateTime($payment['proof_uploaded_at'])]) }}@if ($payment['proof_uploaded_by']) · {{ $payment['proof_uploaded_by'] }}@endif</span>@endif
                            </div>
                        @endif
                        @if ($payment['refund_url'])
                            <div class="rsv-proof">
                                <span>{{ __('messages.receipts.refund_proof') }} :</span>
                                <a class="inline-link" data-refund-proof-link href="{{ $payment['refund_url'] }}" target="_blank" rel="noopener">{{ $payment['refund_name'] ?: __('messages.receipts.refund_proof') }}</a>
                                @if ($payment['refund_reference'])<span class="rsv-muted">{{ __('messages.receipts.refund_reference') }} : {{ $payment['refund_reference'] }}</span>@endif
                                @if ($payment['refunded_at'])<span class="rsv-muted">{{ $dateTime($payment['refunded_at']) }}@if ($payment['refunded_by']) · {{ $payment['refunded_by'] }}@endif</span>@endif
                            </div>
                        @endif

                        @if ($payment['needs_validation'])
                            <form action="{{ route('admin.reservations.payment.validate', ['reservation' => $reservation, 'attempt' => $attempt]) }}" method="POST" class="rsv-inline-action">
                                @csrf
                                <button type="submit" class="btn btn-primary" data-confirm-message="{{ __('messages.admin.rsv_validate_confirm') }}">{{ __('messages.admin.rsv_validate_payment') }}</button>
                            </form>
                        @endif

                        @if (! $payment['is_guarantee'] && in_array($attempt->status, ['paid', 'completed', 'awaiting_validation', 'pending_validation', 'refunded'], true))
                            <form action="{{ route('admin.reservations.payment-reference.update', ['reservation' => $reservation, 'attempt' => $attempt]) }}" method="POST" class="rsv-reference-form" data-payment-reference-form>
                                @csrf
                                @method('PATCH')
                                <label for="payment_reference_{{ $attempt->id }}">{{ __('messages.transactional.payment_reference') }}</label>
                                <div class="rsv-input-row">
                                    <input id="payment_reference_{{ $attempt->id }}" name="provider_reference" type="text" maxlength="120" required value="{{ $payment['reference'] }}">
                                    <button type="submit" class="btn btn-ghost btn-small">{{ __('messages.receipts.update_reference') }}</button>
                                </div>
                            </form>
                        @elseif ($payment['reference'])
                            <p class="rsv-muted">{{ __('messages.transactional.payment_reference') }} : {{ $payment['reference'] }}</p>
                        @endif

                        @if ($payment['transitions']->count() > 1)
                            <details class="rsv-details">
                                <summary>{{ __('messages.admin.rsv_payment_history') }} ({{ $payment['transitions']->count() }})</summary>
                                <ol class="rsv-mini-timeline">
                                    @foreach ($payment['transitions'] as $transition)
                                        <li><span class="rsv-muted">{{ $dateTime($transition['at']) }}</span> {{ $transition['from'] ? \App\Support\ReservationAdminOverview::attemptStatusLabel($transition['from']) . ' → ' : '' }}<strong>{{ \App\Support\ReservationAdminOverview::attemptStatusLabel($transition['to']) }}</strong>@if ($transition['by']) · {{ $transition['by'] }}@endif</li>
                                    @endforeach
                                </ol>
                            </details>
                        @endif
                    </article>
                @empty
                    <p class="rsv-muted">{{ __('messages.admin.rsv_payments_empty') }}</p>
                @endforelse

                <div class="rsv-split rsv-payment-tools">
                    <div class="rsv-subcard">
                        <p class="rsv-subcard-title">{{ __('messages.admin.rsv_receipts') }}</p>
                        @if ($reservation->receipts->isNotEmpty())
                            <ul class="rsv-list">
                                @foreach ($reservation->receipts as $receipt)
                                    <li><a class="inline-link" href="{{ route('reservations.receipt', $reservation) }}">{{ __('messages.receipts.download', ['number' => $receipt->receipt_number]) }}</a> <span class="rsv-muted">{{ $dateTime($receipt->issued_at) }}</span></li>
                                @endforeach
                            </ul>
                        @else
                            <form action="{{ route('admin.reservations.receipt.generate', $reservation) }}" method="POST" class="booking-form">
                                @csrf
                                <label for="receipt_provider_reference">{{ __('messages.receipts.reference_placeholder') }}</label>
                                <input id="receipt_provider_reference" name="provider_reference" type="text" maxlength="120" value="{{ old('provider_reference') }}">
                                <button type="submit" class="btn btn-ghost btn-full">{{ __('messages.receipts.generate') }}</button>
                            </form>
                        @endif
                    </div>
                    <div class="rsv-subcard">
                        <p class="rsv-subcard-title">{{ __('messages.admin.rsv_manual_payment') }}</p>
                        <form action="{{ route('admin.reservations.confirm-offline-payment', $reservation) }}" method="POST" enctype="multipart/form-data" class="booking-form">
                            @csrf
                            <label for="confirm_payment_proof">{{ __('messages.receipts.payment_proof') }}</label>
                            <input id="confirm_payment_proof" name="payment_proof" type="file" accept="image/jpeg,image/png,image/webp,application/pdf" required>
                            <label for="provider_reference">{{ __('messages.receipts.reference_placeholder') }}</label>
                            <input id="provider_reference" name="provider_reference" type="text" maxlength="120">
                            <button type="submit" class="btn btn-primary btn-full">{{ __('messages.receipts.confirm_offline') }}</button>
                        </form>
                    </div>
                </div>
            </section>

            <section id="reservation-timeline" class="rsv-card">
                <h2 class="rsv-card-title">{{ __('messages.admin.rsv_timeline_title') }}</h2>
                <ol class="rsv-timeline">
                    @foreach ($timeline as $event)
                        <li class="rsv-timeline-item rsv-timeline-{{ $event['type'] }}">
                            <span class="rsv-timeline-dot" aria-hidden="true"></span>
                            <div>
                                <p class="rsv-timeline-title"><strong>{{ $event['title'] }}</strong> <span class="rsv-muted">{{ $dateTime($event['at']) }}@if ($event['by']) · {{ $event['by'] }}@endif</span></p>
                                @if ($event['detail'])<p class="rsv-timeline-detail">{{ $event['detail'] }}</p>@endif
                            </div>
                        </li>
                    @endforeach
                </ol>
            </section>
        </div>

        <aside class="rsv-side">
            <section id="reservation-status" class="rsv-card">
                <h2 class="rsv-card-title">{{ __('messages.admin.rsv_status_title') }}</h2>
                <p class="rsv-current-status">{{ __('messages.admin.rsv_status_current') }} <x-badge :variant="$statusTones[$reservation->status] ?? 'neutral'">{{ __('messages.admin.status_' . $reservation->status) }}</x-badge></p>
                <form action="{{ route('admin.reservations.update-status', $reservation) }}" method="POST" class="booking-form" enctype="multipart/form-data">
                    @csrf
                    @method('PATCH')
                    <div>
                        <label for="status">{{ __('messages.admin.rsv_status_change') }}</label>
                        <select id="status" name="status">
                            @foreach ($statuses as $status)
                                <option value="{{ $status }}" @selected(old('status', $reservation->status) === $status)>{{ __('messages.admin.status_' . $status) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="status_payment_proof">{{ __('messages.receipts.payment_proof') }}</label>
                        <input id="status_payment_proof" name="payment_proof" type="file" accept="image/jpeg,image/png,image/webp,application/pdf">
                    </div>
                    @if ($refundRequired)
                        <div data-refund-proof-fields class="rsv-callout rsv-callout-warning">
                            <p class="form-help">{{ __('messages.receipts.refund_proof_help') }}</p>
                            <label for="refund_proof">{{ __('messages.receipts.refund_proof') }}</label>
                            <input id="refund_proof" name="refund_proof" type="file" accept="image/jpeg,image/png,image/webp,application/pdf">
                            @error('refund_proof')<p class="form-error-box">{{ $message }}</p>@enderror
                            <label for="refund_reference">{{ __('messages.receipts.refund_reference') }}</label>
                            <input id="refund_reference" name="refund_reference" type="text" maxlength="120" value="{{ old('refund_reference') }}">
                        </div>
                    @endif
                    <div>
                        <label for="status_note">{{ __('messages.admin.rsv_status_note') }}</label>
                        <textarea id="status_note" name="notes" rows="2" maxlength="500"></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary btn-full" data-confirm-message="{{ __('messages.admin.rsv_status_confirm') }}">{{ __('messages.admin.save_status') }}</button>
                </form>
                <div class="rsv-history">
                    <p class="rsv-subcard-title">{{ __('messages.admin.rsv_status_history') }}</p>
                    @if ($statusHistory->isEmpty())
                        <p class="rsv-muted">{{ __('messages.admin.rsv_status_history_empty') }}</p>
                    @else
                        <ol class="rsv-mini-timeline" data-rsv-status-history>
                            @foreach ($statusHistory->reverse() as $entry)
                                <li>
                                    <strong>{{ $entry['from'] ? __('messages.admin.status_' . $entry['from']) . ' → ' . __('messages.admin.status_' . $entry['to']) : __('messages.admin.rsv_status_initial') . ' : ' . __('messages.admin.status_' . $entry['to']) }}</strong>
                                    <span class="rsv-muted">{{ $dateTime($entry['at']) }} · {{ $entry['by'] ?? __('messages.admin.rsv_system') }}</span>
                                </li>
                            @endforeach
                        </ol>
                    @endif
                </div>
            </section>

            <section id="reservation-notes" class="rsv-card">
                <h2 class="rsv-card-title">{{ __('messages.admin.rsv_notes_title') }}</h2>
                <p class="rsv-muted rsv-small">{{ __('messages.admin.internal_note') }} — {{ __('messages.admin.rsv_notes_private') }}</p>
                <form action="{{ route('admin.reservations.internal-notes.store', $reservation) }}" method="POST" class="booking-form">
                    @csrf
                    <label for="internal_note_body" class="sr-only">{{ __('messages.admin.internal_note') }}</label>
                    <textarea id="internal_note_body" name="body" rows="3" maxlength="5000" required placeholder="{{ __('messages.admin.rsv_note_placeholder') }}">{{ old('body') }}</textarea>
                    <button type="submit" class="btn btn-primary btn-full">{{ __('messages.admin.rsv_note_add') }}</button>
                </form>
                <ol class="rsv-notes" data-rsv-notes>
                    @forelse ($reservation->internalNotes as $note)
                        <li class="rsv-note">
                            <p class="rsv-note-meta"><strong>{{ $note->user?->name ?? __('messages.admin.rsv_system') }}</strong> <span class="rsv-muted">{{ $dateTime($note->created_at) }}</span></p>
                            <p class="rsv-note-body">{{ $note->body }}</p>
                        </li>
                    @empty
                        <li class="rsv-muted">{{ __('messages.admin.rsv_notes_empty') }}</li>
                    @endforelse
                </ol>
                <details class="rsv-details" @if (filled($reservation->notes)) open @endif>
                    <summary>{{ __('messages.admin.rsv_legacy_notes') }}</summary>
                    <form action="{{ route('admin.reservations.notes.update', $reservation) }}" method="POST" class="booking-form" id="reservation-internal-note-editor">
                        @csrf
                        @method('PATCH')
                        <label for="notes" class="sr-only">{{ __('messages.admin.rsv_legacy_notes') }}</label>
                        <textarea id="notes" name="notes" rows="4" placeholder="{{ __('messages.admin.add_management_note') }}">{{ old('notes', $reservation->notes) }}</textarea>
                        <button type="submit" class="btn btn-ghost btn-full">{{ __('messages.admin.save_note') }}</button>
                    </form>
                </details>
            </section>

            <section class="rsv-card">
                <h2 class="rsv-card-title">{{ __('messages.admin.rsv_links') }}</h2>
                <div class="rsv-link-list">
                    @if ($canOpenEstablishment && $reservation->property)
                        <a class="btn btn-ghost btn-full" href="{{ route('admin.properties.edit', $reservation->property) }}">{{ __('messages.admin.rsv_open_property') }}</a>
                        <a class="btn btn-ghost btn-full" href="{{ route('admin.establishments.edit', $establishment) }}">{{ __('messages.admin.rsv_open_establishment') }}</a>
                    @endif
                    @if ($customerAccount && $authUser->isAdmin())
                        <a class="btn btn-ghost btn-full" href="{{ route('admin.users.edit', $customerAccount) }}">{{ __('messages.admin.rsv_open_profile') }}</a>
                    @endif
                    @if ($customerThread)
                        <a class="btn btn-ghost btn-full" href="{{ route('admin.messages.show', $customerThread) }}">{{ __('messages.admin.message_customer') }}</a>
                    @endif
                </div>
            </section>
        </aside>
    </div>
</div>
@endsection
