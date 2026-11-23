<?php

namespace Tests\Feature;

use App\Models\Establishment;
use App\Models\SiteReview;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ReviewImportService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class BookingReviewImportTest extends TestCase
{
    use RefreshDatabase;

    private const HEADER = "Review date,Guest name,Country,Title,Positive,Negative,Score";

    public function test_establishment_reimport_updates_the_original_reviews(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::where('email', 'admin@afrikappart.test')->firstOrFail();
        $establishment = Establishment::firstOrFail();
        $url = route('admin.establishments.reviews.import', $establishment);
        $csv = self::HEADER . "\n2026-02-10,Guest A,BJ,Great,Clean,Noisy,9\n2026-02-11,Guest B,FR,Fine,Good,,7.5";

        $this->actingAs($admin)->post($url, [
            'format' => 'booking',
            'import_file' => UploadedFile::fake()->createWithContent('reviews.csv', $csv),
        ])
            ->assertSessionHas('success', __('messages.flash.reviews_imported_detailed', ['inserted' => 2, 'updated' => 0, 'skipped' => 0]));
        $ids = SiteReview::withoutGlobalScopes()->where('establishment_id', $establishment->id)
            ->where('source', 'booking')->orderBy('reviewer_name')->pluck('id')->all();

        $this->post($url, ['format' => 'booking', 'csv' => str_replace('Clean,Noisy,9', 'Updated,Noisy,5', $csv)])
            ->assertSessionHas('success', __('messages.flash.reviews_imported_detailed', ['inserted' => 0, 'updated' => 2, 'skipped' => 0]));
        $reviews = SiteReview::withoutGlobalScopes()->where('establishment_id', $establishment->id)
            ->where('source', 'booking')->orderBy('reviewer_name')->get();

        $this->assertSame($ids, $reviews->pluck('id')->all());
        $this->assertCount(2, $reviews);
        $this->assertSame(3, $reviews[0]->rating);
        $this->assertStringContainsString('Updated', $reviews[0]->review_text);
    }

    public function test_booking_identity_includes_tenant_establishment_property_reviewer_and_time(): void
    {
        $this->seed(DatabaseSeeder::class);
        $establishment = Establishment::firstOrFail();
        $properties = $establishment->properties()->orderBy('id')->take(2)->get();
        $this->assertCount(2, $properties);

        $service = app(ReviewImportService::class);
        $header = self::HEADER . ',Property';
        $firstProperty = $properties[0]->slug;
        $secondProperty = $properties[1]->slug;
        $csv = $header
            . "\n2026-02-10 10:00:00,Guest,BJ,First,Good,,9,$firstProperty"
            . "\n2026-02-10 11:00:00,Guest,BJ,Second,Good,,8,$firstProperty"
            . "\n2026-02-10 10:00:00,Other,BJ,Third,Good,,8,$firstProperty"
            . "\n2026-02-10 10:00:00,Guest,BJ,Fourth,Good,,8,$secondProperty";
        $this->assertSame(['inserted' => 4, 'updated' => 0, 'skipped' => 0], $service->importBookingCsv($csv, null, $establishment->id));
        $this->assertSame(['inserted' => 0, 'updated' => 4, 'skipped' => 0], $service->importBookingCsv($csv, null, $establishment->id));
        $this->assertSame(4, SiteReview::withoutGlobalScopes()->where('establishment_id', $establishment->id)->where('source', 'booking')->count());
        $this->assertSame(['inserted' => 0, 'updated' => 4, 'skipped' => 0], $service->importBookingCsv(str_replace('First,Good,,9', 'Changed,Good,,2', $csv), null, $establishment->id, true));
        $this->assertDatabaseHas('site_reviews', ['establishment_id' => $establishment->id, 'reviewer_name' => 'Guest', 'review_text' => "First\n\nGood", 'rating' => 5]);

        $otherTenant = Tenant::create(['name' => 'Other tenant', 'slug' => 'booking-import-other']);
        $unattachedCsv = self::HEADER . "\n2026-02-10 10:00:00,Guest,BJ,Title,Good,,9";
        $this->assertSame(['inserted' => 1, 'updated' => 0, 'skipped' => 0], $service->importBookingCsv($unattachedCsv, $establishment->tenant_id, null));
        $this->assertSame(['inserted' => 1, 'updated' => 0, 'skipped' => 0], $service->importBookingCsv($unattachedCsv, $otherTenant->id, null));
        $this->assertSame(['inserted' => 0, 'updated' => 1, 'skipped' => 0], $service->importBookingCsv($unattachedCsv, $otherTenant->id, null));

        $otherEstablishment = $establishment->replicate();
        $otherEstablishment->slug = 'booking-import-other-establishment';
        $otherEstablishment->save();
        $this->assertSame(['inserted' => 1, 'updated' => 0, 'skipped' => 0], $service->importBookingCsv($unattachedCsv, null, $otherEstablishment->id));
        $this->assertSame(['inserted' => 0, 'updated' => 1, 'skipped' => 0], $service->importBookingCsv($unattachedCsv, null, $otherEstablishment->id));
    }

    public function test_invalid_booking_date_returns_an_error_without_partial_import(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::where('email', 'admin@afrikappart.test')->firstOrFail();
        $establishment = Establishment::firstOrFail();
        $existing = SiteReview::create([
            'tenant_id' => $establishment->tenant_id,
            'establishment_id' => $establishment->id,
            'source' => 'booking',
            'reviewer_name' => 'Existing',
            'reviewed_at' => '2026-02-09 00:00:00',
            'rating' => 1,
        ]);
        $csv = self::HEADER . "\n2026-02-09,Existing,BJ,Title,Good,,9\n2026-02-10,Valid,BJ,Title,Good,,9\nnot-a-date,Invalid,BJ,Title,Good,,8";

        $this->actingAs($admin)->from(route('admin.establishments.edit', $establishment))->post(route('admin.establishments.reviews.import', $establishment), [
            'format' => 'booking', 'csv' => $csv,
        ])->assertSessionHasErrors(['import_file' => __('messages.errors.booking_review_invalid_date', ['record' => 4])]);
        $this->assertDatabaseMissing('site_reviews', ['reviewer_name' => 'Valid']);
        $this->assertDatabaseMissing('site_reviews', ['reviewer_name' => 'Invalid']);
        $this->assertSame(1, $existing->fresh()->rating);
        $this->get(route('admin.establishments.edit', $establishment))
            ->assertOk()
            ->assertSee(__('messages.errors.booking_review_invalid_date', ['record' => 4]));
    }

    public function test_booking_command_reports_an_invalid_date_and_fails_even_in_dry_run(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'booking-test-');
        file_put_contents($path, self::HEADER . "\n2026-02-30,Invalid,BJ,Title,Good,,8");

        try {
            $this->artisan('reviews:import-booking-csv', ['path' => $path, '--dry-run' => true])
                ->expectsOutput(__('messages.errors.booking_review_invalid_date', ['record' => 2]))
                ->assertExitCode(1);
            $this->assertDatabaseMissing('site_reviews', ['reviewer_name' => 'Invalid']);
        } finally {
            unlink($path);
        }
    }
}
