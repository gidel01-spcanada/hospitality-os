<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MobileAccessToken;
use App\Models\Property;
use App\Models\Reservation;
use App\Services\AvailabilityService;
use App\Services\ReservationEmailService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class MobileApiController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ]);
        $user = \App\Models\User::query()->where('email', strtolower($credentials['email']))->first();

        if (! $user || ! $user->is_active || ! Hash::check($credentials['password'], $user->password)) {
            return response()->json(['message' => 'Invalid credentials.'], 422);
        }

        $plainToken = Str::random(80);
        MobileAccessToken::query()->create([
            'user_id' => $user->id,
            'name' => $credentials['device_name'] ?? 'mobile-app',
            'token' => hash('sha256', $plainToken),
        ]);

        return response()->json(['token' => $plainToken, 'user' => $this->userData($user)], 201);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->attributes->get('mobile_access_token')?->delete();

        return response()->json(status: 204);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->userData($request->user())]);
    }

    public function updateMe(Request $request): JsonResponse
    {
        $validated = $request->validate(['name' => ['sometimes', 'string', 'max:255'], 'locale' => ['sometimes', 'in:fr,en']]);
        $request->user()->fill($validated)->save();

        return response()->json(['data' => $this->userData($request->user())]);
    }

    public function properties(Request $request): JsonResponse
    {
        $filters = $request->validate(['city' => ['nullable', 'string', 'max:120'], 'guests' => ['nullable', 'integer', 'min:1'], 'establishment_id' => ['nullable', 'integer']]);
        $properties = Property::query()->published()
            ->when($filters['city'] ?? null, fn ($query, $city) => $query->where('city', 'like', "%{$city}%"))
            ->when($filters['guests'] ?? null, fn ($query, $guests) => $query->where('max_guests', '>=', $guests))
            ->when($filters['establishment_id'] ?? null, fn ($query, $id) => $query->where('establishment_id', $id))
            ->with(['establishment', 'images' => fn ($query) => $query->orderBy('sort_order')])
            ->orderBy('nightly_rate_xof')->get();

        return response()->json(['data' => $properties->map(fn (Property $property) => $this->propertyData($property))]);
    }

    public function property(Property $property): JsonResponse
    {
        abort_unless($property->status === 'published', 404);
        $property->load(['establishment', 'images' => fn ($query) => $query->orderBy('sort_order'), 'amenities', 'features' => fn ($query) => $query->where('is_active', true)]);

        return response()->json(['data' => $this->propertyData($property, true)]);
    }

    public function availability(Request $request, Property $property, AvailabilityService $availabilityService): JsonResponse
    {
        abort_unless($property->status === 'published', 404);
        $dates = $request->validate(['check_in' => ['required', 'date'], 'check_out' => ['required', 'date', 'after:check_in']]);

        return response()->json(['available' => $availabilityService->isAvailable($property, Carbon::parse($dates['check_in']), Carbon::parse($dates['check_out']))]);
    }

    public function customerReservations(Request $request): JsonResponse
    {
        $reservations = Reservation::query()->where('user_id', $request->user()->id)->with('property')->latest()->get();

        return response()->json(['data' => $reservations->map(fn (Reservation $reservation) => $this->reservationData($reservation))]);
    }

    public function customerReservation(Request $request, Reservation $reservation): JsonResponse
    {
        abort_unless($reservation->user_id === $request->user()->id, 403);
        $reservation->load(['property', 'guest', 'priceLines', 'paymentAttempts']);

        return response()->json(['data' => $this->reservationData($reservation, true)]);
    }

    public function toggleFavorite(Request $request, Property $property): JsonResponse
    {
        $favorites = $request->user()->favoriteProperties();
        $result = $favorites->toggle($property->id);
        $favorited = $result['attached'] !== [];

        return response()->json(['property_id' => $property->id, 'favorited' => $favorited]);
    }

    public function staffReservations(Request $request): JsonResponse
    {
        $reservations = $this->tenantReservations()->with(['property', 'guest'])->latest()->get();

        return response()->json(['data' => $reservations->map(fn (Reservation $reservation) => $this->reservationData($reservation))]);
    }

    public function staffReservation(Reservation $reservation): JsonResponse
    {
        $reservation->load(['property', 'guest', 'priceLines', 'paymentAttempts']);

        return response()->json(['data' => $this->reservationData($reservation, true)]);
    }

    public function updateReservationStatus(Request $request, Reservation $reservation, ReservationEmailService $emailService): JsonResponse
    {
        $validated = $request->validate(['status' => ['required', 'in:pending,pending_payment,confirmed,checked_in,completed,cancelled'], 'notes' => ['nullable', 'string', 'max:500']]);
        $changed = $reservation->status !== $validated['status'];
        $reservation->update(['status' => $validated['status'], 'notes' => $validated['notes'] ?? $reservation->notes]);
        if ($changed) {
            $emailService->queueForReservation($reservation, 'reservation_status_updated');
        }

        return response()->json(['data' => $this->reservationData($reservation)]);
    }

    public function sendPaymentLink(Reservation $reservation, ReservationEmailService $emailService): JsonResponse
    {
        abort_unless($reservation->canSendPaymentLink(), 403);
        $emailService->queuePaymentLink($reservation);

        return response()->json(['message' => 'Payment link queued.', 'checkout_url' => route('checkout.show', ['reservation' => $reservation, 'token' => $reservation->checkout_token])], 202);
    }

    public function adminProperties(): JsonResponse
    {
        $properties = Property::query()
            ->whereHas('establishment', fn ($query) => $query->where('tenant_id', app(\App\Support\CurrentTenant::class)->id()))
            ->with('establishment')
            ->latest()
            ->get();

        return response()->json(['data' => $properties->map(fn (Property $property) => $this->propertyData($property))]);
    }

    private function tenantReservations()
    {
        return Reservation::query()->whereHas('property.establishment', fn ($query) => $query->where('tenant_id', app(\App\Support\CurrentTenant::class)->id()));
    }

    private function userData($user): array
    {
        return ['id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'role' => $user->role, 'locale' => $user->locale];
    }

    private function propertyData(Property $property, bool $detail = false): array
    {
        return array_filter([
            'id' => $property->id, 'slug' => $property->slug, 'name' => $property->name, 'city' => $property->city,
            'currency' => $property->currency, 'nightly_rate_xof' => $property->nightly_rate_xof, 'max_guests' => $property->max_guests,
            'bedrooms' => $property->bedrooms, 'cover_image' => $property->cover_image, 'establishment' => $property->establishment?->only(['id', 'name', 'city']),
            'images' => $detail ? $property->images?->map(fn ($image) => $image->only(['id', 'file_path', 'room_tag', 'is_cover'])) : null,
            'description' => $detail ? $property->description : null,
            'amenities' => $detail ? $property->amenities?->pluck('name') : null,
        ], fn ($value) => $value !== null);
    }

    private function reservationData(Reservation $reservation, bool $detail = false): array
    {
        return array_filter([
            'id' => $reservation->id, 'reference' => $reservation->reservation_ref, 'status' => $reservation->status,
            'check_in' => $reservation->check_in?->toDateString(), 'check_out' => $reservation->check_out?->toDateString(),
            'total_amount' => $reservation->total_amount, 'currency' => $reservation->currency, 'property' => $reservation->property?->only(['id', 'name', 'slug']),
            'guest' => $detail ? $reservation->guest?->only(['full_name', 'email', 'phone']) : null,
            'price_lines' => $detail ? $reservation->priceLines?->map(fn ($line) => $line->only(['label', 'amount', 'currency'])) : null,
        ], fn ($value) => $value !== null);
    }
}