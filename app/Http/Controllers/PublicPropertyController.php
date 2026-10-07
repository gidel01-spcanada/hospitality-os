<?php

namespace App\Http\Controllers;

use App\Models\Property;
use App\Models\Amenity;
use App\Models\Establishment;
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
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use App\Services\AvailabilityService;
use App\Services\PricingCalculator;
use App\Services\PropertyRecommendationService;

class PublicPropertyController extends Controller
{
    public function index(Request $request, PropertyRecommendationService $recommendations): View
    {
        return view('properties.index', $this->catalogData($request, $recommendations));
    }

    public function catalogData(Request $request, PropertyRecommendationService $recommendations, ?Establishment $establishment = null): array
    {
        $filters = $request->validate([
            'establishment' => ['nullable', 'integer', 'exists:establishments,id'],
            'destination' => ['nullable', 'string', 'max:120'],
            'city' => ['nullable', 'string', 'max:120'],
            'guests' => ['nullable', 'integer', 'min:1', 'max:100'],
            'bedrooms' => ['nullable', 'integer', 'min:1', 'max:50'],
            'beds' => ['nullable', 'integer', 'min:1', 'max:100'],
            'property_type' => ['nullable', 'in:apartment,house,villa,studio,room,other'],
            'flexible_cancellation' => ['nullable', 'boolean'],
            'amenities' => ['nullable', 'array'],
            'amenities.*' => ['integer', 'exists:amenities,id'],
            'check_in' => ['nullable', 'date', 'after_or_equal:today'],
            'check_out' => ['nullable', 'date', 'after:check_in'],
            'min_price' => ['nullable', 'numeric', 'min:0'],
            'max_price' => ['nullable', 'numeric', 'min:0', 'gte:min_price'],
            'favorites' => ['nullable', 'boolean'],
            'sort' => ['nullable', Rule::in(['recommended', 'price_asc', 'price_desc', 'rating', 'newest'])],
        ]);

        // Empty inputs arrive as null, so drop them before they reach the query builder.
        $filters = array_filter($filters, static fn ($value) => $value !== null && $value !== '');
        if ($establishment) {
            $filters['establishment'] = $establishment->id;
        }

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
            ->when($filters['beds'] ?? null, fn ($query, $beds) => $query->where('beds', '>=', $beds))
            ->when($filters['property_type'] ?? null, fn ($query, $type) => $query->where('property_type', $type))
            ->when(($filters['flexible_cancellation'] ?? false), fn ($query) => $query->whereHas('establishment', fn ($establishmentQuery) => $establishmentQuery->where('cancellation_fee_percent', '<=', 0)))
            ->when($filters['amenities'] ?? [], fn ($query, $amenityIds) => $query->whereHas('amenities', fn ($amenityQuery) => $amenityQuery->whereIn('amenities.id', $amenityIds)))
            ->when($checkIn && $checkOut, function ($query) use ($checkIn, $checkOut): void {
                $query
                    ->where(fn ($minimumStayQuery) => $minimumStayQuery->whereNull('minimum_stay')
                        ->orWhere('minimum_stay', '<=', Carbon::parse($checkIn)->diffInDays(Carbon::parse($checkOut))))
                    ->whereDoesntHave('reservations', fn ($reservationQuery) => $reservationQuery
                        ->where('status', '!=', 'cancelled')
                        ->whereDate('check_in', '<', $checkOut)
                        ->whereDate('check_out', '>', $checkIn))
                    ->whereDoesntHave('availabilityBlocks', fn ($blockQuery) => $blockQuery
                        ->whereDate('start_date', '<', $checkOut)
                        ->whereDate('end_date', '>', $checkIn))
                    ->whereDoesntHave('calendarFeeds', fn ($feedQuery) => $feedQuery
                        ->where('is_enabled', true)
                        ->whereHas('events', fn ($eventQuery) => $eventQuery
                            ->whereDate('start_date', '<', $checkOut)
                            ->whereDate('end_date', '>', $checkIn)));
            })
            ->when(isset($filters['min_price']), fn ($query) => $query->where('nightly_rate_xof', '>=', $filters['min_price']))
            ->when(isset($filters['max_price']), fn ($query) => $query->where('nightly_rate_xof', '<=', $filters['max_price']))
            ->when($favoritesOnly && auth()->check(), fn ($query) => $query->whereIn('id', $favoritePropertyIds))
            ->with(['images' => fn ($query) => $query->orderBy('sort_order'), 'reviews' => fn ($query) => $query->active(), 'translations', 'amenities', 'establishment.translations'])
            ->when($favoritePropertyIds, fn ($query) => $query->orderByRaw('CASE WHEN id IN (' . implode(',', array_fill(0, count($favoritePropertyIds), '?')) . ') THEN 0 ELSE 1 END', $favoritePropertyIds))
            ->when(($filters['sort'] ?? 'recommended') === 'price_desc', fn ($query) => $query->orderByDesc('nightly_rate_xof'))
            ->when(($filters['sort'] ?? 'recommended') === 'price_asc', fn ($query) => $query->orderBy('nightly_rate_xof'))
            ->when(($filters['sort'] ?? 'recommended') === 'rating', fn ($query) => $query->withAvg(['reviews' => fn ($reviews) => $reviews->active()], 'rating')->orderByDesc('reviews_avg_rating'))
            ->when(($filters['sort'] ?? 'recommended') === 'newest', fn ($query) => $query->orderByDesc('created_at'))
            ->when(($filters['sort'] ?? 'recommended') === 'recommended', fn ($query) => $query->orderBy('nightly_rate_xof'))
            ->get();

        if (($filters['sort'] ?? 'recommended') === 'recommended') {
            $properties = $recommendations->sort($properties, $favoritePropertyIds);
        }

        if ($destination !== null) {
            $filters['destination'] = $destination;
        }

        $establishments = Establishment::query()->published()->with('translations')->orderBy('name')->get();
        $destinations = Property::query()->published()->whereNotNull('city')->distinct()->orderBy('city')->pluck('city');
        $selectedEstablishment = $establishments->firstWhere('id', $filters['establishment'] ?? null);
        $filterCurrency = $selectedEstablishment?->currency ?: ($properties->first()?->currency ?: 'XOF');
        $amenities = Amenity::query()->with('category')->ordered()->get();
        $priceCeiling = (int) ceil(((float) Property::query()->published()->max('nightly_rate_xof')) / 5000) * 5000;
        $priceCeiling = max($priceCeiling, (int) ($filters['min_price'] ?? 0), (int) ($filters['max_price'] ?? 0), 100000);

        return compact('properties', 'establishments', 'destinations', 'filters', 'favoritePropertyIds', 'filterCurrency', 'amenities', 'priceCeiling');
    }

