@extends('layouts.admin')

    @section('title', $reservation->reservation_ref . ' | ' . __('messages.admin.reservation'))

@section('content')
    <div class="admin-page-header">
        <h1 class="admin-page-title">{{ $reservation->reservation_ref }}</h1>
        <p class="admin-page-description">{{ $reservation->property?->name ?? __('messages.admin.property') }}</p>
    </div>

    <x-card>

        @if (session('status'))
            <div class="mb-6 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                {{ session('status') }}
            </div>
        @endif

        <div class="admin-two-column">
            <div class="admin-panel">
                <dl class="admin-form-grid compact">
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-slate-500">{{ __('messages.admin.property') }}</dt>
                        <dd class="mt-1 font-medium">{{ $reservation->property?->name ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-slate-500">{{ __('messages.admin.guest') }}</dt>
                        <dd class="mt-1 font-medium">{{ $reservation->guest?->full_name ?? $reservation->email }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-slate-500">{{ __('messages.common.email') }}</dt>
                        <dd class="mt-1">{{ $reservation->email }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-slate-500">{{ __('messages.admin.phone') }}</dt>
                        <dd class="mt-1">{{ $reservation->guest?->phone ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-slate-500">{{ __('messages.admin.arrival') }}</dt>
                        <dd class="mt-1">{{ $reservation->check_in?->format('d/m/Y') }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-slate-500">{{ __('messages.admin.departure') }}</dt>
                        <dd class="mt-1">{{ $reservation->check_out?->format('d/m/Y') }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-slate-500">{{ __('messages.admin.travelers') }}</dt>
                        <dd class="mt-1">{{ __('messages.admin.travelers_summary', ['adults' => $reservation->adults, 'children' => $reservation->children, 'infants' => $reservation->infants]) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-slate-500">{{ __('messages.admin.total') }}</dt>
                        <dd class="mt-1 font-semibold">{{ number_format((float) $reservation->total_amount, 0, ',', ' ') }} {{ $reservation->currency }}</dd>
                    </div>
                </dl>

                @if ($reservation->customer_note)
                    <div class="card" style="margin-top: 1.5rem;">
                        <h2>{{ __('messages.admin.customer_note') }}</h2>
                        <p>{{ $reservation->customer_note }}</p>
                    </div>
                @endif

                <div class="card" style="margin-top: 1.5rem;">
                    <h2>{{ __('messages.admin.financial_details') }}</h2>
                    <ul>
                        @foreach($reservation->priceLines as $line)
                            <li>
                                <span>{{ $line->label }}</span>
                                <span>{{ number_format((float) $line->amount, 0, ',', ' ') }} {{ $line->currency ?: $reservation->currency }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>

            <aside class="admin-panel">
                <div class="admin-action-stack">
                    @if ($customerThread)
                        <a href="{{ route('admin.messages.show', $customerThread) }}" class="btn btn-ghost btn-full">{{ __('messages.admin.message_customer') }}</a>
                    @endif

                    <form action="{{ route('admin.reservations.destroy', $reservation) }}" method="POST" data-confirm-message="{{ __('messages.admin.delete_reservation_confirmation') }}">
                        @csrf @method('DELETE')
                        <button type="submit" class="btn btn-danger btn-full">{{ __('messages.admin.delete_reservation') }}</button>
                    </form>
                </div>

                <div class="card" style="margin-top: 1rem;">
                    <div class="admin-note-header">
                        <h2>{{ __('messages.admin.internal_note') }}</h2>
                        <a href="#reservation-internal-note-editor" class="btn btn-ghost btn-small">{{ __('messages.admin.edit') }}</a>
                    </div>
                    @if ($reservation->notes)
                        <p>{{ $reservation->notes }}</p>
                    @else
                        <p class="text-slate-500">{{ __('messages.admin.no_internal_note') }}</p>
                    @endif
                </div>

                <form action="{{ route('admin.reservations.update-status', $reservation) }}" method="POST" class="booking-form" id="reservation-internal-note-editor" style="margin-top: 1rem;">
                    @csrf
                    @method('PATCH')

                    <div>
                        <label for="status">{{ __('messages.admin.status') }}</label>
                        <select id="status" name="status">
                            @foreach(['pending', 'pending_payment', 'pending_validation', 'confirmed', 'checked_in', 'completed', 'cancelled'] as $status)
                                <option value="{{ $status }}" {{ $reservation->status === $status ? 'selected' : '' }}>{{ __('messages.admin.status_' . $status) }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="notes">{{ __('messages.admin.internal_note') }}</label>
                        <textarea id="notes" name="notes" rows="4" placeholder="{{ __('messages.admin.add_management_note') }}">{{ old('notes', $reservation->notes) }}</textarea>
                    </div>

                    <button type="submit" class="btn btn-primary btn-full">{{ __('messages.admin.save_status') }}</button>
                </form>

                @if ($reservation->canSendPaymentLink())
                    @php $paymentLink = route('checkout.show', ['reservation' => $reservation, 'token' => $reservation->checkout_token]); @endphp
                    <div class="payment-link-actions" style="margin-top: 1rem;">
                        <form action="{{ route('admin.reservations.payment-link.send', $reservation) }}" method="POST">
                            @csrf
                            <button type="submit" class="btn btn-ghost btn-full">{{ __('messages.admin.send_payment_link') }}</button>
                        </form>
                        <button type="button" class="btn btn-ghost btn-full" data-copy-payment-link="{{ $paymentLink }}" data-copy-payment-link-success="{{ __('messages.admin.payment_link_copied') }}" data-copy-payment-link-error="{{ __('messages.admin.payment_link_copy_failed') }}">{{ __('messages.admin.copy_payment_link') }}</button>
                        <p class="form-help" data-copy-payment-link-status aria-live="polite" hidden></p>
                    </div>
                @endif

                <div class="payment-link-actions" style="margin-top: 1rem;">
                    @php
                        $latestPaymentAttempt = $reservation->paymentAttempts->sortByDesc('created_at')->first();
                        $paymentProof = data_get($latestPaymentAttempt?->payload, 'payment_proof');
                    @endphp
                    <form action="{{ route('admin.reservations.payment-proof.upload', $reservation) }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        <label for="payment_proof">{{ __('messages.receipts.payment_proof') }}</label>
                        <input id="payment_proof" name="payment_proof" type="file" accept="image/jpeg,image/png,image/webp,application/pdf" required>
                        <button type="submit" class="btn btn-ghost btn-full">{{ __('messages.receipts.upload_proof') }}</button>
                    </form>
                    @if ($paymentProof && data_get($paymentProof, 'path'))
                        <p class="form-help"><a class="inline-link" href="{{ asset(data_get($paymentProof, 'path')) }}" target="_blank" rel="noopener">{{ __('messages.receipts.view_proof') }}</a></p>
                    @endif
                </div>

                <div class="payment-link-actions" style="margin-top: 1rem;">
                    <form action="{{ route('admin.reservations.confirm-offline-payment', $reservation) }}" method="POST">
                        @csrf
                        <input name="provider_reference" type="text" placeholder="{{ __('messages.receipts.reference_placeholder') }}">
                        <button type="submit" class="btn btn-primary btn-full">{{ __('messages.receipts.confirm_offline') }}</button>
                    </form>
                </div>
            </aside>
        </div>
    </x-card>
@endsection
