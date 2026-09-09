<?php

namespace App\Http\Controllers;

use App\Models\Property;
use App\Models\Establishment;
use App\Models\SiteReview;
use App\Models\Reservation;
use App\Models\ReservationGuest;
use App\Models\ReservationPriceLine;
use App\Models\User;
use App\Notifications\GuestAccountSetupNotification;
use App\Services\ReservationEmailService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
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
            'destination' => ['nullable', 'string', 'max:120'],
            'city' => ['nullable', 'string', 'max:120'],
            'guests' => ['nullable', 'integer', 'min:1', 'max:100'],
            'bedrooms' => ['nullable', 'integer', 'min:1', 'max:50'],
            'check_in' => ['nullable', 'date', 'after_or_equal:today'],
            'check_out' => ['nullable', 'date', 'after:check_in'],
            'min_price' => ['nullable', 'numeric', 'min:0'],
            'max_price' => ['nullable', 'numeric', 'min:0', 'gte:min_price'],
            'favorites' => ['nullable', 'boolean'],
        ]);

        // Empty inputs arrive as null, so drop them before they reach the query builder.
        $filters = array_filter($filters, static fn ($value) => $value !== null && $value !== '');

        // "city" remains supported so previously shared search links keep working.
        $destination = $filters['destination'] ?? $filters['city'] ?? null;
        $favoritePropertyIds = auth()->user()?->favoriteProperties()->pluck('properties.id')->all() ?? [];
        $favoritesOnly = (bool) ($filters['favorites'] ?? false);
        $checkIn = $filters['check_in'] ?? null;
        $checkOut = $filters['check_out'] ?? null;

        $properties = Property::query()
            ->published()
            ->when($filters['establishment'] ?? null, fn ($query, $establishment) => $query->where('establishment_id', $establishment))
            ->when($destination, fn ($query, $value) => $query->where('city', 'like', '%' . $value . '%'))
            ->when($filters['guests'] ?? null, fn ($query, $guests) => $query->where('max_guests', '>=', $guests))
            ->when($filters['bedrooms'] ?? null, fn ($query, $bedrooms) => $query->where('bedrooms', '>=', $bedrooms))
            ->when($checkIn && $checkOut, function ($query) use ($checkIn, $checkOut): void {
                $query
                    ->whereDoesntHave('reservations', fn ($reservationQuery) => $reservationQuery
                        ->where('status', '!=', 'cancelled')
                        ->where('check_in', '<', $checkOut)
                        ->where('check_out', '>', $checkIn))
                    ->whereDoesntHave('availabilityBlocks', fn ($blockQuery) => $blockQuery
                        ->where('start_date', '<', $checkOut)
                        ->where('end_date', '>', $checkIn))
                    ->whereDoesntHave('calendarFeeds', fn ($feedQuery) => $feedQuery
                        ->where('is_enabled', true)
                        ->whereHas('events', fn ($eventQuery) => $eventQuery
                            ->where('start_date', '<', $checkOut)
                            ->where('end_date', '>', $checkIn)));
            })
            ->when(isset($filters['min_price']), fn ($query) => $query->where('nightly_rate_xof', '>=', $filters['min_price']))
            ->when(isset($filters['max_price']), fn ($query) => $query->where('nightly_rate_xof', '<=', $filters['max_price']))
            ->when($favoritesOnly && auth()->check(), fn ($query) => $query->whereIn('id', $favoritePropertyIds))
            ->with(['images' => fn ($query) => $query->orderBy('sort_order'), 'translations'])
            ->when($favoritePropertyIds, fn ($query) => $query->orderByRaw('CASE WHEN id IN (' . implode(',', array_fill(0, count($favoritePropertyIds), '?')) . ') THEN 0 ELSE 1 END', $favoritePropertyIds))
            ->orderBy('nightly_rate_xof')
            ->get();

        if ($destination !== null) {
            $filters['destination'] = $destination;
        }

        $establishments = Establishment::query()->with('translations')->orderBy('name')->get();
        $destinations = Property::query()->published()->whereNotNull('city')->distinct()->orderBy('city')->pluck('city');

        return view('properties.index', compact('properties', 'establishments', 'destinations', 'filters', 'favoritePropertyIds'));
    }

    public function show(Property $property): View
    {
        abort_unless($property->status === 'published', 404);
        $property->load(['images' => fn ($query) => $query->orderBy('sort_order'), 'translations', 'establishment.translations', 'amenities', 'features' => fn ($query) => $query->where('is_active', true), 'availabilityBlocks', 'calendarFeeds.events', 'reservations']);
        $reviews = SiteReview::query()
            ->active()
            ->when($property->establishment?->tenant_id, fn ($query, $tenantId) => $query->where('tenant_id', $tenantId))
            ->orderByDesc('reviewed_at')
            ->limit(6)
            ->get();

        return view('properties.show', compact('property', 'reviews'));
    }

    public function resume(Request $request, ReservationEmailService $emailService): RedirectResponse
    {
        $pending = $request->session()->pull('pending_public_reservation');
        abort_unless(is_array($pending) && ! empty($pending['property_id']), 404);

        $property = Property::query()->with('establishment')->findOrFail($pending['property_id']);
        abort_unless($property->status === 'published', 404);
        $request->merge($pending['data'] ?? []);

        return $this->reserve($request, $property, $emailService);
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
        $authenticatedUser = $request->user();
        $validated = $request->validate([
            'full_name' => [$authenticatedUser ? 'nullable' : 'required', 'string', 'max:120'],
            'email' => [$authenticatedUser ? 'nullable' : 'required', 'email:filter', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'country' => ['nullable', 'string', 'max:80'],
            'check_in' => ['required', 'date', 'after_or_equal:today'],
            'check_out' => ['required', 'date', 'after:check_in'],
            'adults' => ['required', 'integer', 'min:1', 'max:8'],
            'children' => ['nullable', 'integer', 'min:0', 'max:8'],
            'infants' => ['nullable', 'integer', 'min:0', 'max:4'],
            'selected_features' => ['nullable', 'array'],
            'selected_features.*' => ['integer', Rule::exists('property_features', 'id')->where(fn ($query) => $query->where('property_id', $property->id)->where('is_active', true))],
            'create_account' => ['sometimes', 'boolean'],
        ], [
            'check_in.after_or_equal' => 'La date d’arrivée doit être aujourd’hui ou plus tard.',
            'check_out.after' => 'La date de départ doit être après la date d’arrivée.',
        ]);

        if ($authenticatedUser) {
            $validated['full_name'] = $authenticatedUser->name;
            $validated['email'] = $authenticatedUser->email;
        }

        $email = strtolower($validated['email']);
        $tenantId = $property->establishment?->tenant_id;
        $existingAccount = User::query()
            ->where('email', $email)
            ->where(function ($query) use ($tenantId): void {
                $query->where('tenant_id', $tenantId)->orWhereNull('tenant_id');
            })
            ->first();

        if (! $request->user() && $existingAccount && $existingAccount->email_verified_at === null) {
            $request->session()->put('pending_public_reservation', [
                'property_id' => $property->id,
                'data' => $validated,
            ]);

            try {
                $existingAccount->notify(new GuestAccountSetupNotification(
                    Password::broker()->createToken($existingAccount),
                    $property->localized('name') ?? $property->name,
                ));
            } catch (\Throwable $exception) {
                Log::warning('Guest account setup notification could not be resent.', [
                    'user_id' => $existingAccount->id,
                    'exception' => $exception,
                ]);
            }

            return back()->withInput()->with('status', __('messages.auth.confirm_email_before_reservation'));
        }

        if (! $request->user() && $existingAccount) {
            $request->session()->put('pending_public_reservation', [
                'property_id' => $property->id,
                'data' => $validated,
            ]);

            return redirect()->route('login')->with('status', __('messages.auth.existing_account_login'));
        }

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
            ['email' => $email],
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

        $reservationUser = $request->user();
        $createdGuestAccount = false;

        if (! $reservationUser && $request->boolean('create_account', true)) {
            $reservationUser = User::query()->firstOrCreate(
                ['email' => $email],
                [
                    'name' => $validated['full_name'],
                    'password' => Str::password(64),
                    'role' => 'customer',
                    'is_admin' => false,
                    'tenant_id' => $tenantId,
                    'locale' => app()->getLocale(),
                    'email_booking_updates' => true,
                    'email_message_updates' => true,
                    'email_marketing' => false,
                    'email_newsletter' => false,
                ]
            );
            $createdGuestAccount = $reservationUser->wasRecentlyCreated;
        }
        $reservation = Reservation::query()->create([
            'property_id' => $property->id,
            'guest_id' => $guest->id,
            'user_id' => $reservationUser?->id,
            'reservation_ref' => 'AFK-' . strtoupper(Str::random(6)) . '-' . now()->format('ymd'),
            'status' => 'pending',
            'check_in' => $checkIn->toDateString(),
            'check_out' => $checkOut->toDateString(),
            'adults' => (int) $validated['adults'],
            'children' => (int) ($validated['children'] ?? 0),
            'infants' => (int) ($validated['infants'] ?? 0),
            'currency' => 'XOF',
            'email' => $email,
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

        if ($createdGuestAccount) {
            try {
                $reservationUser->notify(new GuestAccountSetupNotification(
                    Password::broker()->createToken($reservationUser),
                    $reservation->reservation_ref,
                ));
            } catch (\Throwable $exception) {
                Log::warning('Guest account setup notification could not be sent.', [
                    'reservation_id' => $reservation->id,
                    'user_id' => $reservationUser->id,
                    'exception' => $exception,
                ]);
            }
        }

        return redirect()->route('checkout.show', ['reservation' => $reservation, 'token' => $reservation->checkout_token])
            ->with('status', __('messages.flash.reservation_saved'));
    }
}
