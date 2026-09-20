<?php

namespace App\Console\Commands;

use App\Models\Establishment;
use App\Services\GoogleReviewSyncService;
use Illuminate\Console\Command;

class SyncGoogleReviews extends Command
{
    protected $signature = 'reviews:sync-google {--establishment= : Limit sync to one establishment ID}';

    protected $description = 'Synchronize configured Google review CSV/JSON sources';

    public function handle(GoogleReviewSyncService $service): int
    {
        $query = Establishment::query()->whereNotNull('google_reviews_import_url');
        if ($this->option('establishment')) {
            $query->whereKey((int) $this->option('establishment'));
        }

        $processed = 0;
        $failed = 0;
        foreach ($query->get() as $establishment) {
            try {
                $count = $service->sync($establishment);
                $this->line($establishment->name.': '.$count.' review(s) synchronized.');
                $processed++;
            } catch (\Throwable $exception) {
                $this->error($establishment->name.': '.$exception->getMessage());
                $failed++;
            }
        }

        $this->info("Processed {$processed} establishment(s); {$failed} failure(s).");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
