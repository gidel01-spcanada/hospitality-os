<?php

namespace App\Services;

use App\Models\SiteReview;
use App\Models\Establishment;
use App\Models\Property;
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
    public function importBookingCsv(string $csv, ?int $tenantId, ?int $establishmentId, bool $dryRun = false): array
    {
        $inserted = 0;
        $updated = 0;
        $skipped = 0;

        $stream = fopen('php://temp', 'r+');
        fwrite($stream, preg_replace('/^\xEF\xBB\xBF/', '', $csv));
        rewind($stream);
        $header = fgetcsv($stream, null, ',', '"', '');
        if ($header === false) {
            fclose($stream);

            return ['inserted' => 0, 'updated' => 0, 'skipped' => 0];
        }

        $normalizedHeader = array_map(fn ($value) => $this->normalizeCsvValue((string) $value), $header);
        $columns = [
            'date' => $this->headerColumn($normalizedHeader, ['date', 'review date', 'reviewed at', 'date du commentaire'], 0),
            'reviewer' => $this->headerColumn($normalizedHeader, ['reviewer name', 'reviewer', 'guest name', 'nom du client'], 1),
            'title' => $this->headerColumn($normalizedHeader, ['title', 'review title', 'titre du commentaire'], 3),
            'positive' => $this->headerColumn($normalizedHeader, ['positive', 'positive comments', 'commentaire positif'], 4),
            'negative' => $this->headerColumn($normalizedHeader, ['negative', 'negative comments', 'commentaire negatif', 'commentaire n gatif'], 5),
            'score' => $this->headerColumn($normalizedHeader, ['score', 'review score', 'rating', 'note', 'note des commentaires'], 6),
            'property' => $this->propertyHeaderColumn($normalizedHeader),
        ];

        $establishment = $establishmentId ? Establishment::query()->with('properties.translations')->find($establishmentId) : null;
        $tenantId ??= $establishment?->tenant_id;
        $propertyColumn = $columns['property'];
        $properties = $establishment?->properties;
        if (! $establishment && $propertyColumn !== null) {
            $properties = Property::query()
                ->with(['translations', 'establishment'])
                ->when($tenantId, fn ($query) => $query->whereHas('establishment', fn ($establishmentQuery) => $establishmentQuery->where('tenant_id', $tenantId)))
                ->get();
        }
        $properties ??= collect();
        $propertyMap = $properties
            ->flatMap(function (Property $property): array {
                $names = collect([$property->name, $property->slug])
                    ->merge($property->translations->pluck('name'))
                    ->filter();

                return $names->map(fn ($name) => [$this->normalizeCsvValue((string) $name), $property])->all();
            })
            ->keyBy(fn ($pair) => $pair[0]) ?? collect();

        while (($row = fgetcsv($stream, null, ',', '"', '')) !== false) {
            if (count($row) === 1 && trim((string) $row[0]) === '') {
                continue;
            }

            if (count($row) < 7) {
                $skipped++;
                continue;
            }

            $reviewedAtText = trim((string) ($row[$columns['date']] ?? ''));
            $reviewerName = trim((string) ($row[$columns['reviewer']] ?? ''));
            $title = trim((string) ($row[$columns['title']] ?? ''));
            $positive = trim((string) ($row[$columns['positive']] ?? ''));
            $negative = trim((string) ($row[$columns['negative']] ?? ''));
            $score = (float) str_replace(',', '.', trim((string) ($row[$columns['score']] ?? '0')));

            if ($reviewedAtText === '' || $reviewerName === '' || $score <= 0) {
                $skipped++;
                continue;
            }

            try {
                $reviewedAt = $this->parseBookingDate($reviewedAtText);
            } catch (\Throwable) {
                $skipped++;
                continue;
            }

            $property = null;
            if ($propertyColumn !== null) {
                $propertyName = $this->normalizeCsvValue((string) ($row[$propertyColumn] ?? ''));
                $property = $propertyName !== '' ? $propertyMap->get($propertyName)[1] ?? null : null;
                if (! $property) {
                    $skipped++;
                    continue;
                }
            }

            $reviewText = $this->bookingReviewText($title, $positive, $negative);
            $rating = max(1, min(5, (int) round($score / 2)));
            $attributes = [
                'tenant_id' => $property?->establishment?->tenant_id ?? $tenantId,
                'establishment_id' => $property?->establishment_id ?? $establishmentId,
                'property_id' => $property?->id,
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
            ]);
            if (! $dryRun) {
                $review->save();
            }
        }

        fclose($stream);

        return ['inserted' => $inserted, 'updated' => $updated, 'skipped' => $skipped];
    }

    private function headerColumn(array $header, array $aliases, int $fallback): int
    {
        foreach ($header as $index => $value) {
            if (in_array($value, $aliases, true)) {
                return $index;
            }
        }

        return $fallback;
    }

    private function propertyHeaderColumn(array $header): ?int
    {
        foreach ($header as $index => $value) {
            if (str_contains($value, 'propriet') || str_contains($value, 'property') || in_array($value, ['room', 'room name'], true)) {
                return $index;
            }
        }

        return null;
    }

    private function normalizeCsvValue(string $value): string
    {
        $value = preg_replace('/^\xEF\xBB\xBF/', '', trim($value));
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        $value = strtolower($ascii !== false ? $ascii : $value);

        return trim(preg_replace('/[^a-z0-9]+/', ' ', $value));
    }

    private function parseBookingDate(string $value): Carbon
    {
        foreach (['n-j-Y H:i', 'n-j-Y H:i:s', 'm-d-Y H:i', 'm-d-Y H:i:s', 'Y-m-d H:i', 'Y-m-d H:i:s', 'Y-m-d'] as $format) {
            try {
                $date = Carbon::createFromFormat('!' . $format, $value);
                if ($date !== false) {
                    return $date;
                }
            } catch (\Throwable) {
            }
        }

        return Carbon::parse($value);
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
