<?php

namespace Tests\Feature;

use App\Models\Property;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PropertyMediaImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_property_media_package_matches_expected_inventory(): void
    {
        ini_set('memory_limit', '1G');

        Artisan::call('db:seed');

        $sourceDir = base_path('photo');
        $targetDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'afrikappart-media';
        $manifestPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'afrikappart-manifest.json';

        Artisan::call('property:sync-media', [
            '--source' => $sourceDir,
            '--target' => $targetDir,
            '--manifest' => $manifestPath,
        ]);

        $manifest = json_decode((string) file_get_contents($manifestPath), true);
        $this->assertIsArray($manifest);
        $this->assertCount(4, $manifest['properties']);

        $expectedImages = [14, 12, 19, 13];
        $total = 0;
        foreach ($manifest['properties'] as $property) {
            $total += (int) $property['image_count'];
            $this->assertContains((int) $property['image_count'], $expectedImages);
        }

        $this->assertSame(58, $total);
        $this->assertSame(58, DB::table('property_images')->count());

        foreach (['appartement-401', 'appartement-402', 'appartement-403', 'appartement-404'] as $slug) {
            $property = Property::query()->where('slug', $slug)->firstOrFail();
            $this->assertNotNull($property->cover_image);
            $this->assertStringContainsString("uploads/properties/{$slug}/", $property->cover_image);
        }
    }

    public function test_dry_run_does_not_create_files_or_change_database_records(): void
    {
        $sourceDir = base_path('photo');
        $targetDir = storage_path('framework/testing/media-dry-run-' . uniqid('', true));
        $manifestPath = $targetDir . '-manifest.json';

        $establishmentId = DB::table('establishments')->insertGetId([
            'name' => 'Existing establishment',
            'slug' => 'existing-establishment',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $propertyId = DB::table('properties')->insertGetId([
            'establishment_id' => $establishmentId,
            'name' => 'Existing apartment',
            'slug' => 'appartement-401',
            'status' => 'published',
            'is_published' => true,
            'cover_image' => 'uploads/properties/appartement-401/existing.webp',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('property_images')->insert([
            'property_id' => $propertyId,
            'file_path' => 'uploads/properties/appartement-401/existing.webp',
            'file_name' => 'existing.webp',
            'mime_type' => 'image/webp',
            'sort_order' => 1,
            'is_cover' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $propertiesBefore = DB::table('properties')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all();
        $imagesBefore = DB::table('property_images')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all();

        $exitCode = Artisan::call('property:sync-media', [
            '--source' => $sourceDir,
            '--target' => $targetDir,
            '--manifest' => $manifestPath,
            '--dry-run' => true,
        ]);

        $this->assertSame(0, $exitCode);
        $this->assertDirectoryDoesNotExist($targetDir);
        $this->assertFileDoesNotExist($manifestPath);
        $propertiesAfter = DB::table('properties')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all();
        $imagesAfter = DB::table('property_images')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all();
        $this->assertSame($propertiesBefore, $propertiesAfter);
        $this->assertSame($imagesBefore, $imagesAfter);
    }

    public function test_database_seeding_does_not_run_media_sync_or_rewrite_manifest(): void
    {
        $manifestPath = base_path('seed-assets/properties/manifest.json');
        $manifestBefore = file_get_contents($manifestPath);
        $originalEnvironment = $this->app->environment();
        $this->app['env'] = 'local';

        $this->assertTrue($this->app->environment('local'));
        Artisan::shouldReceive('call')->never();

        try {
            app(DatabaseSeeder::class)->run();
        } finally {
            $this->app['env'] = $originalEnvironment;
        }

        $this->assertSame($manifestBefore, file_get_contents($manifestPath));
    }
}
