{{-- resources/views/admin/bookings/index.blade.php --}}
@extends('layouts.admin')

@section('title', __('messages.admin.reservations'))

@section('content')
<!-- Page Header -->
<div class="admin-page-header">
    <div class="booking-page-header-content">
        <div>
            <h1 class="admin-page-title">{{ __('messages.admin.reservations') }}</h1>
            <p class="admin-page-description">{{ __('messages.admin.reservations_description') }}</p>
        </div>
        <x-button tag="a" href="/admin/bookings/create" variant="primary">
            <svg class="icon" fill="currentColor" viewBox="0 0 24 24">
                <path fill-rule="evenodd" d="M12 3.75a.75.75 0 0 1 .75.75v6.75h6.75a.75.75 0 0 1 0 1.5h-6.75V20.25a.75.75 0 0 1-1.5 0v-6.75H4.5a.75.75 0 0 1 0-1.5h6.75V4.5a.75.75 0 0 1 .75-.75Z" clip-rule="evenodd" />
            </svg>
            <span>{{ __('messages.admin.new_booking') }}</span>
        </x-button>
    </div>
</div>

<!-- Filters -->
<x-card class="booking-list-card">
    <div class="card-body">
        <form method="GET" action="/admin/bookings" class="booking-filters">
            <div>
                <x-input 
                    name="search" 
                    label="{{ __('messages.admin.search') }}"
                    placeholder="{{ __('messages.admin.search_booking_placeholder') }}"
                    value="{{ request('search') }}"
                />
            </div>

            <div>
                <label for="status" style="display: block; font-size: var(--font-sm); font-weight: var(--font-semibold); margin-bottom: var(--space-2);">{{ __('messages.admin.status') }}</label>
                <select name="status" id="status" class="input-md">
                    <option value="">{{ __('messages.admin.all_status') }}</option>
                    <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>{{ __('messages.admin.status_pending') }}</option>
                    <option value="pending_payment" {{ request('status') === 'pending_payment' ? 'selected' : '' }}>{{ __('messages.admin.status_pending_payment') }}</option>
                    <option value="pending_validation" {{ request('status') === 'pending_validation' ? 'selected' : '' }}>{{ __('messages.admin.status_pending_validation') }}</option>
                    <option value="confirmed" {{ request('status') === 'confirmed' ? 'selected' : '' }}>{{ __('messages.admin.status_confirmed') }}</option>
                    <option value="cancelled" {{ request('status') === 'cancelled' ? 'selected' : '' }}>{{ __('messages.admin.status_cancelled') }}</option>
                    <option value="completed" {{ request('status') === 'completed' ? 'selected' : '' }}>{{ __('messages.admin.status_completed') }}</option>
                </select>
            </div>

            <div class="date-range-picker admin-date-range-picker" data-date-range-picker data-incomplete-message="{{ __('messages.home.select_both_dates') }}" data-past-message="{{ __('messages.home.past_dates') }}" data-start-label="{{ __('messages.admin.to') }}" data-end-label="{{ __('messages.admin.to') }}" data-placeholder="{{ __('messages.admin.from') }} / {{ __('messages.admin.to') }}">
                <label for="booking-date-range-trigger"><span>{{ __('messages.admin.from') }} / {{ __('messages.admin.to') }}</span><button type="button" id="booking-date-range-trigger" class="date-range-trigger" data-date-range-trigger aria-expanded="false"><span data-date-range-label>{{ request('date_from') && request('date_to') ? request('date_from') . ' → ' . request('date_to') : __('messages.admin.from') . ' / ' . __('messages.admin.to') }}</span></button></label>
                <input type="hidden" name="date_from" value="{{ request('date_from') }}" data-date-range-start>
                <input type="hidden" name="date_to" value="{{ request('date_to') }}" data-date-range-end>
                <div class="date-range-popover" data-date-range-popover hidden></div>
            </div>

            <div class="admin-filter-actions">
                <x-button type="submit" variant="primary">{{ __('messages.admin.filter') }}</x-button>
                <x-button tag="a" href="/admin/bookings" variant="secondary">{{ __('messages.admin.reset') }}</x-button>
            </div>
        </form>
    </div>
    <div class="booking-list-section">
