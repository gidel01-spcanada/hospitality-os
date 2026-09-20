<?php

namespace App\Services;

use App\Models\SiteReview;
use App\Support\CurrentTenant;
use Carbon\Carbon;

class ReviewImportService
{
    /** Supported export formats for the review importer. */
    public const FORMATS = ['generic', 'booking', 'airbnb', 'google'];

    /** Column header aliases (lowercased) accepted for each mapped field, per source. */
    private const HEADER_ALIASES = [
        'airbnb' => [
            'reviewer_name' => ['reviewer', 'guest', 'guest name', 'author'],
            'rating' => ['rating', 'overall rating', 'star rating', 'stars'],
            'review_text' => ['public review', 'review', 'comment', 'review_text'],
            'reviewed_at' => ['date', 'review date', 'reviewed_at'],
            'source_url' => ['listing url', 'url', 'source_url'],
        ],
        'google' => [
            'reviewer_name' => ['reviewer name', 'author', 'author name', 'name'],
            'rating' => ['star rating', 'rating', 'stars'],
            'review_text' => ['comment', 'review', 'review_text', 'text'],
            'reviewed_at' => ['review date', 'date', 'reviewed_at'],
            'source_url' => ['review link', 'review url', 'url', 'source_url'],
        ],
    ];

    /**
     * Import the simple generic CSV format: reviewer_name,source,rating,review_text,source_url
     */
    public function importGenericCsv(string $csv, ?int $tenantId, ?int $establishmentId): int
    {
        $rows = str_getcsv(trim($csv), "\n");
        $inserted = 0;

        foreach ($rows as $lineIndex => $line) {
            if ($lineIndex === 0 && stripos($line, 'reviewer_name') !== false) {
                continue;
            }

            $fields = str_getcsv($line, ',');
            if (count($fields) < 5) {
                continue;
            }

            $reviewerName = trim((string) ($fields[0] ?? ''));
            $source = trim((string) ($fields[1] ?? 'google'));
            $rating = (int) trim((string) ($fields[2] ?? 5));
            $reviewText = trim((string) ($fields[3] ?? ''));
            $sourceUrl = trim((string) ($fields[4] ?? ''));

            if ($reviewerName === '') {
                continue;
            }

            SiteReview::query()->create([
                'tenant_id' => $tenantId,
                'establishment_id' => $establishmentId,
                'source' => in_array($source, ['booking', 'google'], true) ? $source : 'google',
                'reviewer_name' => $reviewerName,
                'rating' => max(1, min(5, $rating)),
                'review_text' => $reviewText !== '' ? $reviewText : null,
                'source_url' => $sourceUrl !== '' ? $sourceUrl : null,
                'reviewed_at' => now(),
                'is_active' => true,
            ]);

            $inserted++;
        }

        return $inserted;
    }

    /**
     * Import a Booking.com "Guest reviews" export CSV:
     * date,reviewer_name,?,title,positive,negative,score
     *
     * @return array{inserted: int, updated: int, skipped: int}
     */
    public function importBookingCsv(string $csv, ?int $tenantId, ?int $establishmentId): array
    {
        $inserted = 0;
        $updated = 0;
        $skipped = 0;

        $lines = preg_split('/\r\n|\r|\n/', trim($csv));
        array_shift($lines); // header row

        foreach ($lines as $line) {
            if (trim((string) $line) === '') {
                continue;
            }

            $row = str_getcsv($line, ',');
            if (count($row) < 7) {
                $skipped++;
                continue;
            }

            $reviewedAt = trim((string) ($row[0] ?? ''));
            $reviewerName = trim((string) ($row[1] ?? ''));
            $title = trim((string) ($row[3] ?? ''));
            $positive = trim((string) ($row[4] ?? ''));
            $negative = trim((string) ($row[5] ?? ''));
            $score = (float) str_replace(',', '.', (string) ($row[6] ?? '0'));

            if ($reviewedAt === '' || $reviewerName === '' || $score <= 0) {
                $skipped++;
                continue;
            }

            $reviewedAt = Carbon::parse($reviewedAt);
            $reviewText = $this->bookingReviewText($title, $positive, $negative);
            $rating = max(1, min(5, (int) round($score / 2)));
            $attributes = [
                'tenant_id' => $tenantId,
                'establishment_id' => $establishmentId,
                'source' => 'booking',
                'reviewer_name' => $reviewerName,
                'reviewed_at' => $reviewedAt,
            ];

            $review = SiteReview::withoutGlobalScopes()->firstOrNew($attributes);
            $review->exists ? $updated++ : $inserted++;

            $review->fill($attributes + [
                'rating' => $rating,
                'review_text' => $reviewText !== '' ? $reviewText : null,
                'source_url' => null,
                'is_active' => true,
            ])->save();
        }

        return ['inserted' => $inserted, 'updated' => $updated, 'skipped' => $skipped];
    }

