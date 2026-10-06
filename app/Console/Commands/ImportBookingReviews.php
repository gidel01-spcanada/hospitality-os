<?php

namespace App\Console\Commands;

use App\Models\Establishment;
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

        $csv = file_get_contents($path);
        if ($csv === false) {
            $this->error("CSV file cannot be opened: {$path}");

            return self::FAILURE;
        }
        $tenantId = Establishment::query()->value('tenant_id');
        $dryRun = (bool) $this->option('dry-run');
        $result = app(\App\Services\ReviewImportService::class)->importBookingCsv($csv, $tenantId, null, $dryRun);

        $this->info(($dryRun ? 'Dry run complete.' : 'Import complete.') . " Inserted: {$result['inserted']}; updated: {$result['updated']}; skipped: {$result['skipped']}.");

        return self::SUCCESS;
    }
}
