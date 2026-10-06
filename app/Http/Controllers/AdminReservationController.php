<?php

namespace App\Http\Controllers;

use App\Models\MessageThread;
use App\Models\Reservation;
use App\Models\ReservationGuest;
use App\Models\ReservationPriceLine;
use App\Models\Property;
use App\Models\User;
use App\Services\AvailabilityService;
use App\Services\PricingCalculator;
use App\Services\PaymentProofService;
use App\Services\ReservationEmailService;
use App\Support\CurrentTenant;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AdminReservationController extends Controller
{
    public function create(Request $request): View
    {
        $propertyId = $request->integer('property_id');
        $property = $propertyId ? $this->ownedProperty($propertyId) : null;

        return view('admin.reservations.form', [
            'reservation' => new Reservation(['property_id' => $property?->id, 'adults' => 1, 'children' => 0, 'infants' => 0]),
            'properties' => $this->tenantProperties(),
        ]);
    }

    public function store(Request $request, AvailabilityService $availabilityService, ReservationEmailService $emailService): RedirectResponse
    {
        $validated = $this->validateReservation($request);
        $property = $this->ownedProperty($validated['property_id']);
        $checkIn = Carbon::parse($validated['check_in']);
        $checkOut = Carbon::parse($validated['check_out']);
        $ignoreExternalConflicts = $request->boolean('ignore_external_calendar_conflicts');

        $this->ensureReservationRules($property, $checkIn, $checkOut, $validated);
        $this->authorizeExternalCalendarOverride($ignoreExternalConflicts);
        $externalConflictOverridden = $ignoreExternalConflicts
            && $availabilityService->hasExternalCalendarConflict($property, $checkIn, $checkOut);
        if (! $availabilityService->isAvailable($property, $checkIn, $checkOut, null, $ignoreExternalConflicts)) {
            throw ValidationException::withMessages([
                'check_in' => __('messages.errors.property_unavailable'),
            ]);
        }
        if ($externalConflictOverridden) {
            $validated['notes'] = $this->appendExternalCalendarOverrideAudit($validated['notes'] ?? null);
        }

        $reservation = $this->persistReservation($validated, $property, $checkIn, $checkOut);
        $emailService->queueForReservation($reservation, 'reservation_created');

        return redirect()->route('admin.reservations.show', $reservation)->with('success', __('messages.flash.reservation_created_email_queued'));
    }

    public function edit(Reservation $reservation): View
    {
        $this->ownedReservation($reservation);
        $reservation->load('guest');

        return view('admin.reservations.form', [
            'reservation' => $reservation,
            'properties' => $this->tenantProperties(),
        ]);
    }

    public function update(Request $request, Reservation $reservation, AvailabilityService $availabilityService): RedirectResponse
    {
        $this->ownedReservation($reservation);
        $validated = $this->validateReservation($request, $reservation);
        $property = $this->ownedProperty($validated['property_id']);
        $checkIn = Carbon::parse($validated['check_in']);
        $checkOut = Carbon::parse($validated['check_out']);
        $ignoreExternalConflicts = $request->boolean('ignore_external_calendar_conflicts');

        $this->ensureReservationRules($property, $checkIn, $checkOut, $validated);
        $this->authorizeExternalCalendarOverride($ignoreExternalConflicts);
        $externalConflictOverridden = $ignoreExternalConflicts
            && $availabilityService->hasExternalCalendarConflict($property, $checkIn, $checkOut);
        abort_unless(
            $availabilityService->isAvailable($property, $checkIn, $checkOut, $reservation->id, $ignoreExternalConflicts),
            422,
            __('messages.errors.property_unavailable'),
        );
        if ($externalConflictOverridden) {
            $validated['notes'] = $this->appendExternalCalendarOverrideAudit($validated['notes'] ?? null);
        }

        $guest = $reservation->guest ?: new ReservationGuest();
        $guest->fill([
            'full_name' => $validated['full_name'],
            'email' => strtolower($validated['email']),
            'phone' => $validated['phone'] ?? null,
            'country' => $validated['country'] ?? null,
        ])->save();

        $pricing = $this->calculatePricing($property, $checkIn, $checkOut, (int) $validated['adults'], (int) ($validated['children'] ?? 0));

        DB::transaction(function () use ($reservation, $property, $guest, $validated, $checkIn, $checkOut, $pricing): void {
            $reservation->update([
                'property_id' => $property->id,
                'guest_id' => $guest->id,
                'check_in' => $checkIn->toDateString(),
                'check_out' => $checkOut->toDateString(),
                'adults' => $validated['adults'],
                'children' => $validated['children'] ?? 0,
                'infants' => $validated['infants'] ?? 0,
                'currency' => $pricing['currency'],
                'email' => strtolower($validated['email']),
                'locale' => $validated['locale'] ?? null,
                'subtotal' => $pricing['subtotal'],
                'fees' => $pricing['fees'],
                'taxes' => $pricing['taxes'],
                'total_amount' => $pricing['total_amount'],
                'notes' => $validated['notes'] ?? null,
                'customer_note' => $validated['customer_note'] ?? null,
            ]);

            $reservation->priceLines()->delete();
            ReservationPriceLine::query()->insert($this->priceLines($reservation, $pricing));
        });

        return redirect()->route('admin.reservations.show', $reservation)->with('success', 'Reservation updated successfully.');
    }

    public function index(Request $request): View
    {
        $user = auth()->user();
        $viewOptions = $request->validate([
            'view' => ['nullable', 'in:list,calendar'],
            'month' => ['nullable', 'date_format:Y-m'],
        ]);
        $viewMode = $viewOptions['view'] ?? 'list';
        $calendarMonth = Carbon::createFromFormat('!Y-m', $viewOptions['month'] ?? now()->format('Y-m'));
        $calendarStart = $calendarMonth->copy()->startOfWeek(Carbon::MONDAY);
        $calendarEnd = $calendarMonth->copy()->endOfMonth()->endOfWeek(Carbon::SUNDAY);

        $reservationQuery = Reservation::query()
            ->whereHas('property.establishment', fn ($query) => $query->where('tenant_id', app(CurrentTenant::class)->id()))
            ->when($user && $user->isHost(), fn ($query) => $query->whereHas('property', fn ($q) => $q->whereIn('establishment_id', $user->establishments()->pluck('establishments.id'))))
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = '%' . $request->string('search')->trim() . '%';

                $query->where(function ($query) use ($search): void {
                    $query->where('reservation_ref', 'like', $search)
                        ->orWhere('email', 'like', $search)
                        ->orWhereHas('property', fn ($property) => $property->where('name', 'like', $search))
                        ->orWhereHas('guest', fn ($guest) => $guest->where('full_name', 'like', $search));
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->when($request->filled('date_from'), fn ($query) => $query->whereDate('check_in', '>=', $request->date('date_from')))
            ->when($request->filled('date_to'), fn ($query) => $query->whereDate('check_out', '<=', $request->date('date_to')));

        $reservations = (clone $reservationQuery)
            ->with(['property', 'guest'])
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $calendarDays = collect();
        $calendarReservations = collect();
        if ($viewMode === 'calendar') {
            for ($day = $calendarStart->copy(); $day->lte($calendarEnd); $day->addDay()) {
                $calendarDays->push($day->copy());
            }

            $calendarReservations = (clone $reservationQuery)
                ->with(['property', 'guest'])
                ->whereDate('check_in', '<=', $calendarEnd->toDateString())
                ->whereDate('check_out', '>', $calendarStart->toDateString())
                ->orderBy('check_in')
                ->get();
        }

        return view('admin.bookings.index', compact('reservations', 'calendarReservations', 'calendarDays', 'calendarMonth', 'viewMode') + ['bookings' => $reservations]);
    }

    public function show(Reservation $reservation): View
    {
        $this->ownedReservation($reservation);

        $reservation->load(['property.establishment', 'guest', 'user', 'priceLines', 'paymentAttempts', 'receipts', 'internalNotes.user']);
        $customerThread = $this->ensureCustomerThread($reservation);
        $overview = \App\Support\ReservationAdminOverview::make($reservation);
        $user = auth()->user();
        $customerReservations = Reservation::query()
            ->with('property')
            ->whereKeyNot($reservation->id)
            ->where(fn ($query) => $query->where('email', $reservation->email)->when($reservation->user_id, fn ($inner) => $inner->orWhere('user_id', $reservation->user_id)))
            ->whereHas('property.establishment', fn ($query) => $query->where('tenant_id', app(CurrentTenant::class)->id()))
            ->when($user->isHost(), fn ($query) => $query->whereHas('property', fn ($inner) => $inner->whereIn('establishment_id', $user->establishments()->pluck('establishments.id'))))
            ->latest('check_in')
            ->get();

        return view('admin.reservations.show', compact('reservation', 'customerThread', 'customerReservations') + $overview);
    }

    public function storeInternalNote(Request $request, Reservation $reservation): RedirectResponse
    {
        $this->ownedReservation($reservation);
        $validated = $request->validate(['body' => ['required', 'string', 'max:5000']]);
        $reservation->internalNotes()->create(['user_id' => auth()->id(), 'body' => trim($validated['body'])]);

        return redirect()->to(route('admin.reservations.show', $reservation) . '#reservation-notes')
            ->with('status', __('messages.admin.internal_note_added'));
    }

    public function validatePayment(Reservation $reservation, \App\Models\PaymentAttempt $attempt, ReservationEmailService $emailService): RedirectResponse
    {
        $this->ownedReservation($reservation);
        abort_unless((int) $attempt->reservation_id === (int) $reservation->id, 404);

        if (! $this->validateSubmittedProof($reservation, $attempt)) {
            return redirect()->route('admin.reservations.show', $reservation)->with('status', __('messages.receipts.no_paid_attempt'));
        }
        if (in_array($reservation->status, ['pending', 'pending_payment', 'pending_validation', 'payment_failed'], true)) {
            $reservation->update(['status' => 'confirmed']);
            $emailService->queueStatusUpdate($reservation, 'confirmed');
        }
        $emailService->issueReceiptAndQueueEmail($reservation, $attempt->fresh(), auth()->user()?->email);

        return redirect()->route('admin.reservations.show', $reservation)
            ->with('status', __('messages.admin.payment_validated'));
    }

    private function ensureCustomerThread(Reservation $reservation): ?MessageThread
    {
        $customerUser = $reservation->user ?? User::query()->firstOrCreate(
            ['email' => strtolower((string) $reservation->email)],
            [
                'name' => $reservation->guest?->full_name ?? $reservation->email,
                'password' => bcrypt(Str::random(24)),
                'role' => 'customer',
                'tenant_id' => $reservation->property?->establishment?->tenant_id,
                'locale' => app()->getLocale(),
                'email_booking_updates' => true,
                'email_message_updates' => true,
            ]
        );

        if ($reservation->user_id !== $customerUser->id) {
            $reservation->update(['user_id' => $customerUser->id]);
        }

        $establishmentId = $reservation->property?->establishment_id;
        if (! $establishmentId) {
            return null;
        }

        return MessageThread::query()->firstOrCreate([
            'customer_id' => $customerUser->id,
            'establishment_id' => $establishmentId,
        ]);
    }

    public function destroy(Reservation $reservation): RedirectResponse
    {
        abort_unless(auth()->user()?->isAdmin(), 403, __('messages.errors.admin_required'));
        $this->ownedReservation($reservation);
        $reservation->load(['paymentAttempts', 'guest']);

        DB::transaction(function () use ($reservation): void {
            foreach ($reservation->paymentAttempts as $attempt) {
                app(PaymentProofService::class)->delete($reservation, $attempt);
            }

            $guest = $reservation->guest;
            $reservation->delete();

            if ($guest && ! $guest->reservations()->exists()) {
                $guest->delete();
            }
        });

        Log::notice('Reservation deleted by administrator', ['reservation_id' => $reservation->id, 'reservation_ref' => $reservation->reservation_ref, 'deleted_by' => auth()->id()]);

        return redirect()->route('admin.reservations.index')->with('status', __('messages.flash.reservation_deleted'));
    }

    public function uploadPaymentProof(Request $request, Reservation $reservation): RedirectResponse
    {
        $this->ownedReservation($reservation);
        $validated = $request->validate([
            'payment_proof' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
        ]);

        $attempt = $reservation->paymentAttempts()->latest()->first();
        if (! $attempt) {
            $attempt = $reservation->paymentAttempts()->create([
                'provider' => 'offline',
                'provider_reference' => 'offline-' . Str::lower(Str::random(12)),
                'currency' => $reservation->currency,
                'amount' => $reservation->total_amount,
                'status' => 'created',
                'idempotency_key' => 'offline-' . $reservation->id . '-' . now()->format('YmdHis'),
                'payload' => [],
            ]);
        }

        app(PaymentProofService::class)->attach($reservation, $attempt, $validated['payment_proof'], auth()->id());

        return back()->with('status', __('messages.receipts.proof_uploaded'));
    }

    public function updateStatus(Request $request, Reservation $reservation, ReservationEmailService $emailService): RedirectResponse
    {
        $this->ownedReservation($reservation);

        $validated = $request->validate([
            'status' => ['required', 'string', 'in:pending,pending_payment,pending_validation,confirmed,checked_in,completed,cancelled'],
            'notes' => ['nullable', 'string', 'max:500'],
            'payment_proof' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
            'refund_proof' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
            'refund_reference' => ['nullable', 'string', 'max:120'],
        ]);

        $oldStatus = $reservation->status;
        $newStatus = $validated['status'];
        $refundableAttempts = $newStatus === 'cancelled' && $oldStatus !== 'cancelled' ? $this->refundableOfflineAttempts($reservation) : collect();
        if ($refundableAttempts->isNotEmpty() && ! isset($validated['refund_proof'])) {
            throw \Illuminate\Validation\ValidationException::withMessages(['refund_proof' => __('messages.receipts.refund_proof_required')]);
        }

        $reservation->update([
            'status' => $newStatus,
        ]);

        if (filled($validated['notes'] ?? null)) {
            $reservation->internalNotes()->create(['user_id' => auth()->id(), 'body' => trim($validated['notes'])]);
        }

        if (isset($validated['payment_proof'])) {
            $attempt = $reservation->paymentAttempts()->latest()->first();
            if (! $attempt) {
                $attempt = $reservation->paymentAttempts()->create([
                    'provider' => 'offline',
                    'provider_reference' => 'offline-' . Str::lower(Str::random(12)),
                    'currency' => $reservation->currency,
                    'amount' => $reservation->total_amount,
                    'status' => 'created',
                    'idempotency_key' => 'offline-' . $reservation->id . '-' . now()->format('YmdHis'),
                    'payload' => [],
                ]);
            }

            app(PaymentProofService::class)->attach($reservation, $attempt, $validated['payment_proof'], auth()->id());
        }

        foreach ($refundableAttempts as $attempt) {
            app(PaymentProofService::class)->attach($reservation, $attempt, $validated['refund_proof'], auth()->id(), [], PaymentProofService::REFUND);
            $attempt->update([
                'status' => 'refunded',
                'payload' => array_merge((array) $attempt->fresh()->payload, array_filter([
                    'refunded_by' => auth()->id(),
                    'refunded_at' => now()->toIso8601String(),
                    'refund_reference' => filled($validated['refund_reference'] ?? null) ? trim($validated['refund_reference']) : null,
                ])),
            ]);
        }

        if ($oldStatus !== $newStatus) {
            $emailService->queueForReservation($reservation, 'reservation_status_updated');
        }

        $paidStatuses = ['confirmed', 'checked_in', 'completed'];
        if (in_array($newStatus, $paidStatuses, true) && ! in_array($oldStatus, $paidStatuses, true)
            && ($attempt = $this->validateSubmittedProof($reservation))) {
            $emailService->issueReceiptAndQueueEmail($reservation, $attempt, auth()->user()?->email);
        }

        return redirect()->route('admin.reservations.show', $reservation)
            ->with('status', __('messages.flash.reservation_status_updated'));
    }

    public function updateNotes(Request $request, Reservation $reservation): RedirectResponse
    {
        $this->ownedReservation($reservation);
        $validated = $request->validate(['notes' => ['nullable', 'string', 'max:5000']]);
        $reservation->update(['notes' => $validated['notes'] ?? null]);

        return redirect()->route('admin.reservations.show', $reservation)
            ->with('status', __('messages.flash.reservation_notes_saved'));
    }

    public function sendPaymentLink(Reservation $reservation, ReservationEmailService $emailService): RedirectResponse
    {
        $this->ownedReservation($reservation);
        abort_unless($reservation->canSendPaymentLink(), 403);

        $emailService->queuePaymentLink($reservation);

        return redirect()->route('admin.reservations.show', $reservation)
            ->with('status', __('messages.flash.payment_link_queued'));
    }

    public function generateReceipt(Request $request, Reservation $reservation, ReservationEmailService $emailService): RedirectResponse
    {
        $this->ownedReservation($reservation);
        $validated = $request->validate(['provider_reference' => ['nullable', 'string', 'max:120']]);

        $attempt = $reservation->paymentAttempts()->whereIn('status', ['paid', 'completed'])->latest('id')->get()
            ->first(fn ($attempt) => ! data_get($attempt->payload, 'is_guarantee'));
        if (! $attempt && ($attempt = $this->validateSubmittedProof($reservation))
            && in_array($reservation->status, ['pending', 'pending_payment', 'pending_validation', 'payment_failed'], true)) {
            $reservation->update(['status' => 'confirmed']);
            $emailService->queueStatusUpdate($reservation, 'confirmed');
        }

        if (! $attempt) {
            return redirect()->route('admin.reservations.show', $reservation)
                ->with('status', __('messages.receipts.no_paid_attempt'));
        }

        if (filled($validated['provider_reference'] ?? null)) {
            $attempt->update(['provider_reference' => trim($validated['provider_reference'])]);
        }

        $emailService->issueReceiptAndQueueEmail($reservation, $attempt, auth()->user()?->email);

        return redirect()->route('admin.reservations.show', $reservation)
            ->with('status', __('messages.receipts.generated_and_sent'));
    }

    public function updatePaymentReference(Request $request, Reservation $reservation, \App\Models\PaymentAttempt $attempt): RedirectResponse
    {
        $this->ownedReservation($reservation);
        abort_unless((int) $attempt->reservation_id === (int) $reservation->id, 404);
        $validated = $request->validate(['provider_reference' => ['required', 'string', 'max:120']]);

        $attempt->update([
            'provider_reference' => trim($validated['provider_reference']),
            'payload' => array_merge((array) $attempt->payload, ['reference_updated_by' => auth()->id(), 'reference_updated_at' => now()->toIso8601String()]),
        ]);

        return redirect()->route('admin.reservations.show', $reservation)
            ->with('status', __('messages.receipts.reference_updated'));
    }

    private function refundableOfflineAttempts(Reservation $reservation): \Illuminate\Support\Collection
    {
        return $reservation->paymentAttempts()->whereIn('status', ['paid', 'completed'])
            ->whereIn('provider', PaymentProofService::REFUND_PROOF_PROVIDERS)->get()
            ->reject(fn ($attempt) => (bool) data_get($attempt->payload, 'is_guarantee'))
            ->values();
    }

    /** Generating a receipt or confirming the stay is the staff validation of the guest's submitted proof. */
    private function validateSubmittedProof(Reservation $reservation, ?\App\Models\PaymentAttempt $only = null): ?\App\Models\PaymentAttempt
    {
        $attempt = $reservation->paymentAttempts()->whereIn('status', ['awaiting_validation', 'pending_validation'])
            ->when($only, fn ($query) => $query->whereKey($only->id))
            ->latest('id')->get()
            ->first(fn ($attempt) => data_get($attempt->payload, 'payment_proof.path') && ! data_get($attempt->payload, 'is_guarantee'));

        $attempt?->update([
            'status' => 'paid',
            'payload' => array_merge((array) $attempt->payload, ['confirmed_by' => auth()->id(), 'confirmed_at' => now()->toIso8601String()]),
        ]);

        return $attempt;
    }

    private function tenantProperties()
    {
        $user = auth()->user();

        return Property::query()
            ->whereHas('establishment', fn ($query) => $query->where('tenant_id', app(CurrentTenant::class)->id()))
            ->when($user && $user->isHost(), fn ($query) => $query->whereIn('establishment_id', $user->establishments()->pluck('establishments.id')))
            ->orderBy('name')
            ->get();
    }

    /**
     * Property has no tenant_id of its own, so a manual lookup (not route-model binding) needs
     * its own tenant check -- otherwise staff could assign a reservation to another tenant's property.
     */
    private function ownedProperty(int $propertyId): Property
    {
        $property = Property::query()
            ->whereKey($propertyId)
            ->whereHas('establishment', fn ($query) => $query->where('tenant_id', app(CurrentTenant::class)->id()))
            ->firstOrFail();

        $user = auth()->user();
        if ($user && $user->isHost()) {
            abort_unless($user->managesEstablishment($property->establishment_id), 403, __('messages.errors.establishment_manager_required'));
        }

        return $property;
    }

    private function ownedReservation(Reservation $reservation): Reservation
    {
        abort_unless($reservation->property?->establishment?->tenant_id === app(CurrentTenant::class)->id(), 404);

        $user = auth()->user();
        if ($user && $user->isHost()) {
            abort_unless($user->managesEstablishment($reservation->property?->establishment_id), 403, __('messages.errors.establishment_manager_required'));
        }

        return $reservation;
    }

    private function validateReservation(Request $request, ?Reservation $reservation = null): array
    {
        return $request->validate([
            'property_id' => ['required', 'integer', 'exists:properties,id'],
            'full_name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'country' => ['nullable', 'string', 'max:80'],
            'locale' => ['nullable', 'in:fr,en'],
            'check_in' => ['required', 'date', $reservation ? 'date' : 'after_or_equal:today'],
            'check_out' => ['required', 'date', 'after:check_in'],
            'adults' => ['required', 'integer', 'min:1', 'max:8'],
            'children' => ['nullable', 'integer', 'min:0', 'max:8'],
            'infants' => ['nullable', 'integer', 'min:0', 'max:4'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'customer_note' => ['nullable', 'string', 'max:2000'],
            'ignore_external_calendar_conflicts' => ['sometimes', 'boolean'],
        ]);
    }

    private function authorizeExternalCalendarOverride(bool $requested): void
    {
        abort_if($requested && ! auth()->user()?->isEstablishmentManager(), 403, __('messages.errors.establishment_manager_required'));
    }

    private function appendExternalCalendarOverrideAudit(?string $notes): string
    {
        $audit = __('messages.admin.external_calendar_override_audit', [
            'user' => auth()->user()->name,
            'date' => now()->toDateTimeString(),
        ]);

        return trim(trim((string) $notes).PHP_EOL.$audit);
    }

    private function ensureReservationRules(Property $property, Carbon $checkIn, Carbon $checkOut, array $validated): void
    {
        $nights = $checkIn->diffInDays($checkOut);
        $guests = (int) $validated['adults'] + (int) ($validated['children'] ?? 0);

        if ($nights < (int) ($property->minimum_stay ?? 1)) {
            throw ValidationException::withMessages([
                'check_out' => __('messages.properties.minimum_stay_error', ['nights' => $property->minimum_stay]),
            ]);
        }

        if ($property->max_guests && $guests > $property->max_guests) {
            throw ValidationException::withMessages([
                'adults' => __('messages.errors.reservation_capacity_exceeded'),
            ]);
        }
    }

    private function persistReservation(array $validated, Property $property, Carbon $checkIn, Carbon $checkOut): Reservation
    {
        $pricing = $this->calculatePricing($property, $checkIn, $checkOut, (int) $validated['adults'], (int) ($validated['children'] ?? 0));
        $guest = ReservationGuest::query()->firstOrCreate(
            ['email' => strtolower($validated['email'])],
            ['full_name' => $validated['full_name'], 'phone' => $validated['phone'] ?? null, 'country' => $validated['country'] ?? null]
        );

        return DB::transaction(function () use ($validated, $property, $checkIn, $checkOut, $guest, $pricing): Reservation {
            $reservation = Reservation::query()->create([
                'property_id' => $property->id,
                'guest_id' => $guest->id,
                'user_id' => auth()->id(),
                'reservation_ref' => 'AFK-' . strtoupper(Str::random(6)) . '-' . now()->format('ymd'),
                'status' => 'pending',
                'check_in' => $checkIn->toDateString(),
                'check_out' => $checkOut->toDateString(),
                'adults' => $validated['adults'],
                'children' => $validated['children'] ?? 0,
                'infants' => $validated['infants'] ?? 0,
                'currency' => $pricing['currency'],
                'email' => strtolower($validated['email']),
                'locale' => $validated['locale'] ?? null,
                'subtotal' => $pricing['subtotal'],
                'fees' => $pricing['fees'],
                'taxes' => $pricing['taxes'],
                'total_amount' => $pricing['total_amount'],
                'source' => 'admin',
                'notes' => $validated['notes'] ?? null,
                'customer_note' => $validated['customer_note'] ?? null,
            ]);

            ReservationPriceLine::query()->insert($this->priceLines($reservation, $pricing));

            return $reservation;
        });
    }

    private function calculatePricing(Property $property, Carbon $checkIn, Carbon $checkOut, int $adults = 1, int $children = 0): array
    {
        return PricingCalculator::calculate($property, $checkIn, $checkOut, $adults, $children);
    }

    private function priceLines(Reservation $reservation, array $pricing): array
    {
        $timestamp = now();

        return array_map(function (array $line) use ($reservation, $timestamp): array {
            return array_merge($line, [
                'reservation_id' => $reservation->id,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);
        }, $pricing['price_lines']);
    }
}
