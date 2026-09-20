<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use App\Models\ReservationGuest;
use App\Models\ReservationPriceLine;
use App\Models\Property;
use App\Services\AvailabilityService;
use App\Services\PricingCalculator;
use App\Services\ReservationEmailService;
use App\Support\CurrentTenant;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\UploadedFile;
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

    public function store(Request $request, AvailabilityService $availabilityService): RedirectResponse
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
        abort_unless(
            $availabilityService->isAvailable($property, $checkIn, $checkOut, null, $ignoreExternalConflicts),
            422,
            __('messages.errors.property_unavailable'),
        );
        if ($externalConflictOverridden) {
            $validated['notes'] = $this->appendExternalCalendarOverrideAudit($validated['notes'] ?? null);
        }

        $reservation = $this->persistReservation($validated, $property, $checkIn, $checkOut);

        return redirect()->route('admin.reservations.show', $reservation)->with('success', 'Reservation created successfully.');
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
                'subtotal' => $pricing['subtotal'],
                'fees' => $pricing['fees'],
                'taxes' => $pricing['taxes'],
                'total_amount' => $pricing['total_amount'],
                'notes' => $validated['notes'] ?? null,
            ]);

            $reservation->priceLines()->delete();
            ReservationPriceLine::query()->insert($this->priceLines($reservation, $pricing));
        });

        return redirect()->route('admin.reservations.show', $reservation)->with('success', 'Reservation updated successfully.');
    }

    public function index(Request $request): View
    {
        $user = auth()->user();

        $reservations = Reservation::query()
            ->whereHas('property.establishment', fn ($query) => $query->where('tenant_id', app(CurrentTenant::class)->id()))
            ->when($user && $user->isHost(), fn ($query) => $query->whereHas('property', fn ($q) => $q->whereIn('establishment_id', $user->establishments()->pluck('establishments.id'))))
            ->with(['property', 'guest'])
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
            ->when($request->filled('date_to'), fn ($query) => $query->whereDate('check_out', '<=', $request->date('date_to')))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('admin.bookings.index', ['bookings' => $reservations]);
    }

    public function show(Reservation $reservation): View
    {
        $this->ownedReservation($reservation);

        $reservation->load(['property', 'guest', 'priceLines', 'paymentAttempts']);

        return view('admin.reservations.show', compact('reservation'));
    }

    public function destroy(Reservation $reservation): RedirectResponse
    {
        abort_unless(auth()->user()?->isAdmin(), 403, __('messages.errors.admin_required'));
        $this->ownedReservation($reservation);
        $reservation->load(['paymentAttempts', 'guest']);

        DB::transaction(function () use ($reservation): void {
            foreach ($reservation->paymentAttempts as $attempt) {
                $proofPath = data_get($attempt->payload, 'payment_proof.path');
                if ($proofPath && File::exists(public_path($proofPath))) {
                    File::delete(public_path($proofPath));
                }
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

        /** @var UploadedFile $file */
        $file = $validated['payment_proof'];
        $directory = rtrim(config('filesystems.public_upload_path'), '/\\') . DIRECTORY_SEPARATOR . 'reservations' . DIRECTORY_SEPARATOR . $reservation->id;
        File::ensureDirectoryExists($directory);

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

        $oldPath = data_get($attempt->payload, 'payment_proof.path');
        if ($oldPath && File::exists(public_path($oldPath))) {
            File::delete(public_path($oldPath));
        }

        $filename = now()->format('YmdHisv') . '-' . Str::lower(Str::random(12)) . '.' . strtolower($file->getClientOriginalExtension());
        $file->move($directory, $filename);
        $relativePath = 'uploads/reservations/' . $reservation->id . '/' . $filename;
        $attempt->update([
            'payload' => array_merge((array) $attempt->payload, [
                'payment_proof' => [
                    'path' => $relativePath,
                    'original_name' => $file->getClientOriginalName(),
                    'uploaded_by' => auth()->id(),
                    'uploaded_at' => now()->toIso8601String(),
                ],
            ]),
        ]);

        return back()->with('status', __('messages.receipts.proof_uploaded'));
    }

    public function updateStatus(Request $request, Reservation $reservation, ReservationEmailService $emailService): RedirectResponse
    {
        $this->ownedReservation($reservation);

        $validated = $request->validate([
            'status' => ['required', 'string', 'in:pending,pending_payment,confirmed,checked_in,completed,cancelled'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $oldStatus = $reservation->status;
        $newStatus = $validated['status'];

        $reservation->update([
            'status' => $newStatus,
            'notes' => trim(($reservation->notes ?? '') . PHP_EOL . ($validated['notes'] ?? 'Status updated by admin.')),
        ]);

        if ($oldStatus !== $newStatus) {
            $emailService->queueForReservation($reservation, 'reservation_status_updated', 'Mise à jour de votre réservation');
        }

        return redirect()->route('admin.reservations.show', $reservation)
            ->with('status', __('messages.flash.reservation_status_updated'));
    }

    public function sendPaymentLink(Reservation $reservation, ReservationEmailService $emailService): RedirectResponse
    {
        $this->ownedReservation($reservation);
        abort_unless($reservation->canSendPaymentLink(), 403);

        $emailService->queuePaymentLink($reservation);

        return redirect()->route('admin.reservations.show', $reservation)
            ->with('status', __('messages.flash.payment_link_queued'));
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
            'check_in' => ['required', 'date', $reservation ? 'date' : 'after_or_equal:today'],
            'check_out' => ['required', 'date', 'after:check_in'],
            'adults' => ['required', 'integer', 'min:1', 'max:8'],
            'children' => ['nullable', 'integer', 'min:0', 'max:8'],
            'infants' => ['nullable', 'integer', 'min:0', 'max:4'],
            'notes' => ['nullable', 'string', 'max:2000'],
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

        abort_if($nights < (int) ($property->minimum_stay ?? 1), 422, 'The reservation does not meet the property minimum stay.');
        abort_if($property->max_guests && $guests > $property->max_guests, 422, 'The number of guests exceeds this property capacity.');
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
                'subtotal' => $pricing['subtotal'],
                'fees' => $pricing['fees'],
                'taxes' => $pricing['taxes'],
                'total_amount' => $pricing['total_amount'],
                'source' => 'admin',
                'notes' => $validated['notes'] ?? null,
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