    public function show(Request $request, Property $property, AvailabilityService $availabilityService): \Illuminate\Http\Response
    {
        abort_unless($property->status === 'published' && $property->is_active && $property->establishment?->is_active && $property->establishment?->is_published, 404);
        $dateFilters = $request->validate([
            'establishment' => ['nullable', 'integer', 'exists:establishments,id'],
            'destination' => ['nullable', 'string', 'max:120'],
            'city' => ['nullable', 'string', 'max:120'],
            'check_in' => ['nullable', 'date'],
            'check_out' => ['nullable', 'date', 'after:check_in'],
            'guests' => ['nullable', 'integer', 'min:1', 'max:100'],
            'bedrooms' => ['nullable', 'integer', 'min:1', 'max:50'],
            'beds' => ['nullable', 'integer', 'min:1', 'max:100'],
            'property_type' => ['nullable', 'in:apartment,house,villa,studio,room,other'],
            'min_price' => ['nullable', 'numeric', 'min:0'],
            'max_price' => ['nullable', 'numeric', 'min:0', 'gte:min_price'],
            'flexible_cancellation' => ['nullable', 'boolean'],
            'favorites' => ['nullable', 'boolean'],
            'amenities' => ['nullable', 'array'],
            'amenities.*' => ['integer', 'exists:amenities,id'],
            'sort' => ['nullable', Rule::in(['recommended', 'price_asc', 'price_desc', 'rating', 'newest'])],
        ]);
        $listingUrl = route('properties.index', array_filter($dateFilters, static fn ($value) => $value !== null && $value !== '' && $value !== [] && $value !== false));
        $bookingCheckIn = $dateFilters['check_in'] ?? '';
        $bookingCheckOut = $dateFilters['check_out'] ?? '';
        $bookingGuests = $dateFilters['guests'] ?? '';
        $property->load(['images' => fn ($query) => $query->orderBy('sort_order'), 'reviews' => fn ($query) => $query->active()->orderByDesc('reviewed_at'), 'translations', 'establishment.translations', 'amenities', 'features' => fn ($query) => $query->where('is_active', true), 'availabilityBlocks', 'calendarFeeds.events', 'reservations']);
        $bookingAvailability = null;
        $bookingPricing = null;

        if ($bookingCheckIn && $bookingCheckOut) {
            $checkIn = Carbon::parse($bookingCheckIn);
            $checkOut = Carbon::parse($bookingCheckOut);

            if ($checkIn->startOfDay()->lt(today())) {
                $bookingAvailability = [
                    'state' => 'expired',
                    'message' => __('messages.properties.availability_dates_expired'),
                ];
            } elseif ($checkIn->diffInDays($checkOut) < max(1, (int) $property->minimum_stay)) {
                $bookingAvailability = [
                    'state' => 'minimum-stay',
                    'message' => __('messages.properties.minimum_stay_error', ['nights' => $property->minimum_stay]),
                ];
            } else {
                $pricing = PricingCalculator::calculate($property, $checkIn, $checkOut, (int) ($bookingGuests ?: 1));
                $isAvailable = $availabilityService->isAvailable($property, $checkIn, $checkOut);
                $bookingPricing = $isAvailable ? $this->pricingBreakdown($pricing) : null;
                $bookingAvailability = [
                    'state' => $isAvailable ? 'available' : 'unavailable',
                    'message' => $isAvailable
                        ? __('messages.properties.availability_available_amount', [
                            'amount' => number_format($pricing['total_amount'], 0, ',', ' '),
                            'currency' => $pricing['currency'],
                        ])
                        : __('messages.properties.availability_unavailable_reselect'),
                ];
            }
        }
        $reviews = $property->reviews;
        $reviews->load(['property.translations', 'property.establishment']);
        $reviewStats = [
            'count' => $reviews->count(),
            'average' => round((float) $reviews->avg('rating'), 1),
        ];
        $reviews = $reviews->take(6);

        return response()->view('properties.show', compact('property', 'reviews', 'reviewStats', 'bookingCheckIn', 'bookingCheckOut', 'bookingGuests', 'bookingAvailability', 'bookingPricing', 'listingUrl'))
            ->header('Cache-Control', 'private, no-store');
    }

