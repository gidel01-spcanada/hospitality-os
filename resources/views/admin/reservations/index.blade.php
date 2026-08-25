@extends('layouts.admin')

@section('title', __('messages.admin.reservations'))

@section('content')
    <div class="admin-page-header">
        <h1 class="admin-page-title">{{ __('messages.admin.reservations') }}</h1>
        <p class="admin-page-description">{{ __('messages.admin.reservations_description') }}</p>
    </div>

    <x-card>

            <table class="admin-table">
                <thead class="bg-slate-50 text-left">
                    <tr>
                        <th>{{ __('messages.admin.reference') }}</th><th>{{ __('messages.admin.property') }}</th><th>{{ __('messages.admin.guest') }}</th><th>{{ __('messages.reservation.dates') }}</th><th>{{ __('messages.admin.total') }}</th><th>{{ __('messages.reservation.status') }}</th><th>{{ __('messages.admin.action') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($reservations as $reservation)
                        <tr>
                            <td><strong>{{ $reservation->reservation_ref }}</strong></td>
                            <td>{{ $reservation->property?->name ?? '—' }}</td>
                            <td>{{ $reservation->guest?->full_name ?? $reservation->email }}</td>
                            <td>{{ $reservation->check_in?->format('d/m/Y') }} → {{ $reservation->check_out?->format('d/m/Y') }}</td>
                            <td>{{ number_format((float) $reservation->total_amount, 0, ',', ' ') }} XOF</td>
                            <td><x-badge variant="warning" size="sm">{{ __('messages.admin.status_' . $reservation->status) }}</x-badge></td>
                            <td><a class="inline-link" href="{{ route('admin.reservations.show', $reservation) }}">{{ __('messages.admin.view') }}</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="empty-state-inline">{{ __('messages.admin.no_reservations') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
    </x-card>
@endsection
