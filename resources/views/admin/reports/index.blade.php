@extends('layouts.admin')

@section('title', __('messages.reports.title'))

@section('content')
    <div class="admin-page-header report-page-header">
        <div>
            <h1 class="admin-page-title">{{ __('messages.reports.title') }}</h1>
            <p class="admin-page-description">{{ __('messages.reports.description') }}</p>
        </div>
        <div class="report-header-actions">
            <a href="{{ route('admin.reports.export', array_filter([
                'date_from' => $from->toDateString(),
                'date_to' => $to->toDateString(),
                'establishment' => $selectedEstablishmentId,
                'property' => $selectedPropertyId,
            ])) }}" class="btn btn-ghost">{{ __('messages.reports.export_csv') }}</a>
            <button type="button" class="btn btn-ghost" data-print-report>{{ __('messages.reports.print') }}</button>
        </div>
    </div>

    <x-card class="report-filter-card">
        <nav class="report-section-nav" aria-label="{{ __('messages.reports.sections') }}">
            <a href="#operations">{{ __('messages.reports.operations') }}</a>
            <a href="#finance">{{ __('messages.reports.finance') }}</a>
            <a href="#performance">{{ __('messages.reports.performance') }}</a>
            <a href="#quality">{{ __('messages.reports.quality') }}</a>
        </nav>
        <form method="GET" action="{{ route('admin.reports.index') }}" class="report-filters">
            <div class="date-range-picker admin-date-range-picker" data-date-range-picker data-incomplete-message="{{ __('messages.home.select_both_dates') }}" data-past-message="{{ __('messages.home.past_dates') }}" data-start-label="{{ __('messages.reports.to') }}" data-end-label="{{ __('messages.reports.to') }}" data-placeholder="{{ __('messages.reports.from') }} / {{ __('messages.reports.to') }}">
                <label for="report-date-range-trigger"><span>{{ __('messages.reports.from') }} / {{ __('messages.reports.to') }}</span><button type="button" id="report-date-range-trigger" class="date-range-trigger" data-date-range-trigger aria-expanded="false"><span data-date-range-label>{{ $from->toDateString() }} → {{ $to->toDateString() }}</span></button></label>
                <input type="hidden" name="date_from" value="{{ $from->toDateString() }}" data-date-range-start required>
                <input type="hidden" name="date_to" value="{{ $to->toDateString() }}" data-date-range-end required>
                <div class="date-range-popover" data-date-range-popover hidden></div>
            </div>
            <div>
                <label for="establishment">{{ __('messages.common.establishment') }}</label>
                <select id="establishment" name="establishment">
                    <option value="">{{ __('messages.reports.all_establishments') }}</option>
                    @foreach ($establishments as $establishment)
                        <option value="{{ $establishment->id }}" @selected($selectedEstablishmentId === $establishment->id)>{{ $establishment->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="property">{{ __('messages.admin.property') }}</label>
                <select id="property" name="property">
                    <option value="">{{ __('messages.reports.all_properties') }}</option>
                    @foreach ($properties as $property)
                        <option value="{{ $property->id }}" @selected($selectedPropertyId === $property->id)>{{ $property->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="report-filter-actions">
                <button type="submit" class="btn btn-primary">{{ __('messages.reports.apply') }}</button>
                <a href="{{ route('admin.reports.index') }}" class="btn btn-ghost">{{ __('messages.reports.reset') }}</a>
            </div>
        </form>
    </x-card>

    <p class="report-period-note">
        {{ __('messages.reports.period_summary', [
            'from' => $from->translatedFormat('d M Y'),
            'to' => $to->translatedFormat('d M Y'),
            'days' => $report['period']['days'],
        ]) }}
    </p>

    <section id="operations" class="report-section">
        <div class="report-section-heading">
            <div>
                <span class="report-kicker">{{ __('messages.reports.operations') }}</span>
                <h2>{{ __('messages.reports.operations_heading') }}</h2>
            </div>
        </div>

        <div class="report-metric-grid">
            <article class="report-metric report-metric-primary"><span>{{ __('messages.reports.occupancy') }}</span><strong>{{ number_format($report['operations']['occupancy_rate'], 1, ',', ' ') }}%</strong><small>{{ __('messages.reports.occupied_sellable', ['occupied' => $report['operations']['occupied_nights'], 'sellable' => $report['operations']['sellable_nights']]) }}</small></article>
            <article class="report-metric"><span>{{ __('messages.reports.reservations') }}</span><strong>{{ $report['operations']['reservations'] }}</strong><small>{{ __('messages.reports.arrivals_departures', ['arrivals' => $report['operations']['arrivals'], 'departures' => $report['operations']['departures']]) }}</small></article>
            <article class="report-metric"><span>{{ __('messages.reports.average_stay') }}</span><strong>{{ number_format($report['operations']['average_stay'], 1, ',', ' ') }}</strong><small>{{ __('messages.reports.nights') }}</small></article>
            <article class="report-metric"><span>{{ __('messages.reports.guests') }}</span><strong>{{ $report['operations']['guests'] }}</strong><small>{{ __('messages.reports.total_travelers') }}</small></article>
            <article class="report-metric"><span>{{ __('messages.reports.cancellation_rate') }}</span><strong>{{ number_format($report['operations']['cancellation_rate'], 1, ',', ' ') }}%</strong><small>{{ trans_choice('messages.reports.cancellations_count', $report['operations']['cancellations'], ['count' => $report['operations']['cancellations']]) }}</small></article>
            <article class="report-metric"><span>{{ __('messages.reports.blocked_nights') }}</span><strong>{{ $report['operations']['blocked_nights'] }}</strong><small>{{ __('messages.reports.manual_external_blocks') }}</small></article>
        </div>

        <div class="report-two-column">
            <div class="report-panel">
                <h3>{{ __('messages.reports.status_breakdown') }}</h3>
                <div class="report-breakdown-list">
                    @forelse ($report['operations']['statuses'] as $status)
                        @php($statusShare = $report['operations']['reservations'] > 0 ? ($status['count'] / $report['operations']['reservations']) * 100 : 0)
                        <div class="report-breakdown-item">
                            <div><span>{{ __('messages.admin.status_' . $status['status']) }}</span><strong>{{ $status['count'] }}</strong></div>
                            <span class="report-bar"><span style="width: {{ min(100, $statusShare) }}%"></span></span>
                        </div>
                    @empty
                        <p class="empty-note">{{ __('messages.reports.no_data') }}</p>
                    @endforelse
                </div>
            </div>

            <div class="report-panel">
                <h3>{{ __('messages.reports.upcoming_arrivals') }}</h3>
                <div class="report-compact-list">
                    @forelse ($report['upcoming'] as $reservation)
                        <a href="{{ route('admin.reservations.show', $reservation) }}">
                            <span><strong>{{ $reservation->property?->name }}</strong><small>{{ $reservation->guest?->full_name ?? $reservation->email }}</small></span>
                            <time datetime="{{ $reservation->check_in->toDateString() }}">{{ $reservation->check_in->translatedFormat('d M') }}</time>
                        </a>
                    @empty
                        <p class="empty-note">{{ __('messages.reports.no_upcoming_arrivals') }}</p>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="report-two-column">
            <div class="report-panel">
                <h3>{{ __('messages.reports.booking_channels') }}</h3>
                <div class="report-compact-list">
                    @forelse ($report['operations']['channels'] as $channel)
                        <div class="report-summary-row"><span>{{ ucfirst($channel['source']) }}</span><strong>{{ $channel['count'] }}</strong></div>
                    @empty
                        <p class="empty-note">{{ __('messages.reports.no_data') }}</p>
                    @endforelse
                </div>
            </div>
            <div class="report-panel">
                <h3>{{ __('messages.reports.guest_origins') }}</h3>
                <div class="report-compact-list">
                    @forelse ($report['operations']['guest_origins'] as $origin)
                        <div class="report-summary-row"><span>{{ $origin['country'] }}</span><strong>{{ $origin['count'] }}</strong></div>
                    @empty
                        <p class="empty-note">{{ __('messages.reports.no_data') }}</p>
                    @endforelse
                </div>
            </div>
        </div>
    </section>

    <section id="finance" class="report-section report-section-band">
        <div class="report-section-heading">
            <div>
                <span class="report-kicker">{{ __('messages.reports.finance') }}</span>
                <h2>{{ __('messages.reports.finance_heading') }}</h2>
            </div>
        </div>

        <div class="report-metric-grid">
            <article class="report-metric report-metric-primary"><span>{{ __('messages.reports.collected_revenue') }}</span><strong>@include('admin.reports._money', ['items' => $report['finance']['collected']])</strong><small>{{ trans_choice('messages.reports.receipts_count', $report['finance']['receipts'], ['count' => $report['finance']['receipts']]) }}</small></article>
            <article class="report-metric"><span>{{ __('messages.reports.booked_value') }}</span><strong>@include('admin.reports._money', ['items' => $report['finance']['booked_value']])</strong><small>{{ __('messages.reports.confirmed_completed') }}</small></article>
            <article class="report-metric"><span>{{ __('messages.reports.outstanding') }}</span><strong>@include('admin.reports._money', ['items' => $report['finance']['outstanding']])</strong><small>{{ __('messages.reports.pending_payments') }}</small></article>
            <article class="report-metric"><span>{{ __('messages.reports.average_booking_value') }}</span><strong>@include('admin.reports._money', ['items' => $report['finance']['average_booking']])</strong><small>{{ __('messages.reports.confirmed_completed') }}</small></article>
            <article class="report-metric"><span>{{ __('messages.reports.taxes') }}</span><strong>@include('admin.reports._money', ['items' => $report['finance']['taxes']])</strong><small>{{ __('messages.reports.booked_breakdown') }}</small></article>
            <article class="report-metric"><span>{{ __('messages.reports.fees') }}</span><strong>@include('admin.reports._money', ['items' => $report['finance']['fees']])</strong><small>{{ __('messages.reports.booked_breakdown') }}</small></article>
        </div>

        <div class="report-panel">
            <div class="report-panel-heading">
                <h3>{{ __('messages.reports.payment_performance') }}</h3>
                <span>{{ __('messages.reports.success_rate') }}: <strong>{{ number_format($report['finance']['payment_success_rate'], 1, ',', ' ') }}%</strong></span>
            </div>
            <div class="admin-table-wrap" tabindex="0" role="region" aria-label="{{ __('messages.reports.payment_performance') }}">
                <table class="admin-table report-table">
                    <thead><tr><th>{{ __('messages.reports.provider') }}</th><th>{{ __('messages.reports.attempts') }}</th><th>{{ __('messages.reports.successful') }}</th><th>{{ __('messages.reports.collected_amount') }}</th></tr></thead>
                    <tbody>
                        @forelse ($report['finance']['providers'] as $provider)
                            <tr><td>{{ ucfirst($provider['provider']) }}</td><td>{{ $provider['attempts'] }}</td><td>{{ $provider['successful'] }}</td><td>@include('admin.reports._money', ['items' => $provider['amounts']])</td></tr>
                        @empty
                            <tr><td colspan="4" class="empty-state-inline">{{ __('messages.reports.no_payment_data') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <p class="report-limit-note">{{ __('messages.reports.financial_limit_note') }}</p>
    </section>

    <section id="performance" class="report-section">
        <div class="report-section-heading"><div><span class="report-kicker">{{ __('messages.reports.performance') }}</span><h2>{{ __('messages.reports.property_performance') }}</h2></div></div>
        <div class="admin-table-wrap" tabindex="0" role="region" aria-label="{{ __('messages.reports.property_performance') }}">
            <table class="admin-table report-table">
                <thead><tr><th>{{ __('messages.admin.property') }}</th><th>{{ __('messages.common.establishment') }}</th><th>{{ __('messages.reports.reservations') }}</th><th>{{ __('messages.reports.occupied_nights') }}</th><th>{{ __('messages.reports.booked_value') }}</th><th>{{ __('messages.reports.cancelled') }}</th></tr></thead>
                <tbody>
                    @forelse ($report['property_performance'] as $performance)
                        <tr>
                            <td><a class="inline-link" href="{{ route('admin.properties.edit', $performance['property']) }}">{{ $performance['property']->name }}</a></td>
                            <td>{{ $performance['property']->establishment?->name }}</td>
                            <td>{{ $performance['reservations'] }}</td>
                            <td>{{ $performance['occupied_nights'] }}</td>
                            <td>@include('admin.reports._money', ['items' => $performance['booked_value']])</td>
                            <td>{{ $performance['cancelled'] }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="empty-state-inline">{{ __('messages.reports.no_properties') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section id="quality" class="report-section report-section-band">
        <div class="report-section-heading"><div><span class="report-kicker">{{ __('messages.reports.quality') }}</span><h2>{{ __('messages.reports.quality_heading') }}</h2></div></div>
        <div class="report-metric-grid report-metric-grid-small">
            <article class="report-metric"><span>{{ __('messages.reports.average_rating') }}</span><strong>{{ $report['quality']['average_rating'] !== null ? number_format($report['quality']['average_rating'], 1, ',', ' ') . ' / 5' : '—' }}</strong><small>{{ trans_choice('messages.reports.reviews_count', $report['quality']['review_count'], ['count' => $report['quality']['review_count']]) }}</small></article>
            @foreach ($report['quality']['sources'] as $source => $count)
                <article class="report-metric"><span>{{ ucfirst($source) }}</span><strong>{{ $count }}</strong><small>{{ __('messages.reports.review_source') }}</small></article>
            @endforeach
        </div>
    </section>
@endsection
