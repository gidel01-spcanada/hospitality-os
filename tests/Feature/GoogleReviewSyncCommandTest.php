<?php

namespace Tests\Feature;

use App\Models\Establishment;
use App\Models\SiteReview;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GoogleReviewSyncCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_syncs_configured_establishment_source(): void
    {
        $this->seed(DatabaseSeeder::class);
        $establishment = Establishment::query()->firstOrFail();
        $establishment->update(['google_reviews_import_url' => 'https://example.test/google.csv']);
        Http::fake(['https://example.test/*' => Http::response(
            "Review Date,Reviewer Name,Star Rating,Comment\n2027-04-01,Command Guest,5,Automated sync\n",
            200,
            ['Content-Type' => 'text/csv'],
        )]);

        $this->artisan('reviews:sync-google')
            ->expectsOutputToContain($establishment->name)
            ->assertExitCode(0);

        $this->assertDatabaseHas('site_reviews', [
            'establishment_id' => $establishment->id,
            'reviewer_name' => 'Command Guest',
            'source' => 'google',
        ]);
    }
}