<!-- Bookings Table -->
    <div class="admin-table-wrap" tabindex="0" role="region" aria-label="{{ __('messages.admin.reservations') }}">
        <table class="admin-table">
        <thead>
            <tr>
                <th>{{ __('messages.admin.property') }}</th>
                <th>{{ __('messages.admin.guest') }}</th>
                <th>{{ __('messages.admin.check_in') }}</th>
                <th>{{ __('messages.admin.check_out') }}</th>
                <th>{{ __('messages.admin.nights') }}</th>
                <th>{{ __('messages.admin.total') }}</th>
                <th>{{ __('messages.admin.status') }}</th>
                <th style="text-align: right;">{{ __('messages.admin.actions') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($bookings as $booking)
                <tr>
                    <td><strong>{{ $booking->property?->name ?? __('messages.common.not_available') }}</strong></td>
                    <td>{{ $booking->guest?->full_name ?? $booking->email ?? __('messages.common.not_available') }}</td>
                    <td>{{ $booking->check_in->translatedFormat('d M Y') }}</td>
                    <td>{{ $booking->check_out->translatedFormat('d M Y') }}</td>
                    <td class="text-center">{{ $booking->check_in->diffInDays($booking->check_out) }}</td>
                    <td class="font-semibold">{{ $booking->total_amount !== null ? number_format((float) $booking->total_amount, 2) . ' ' . ($booking->currency ?: $booking->property?->currency ?? 'XOF') : __('messages.common.to_be_announced') }}</td>
                    <td>
                        @php
                            $statusColors = [
                                'pending' => 'warning',
                                'pending_payment' => 'warning',
                                'pending_validation' => 'warning',
                                'confirmed' => 'success',
                                'cancelled' => 'error',
                                'completed' => 'info',
                            ];
                        @endphp
                        <x-badge variant="{{ $statusColors[$booking->status] ?? 'neutral' }}" size="sm">
                            {{ __('messages.admin.status_' . $booking->status, [], app()->getLocale()) }}
                        </x-badge>
                    </td>
                    <td style="text-align: right;">
                        <div class="admin-row-actions">
                            <x-button tag="a" href="/admin/bookings/{{ $booking->id }}" variant="ghost" size="sm" class="btn-icon" title="{{ __('messages.admin.view') }}" aria-label="{{ __('messages.admin.view') }}">
                                <svg class="icon" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M12 15a3 3 0 100-6 3 3 0 000 6z" />
                                    <path fill-rule="evenodd" d="M1.646 4.646a.5.5 0 01.708 0L12 14.293l9.646-9.647a.5.5 0 11.708.708l-10 10a.5.5 0 01-.708 0l-10-10a.5.5 0 010-.708z" clip-rule="evenodd" />
                                </svg>
                            </x-button>

                            <x-button tag="a" href="/admin/bookings/{{ $booking->id }}/edit" variant="ghost" size="sm" class="btn-icon" title="{{ __('messages.admin.edit') }}" aria-label="{{ __('messages.admin.edit') }}">
                                <svg class="icon" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 9l-6.455 6.456M9 9l6 6" />
                                </svg>
                            </x-button>

                            @if (auth()->user()->isAdmin())
                                <form method="POST" action="{{ route('admin.reservations.destroy', $booking) }}" data-confirm-message="{{ __('messages.admin.delete_reservation_confirmation') }}" style="display: inline;">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-danger btn-sm btn-icon" title="{{ __('messages.admin.delete_reservation') }}" aria-label="{{ __('messages.admin.delete_reservation') }}">
                                        <svg class="icon" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path fill-rule="evenodd" d="M9 3.75A2.25 2.25 0 0 1 11.25 1.5h1.5A2.25 2.25 0 0 1 15 3.75V5h3.75a.75.75 0 0 1 0 1.5h-.66l-.72 12.03A3.75 3.75 0 0 1 13.63 22H10.37a3.75 3.75 0 0 1-3.74-3.47L5.91 6.5h-.66a.75.75 0 0 1 0-1.5H9V3.75ZM10.5 5h3V3.75a.75.75 0 0 0-.75-.75h-1.5a.75.75 0 0 0-.75.75V5Zm-2.09 1.5.72 11.94a2.25 2.25 0 0 0 2.24 2.06h1.26a2.25 2.25 0 0 0 2.24-2.06l.72-11.94H8.41Z" clip-rule="evenodd" />
                                        </svg>
                                    </button>
                                </form>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="admin-table-empty">
                        <p>{{ __('messages.admin.no_reservations') }}</p>
                    </td>
                </tr>
            @endforelse
        </tbody>
        </table>
    </div>

    @if ($bookings->hasPages())
        <div class="card-footer" style="justify-content: center;">
            {{ $bookings->links() }}
        </div>
    @endif
    </div>
</x-card>
@endsection
