@extends('layouts.app')

@section('title', __('messages.admin.reservations'))

@section('content')
    <section class="page-hero compact-hero">
        <div class="container">
            <span class="badge badge-gold">{{ __('messages.admin.title') }}</span>
            <h1>{{ __('messages.admin.reservations') }}</h1>
            <p>{{ __('messages.admin.reservations_description') }}</p>
        </div>
    </section>

    <section class="container admin-list-shell">
        <div class="admin-table-wrap">

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
                            <td><span class="badge badge-gold">{{ __('messages.admin.status_' . $reservation->status) }}</span></td>
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
        </div>
        </div>
    </section>
@endsection
