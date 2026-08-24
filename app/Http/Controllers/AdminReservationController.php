<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use App\Services\ReservationEmailService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminReservationController extends Controller
{
    public function index(): View
    {
        $reservations = Reservation::query()
            ->with(['property', 'guest'])
            ->latest()
            ->get();

        return view('admin.reservations.index', compact('reservations'));
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
}