    public function compare(Request $request, AvailabilityService $availabilityService)
    {
        $validated = $request->validate([
            'properties' => ['sometimes', 'array', 'max:4'],
            'properties.*' => ['integer', 'distinct'],
            'check_in' => ['nullable', 'required_with:check_out', 'date', 'after_or_equal:today'],
            'check_out' => ['nullable', 'required_with:check_in', 'date', 'after:check_in'],
            'guests' => ['nullable', 'integer', 'min:1', 'max:100'],
            'currency' => ['nullable', 'string', 'regex:/^[A-Z]{3}$/'],
        ]);
        $selectedIds = array_map('intval', $validated['properties'] ?? []);
        $properties = Property::query()
            ->published()
            ->whereIn('id', $selectedIds)
            ->with(['images', 'translations', 'establishment.translations', 'amenities', 'reviews' => fn ($query) => $query->active()])
            ->get()
            ->sortBy(fn (Property $property) => array_search($property->id, $selectedIds, true))
            ->values();
        $comparison = \App\Support\PropertyComparison::make($properties, $validated, $availabilityService);
        if ($properties->isNotEmpty() && filled($validated['currency'] ?? null) && ! $comparison['currencies']->contains($validated['currency'])) {
            throw ValidationException::withMessages(['currency' => __('messages.comparison.rate_missing')]);
        }
        if (filled($validated['currency'] ?? null) && $comparison['currencies']->contains($validated['currency'])) {
            $request->session()->put('comparison_currency', $validated['currency']);
        }
        $candidates = Property::query()->published()->with('translations')->orderBy('name')->get();
        $missingCount = count($selectedIds) - $properties->count();
        $criteria = array_filter($validated, fn ($value) => $value !== null && $value !== '');
        $criteria['properties'] = $properties->pluck('id')->all();
        $data = $comparison + compact('properties', 'candidates', 'missingCount', 'criteria');

        if ($request->expectsJson()) {
            return response()->json([
                'html' => view('properties.partials.comparison-table', $data)->render(),
                'currency' => $comparison['currency'],
                'currencies' => $comparison['currencies']->all(),
                'properties' => $properties->pluck('id')->all(),
                'missing_count' => $missingCount,
            ])->header('Cache-Control', 'private, no-store');
        }

        return response()->view('properties.compare', $data)->header('Cache-Control', 'private, no-store');
    }

