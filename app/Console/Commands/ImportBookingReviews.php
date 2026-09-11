<?php

namespace App\Console\Commands;

use App\Models\Establishment;
use App\Models\SiteReview;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ImportBookingReviews extends Command
{
    protected $signature = 'reviews:import-booking-csv {path : Absolute or app-relative CSV path} {--dry-run : Parse the CSV without saving rows}';

    protected $description = 'Import Booking.com review exports into local site reviews';

    public function handle(): int
    {
        $path = (string) $this->argument('path');
        $path = file_exists($path) ? $path : base_path($path);

        if (! is_file($path)) {
            $this->error("CSV file not found: {$path}");

            return self::FAILURE;
        }

        $handle = fopen($path, 'rb');

        if (! $handle) {
            $this->error("CSV file cannot be opened: {$path}");

            return self::FAILURE;
        }

        fgetcsv($handle);

        $tenantId = Establishment::query()->value('tenant_id');
        $inserted = 0;
        $updated = 0;
        $skipped = 0;
        $dryRun = (bool) $this->option('dry-run');

        while (($row = fgetcsv($handle)) !== false) {
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
            $reviewText = $this->reviewText($title, $positive, $negative);
            $rating = max(1, min(5, (int) round($score / 2)));
            $attributes = [
                'tenant_id' => $tenantId,
                'source' => 'booking',
                'reviewer_name' => $reviewerName,
                'reviewed_at' => $reviewedAt,
            ];

            $review = SiteReview::withoutGlobalScopes()->firstOrNew($attributes);
            $review->exists ? $updated++ : $inserted++;

            if (! $dryRun) {
                $review->fill($attributes + [
                    'rating' => $rating,
                    'review_text' => $reviewText !== '' ? $reviewText : null,
                    'source_url' => null,
                    'is_active' => true,
                ])->save();
            }
        }

        fclose($handle);

        $this->info(($dryRun ? 'Dry run complete.' : 'Import complete.') . " Inserted: {$inserted}; updated: {$updated}; skipped: {$skipped}.");

        return self::SUCCESS;
    }

    private function reviewText(string $title, string $positive, string $negative): string
    {
        $parts = array_values(array_filter([$title, $positive], fn (string $value): bool => $value !== ''));

        if ($negative !== '') {
            $parts[] = __('messages.reviews.negative_note', ['comment' => $negative]);
        }

        return trim(implode("\n\n", $parts));
    }
}
