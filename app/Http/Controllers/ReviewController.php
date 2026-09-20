<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use App\Models\SiteReview;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReviewController extends Controller
{
    public function create(Request $request, Reservation $reservation): View
    {
        $this->authorizeCompletedReservation($reservation);
        $establishment = $reservation->property->establishment;
        $existingReview = SiteReview::query()->where('reservation_id', $reservation->id)->first();

        return view('reviews/submit', [
            'reservation' => $reservation,
            'establishment' => $establishment,
            'existingReview' => $existingReview,
            'googleUrl' => $establishment->google_review_url,
            'channel' => $establishment->review_channel ?: 'internal',
        ]);
    }

    public function store(Request $request, Reservation $reservation): RedirectResponse
    {
        $this->authorizeCompletedReservation($reservation);
        abort_if(SiteReview::query()->where('reservation_id', $reservation->id)->exists(), 409, __('messages.review_request.already_submitted'));

        $validated = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'review_text' => ['nullable', 'string', 'max:2000'],
        ]);
        $reservation->loadMissing('property.establishment');

        SiteReview::query()->create([
            'tenant_id' => $reservation->property->establishment->tenant_id,
            'establishment_id' => $reservation->property->establishment_id,
            'property_id' => $reservation->property_id,
            'reservation_id' => $reservation->id,
            'source' => 'website',
            'reviewer_name' => $reservation->guest?->full_name ?: __('messages.review_request.guest'),
            'rating' => $validated['rating'],
            'review_text' => $validated['review_text'] ?? null,
            'reviewed_at' => now(),
            'is_active' => true,
        ]);

        return redirect()->route('reviews.submit', ['reservation' => $reservation, 'signature' => $request->query('signature'), 'expires' => $request->query('expires')])
            ->with('review_submitted', true);
    }

    public function google(Request $request, Reservation $reservation): RedirectResponse
    {
        $this->authorizeCompletedReservation($reservation);
        $url = $reservation->property->establishment->google_review_url;
        abort_unless($url, 404);

        return redirect()->away($url);
    }

    private function authorizeCompletedReservation(Reservation $reservation): void
    {
        abort_unless($reservation->status === 'completed', 404);
    }
}