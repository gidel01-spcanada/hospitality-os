<?php

namespace App\Services;

use App\Models\Establishment;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GoogleReviewSyncService
{
    public function sync(Establishment $establishment): int
    {
        $url = trim((string) $establishment->google_reviews_import_url);
        if ($url === '') {
            throw new RuntimeException(__('messages.review_request.google_import_url_missing'));
        }

        try {
            $response = Http::withHeaders(['Accept' => 'text/csv, application/json'])
                ->timeout(20)
                ->retry(2, 250, throw: false)
                ->get($url);
            if (! $response->successful()) {
                throw new RuntimeException('Google review source returned HTTP '.$response->status());
            }

            $contentType = strtolower((string) $response->header('Content-Type'));
            $payload = $response->body();
            if (str_contains($contentType, 'json')) {
                $payload = $this->jsonToCsv($response->json());
            }
            $count = app(ReviewImportService::class)->importGoogleCsv($payload, $establishment->tenant_id, $establishment->id);

            $establishment->update([
                'google_reviews_last_sync_at' => now(),
                'google_reviews_last_sync_error' => null,
            ]);

            return $count;
        } catch (\Throwable $exception) {
            $establishment->update(['google_reviews_last_sync_error' => $exception->getMessage()]);
            throw $exception;
        }
    }

    private function jsonToCsv(mixed $payload): string
    {
        $rows = data_get($payload, 'reviews', is_array($payload) ? $payload : []);
        if (! is_array($rows) || $rows === []) {
            throw new RuntimeException(__('messages.review_request.google_import_invalid_payload'));
        }

        $output = fopen('php://temp', 'w+');
        fputcsv($output, ['Review Date', 'Reviewer Name', 'Star Rating', 'Comment', 'Review Link']);
        foreach ($rows as $row) {
            if (! is_array($row)) continue;
            fputcsv($output, [
                $row['review_date'] ?? $row['date'] ?? '',
                $row['reviewer_name'] ?? $row['author'] ?? $row['name'] ?? '',
                $row['rating'] ?? $row['stars'] ?? '',
                $row['review_text'] ?? $row['comment'] ?? $row['text'] ?? '',
                $row['source_url'] ?? $row['review_url'] ?? $row['url'] ?? '',
            ]);
        }
        rewind($output);
        return stream_get_contents($output) ?: '';
    }
}
