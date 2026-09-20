<?php

namespace App\Http\Controllers;

use App\Models\Establishment;
use App\Models\Property;
use App\Services\ReportingService;
use App\Support\CurrentTenant;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminReportController extends Controller
{
    public function index(Request $request, ReportingService $reporting): View
    {
        return view('admin.reports.index', $this->reportContext($request, $reporting));
    }

    public function exportCsv(Request $request, ReportingService $reporting): StreamedResponse
    {
        $context = $this->reportContext($request, $reporting);
        $report = $context['report'];
        $filename = 'reports-' . $context['from']->toDateString() . '-' . $context['to']->toDateString() . '.csv';

        return response()->streamDownload(function () use ($report): void {
            $output = fopen('php://output', 'wb');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, [
                __('messages.reports.csv_section'),
                __('messages.reports.csv_metric'),
                __('messages.reports.csv_entity'),
                __('messages.reports.csv_currency'),
                __('messages.reports.csv_value'),
                __('messages.reports.csv_notes'),
            ], ';');

            foreach ($this->csvRows($report) as $row) {
                fputcsv($output, $row, ';');
            }

            fclose($output);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function reportContext(Request $request, ReportingService $reporting): array
    {
        $filters = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'establishment' => ['nullable', 'integer'],
            'property' => ['nullable', 'integer'],
        ]);

        $from = CarbonImmutable::parse($filters['date_from'] ?? now()->startOfMonth()->toDateString())->startOfDay();
        $to = CarbonImmutable::parse($filters['date_to'] ?? now()->endOfMonth()->toDateString())->endOfDay();

        if ($from->diffInDays($to) > 366) {
            throw ValidationException::withMessages([
                'date_to' => __('messages.reports.period_too_long'),
            ]);
        }

        $user = $request->user();
        $establishments = Establishment::query()
            ->when($user->isHost(), fn ($query) => $query->whereIn('id', $user->establishments()->pluck('establishments.id')))
            ->orderBy('name')
            ->get();
        $allowedEstablishmentIds = $establishments->pluck('id')->map(fn ($id) => (int) $id);
        $selectedEstablishmentId = isset($filters['establishment']) ? (int) $filters['establishment'] : null;

        if ($selectedEstablishmentId && ! $allowedEstablishmentIds->contains($selectedEstablishmentId)) {
            abort(403, __('messages.errors.establishment_manager_required'));
        }

        $properties = Property::query()
            ->whereHas('establishment', fn ($query) => $query->where('tenant_id', app(CurrentTenant::class)->id()))
            ->when($user->isHost(), fn ($query) => $query->whereIn('establishment_id', $allowedEstablishmentIds))
            ->when($selectedEstablishmentId, fn ($query) => $query->where('establishment_id', $selectedEstablishmentId))
            ->with(['establishment', 'availabilityBlocks', 'calendarFeeds.events'])
            ->orderBy('name')
            ->get();

        $selectedPropertyId = isset($filters['property']) ? (int) $filters['property'] : null;
        if ($selectedPropertyId && ! $properties->contains('id', $selectedPropertyId)) {
            abort(403, __('messages.errors.establishment_manager_required'));
        }

        $reportProperties = $selectedPropertyId ? $properties->where('id', $selectedPropertyId)->values() : $properties;
        $report = $reporting->build($reportProperties, $from, $to);

        return compact(
            'report',
            'establishments',
            'properties',
            'selectedEstablishmentId',
            'selectedPropertyId',
            'from',
            'to',
        );
    }

    private function csvRows(array $report): array
    {
        $rows = [];
        $add = function (string $section, string $metric, string $entity, string $currency, $value, string $notes = '') use (&$rows): void {
            $rows[] = [$section, $metric, $entity, $currency, $value, $notes];
        };

        $operations = $report['operations'];
        foreach ([
            'reservations' => $operations['reservations'],
            'occupied_nights' => $operations['occupied_nights'],
            'sellable_nights' => $operations['sellable_nights'],
            'blocked_nights' => $operations['blocked_nights'],
            'occupancy' => $operations['occupancy_rate'],
            'average_stay' => $operations['average_stay'],
            'guests' => $operations['guests'],
            'arrivals' => $operations['arrivals'],
            'departures' => $operations['departures'],
            'cancellations' => $operations['cancellations'],
            'cancellation_rate' => $operations['cancellation_rate'],
        ] as $metric => $value) {
            $add(__('messages.reports.operations'), __('messages.reports.csv_' . $metric), '', '', $value);
        }

        foreach ($operations['statuses'] as $status) {
            $add(__('messages.reports.operations'), __('messages.reports.status_breakdown'), __('messages.admin.status_' . $status['status']), '', $status['count']);
        }
        foreach ($operations['channels'] as $channel) {
            $add(__('messages.reports.operations'), __('messages.reports.booking_channels'), $channel['source'], '', $channel['count']);
        }
        foreach ($operations['guest_origins'] as $origin) {
            $add(__('messages.reports.operations'), __('messages.reports.guest_origins'), $origin['country'], '', $origin['count']);
        }

        foreach (['collected', 'booked_value', 'outstanding', 'subtotal', 'fees', 'taxes', 'average_booking'] as $metric) {
            foreach ($report['finance'][$metric] as $money) {
                $add(__('messages.reports.finance'), __('messages.reports.csv_' . $metric), '', $money['currency'], $money['amount']);
            }
        }
        foreach ([
            'receipts' => $report['finance']['receipts'],
            'payment_attempts' => $report['finance']['payment_attempts'],
            'successful_payments' => $report['finance']['successful_payments'],
            'payment_success_rate' => $report['finance']['payment_success_rate'],
        ] as $metric => $value) {
            $add(__('messages.reports.finance'), __('messages.reports.csv_' . $metric), '', '', $value);
        }
        foreach ($report['finance']['providers'] as $provider) {
            if ($provider['amounts']->isEmpty()) {
                $add(__('messages.reports.finance'), __('messages.reports.payment_performance'), $provider['provider'], '', $provider['successful'], __('messages.reports.csv_attempts_note', ['count' => $provider['attempts']]));
            }
            foreach ($provider['amounts'] as $money) {
                $add(__('messages.reports.finance'), __('messages.reports.payment_performance'), $provider['provider'], $money['currency'], $money['amount'], __('messages.reports.csv_provider_note', ['successful' => $provider['successful'], 'attempts' => $provider['attempts']]));
            }
        }

        foreach ($report['property_performance'] as $performance) {
            if ($performance['booked_value']->isEmpty()) {
                $add(__('messages.reports.performance'), __('messages.reports.property_performance'), $performance['property']->name, '', $performance['reservations'], __('messages.reports.csv_property_note', ['nights' => $performance['occupied_nights'], 'cancelled' => $performance['cancelled']]));
            }
            foreach ($performance['booked_value'] as $money) {
                $add(__('messages.reports.performance'), __('messages.reports.booked_value'), $performance['property']->name, $money['currency'], $money['amount'], __('messages.reports.csv_property_note', ['nights' => $performance['occupied_nights'], 'cancelled' => $performance['cancelled']]));
            }
        }

        $add(__('messages.reports.quality'), __('messages.reports.average_rating'), '', '', $report['quality']['average_rating'] ?? '');
        $add(__('messages.reports.quality'), __('messages.reports.csv_reviews'), '', '', $report['quality']['review_count']);
        foreach ($report['quality']['sources'] as $source => $count) {
            $add(__('messages.reports.quality'), __('messages.reports.review_source'), $source, '', $count);
        }

        return $rows;
    }
}
