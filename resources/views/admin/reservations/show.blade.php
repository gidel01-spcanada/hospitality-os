@extends('layouts.admin')

    @section('title', $reservation->reservation_ref . ' | ' . __('messages.admin.reservation'))

@section('content')
    <div class="admin-page-header reservation-detail-header">
        <div>
            <h1 class="admin-page-title">{{ $reservation->reservation_ref }}</h1>
            <p class="admin-page-description">{{ $reservation->property?->name ?? __('messages.admin.property') }}</p>
        </div>
        <div class="reservation-header-actions">
            <a class="btn btn-ghost" href="{{ route('admin.reservations.edit', $reservation) }}">{{ __('messages.admin.edit') }}</a>
            @if ($customerThread)
                <a href="{{ route('admin.messages.show', $customerThread) }}" class="btn btn-ghost">{{ __('messages.admin.message_customer') }}</a>
            @endif
            @if (auth()->user()->isAdmin())
                <form action="{{ route('admin.reservations.destroy', $reservation) }}" method="POST" data-confirm-message="{{ __('messages.admin.delete_reservation_confirmation') }}">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn btn-danger">{{ __('messages.admin.delete_reservation') }}</button>
                </form>
            @endif
        </div>
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

                <form action="{{ route('admin.reservations.notes.update', $reservation) }}" method="POST" class="booking-form" id="reservation-internal-note-editor" style="margin-top: 1rem;">
                    @csrf
                    @method('PATCH')
                    <div>
                        <label for="notes">{{ __('messages.admin.internal_note') }}</label>
                        <textarea id="notes" name="notes" rows="4" placeholder="{{ __('messages.admin.add_management_note') }}">{{ old('notes', $reservation->notes) }}</textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">{{ __('messages.admin.save_note') }}</button>
                </form>

                <form action="{{ route('admin.reservations.update-status', $reservation) }}" method="POST" class="booking-form" enctype="multipart/form-data" style="margin-top: 1rem;">
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
                        <label for="status_payment_proof">{{ __('messages.receipts.payment_proof') }}</label>
                        <input id="status_payment_proof" name="payment_proof" type="file" accept="image/jpeg,image/png,image/webp,application/pdf">
                    </div>
                    <button type="submit" class="btn btn-primary">{{ __('messages.admin.save_status') }}</button>
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
                        $proofAttempts = $reservation->paymentAttempts
                            ->filter(fn ($attempt) => data_get($attempt->payload, 'payment_proof.path'))
                            ->sortByDesc('created_at');
                    @endphp
                    @if ($proofAttempts->isNotEmpty())
                        <ul class="uploaded-proof-list" style="margin-top: 0.75rem;">
                            @foreach ($proofAttempts as $attempt)
                                @php
                                    $proof = data_get($attempt->payload, 'payment_proof');
                                    $proofUrl = route('reservations.payment-proof.download', ['reservation' => $reservation, 'attempt' => $attempt]);
                                @endphp
                                <li style="margin-top: 0.5rem;">
                                    <a class="inline-link" href="{{ $proofUrl }}">{{ data_get($proof, 'original_name') ?: __('messages.receipts.download_proof') }}</a>
                                    <p class="form-help">{{ __('messages.checkout.' . $attempt->provider) }} — {{ $attempt->created_at?->format('d/m/Y H:i') }}</p>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>

                <div class="payment-link-actions" style="margin-top: 1rem;">
                    <form action="{{ route('admin.reservations.confirm-offline-payment', $reservation) }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        <label for="confirm_payment_proof">{{ __('messages.receipts.payment_proof') }}</label>
                        <input id="confirm_payment_proof" name="payment_proof" type="file" accept="image/jpeg,image/png,image/webp,application/pdf" required>
                        <label for="provider_reference">{{ __('messages.receipts.reference_placeholder') }}</label>
                        <input id="provider_reference" name="provider_reference" type="text">
                        <button type="submit" class="btn btn-primary">{{ __('messages.receipts.confirm_offline') }}</button>
                    </form>
                </div>

                <div class="card" style="margin-top: 1rem;">
                    <h2>{{ __('messages.receipts.title') }}</h2>
                    @if ($reservation->receipts->isNotEmpty())
                        <ul>
                            @foreach ($reservation->receipts as $receipt)
                                <li><a class="inline-link" href="{{ route('reservations.receipt', $reservation) }}">{{ __('messages.receipts.download', ['number' => $receipt->receipt_number]) }}</a></li>
                            @endforeach
                        </ul>
                    @else
                        <form action="{{ route('admin.reservations.receipt.generate', $reservation) }}" method="POST">
                            @csrf
                            <button type="submit" class="btn btn-ghost btn-full">{{ __('messages.receipts.generate') }}</button>
                        </form>
                    @endif
                </div>
            </aside>
        </div>
    </x-card>
@endsection
