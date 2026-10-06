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

    public function download(Request $request, Reservation $reservation, ReservationEmailService $emailService): Response
    {
        $this->authorizeReservation($request, $reservation);
        $receipt = $reservation->receipts()->latest('issued_at')->firstOrFail();
        $reservation->load(['property', 'guest', 'priceLines']);
        $summary = \App\Support\ReservationSummary::make($reservation);
        $transaction = $emailService->receiptTransaction($receipt);
        $images = [
            'establishment' => $this->pdfImage($summary['establishment']['image'] ?? null),
            'property' => $this->pdfImage($summary['property']['image'] ?? null),
        ];

        return Pdf::loadView('receipts.show', compact('receipt', 'reservation', 'summary', 'transaction', 'images'))
            ->setPaper('a4')
            ->download($receipt->receipt_number . '.pdf');
    }

    /** Inline local images as data URIs so the PDF renderer never fetches remote URLs. */
    private function pdfImage(?string $url): ?string
    {
        $base = rtrim(asset(''), '/');
        if (! $url || ! str_starts_with($url, $base . '/')) {
            return null;
        }

        $relative = ltrim(substr($url, strlen($base)), '/');
        $uploadRoot = rtrim((string) config('filesystems.public_upload_path'), '/\\');
        $candidates = [public_path($relative)];
        if (str_starts_with($relative, 'uploads/') && $uploadRoot !== '') {
            $candidates[] = $uploadRoot . '/' . substr($relative, strlen('uploads/'));
        }

        $allowedRoots = array_filter([realpath(public_path()), $uploadRoot !== '' ? realpath($uploadRoot) : false]);

        foreach ($candidates as $path) {
            $real = realpath($path);
            if (! $real || ! is_file($real) || filesize($real) > 5 * 1024 * 1024
                || ! collect($allowedRoots)->contains(fn ($root) => str_starts_with($real, $root . DIRECTORY_SEPARATOR))) {
                continue;
            }
            $mime = mime_content_type($real);
            if (in_array($mime, ['image/jpeg', 'image/png', 'image/gif'], true)) {
                return 'data:' . $mime . ';base64,' . base64_encode((string) file_get_contents($real));
            }
        }

        return null;
    }

    public function downloadProof(Request $request, Reservation $reservation, PaymentAttempt $attempt, PaymentProofService $proofs): BinaryFileResponse
    {
        $this->authorizeReservation($request, $reservation, allowCheckoutToken: true);
        abort_unless((int) $attempt->reservation_id === (int) $reservation->id, 404);

        $key = $request->query('type') === 'refund' ? PaymentProofService::REFUND : PaymentProofService::PAYMENT;
        $proofPath = $proofs->resolve($reservation, $attempt, $key);
        abort_unless($proofPath, 404);

        $downloadName = basename((string) data_get($attempt->payload, $key . '.original_name', basename($proofPath)));

        return response()->file($proofPath, [
            'Content-Disposition' => \Symfony\Component\HttpFoundation\HeaderUtils::makeDisposition('inline', $downloadName, Str::ascii($downloadName) ?: 'payment-proof'),
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
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
                || ($request->user()?->canManageReservations() && $this->staffCanAccess($request->user(), $reservation))
                || $hasValidCheckoutToken,
            403
        );
    }

    private function staffCanAccess(\App\Models\User $user, Reservation $reservation): bool
    {
        $establishment = $reservation->property?->establishment;
        if (! $establishment || ($user->tenant_id && $establishment->tenant_id !== $user->tenant_id)) {
            return false;
        }

        return ! $user->isHost() || $user->managesEstablishment($establishment->id);
    }
}
