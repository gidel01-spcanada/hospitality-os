<?php

namespace Tests\Feature;

use App\Models\Property;
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
        $targetDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'afrikappart-media-dry-run-' . uniqid('', true);
        $manifestPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'afrikappart-manifest-dry-run-' . uniqid('', true) . '.json';

        Artisan::call('property:sync-media', [
            '--source' => $sourceDir,
            '--target' => $targetDir,
            '--manifest' => $manifestPath,
            '--dry-run' => true,
        ]);

        $this->assertDirectoryDoesNotExist($targetDir);
        $this->assertFileDoesNotExist($manifestPath);
        $this->assertSame(0, DB::table('properties')->count());
        $this->assertSame(0, DB::table('property_images')->count());
    }
}
