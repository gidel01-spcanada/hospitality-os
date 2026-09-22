<?php

namespace App\Http\Controllers;

use App\Models\CleaningScheduleShare;
use App\Models\CleaningVisit;
use App\Models\Property;
use App\Models\Reservation;
use App\Support\CurrentTenant;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CleaningScheduleController extends Controller
{
    public function index(Request $request): View
    {
        $properties = $this->manageableProperties();
        [$mode, $reference, $from, $to] = $this->period($request);
        $propertyId = $request->integer('property') ?: null;
        if ($propertyId) {
            abort_unless($properties->contains('id', $propertyId), 403);
        }
        $assignees = CleaningVisit::query()
            ->whereIn('property_id', $properties->pluck('id'))
            ->whereNotNull('assignee_name')->distinct()->orderBy('assignee_name')->pluck('assignee_name');
        $assignee = trim($request->string('assignee')->toString()) ?: null;
        $status = $request->string('status')->toString() ?: null;
        if ($assignee && ! $assignees->containsStrict($assignee)) {
            throw ValidationException::withMessages(['assignee' => __('messages.cleaning.unknown_assignee')]);
        }

        abort_unless($status === null || in_array($status, ['scheduled', 'in_progress', 'completed', 'cancelled'], true), 422);
        $visits = $this->visitQuery($properties, $from, $to, $propertyId, $assignee, $status)->get();
        $shares = CleaningScheduleShare::query()
            ->where('tenant_id', app(CurrentTenant::class)->id())
            ->when(auth()->user()->isHost(), fn ($query) => $query->where('created_by', auth()->id()))
            ->latest()->limit(10)->get();

        return view('admin.cleaning.index', compact(
            'properties', 'visits', 'shares', 'mode',
            'reference', 'from', 'to', 'propertyId', 'assignees', 'assignee', 'status',
        ));
    }

    public function create(Request $request): View
    {
        $properties = $this->manageableProperties();
        [$mode, $reference, $from, $to] = $this->period($request);
        $propertyId = $request->integer('property') ?: null;
        $assignee = trim($request->string('assignee')->toString()) ?: null;
        $reservations = $this->availableReservations($properties, $from, $to);

        return view('admin.cleaning.create', compact(
            'properties', 'reservations', 'mode', 'reference', 'propertyId', 'assignee',
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateVisit($request);
        $property = $this->ownedProperty((int) $validated['property_id']);
        $this->validateReservationProperty($validated['reservation_id'] ?? null, $property);
        $this->ensureAssigneeAvailable($validated);

        CleaningVisit::query()->create($validated + ['created_by' => auth()->id()]);

        return $this->scheduleRedirect($request)->with('success', __('messages.cleaning.created'));
    }

    public function generateForm(Request $request): View
    {
        $properties = $this->manageableProperties();
        [$mode, $reference] = $this->period($request);
        $propertyId = $request->integer('property') ?: null;
        $assignee = trim($request->string('assignee')->toString()) ?: null;

        return view('admin.cleaning.generate', compact('mode', 'reference', 'propertyId', 'assignee'));
    }

    public function generateFromDepartures(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'assignee_name' => ['required', 'string', 'max:120'],
            'scheduled_time' => ['required', 'date_format:H:i'],
            'duration_minutes' => ['required', 'integer', 'min:15', 'max:1440'],
            'instructions' => ['nullable', 'string', 'max:2000'],
        ]);
        $properties = $this->manageableProperties();
        [$mode, $reference, $from, $to] = $this->period($request);
        $propertyId = $request->integer('property') ?: null;
        if ($propertyId) {
            abort_unless($properties->contains('id', $propertyId), 403);
            $properties = $properties->where('id', $propertyId)->values();
        }

        $existingReservationIds = CleaningVisit::query()
            ->whereNotNull('reservation_id')->pluck('reservation_id');
        $departures = Reservation::query()
            ->whereIn('property_id', $properties->pluck('id'))
            ->whereIn('status', ['confirmed', 'checked_in', 'completed'])
            ->whereBetween('check_out', [$from->toDateString(), $to->toDateString()])
            ->whereNotIn('id', $existingReservationIds)
            ->orderBy('check_out')->orderBy('property_id')
            ->get();
        [$hour, $minute] = array_map('intval', explode(':', $validated['scheduled_time']));

        foreach ($departures as $reservation) {
            $scheduledAt = $this->nextAvailableSlot(
                $validated['assignee_name'],
                $reservation->check_out->copy()->setTime($hour, $minute),
                (int) $validated['duration_minutes'],
            );
            CleaningVisit::query()->create([
                'property_id' => $reservation->property_id,
                'reservation_id' => $reservation->id,
                'created_by' => auth()->id(),
                'assignee_name' => $validated['assignee_name'],
                'scheduled_at' => $scheduledAt,
                'duration_minutes' => $validated['duration_minutes'],
                'status' => 'scheduled',
                'instructions' => $validated['instructions'] ?? null,
            ]);
        }

        return $this->scheduleRedirect($request)->with(
            'success',
            trans_choice('messages.cleaning.generated', $departures->count(), ['count' => $departures->count()]),
        );
    }

    public function update(Request $request, CleaningVisit $visit): RedirectResponse
    {
        $this->authorizeVisit($visit);
        $validated = $this->validateVisit($request);
        $property = $this->ownedProperty((int) $validated['property_id']);
        $this->validateReservationProperty($validated['reservation_id'] ?? null, $property);
        $this->ensureAssigneeAvailable($validated, $visit->id);
        $visit->update($validated + $this->executionTimestamps($visit, $validated['status']));

        return $this->scheduleRedirect($request)->with('success', __('messages.cleaning.updated'));
    }

    public function edit(CleaningVisit $visit): View
    {
        $this->authorizeVisit($visit);
        $properties = $this->manageableProperties();
        $reservations = Reservation::query()
            ->whereIn('property_id', $properties->pluck('id'))
            ->where(function ($query) use ($visit): void {
                $query->whereIn('status', ['confirmed', 'checked_in', 'completed']);
                if ($visit->reservation_id) {
                    $query->orWhereKey($visit->reservation_id);
                }
            })
            ->with('property')->orderBy('check_out')->get();

        return view('admin.cleaning.edit', compact('visit', 'properties', 'reservations'));
    }

    public function destroy(Request $request, CleaningVisit $visit): RedirectResponse
    {
        $this->authorizeVisit($visit);
        $visit->delete();

        return $this->scheduleRedirect($request)->with('success', __('messages.cleaning.deleted'));
    }

    public function share(Request $request): RedirectResponse
    {
        $shareOptions = $request->validate([
            'expires_in_days' => ['required', 'integer', Rule::in([7, 30, 90])],
            'assignee' => ['nullable', 'string', 'max:120'],
        ]);
        $properties = $this->manageableProperties();
        [$mode, $reference, $from, $to] = $this->period($request);
        $propertyId = $request->integer('property') ?: null;
        if ($propertyId) {
            abort_unless($properties->contains('id', $propertyId), 403);
            $properties = $properties->where('id', $propertyId)->values();
        }
        $assignee = trim((string) ($shareOptions['assignee'] ?? '')) ?: null;
        if ($assignee) {
            $exists = CleaningVisit::query()
                ->whereIn('property_id', $properties->pluck('id'))
                ->where('assignee_name', $assignee)->exists();
            if (! $exists) {
                throw ValidationException::withMessages(['assignee' => __('messages.cleaning.unknown_assignee')]);
            }
        }

        $share = CleaningScheduleShare::query()->create([
            'tenant_id' => app(CurrentTenant::class)->id(),
            'created_by' => auth()->id(),
            'token' => bin2hex(random_bytes(32)),
            'period_start' => $from,
            'period_end' => $to,
            'view_mode' => $mode,
            'property_ids' => $properties->pluck('id')->all(),
            'assignee_name' => $assignee,
            'expires_at' => now()->addDays((int) $shareOptions['expires_in_days']),
        ]);

        return $this->scheduleRedirect($request)
            ->with('success', __('messages.cleaning.share_created'))
            ->with('cleaning_share_url', route('cleaning.public', $share->token));
    }

    public function revoke(CleaningScheduleShare $share): RedirectResponse
    {
        abort_unless($share->tenant_id === app(CurrentTenant::class)->id(), 404);
        abort_if(auth()->user()->isHost() && $share->created_by !== auth()->id(), 403);
        $share->delete();

        return back()->with('success', __('messages.cleaning.share_revoked'));
    }

    public function publicSchedule(string $token): View
    {
        return view('cleaning.schedule', $this->publicContext($token));
    }

    public function publicPdf(string $token): Response
    {
        $context = $this->publicContext($token);

        return Pdf::loadView('cleaning.pdf', $context)
            ->setPaper('a4', 'landscape')
            ->download('planning-menage-'.$context['share']->period_start->format('Y-m-d').'.pdf');
    }

    public function publicStatus(Request $request, string $token, CleaningVisit $visit): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['in_progress', 'completed'])],
        ]);
        $context = $this->publicContext($token);
        $propertyIds = $context['properties']->pluck('id');
        abort_unless($propertyIds->contains($visit->property_id), 404);
        abort_if($context['share']->assignee_name && $visit->assignee_name !== $context['share']->assignee_name, 404);
        abort_unless(
            $visit->scheduled_at->betweenIncluded(
                $context['share']->period_start->startOfDay(),
                $context['share']->period_end->endOfDay(),
            ),
            404,
        );

        $visit->update([
            'status' => $validated['status'],
            ...$this->executionTimestamps($visit, $validated['status']),
        ]);

        return redirect()->route('cleaning.public', $token)
            ->with('cleaning_status_updated', __('messages.cleaning.field_status_updated'));
    }

    private function publicContext(string $token): array
    {
        $share = CleaningScheduleShare::query()->where('token', $token)->firstOrFail();
        abort_if($share->expires_at?->isPast(), 404);
        $properties = Property::query()
            ->whereIn('id', $share->property_ids)
            ->whereHas('establishment', fn ($query) => $query->where('tenant_id', $share->tenant_id))
            ->with('establishment')->get();
        $visits = $this->visitQuery(
            $properties,
            $share->period_start->toImmutable(),
            $share->period_end->toImmutable(),
            null,
            $share->assignee_name,
        )->get();

        return compact('share', 'properties', 'visits');
    }

    private function manageableProperties(): Collection
    {
        $user = auth()->user();

        return Property::query()
            ->whereHas('establishment', fn ($query) => $query->where('tenant_id', app(CurrentTenant::class)->id()))
            ->when($user->isHost(), fn ($query) => $query->whereIn('establishment_id', $user->establishments()->pluck('establishments.id')))
            ->with('establishment')->orderBy('name')->get();
    }

    private function ownedProperty(int $id): Property
    {
        return $this->manageableProperties()->firstWhere('id', $id) ?? abort(403);
    }

    private function authorizeVisit(CleaningVisit $visit): void
    {
        abort_unless($this->manageableProperties()->contains('id', $visit->property_id), 404);
    }

    private function validateVisit(Request $request): array
    {
        return $request->validate([
            'property_id' => ['required', 'integer'],
            'reservation_id' => ['nullable', 'integer', 'exists:reservations,id'],
            'assignee_name' => ['required', 'string', 'max:120'],
            'scheduled_at' => ['required', 'date'],
            'duration_minutes' => ['required', 'integer', 'min:15', 'max:1440'],
            'status' => ['required', Rule::in(['scheduled', 'in_progress', 'completed', 'cancelled'])],
            'instructions' => ['nullable', 'string', 'max:2000'],
        ]);
    }

    private function validateReservationProperty(?int $reservationId, Property $property): void
    {
        if ($reservationId) {
            abort_unless(Reservation::query()->whereKey($reservationId)->where('property_id', $property->id)->exists(), 422);
        }
    }

    private function availableReservations(Collection $properties, CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        return Reservation::query()
            ->whereIn('property_id', $properties->pluck('id'))
            ->whereIn('status', ['confirmed', 'checked_in', 'completed'])
            ->whereDate('check_out', '>=', $from->subDays(7)->toDateString())
            ->whereDate('check_out', '<=', $to->addDays(7)->toDateString())
            ->with('property')->orderBy('check_out')->get();
    }

    private function executionTimestamps(CleaningVisit $visit, string $status): array
    {
        return match ($status) {
            'scheduled' => ['started_at' => null, 'completed_at' => null],
            'in_progress' => ['started_at' => $visit->started_at ?? now(), 'completed_at' => null],
            'completed' => ['started_at' => $visit->started_at ?? now(), 'completed_at' => now()],
            default => [],
        };
    }

    private function ensureAssigneeAvailable(array $visit, ?int $ignoreVisitId = null): void
    {
        $start = Carbon::parse($visit['scheduled_at']);
        $end = $start->copy()->addMinutes((int) $visit['duration_minutes']);

        if ($this->overlappingAssigneeVisits($visit['assignee_name'], $start, $end, $ignoreVisitId)->isNotEmpty()) {
            throw ValidationException::withMessages([
                'scheduled_at' => __('messages.cleaning.assignee_conflict'),
            ]);
        }
    }

    private function nextAvailableSlot(string $assignee, Carbon $start, int $durationMinutes): Carbon
    {
        $candidate = $start->copy();

        while (true) {
            $end = $candidate->copy()->addMinutes($durationMinutes);
            $conflict = $this->overlappingAssigneeVisits($assignee, $candidate, $end)->sortBy('scheduled_at')->first();
            if (! $conflict) {
                return $candidate;
            }

            $candidate = $conflict->scheduled_at->copy()->addMinutes($conflict->duration_minutes);
        }
    }

    private function overlappingAssigneeVisits(string $assignee, Carbon $start, Carbon $end, ?int $ignoreVisitId = null): Collection
    {
        $normalizedAssignee = mb_strtolower(trim($assignee));

        return CleaningVisit::query()
            ->where('status', '!=', 'cancelled')
            ->when($ignoreVisitId, fn ($query) => $query->whereKeyNot($ignoreVisitId))
            ->whereBetween('scheduled_at', [$start->copy()->subDay(), $end])
            ->whereHas('property.establishment', fn ($query) => $query->where('tenant_id', app(CurrentTenant::class)->id()))
            ->get()
            ->filter(function (CleaningVisit $visit) use ($normalizedAssignee, $start, $end): bool {
                $visitEnd = $visit->scheduled_at->copy()->addMinutes($visit->duration_minutes);

                return mb_strtolower(trim($visit->assignee_name)) === $normalizedAssignee
                    && $visit->scheduled_at->lt($end)
                    && $visitEnd->gt($start);
            });
    }

    private function visitQuery(Collection $properties, CarbonImmutable $from, CarbonImmutable $to, ?int $propertyId = null, ?string $assignee = null, ?string $status = null)
    {
        return CleaningVisit::query()
            ->whereIn('property_id', $properties->pluck('id'))
            ->when($propertyId, fn ($query) => $query->where('property_id', $propertyId))
            ->when($assignee, fn ($query) => $query->where('assignee_name', $assignee))
            ->when($status, fn ($query) => $query->where('status', $status))
            ->whereBetween('scheduled_at', [$from->startOfDay(), $to->endOfDay()])
            ->with(['property.establishment', 'reservation'])
            ->orderBy('scheduled_at');
    }

    private function period(Request $request): array
    {
        $validated = $request->validate([
            'mode' => ['nullable', Rule::in(['week', 'month'])],
            'date' => ['nullable', 'date'],
            'property' => ['nullable', 'integer'],
        ]);
        $mode = $validated['mode'] ?? 'week';
        $reference = CarbonImmutable::parse($validated['date'] ?? now()->toDateString());
        $from = $mode === 'month' ? $reference->startOfMonth() : $reference->startOfWeek();
        $to = $mode === 'month' ? $reference->endOfMonth() : $reference->endOfWeek();

        return [$mode, $reference, $from, $to];
    }

    private function scheduleRedirect(Request $request): RedirectResponse
    {
        return redirect()->route('admin.cleaning.index', array_filter([
            'mode' => $request->input('mode'),
            'date' => $request->input('date'),
            'property' => $request->input('property'),
            'assignee' => $request->input('assignee'),
            'status' => $request->input('status'),
        ]));
    }
}