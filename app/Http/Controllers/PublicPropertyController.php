<?php

namespace App\Http\Controllers;

use App\Models\Property;
use App\Models\Establishment;
use App\Models\SiteReview;
use App\Models\Reservation;
use App\Models\ReservationGuest;
use App\Models\ReservationPriceLine;
use App\Services\ReservationEmailService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use App\Services\AvailabilityService;

class PublicPropertyController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'establishment' => ['nullable', 'integer', 'exists:establishments,id'],
            'city' => ['nullable', 'string', 'max:120'],
            'guests' => ['nullable', 'integer', 'min:1', 'max:100'],
            'bedrooms' => ['nullable', 'integer', 'min:1', 'max:50'],
            'min_price' => ['nullable', 'numeric', 'min:0'],
            'max_price' => ['nullable', 'numeric', 'min:0', 'gte:min_price'],
        ]);

        $properties = Property::query()
            ->published()
            ->when($filters['establishment'] ?? null, fn ($query, $establishment) => $query->where('establishment_id', $establishment))
            ->when($filters['city'] ?? null, fn ($query, $city) => $query->where('city', 'like', '%' . $city . '%'))
            ->when($filters['guests'] ?? null, fn ($query, $guests) => $query->where('max_guests', '>=', $guests))
            ->when($filters['bedrooms'] ?? null, fn ($query, $bedrooms) => $query->where('bedrooms', '>=', $bedrooms))
            ->when(array_key_exists('min_price', $filters), fn ($query) => $query->where('nightly_rate_xof', '>=', $filters['min_price']))
            ->when(array_key_exists('max_price', $filters), fn ($query) => $query->where('nightly_rate_xof', '<=', $filters['max_price']))
            ->with(['images' => fn ($query) => $query->orderBy('sort_order'), 'translations'])
            ->orderBy('nightly_rate_xof')
            ->get();

        $establishments = Establishment::query()->with('translations')->orderBy('name')->get();

        return view('properties.index', compact('properties', 'establishments', 'filters'));
    }

    public function show(Property $property): View
    {
        abort_unless($property->status === 'published', 404);
        $property->load(['images' => fn ($query) => $query->orderBy('sort_order'), 'translations', 'establishment.translations', 'amenities', 'features' => fn ($query) => $query->where('is_active', true)]);
        $reviews = SiteReview::query()->active()->orderByDesc('reviewed_at')->limit(6)->get();

        return view('properties.show', compact('property', 'reviews'));
    }

    public function availability(Request $request, Property $property, AvailabilityService $availabilityService)
    {
        abort_unless($property->status === 'published', 404);

        $validated = $request->validate([
            'check_in' => ['required', 'date', 'after_or_equal:today'],
            'check_out' => ['required', 'date', 'after:check_in'],
        ]);

        return response()->json([
            'available' => $availabilityService->isAvailable($property, Carbon::parse($validated['check_in']), Carbon::parse($validated['check_out'])),
        ]);
    }

    public function reserve(Request $request, Property $property, ReservationEmailService $emailService): RedirectResponse
    {
        $validated = $request->validate([
            'full_name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email:filter', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'country' => ['nullable', 'string', 'max:80'],
            'check_in' => ['required', 'date', 'after_or_equal:today'],
            'check_out' => ['required', 'date', 'after:check_in'],
            'adults' => ['required', 'integer', 'min:1', 'max:8'],
            'children' => ['nullable', 'integer', 'min:0', 'max:8'],
            'infants' => ['nullable', 'integer', 'min:0', 'max:4'],
            'selected_features' => ['nullable', 'array'],
            'selected_features.*' => ['integer', Rule::exists('property_features', 'id')->where(fn ($query) => $query->where('property_id', $property->id)->where('is_active', true))],
        ], [
            'check_in.after_or_equal' => 'La date d’arrivée doit être aujourd’hui ou plus tard.',
            'check_out.after' => 'La date de départ doit être après la date d’arrivée.',
        ]);

        $checkIn = Carbon::parse($validated['check_in']);
        $checkOut = Carbon::parse($validated['check_out']);
        $nights = max(1, $checkIn->diffInDays($checkOut));
        $baseRate = (float) $property->nightly_rate_xof;
        $selectedFeatures = $property->features()->whereIn('id', $validated['selected_features'] ?? [])->where('is_active', true)->get();
        $extrasTotal = (float) $selectedFeatures->sum('cost_xof');
        $subtotal = round(($baseRate * $nights) + $extrasTotal, 2);
        $fees = round($subtotal * 0.10, 2);
        $taxes = round($subtotal * 0.05, 2);
        $totalAmount = round($subtotal + $fees + $taxes, 2);

        $guest = ReservationGuest::query()->firstOrCreate(
            ['email' => strtolower($validated['email'])],
            [
                'full_name' => $validated['full_name'],
                'phone' => $validated['phone'] ?? null,
                'country' => $validated['country'] ?? null,
                'metadata' => [
                    'source' => 'website',
                    'guest_count' => (int) $validated['adults'] + (int) ($validated['children'] ?? 0),
                ],
            ]
        );

        $reservation = Reservation::query()->create([
            'property_id' => $property->id,
            'guest_id' => $guest->id,
            'user_id' => auth()->id(),
            'reservation_ref' => 'AFK-' . strtoupper(Str::random(6)) . '-' . now()->format('ymd'),
            'status' => 'pending',
            'check_in' => $checkIn->toDateString(),
            'check_out' => $checkOut->toDateString(),
            'adults' => (int) $validated['adults'],
            'children' => (int) ($validated['children'] ?? 0),
            'infants' => (int) ($validated['infants'] ?? 0),
            'currency' => 'XOF',
            'email' => strtolower($validated['email']),
            'subtotal' => $subtotal,
            'fees' => $fees,
            'taxes' => $taxes,
            'total_amount' => $totalAmount,
            'source' => 'website',
            'notes' => 'Reservation placed from public booking form.' . ($selectedFeatures->isNotEmpty() ? ' Extras: ' . $selectedFeatures->pluck('name')->implode(', ') : ''),
        ]);

        $priceLines = [
            ['reservation_id' => $reservation->id, 'label' => 'Nuit(s) x ' . $nights, 'amount' => round($baseRate * $nights, 2), 'currency' => 'XOF', 'created_at' => now(), 'updated_at' => now()],
            ['reservation_id' => $reservation->id, 'label' => 'Frais de service', 'amount' => $fees, 'currency' => 'XOF', 'created_at' => now(), 'updated_at' => now()],
            ['reservation_id' => $reservation->id, 'label' => 'Taxes', 'amount' => $taxes, 'currency' => 'XOF', 'created_at' => now(), 'updated_at' => now()],
        ];

        foreach ($selectedFeatures as $feature) {
            $priceLines[] = [
                'reservation_id' => $reservation->id,
                'label' => 'Option: ' . $feature->name,
                'amount' => (float) $feature->cost_xof,
                'currency' => 'XOF',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        ReservationPriceLine::query()->insert($priceLines);

        $emailService->queueForReservation($reservation, 'reservation_received', 'Confirmation de votre demande de réservation');

        return redirect()->route('properties.show', $property)->with('status', __('messages.flash.reservation_saved'));
    }
}
