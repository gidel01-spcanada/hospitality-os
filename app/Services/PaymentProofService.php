<?php

namespace App\Services;

use App\Models\PaymentAttempt;
use App\Models\Reservation;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PaymentProofService
{
    private const PRIVATE_PREFIX = 'payment-proofs/';

    public const PAYMENT = 'payment_proof';

    public const REFUND = 'refund_proof';

    /** Offline transfers need a staff-uploaded refund proof on cancellation; cash (pay_later) and online gateways do not. */
    public const REFUND_PROOF_PROVIDERS = ['interac', 'wise', 'revolut', 'offline'];

    public function attach(Reservation $reservation, PaymentAttempt $attempt, UploadedFile $file, int|string|null $uploadedBy, array $attributes = [], string $key = self::PAYMENT): PaymentAttempt
    {
        $this->delete($reservation, $attempt, $key);

        $filename = now()->format('YmdHisv') . '-' . Str::lower(Str::random(12)) . '.' . strtolower($file->extension() ?: $file->getClientOriginalExtension());
        $relativePath = Storage::disk('local')->putFileAs(self::PRIVATE_PREFIX . $reservation->id, $file, $filename);

        $attempt->update(array_merge($attributes, [
            'payload' => array_merge((array) $attempt->payload, [
                $key => [
                    'path' => $relativePath,
                    'original_name' => $file->getClientOriginalName(),
                    'uploaded_by' => $uploadedBy,
                    'uploaded_at' => now()->toIso8601String(),
                ],
            ]),
        ]));

        return $attempt;
    }

    /** Absolute path of the stored proof, or null when missing or outside the reservation's folders. */
    public function resolve(Reservation $reservation, PaymentAttempt $attempt, string $key = self::PAYMENT): ?string
    {
        $relativePath = (string) data_get($attempt->payload, $key . '.path');
        if ($relativePath === '' || (int) $attempt->reservation_id !== (int) $reservation->id) {
            return null;
        }

        // Legacy proofs were stored under a public "uploads/" folder that may live in either web root.
        $candidates = str_starts_with($relativePath, self::PRIVATE_PREFIX)
            ? [[Storage::disk('local')->path(self::PRIVATE_PREFIX . $reservation->id), Storage::disk('local')->path($relativePath)]]
            : collect([rtrim((string) config('filesystems.public_upload_path'), '/\\'), public_path('uploads')])
                ->filter()->unique()
                ->map(fn (string $root) => [$root . '/reservations/' . $reservation->id, $root . '/' . Str::after($relativePath, 'uploads/')])
                ->all();

        foreach ($candidates as [$directory, $path]) {
            $directory = realpath($directory);
            $path = realpath($path);
            if ($directory && $path && str_starts_with($path, $directory . DIRECTORY_SEPARATOR) && is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    public function delete(Reservation $reservation, PaymentAttempt $attempt, ?string $key = null): void
    {
        foreach ($key ? [$key] : [self::PAYMENT, self::REFUND] as $proofKey) {
            if ($path = $this->resolve($reservation, $attempt, $proofKey)) {
                File::delete($path);
            }
        }
    }
}