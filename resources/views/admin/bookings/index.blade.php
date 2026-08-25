{{-- resources/views/admin/bookings/index.blade.php --}}
@extends('layouts.admin')

@section('title', 'Bookings')

@section('content')
<!-- Page Header -->
<div class="admin-page-header">
    <div style="display: flex; justify-content: space-between; align-items: center;">
        <div>
            <h1 class="admin-page-title">Booking Management</h1>
            <p class="admin-page-description">View and manage all reservations</p>
        </div>
        <x-button tag="a" href="/admin/bookings/create" variant="primary">
            <svg class="icon" fill="currentColor" viewBox="0 0 24 24">
                <path fill-rule="evenodd" d="M12 3.75a.75.75 0 0 1 .75.75v6.75h6.75a.75.75 0 0 1 0 1.5h-6.75V20.25a.75.75 0 0 1-1.5 0v-6.75H4.5a.75.75 0 0 1 0-1.5h6.75V4.5a.75.75 0 0 1 .75-.75Z" clip-rule="evenodd" />
            </svg>
            <span>New Booking</span>
        </x-button>
    </div>
</div>

<!-- Filters -->
<x-card style="margin-bottom: var(--space-6);">
    <div class="card-body">
        <form method="GET" action="/admin/bookings" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: var(--space-4); align-items: flex-end;">
            <div>
                <x-input 
                    name="search" 
                    label="Search"
                    placeholder="Property or guest name..."
                    value="{{ request('search') }}"
                />
            </div>
            
            <div>
                <label for="status" style="display: block; font-size: var(--font-sm); font-weight: var(--font-semibold); margin-bottom: var(--space-2);">Status</label>
                <select name="status" id="status" class="input-md">
                    <option value="">All Status</option>
                    <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>Pending</option>
                    <option value="pending_payment" {{ request('status') === 'pending_payment' ? 'selected' : '' }}>Pending Payment</option>
                    <option value="confirmed" {{ request('status') === 'confirmed' ? 'selected' : '' }}>Confirmed</option>
                    <option value="cancelled" {{ request('status') === 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                    <option value="completed" {{ request('status') === 'completed' ? 'selected' : '' }}>Completed</option>
                </select>
            </div>

            <div>
                <label for="date_from" style="display: block; font-size: var(--font-sm); font-weight: var(--font-semibold); margin-bottom: var(--space-2);">From</label>
                <input type="date" name="date_from" id="date_from" class="input-md" value="{{ request('date_from') }}" />
            </div>

            <div>
                <label for="date_to" style="display: block; font-size: var(--font-sm); font-weight: var(--font-semibold); margin-bottom: var(--space-2);">To</label>
                <input type="date" name="date_to" id="date_to" class="input-md" value="{{ request('date_to') }}" />
            </div>

            <div style="display: flex; gap: var(--space-2);">
                <x-button type="submit" variant="primary">Filter</x-button>
                <x-button tag="a" href="/admin/bookings" variant="secondary">Reset</x-button>
            </div>
        </form>
    </div>
</x-card>

<!-- Bookings Table -->
<x-card>
    <table class="admin-table">
        <thead>
            <tr>
                <th>Property</th>
                <th>Guest</th>
                <th>Check-in</th>
                <th>Check-out</th>
                <th>Nights</th>
                <th>Price</th>
                <th>Status</th>
                <th style="text-align: right;">Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($bookings as $booking)
                <tr>
                    <td><strong>{{ $booking->property?->name ?? 'N/A' }}</strong></td>
                    <td>{{ $booking->guest?->full_name ?? $booking->email ?? 'N/A' }}</td>
                    <td>{{ $booking->check_in->format('M d, Y') }}</td>
                    <td>{{ $booking->check_out->format('M d, Y') }}</td>
                    <td class="text-center">{{ $booking->check_out->diffInDays($booking->check_in) }}</td>
                    <td class="font-semibold">{{ $booking->total_amount !== null ? number_format((float) $booking->total_amount, 2) . ' ' . ($booking->currency ?: 'XOF') : 'TBA' }}</td>
                    <td>
                        @php
                            $statusColors = [
                                'pending' => 'warning',
                                'pending_payment' => 'warning',
                                'confirmed' => 'success',
                                'cancelled' => 'error',
                                'completed' => 'info',
                            ];
                        @endphp
                        <x-badge variant="{{ $statusColors[$booking->status] ?? 'neutral' }}" size="sm">
                            {{ ucfirst($booking->status) }}
                        </x-badge>
                    </td>
                    <td style="text-align: right;">
                        <div style="display: flex; gap: var(--space-2); justify-content: flex-end;">
                            <x-button tag="a" href="/admin/bookings/{{ $booking->id }}" variant="ghost" size="sm" title="View details">
                                <svg class="icon" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M12 15a3 3 0 100-6 3 3 0 000 6z" />
                                    <path fill-rule="evenodd" d="M1.646 4.646a.5.5 0 01.708 0L12 14.293l9.646-9.647a.5.5 0 11.708.708l-10 10a.5.5 0 01-.708 0l-10-10a.5.5 0 010-.708z" clip-rule="evenodd" />
                                </svg>
                            </x-button>

                            <x-button tag="a" href="/admin/bookings/{{ $booking->id }}/edit" variant="ghost" size="sm" title="Edit">
                                <svg class="icon" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 9l-6.455 6.456M9 9l6 6" />
                                </svg>
                            </x-button>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="admin-table-empty">
                        <p>No bookings found matching your criteria.</p>
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    @if ($bookings->hasPages())
        <div class="card-footer" style="justify-content: center;">
            {{ $bookings->links() }}
        </div>
    @endif
</x-card>
@endsection
