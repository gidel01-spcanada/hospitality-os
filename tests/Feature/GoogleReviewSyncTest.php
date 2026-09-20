<?php

namespace Tests\Feature;

use App\Models\Establishment;
use App\Models\SiteReview;
use App\Services\GoogleReviewSyncService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GoogleReviewSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_google_review_sync_imports_and_updates_csv_reviews_idempotently(): void
    {
        $this->seed(DatabaseSeeder::class);
        $establishment = Establishment::query()->firstOrFail();
        $establishment->update(['google_reviews_import_url' => 'https://example.test/google-reviews.csv']);
        $csv = "Review Date,Reviewer Name,Star Rating,Comment\n2027-01-10,Google Guest,5,Excellent stay\n";
        $updatedCsv = "Review Date,Reviewer Name,Star Rating,Comment\n2027-01-10,Google Guest,4,Updated review\n";
        Http::fake(['https://example.test/*' => Http::sequence()
            ->push($csv, 200, ['Content-Type' => 'text/csv'])
            ->push($updatedCsv, 200, ['Content-Type' => 'text/csv'])]);

        $service = app(GoogleReviewSyncService::class);
        $this->assertSame(1, $service->sync($establishment));
        $this->assertSame(1, SiteReview::query()->where('establishment_id', $establishment->id)->where('source', 'google')->count());

        $service->sync($establishment->fresh());

        $this->assertSame(1, SiteReview::query()->where('establishment_id', $establishment->id)->where('source', 'google')->count());
        $this->assertDatabaseHas('site_reviews', ['reviewer_name' => 'Google Guest', 'rating' => 4, 'review_text' => 'Updated review']);
        $this->assertNotNull($establishment->fresh()->google_reviews_last_sync_at);
    }
}
