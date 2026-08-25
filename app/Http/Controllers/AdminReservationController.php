<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use App\Models\ReservationGuest;
use App\Models\ReservationPriceLine;
use App\Models\Property;
use App\Services\AvailabilityService;
use App\Services\ReservationEmailService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AdminReservationController extends Controller
{
    public function create(): View
    {
        return view('admin.reservations.form', [
            'reservation' => new Reservation(['adults' => 1, 'children' => 0, 'infants' => 0]),
            'properties' => Property::query()->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, AvailabilityService $availabilityService): RedirectResponse
    {
        $validated = $this->validateReservation($request);
        $property = Property::query()->findOrFail($validated['property_id']);
        $checkIn = Carbon::parse($validated['check_in']);
        $checkOut = Carbon::parse($validated['check_out']);

        $this->ensureReservationRules($property, $checkIn, $checkOut, $validated);
        abort_unless($availabilityService->isAvailable($property, $checkIn, $checkOut), 422, 'The property is not available for these dates.');

        $reservation = $this->persistReservation($validated, $property, $checkIn, $checkOut);

        return redirect()->route('admin.reservations.show', $reservation)->with('success', 'Reservation created successfully.');
    }

    public function edit(Reservation $reservation): View
    {
        $reservation->load('guest');

        return view('admin.reservations.form', [
            'reservation' => $reservation,
            'properties' => Property::query()->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Reservation $reservation, AvailabilityService $availabilityService): RedirectResponse
    {
        $validated = $this->validateReservation($request, $reservation);
        $property = Property::query()->findOrFail($validated['property_id']);
        $checkIn = Carbon::parse($validated['check_in']);
        $checkOut = Carbon::parse($validated['check_out']);

        $this->ensureReservationRules($property, $checkIn, $checkOut, $validated);
        abort_unless($availabilityService->isAvailable($property, $checkIn, $checkOut, $reservation->id), 422, 'The property is not available for these dates.');

        $guest = $reservation->guest ?: new ReservationGuest();
        $guest->fill([
            'full_name' => $validated['full_name'],
            'email' => strtolower($validated['email']),
            'phone' => $validated['phone'] ?? null,
            'country' => $validated['country'] ?? null,
        ])->save();

        $pricing = $this->calculatePricing($property, $checkIn, $checkOut);

        DB::transaction(function () use ($reservation, $property, $guest, $validated, $checkIn, $checkOut, $pricing): void {
            $reservation->update([
                'property_id' => $property->id,
                'guest_id' => $guest->id,
                'check_in' => $checkIn->toDateString(),
                'check_out' => $checkOut->toDateString(),
                'adults' => $validated['adults'],
                'children' => $validated['children'] ?? 0,
                'infants' => $validated['infants'] ?? 0,
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
        $reservations = Reservation::query()
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
        $reservation->load(['property', 'guest', 'priceLines']);

        return view('admin.reservations.show', compact('reservation'));
    }

    public function updateStatus(Request $request, Reservation $reservation, ReservationEmailService $emailService): RedirectResponse
    {
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
        ]);
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
        $pricing = $this->calculatePricing($property, $checkIn, $checkOut);
        $nights = $pricing['nights'];
        $subtotal = $pricing['subtotal'];
        $fees = $pricing['fees'];
        $taxes = $pricing['taxes'];
        $guest = ReservationGuest::query()->firstOrCreate(
            ['email' => strtolower($validated['email'])],
            ['full_name' => $validated['full_name'], 'phone' => $validated['phone'] ?? null, 'country' => $validated['country'] ?? null]
        );

        return DB::transaction(function () use ($validated, $property, $checkIn, $checkOut, $guest, $subtotal, $fees, $taxes, $nights): Reservation {
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
                'currency' => 'XOF',
                'email' => strtolower($validated['email']),
                'subtotal' => $subtotal,
                'fees' => $fees,
                'taxes' => $taxes,
                'total_amount' => $subtotal + $fees + $taxes,
                'source' => 'admin',
                'notes' => $validated['notes'] ?? null,
            ]);

            ReservationPriceLine::query()->insert($this->priceLines($reservation, $pricing));

            return $reservation;
        });
    }

    private function calculatePricing(Property $property, Carbon $checkIn, Carbon $checkOut): array
    {
        $nights = $checkIn->diffInDays($checkOut);
        $subtotal = round((float) $property->nightly_rate_xof * $nights, 2);
        $fees = round($subtotal * 0.10, 2);
        $taxes = round($subtotal * 0.05, 2);

        return [
            'nights' => $nights,
            'subtotal' => $subtotal,
            'fees' => $fees,
            'taxes' => $taxes,
            'total_amount' => round($subtotal + $fees + $taxes, 2),
        ];
    }

    private function priceLines(Reservation $reservation, array $pricing): array
    {
        $timestamp = now();

        return [
            ['reservation_id' => $reservation->id, 'label' => 'Nuit(s) x ' . $pricing['nights'], 'amount' => $pricing['subtotal'], 'currency' => 'XOF', 'created_at' => $timestamp, 'updated_at' => $timestamp],
            ['reservation_id' => $reservation->id, 'label' => 'Frais de service', 'amount' => $pricing['fees'], 'currency' => 'XOF', 'created_at' => $timestamp, 'updated_at' => $timestamp],
            ['reservation_id' => $reservation->id, 'label' => 'Taxes', 'amount' => $pricing['taxes'], 'currency' => 'XOF', 'created_at' => $timestamp, 'updated_at' => $timestamp],
        ];
    }
}