    /**
     * Import an Airbnb host review export CSV, matched by column headers rather than
     * fixed positions since Airbnb's export tools vary in column order.
     */
    public function importAirbnbCsv(string $csv, ?int $tenantId, ?int $establishmentId): int
    {
        return $this->importHeaderMappedCsv($csv, 'airbnb', $tenantId, $establishmentId);
    }

    /**
     * Import a Google Business Profile review export CSV, matched by column headers.
     */
    public function importGoogleCsv(string $csv, ?int $tenantId, ?int $establishmentId): int
    {
        return $this->importHeaderMappedCsv($csv, 'google', $tenantId, $establishmentId);
    }

    private function importHeaderMappedCsv(string $csv, string $source, ?int $tenantId, ?int $establishmentId): int
    {
        $tenantId ??= app(CurrentTenant::class)->id();
        $lines = preg_split('/\r\n|\r|\n/', trim($csv));
        $header = str_getcsv((string) array_shift($lines), ',');
        $columns = $this->mapHeaderColumns($header, self::HEADER_ALIASES[$source]);
        $inserted = 0;

        foreach ($lines as $line) {
            if (trim((string) $line) === '') {
                continue;
            }

            $fields = str_getcsv($line, ',');
            $reviewerName = trim((string) ($fields[$columns['reviewer_name']] ?? ''));
            $rating = (float) str_replace(',', '.', (string) ($fields[$columns['rating']] ?? '0'));

            if ($reviewerName === '' || $rating <= 0) {
                continue;
            }

            $reviewedAt = trim((string) ($fields[$columns['reviewed_at']] ?? ''));
            $reviewText = trim((string) ($fields[$columns['review_text']] ?? ''));
            $sourceUrl = trim((string) ($fields[$columns['source_url']] ?? ''));

            $reviewedAtValue = $reviewedAt !== '' ? Carbon::parse($reviewedAt) : now();
            $identity = [
                'tenant_id' => $tenantId,
                'establishment_id' => $establishmentId,
                'source' => $source,
                'reviewer_name' => $reviewerName,
            ];
            $review = SiteReview::withoutGlobalScopes()
                ->where('establishment_id', $establishmentId)
                ->where('source', $source)
                ->where('reviewer_name', $reviewerName)
                ->first()
                ?? new SiteReview();
            $review->fill([
                ...$identity,
                'reviewed_at' => $reviewedAtValue,
                'rating' => max(1, min(5, (int) round($rating))),
                'review_text' => $reviewText !== '' ? $reviewText : null,
                'source_url' => $sourceUrl !== '' ? $sourceUrl : null,
                'is_active' => true,
            ])->save();

            $inserted++;
        }

        return $inserted;
    }

    /** @param array<string, array<int, string>> $aliases */
    private function mapHeaderColumns(array $header, array $aliases): array
    {
        $normalized = array_map(fn (string $value): string => strtolower(trim($value)), $header);
        $columns = [];

        foreach ($aliases as $field => $candidates) {
            $columns[$field] = null;
            foreach ($candidates as $candidate) {
                $index = array_search($candidate, $normalized, true);
                if ($index !== false) {
                    $columns[$field] = $index;
                    break;
                }
            }
        }

        return $columns;
    }

    private function bookingReviewText(string $title, string $positive, string $negative): string
    {
        $parts = array_values(array_filter([$title, $positive], fn (string $value): bool => $value !== ''));

        if ($negative !== '') {
            $parts[] = __('messages.reviews.negative_note', ['comment' => $negative]);
        }

        return trim(implode("\n\n", $parts));
    }
}
