<?php

namespace App\Http\Controllers;

use App\Models\PaymentAttempt;
use App\Models\Receipt;
use App\Models\Reservation;
use App\Services\ReservationEmailService;
use App\Services\PaymentProofService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReceiptController extends Controller
{
    public function confirmOfflinePayment(Request $request, Reservation $reservation, ReservationEmailService $emailService, PaymentProofService $proofs): RedirectResponse
    {
        $validated = $request->validate([
            'provider_reference' => ['nullable', 'string', 'max:120'],
            'payment_proof' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
        ]);

        $attempt = $reservation->paymentAttempts()->whereIn('provider', ['interac', 'wise', 'revolut'])->latest()->first();
        if ($attempt) {
            $attempt->update([
                'provider_reference' => $validated['provider_reference'] ?: $attempt->provider_reference,
                'status' => 'paid',
                'payload' => array_merge((array) $attempt->payload, ['confirmed_by' => auth()->id(), 'confirmed_at' => now()->toIso8601String()]),
            ]);
        } else {
            $attempt = $reservation->paymentAttempts()->create([
                'provider' => 'offline',
                'provider_reference' => $validated['provider_reference'] ?? 'offline-' . Str::lower(Str::random(12)),
                'currency' => $reservation->currency,
                'amount' => $reservation->total_amount,
                'status' => 'paid',
                'idempotency_key' => 'offline-' . $reservation->id . '-' . now()->format('YmdHis'),
                'payload' => ['confirmed_by' => auth()->id()],
            ]);
        }

        $proofs->attach($reservation, $attempt, $validated['payment_proof'], auth()->id());

        $reservation->update([
            'status' => 'confirmed',
            'notes' => trim(($reservation->notes ?? '') . PHP_EOL . 'Offline payment confirmed by admin.'),
        ]);

        $emailService->issueReceiptAndQueueEmail($reservation, $attempt, auth()->user()?->email);
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

    public function downloadProof(Request $request, Reservation $reservation, PaymentAttempt $attempt): BinaryFileResponse
    {
        $this->authorizeReservation($request, $reservation, allowCheckoutToken: true);
        abort_unless($attempt->reservation_id === $reservation->id, 404);

        $relativePath = (string) data_get($attempt->payload, 'payment_proof.path');
        $proofDirectory = realpath(public_path('uploads/reservations/' . $reservation->id));
        $proofPath = $relativePath ? realpath(public_path($relativePath)) : false;
        abort_unless(
            $proofDirectory && $proofPath && str_starts_with($proofPath, $proofDirectory . DIRECTORY_SEPARATOR) && is_file($proofPath),
            404
        );

        $downloadName = basename((string) data_get($attempt->payload, 'payment_proof.original_name', basename($proofPath)));

        return response()->download($proofPath, $downloadName);
    }

    private function authorizeReservation(Request $request, Reservation $reservation, bool $allowCheckoutToken = false): void
    {
        $token = (string) $request->query('token', '');
        $hasValidCheckoutToken = $allowCheckoutToken
            && $token !== ''
            && $reservation->checkout_token
            && hash_equals($reservation->checkout_token, $token);

        abort_unless(
            ($request->user() && (
                $reservation->user_id === $request->user()->id
                || strtolower((string) $reservation->email) === strtolower((string) $request->user()->email)
            ))
                || ($request->user()?->canManageReservations())
                || $hasValidCheckoutToken,
            403
        );
    }
}
