<?php

namespace App\Services;

use App\Models\PaymentAttempt;
use App\Models\Reservation;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class PaymentProofService
{
    public function attach(Reservation $reservation, PaymentAttempt $attempt, UploadedFile $file, ?int $userId): PaymentAttempt
    {
        $directory = rtrim(config('filesystems.public_upload_path'), '/\\') . DIRECTORY_SEPARATOR . 'reservations' . DIRECTORY_SEPARATOR . $reservation->id;
        File::ensureDirectoryExists($directory);

        $oldPath = data_get($attempt->payload, 'payment_proof.path');
        if ($oldPath && File::exists(public_path($oldPath))) {
            File::delete(public_path($oldPath));
        }

        $filename = now()->format('YmdHisv') . '-' . Str::lower(Str::random(12)) . '.' . strtolower($file->getClientOriginalExtension());
        $file->move($directory, $filename);
        $relativePath = 'uploads/reservations/' . $reservation->id . '/' . $filename;

        $attempt->update([
            'payload' => array_merge((array) $attempt->payload, [
                'payment_proof' => [
                    'path' => $relativePath,
                    'original_name' => $file->getClientOriginalName(),
                    'uploaded_by' => $userId,
                    'uploaded_at' => now()->toIso8601String(),
                ],
            ]),
        ]);

        return $attempt;
    }
}