    public function resume(Request $request, AvailabilityService $availabilityService, ReservationEmailService $emailService, \App\Services\GoogleRecaptchaVerifier $recaptcha): RedirectResponse
    {
        $pending = $request->session()->pull('pending_public_reservation');
        abort_unless(is_array($pending) && ! empty($pending['property_id']), 404);

        $property = Property::query()->with('establishment')->findOrFail($pending['property_id']);
        abort_unless($property->status === 'published' && $property->is_active && $property->establishment?->is_active && $property->establishment?->is_published, 404);
        $request->merge($pending['data'] ?? []);

        return $this->reserve($request, $property, $availabilityService, $emailService, $recaptcha);
    }

    public function availability(Request $request, Property $property, AvailabilityService $availabilityService)
    {
        abort_unless($property->status === 'published' && $property->is_active && $property->establishment?->is_active && $property->establishment?->is_published, 404);

        $validated = $request->validate([
            'check_in' => ['required', 'date', 'after_or_equal:today'],
            'check_out' => ['required', 'date', 'after:check_in'],
            'adults' => ['nullable', 'integer', 'min:1', 'max:8'],
            'children' => ['nullable', 'integer', 'min:0', 'max:8'],
            'selected_features' => ['nullable', 'array'],
            'selected_features.*' => ['integer', Rule::exists('property_features', 'id')->where(fn ($query) => $query->where('property_id', $property->id)->where('is_active', true))],
        ]);

        $checkIn = Carbon::parse($validated['check_in']);
        $checkOut = Carbon::parse($validated['check_out']);
        $nights = $checkIn->diffInDays($checkOut);
        if ($nights < max(1, (int) $property->minimum_stay)) {
            return response()->json([
                'available' => false,
                'reason' => 'minimum_stay',
                'minimum_stay' => (int) $property->minimum_stay,
            ])->header('Cache-Control', 'private, no-store');
        }

        $selectedFeatures = $property->features()->whereIn('id', $validated['selected_features'] ?? [])->where('is_active', true)->get();
        $pricing = PricingCalculator::calculate(
            $property,
            $checkIn,
            $checkOut,
            (int) ($validated['adults'] ?? 1),
            (int) ($validated['children'] ?? 0),
            $selectedFeatures
        );

        $isAvailable = $availabilityService->isAvailable($property, $checkIn, $checkOut);

        return response()->json([
            'available' => $isAvailable,
            'estimated_total' => $pricing['total_amount'],
            'currency' => $pricing['currency'],
            'breakdown' => $isAvailable ? $this->pricingBreakdown($pricing) : null,
            'message' => $isAvailable
                ? __('messages.properties.availability_available_amount', [
                    'amount' => number_format($pricing['total_amount'], 0, ',', ' '),
                    'currency' => $pricing['currency'],
                ])
                : __('messages.properties.availability_unavailable_reselect'),
        ])->header('Cache-Control', 'private, no-store');
    }

