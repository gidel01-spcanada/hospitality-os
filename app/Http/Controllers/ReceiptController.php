<?php

namespace App\Http\Controllers;

use App\Models\PaymentAttempt;
use App\Models\Receipt;
use App\Models\Reservation;
use App\Services\ReservationEmailService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

class ReceiptController extends Controller
{
    public function confirmOfflinePayment(Request $request, Reservation $reservation, ReservationEmailService $emailService): RedirectResponse
    {
        $validated = $request->validate([
            'provider_reference' => ['nullable', 'string', 'max:120'],
        ]);

        $attempt = $reservation->paymentAttempts()->create([
            'provider' => 'offline',
            'provider_reference' => $validated['provider_reference'] ?? 'offline-' . Str::lower(Str::random(12)),
            'currency' => $reservation->currency,
            'amount' => $reservation->total_amount,
            'status' => 'paid',
            'idempotency_key' => 'offline-' . $reservation->id . '-' . now()->format('YmdHis'),
            'payload' => ['confirmed_by' => auth()->id()],
        ]);

        $reservation->update([
            'status' => 'confirmed',
            'notes' => trim(($reservation->notes ?? '') . PHP_EOL . 'Offline payment confirmed by admin.'),
        ]);

        $receipt = $emailService->issueReceiptAndQueueEmail($reservation, $attempt, auth()->user()?->email);
        $emailService->queueStatusUpdate($reservation, 'confirmed');

        return back()->with('status', __('messages.receipts.confirmed_and_sent'));
    }

    public function download(Request $request, Reservation $reservation): Response
    {
        $this->authorizeReservation($request, $reservation);
        $receipt = $reservation->receipts()->latest('issued_at')->firstOrFail();
        $reservation->load(['property', 'guest', 'priceLines']);

        return Pdf::loadView('receipts.show', compact('receipt', 'reservation'))
            ->download($receipt->receipt_number . '.pdf');
    }

    private function authorizeReservation(Request $request, Reservation $reservation): void
    {
        abort_unless(
            ($request->user() && $reservation->user_id === $request->user()->id)
                || ($request->user()?->canManageReservations()),
            403
        );
    }
}