    private function pricingBreakdown(array $pricing): array
    {
        return [
            'nights' => $pricing['nights'],
            'nightly_rate' => $pricing['nightly_rate'],
            'room_subtotal' => $pricing['room_subtotal'],
            'extra_lines' => $pricing['extra_lines'],
            'fees' => $pricing['fees'],
            'tax_lines' => $pricing['tax_lines'],
            'discount_lines' => $pricing['discount_lines'],
            'discount_total' => $pricing['discount_total'],
            'total_amount' => $pricing['total_amount'],
            'currency' => $pricing['currency'],
        ];
    }

    public function reserve(Request $request, Property $property, AvailabilityService $availabilityService, ReservationEmailService $emailService, \App\Services\GoogleRecaptchaVerifier $recaptcha): RedirectResponse
    {
        abort_unless($property->status === 'published' && $property->is_active && $property->establishment?->is_active && $property->establishment?->is_published, 404);
        $authenticatedUser = $request->user()?->role === 'customer' ? $request->user() : null;
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
            'customer_note' => ['nullable', 'string', 'max:2000'],
            'selected_features' => ['nullable', 'array'],
            'selected_features.*' => ['integer', Rule::exists('property_features', 'id')->where(fn ($query) => $query->where('property_id', $property->id)->where('is_active', true))],
            'create_account' => ['sometimes', 'boolean'],
            'g-recaptcha-response' => ['nullable', 'string'],
        ], [
            'check_in.after_or_equal' => 'La date d’arrivée doit être aujourd’hui ou plus tard.',
            'check_out.after' => 'La date de départ doit être après la date d’arrivée.',
        ]);

        if (! $authenticatedUser && ! $recaptcha->verify($request->input('g-recaptcha-response'), $request->ip())) {
            throw ValidationException::withMessages(['email' => __('messages.security.captcha_failed')]);
        }

        $checkIn = Carbon::parse($validated['check_in']);
        $checkOut = Carbon::parse($validated['check_out']);
        if ($checkIn->diffInDays($checkOut) < max(1, (int) $property->minimum_stay)) {
            return back()->withInput()->withErrors([
                'check_in' => __('messages.properties.minimum_stay_error', ['nights' => (int) $property->minimum_stay]),
            ]);
        }
        if (! $availabilityService->isAvailable($property, $checkIn, $checkOut)) {
            return back()->withInput()->withErrors([
                'check_in' => __('messages.properties.availability_unavailable'),
            ]);
        }

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
        $existingAccount ??= User::query()->where('email', $email)->where('role', 'customer')->first();

        $bookingAccount = $authenticatedUser ?: $existingAccount;
        $deferAccountSetup = (! $request->user() || $authenticatedUser !== null)
            && $bookingAccount?->role === 'customer' && ! $bookingAccount->email_verified_at;

        if (! $deferAccountSetup && ((! $request->user() && $existingAccount && ! $existingAccount->email_verified_at)
            || ($request->user() && ! $request->user()->email_verified_at))) {
            $account = $request->user() ?: $existingAccount;
            $this->queueReservationAccountSetup($request, $account, $property, $validated);

            return back()->withInput()->with('status', __('messages.auth.confirm_email_before_reservation'));
        }

        if (! $request->user() && $existingAccount && ! $deferAccountSetup) {
            $request->session()->put('pending_public_reservation', [
                'property_id' => $property->id,
                'data' => $validated,
            ]);

            return redirect()->route('login')
                ->withInput(['email' => $email])
                ->with('status', __('messages.auth.existing_account_login'));
        }

        if (! $request->user() && ! $existingAccount && $request->boolean('create_account', true)) {
            $existingAccount = User::query()->create([
                'name' => $validated['full_name'],
                'email' => $email,
                'password' => Str::password(64),
                'role' => 'customer',
                'tenant_id' => $tenantId,
                'locale' => app()->getLocale(),
                'email_booking_updates' => true,
                'email_message_updates' => true,
            ]);
            // New guests continue to checkout; account activation is sent by email and can be done later.
            $deferAccountSetup = true;
        }

        $selectedFeatures = $property->features()->whereIn('id', $validated['selected_features'] ?? [])->where('is_active', true)->get();

        $pricing = PricingCalculator::calculate(
            $property,
            $checkIn,
            $checkOut,
            (int) $validated['adults'],
            (int) ($validated['children'] ?? 0),
            $selectedFeatures
        );

        $guest = ReservationGuest::query()->firstOrCreate(
            ['email' => $email],
            [
                'full_name' => $validated['full_name'],
                'phone' => $validated['phone'] ?? null,
                'country' => $validated['country'] ?? null,
                'metadata' => [
                    'source' => 'website',
                    'guest_count' => $pricing['guests'],
                ],
            ]
        );

        $reservationUser = $request->user() ?: ($deferAccountSetup ? $existingAccount : null);
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
            'account_setup_deferred' => $deferAccountSetup,
            'reservation_ref' => 'AFK-' . strtoupper(Str::random(6)) . '-' . now()->format('ymd'),
            'status' => 'pending',
            'check_in' => $checkIn->toDateString(),
            'check_out' => $checkOut->toDateString(),
            'adults' => (int) $validated['adults'],
            'children' => (int) ($validated['children'] ?? 0),
            'infants' => (int) ($validated['infants'] ?? 0),
            'currency' => $pricing['currency'],
            'email' => $email,
            'locale' => app()->getLocale(),
            'subtotal' => $pricing['subtotal'],
            'fees' => $pricing['fees'],
            'taxes' => $pricing['taxes'],
            'total_amount' => $pricing['total_amount'],
            'source' => 'website',
            'notes' => 'Reservation placed from public booking form.' . ($selectedFeatures->isNotEmpty() ? ' Extras: ' . $selectedFeatures->pluck('name')->implode(', ') : ''),
            'customer_note' => $validated['customer_note'] ?? null,
        ]);

        $priceLines = array_map(function (array $line) use ($reservation): array {
            return array_merge($line, [
                'reservation_id' => $reservation->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }, $pricing['price_lines']);

        ReservationPriceLine::query()->insert($priceLines);

        $emailService->queueForReservation($reservation, 'reservation_received');

        if ($deferAccountSetup) {
            $pendingReservation = $request->session()->get('pending_public_reservation');
            if (data_get($pendingReservation, 'property_id') == $property->id && strtolower((string) data_get($pendingReservation, 'data.email')) === $email) {
                $request->session()->forget('pending_public_reservation');
            }
        }

        $activationEmailFailed = false;
        if ($createdGuestAccount || $deferAccountSetup) {
            try {
                $reservationUser->notify(new GuestAccountSetupNotification(
                    Password::broker()->createToken($reservationUser),
                    $reservation->reservation_ref,
                    $deferAccountSetup ? route('checkout.show', ['reservation' => $reservation, 'token' => $reservation->checkout_token]) : null,
                ));
            } catch (\Throwable $exception) {
                $activationEmailFailed = $deferAccountSetup;
                Log::warning('Guest account setup notification could not be sent.', [
                    'reservation_id' => $reservation->id,
                    'user_id' => $reservationUser->id,
                    'exception' => $exception,
                ]);
            }
        }

        return redirect()->route('checkout.show', ['reservation' => $reservation, 'token' => $reservation->checkout_token])
            ->with('status', __('messages.flash.reservation_saved'))
            ->with('account_activation_email_failed', $activationEmailFailed);
    }

    private function queueReservationAccountSetup(Request $request, User $account, Property $property, array $validated): void
    {
        $request->session()->put('pending_public_reservation', [
            'property_id' => $property->id,
            'data' => $validated,
        ]);

        try {
            $account->notify(new GuestAccountSetupNotification(
                Password::broker()->createToken($account),
                $property->localized('name') ?? $property->name,
                isReservationReference: false,
            ));
        } catch (\Throwable $exception) {
            Log::warning('Guest account setup notification could not be sent.', [
                'user_id' => $account->id,
                'exception' => $exception,
            ]);
        }
    }
}
