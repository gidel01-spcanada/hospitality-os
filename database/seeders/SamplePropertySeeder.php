<?php

namespace Database\Seeders;

use App\Models\Amenity;
use App\Models\Establishment;
use App\Models\Property;
use App\Models\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class SamplePropertySeeder extends Seeder
{
    public function run(): void
    {
        // Seeders run outside any authenticated request, so there's no tenant context to stamp rows with automatically.
        $tenantId = Tenant::query()->firstOrCreate(['slug' => 'default'], ['name' => 'Default', 'status' => 'active'])->id;

        $establishment = Establishment::query()->updateOrCreate(
            ['slug' => 'afrik-appart-cotonou', 'tenant_id' => $tenantId],
            [
                'name' => 'Afrik Appart Cotonou',
                'country_code' => 'BJ',
                'city' => 'Cotonou',
                'currency' => 'XOF',
                'description' => 'Draft establishment placeholder awaiting owner validation.',
                'email' => '[TO BE PROVIDED]',
                'phone' => '[TO BE PROVIDED]',
                'metadata' => ['draft' => true, 'source' => 'master_prompt'],
            ]
        );

        $properties = [
            [
                'name' => 'Appartement 401',
                'slug' => 'appartement-401',
                'status' => 'published',
                'is_published' => true,
                'currency' => 'XOF',
                'nightly_rate_xof' => 25000,
                'nightly_rate_eur' => 38.15,
                'max_guests' => 2,
                'bedrooms' => 1,
                'bathrooms' => 1,
                'beds' => 1,
                'address' => 'Draft address to be confirmed',
                'city' => 'Cotonou',
                'country' => 'Benin',
                'summary' => 'Draft property summary awaiting confirmation.',
                'description' => 'Draft description to be replaced with owner-supplied content.',
                'cover_image' => 'uploads/properties/appartement-401/cover.jpg',
                'minimum_stay' => 2,
                'metadata' => ['source_folder' => 'appartement 401', 'draft' => true],
            ],
            [
                'name' => 'Appartement 402',
                'slug' => 'appartement-402',
                'status' => 'published',
                'is_published' => true,
                'currency' => 'XOF',
                'nightly_rate_xof' => 32000,
                'nightly_rate_eur' => 48.78,
                'max_guests' => 4,
                'bedrooms' => 2,
                'bathrooms' => 2,
                'beds' => 2,
                'address' => 'Draft address to be confirmed',
                'city' => 'Cotonou',
                'country' => 'Benin',
                'summary' => 'Draft property summary awaiting confirmation.',
                'description' => 'Draft description to be replaced with owner-supplied content.',
                'cover_image' => 'uploads/properties/appartement-402/cover.jpg',
                'minimum_stay' => 2,
                'metadata' => ['source_folder' => 'appartement 402', 'draft' => true],
            ],
            [
                'name' => 'Appartement 403',
                'slug' => 'appartement-403',
                'status' => 'published',
                'is_published' => true,
                'currency' => 'XOF',
                'nightly_rate_xof' => 39000,
                'nightly_rate_eur' => 59.45,
                'max_guests' => 4,
                'bedrooms' => 2,
                'bathrooms' => 2,
                'beds' => 2,
                'address' => 'Draft address to be confirmed',
                'city' => 'Cotonou',
                'country' => 'Benin',
                'summary' => 'Draft property summary awaiting confirmation.',
                'description' => 'Draft description to be replaced with owner-supplied content.',
                'cover_image' => 'uploads/properties/appartement-403/cover.jpg',
                'minimum_stay' => 2,
                'metadata' => ['source_folder' => 'appartement 403', 'draft' => true],
            ],
            [
                'name' => 'Appartement 404',
                'slug' => 'appartement-404',
                'status' => 'published',
                'is_published' => true,
                'currency' => 'XOF',
                'nightly_rate_xof' => 45000,
                'nightly_rate_eur' => 68.58,
                'max_guests' => 5,
                'bedrooms' => 3,
                'bathrooms' => 2,
                'beds' => 3,
                'address' => 'Draft address to be confirmed',
                'city' => 'Cotonou',
                'country' => 'Benin',
                'summary' => 'Draft property summary awaiting confirmation.',
                'description' => 'Draft description to be replaced with owner-supplied content.',
                'cover_image' => 'uploads/properties/appartement-404/cover.jpg',
                'minimum_stay' => 3,
                'metadata' => ['source_folder' => 'appartement 404', 'draft' => true],
            ],
        ];

        $amenityIds = Amenity::query()->pluck('id', 'slug')->all();

        foreach ($properties as $propertyData) {
            $property = Property::query()->updateOrCreate(
                ['slug' => $propertyData['slug']],
                array_merge(['establishment_id' => $establishment->id], $propertyData)
            );

            $selected = [];
            foreach (['wifi', 'kitchen', 'air-conditioning', 'terrace'] as $slug) {
                if (isset($amenityIds[$slug])) {
                    $selected[] = $amenityIds[$slug];
                }
            }

            $property->amenities()->sync($selected);

        }

        DB::table('currency_configs')->updateOrInsert(
            ['currency_code' => 'XOF'],
            ['name' => 'West African CFA Franc', 'xof_per_eur' => 655.957000, 'is_enabled' => true, 'effective_at' => now()]
        );

        DB::table('currency_configs')->updateOrInsert(
            ['currency_code' => 'EUR'],
            ['name' => 'Euro', 'xof_per_eur' => 655.957000, 'is_enabled' => true, 'effective_at' => now()]
        );

        // Real apartment photos live in the workspace-level photo/ folder (outside the repo), so a fresh
        // migrate:fresh/db:seed only re-links them in local dev, where that folder is actually present.
        $workspaceRoot = dirname(base_path());
        $photoSource = $workspaceRoot . DIRECTORY_SEPARATOR . 'photo';
        if (app()->environment('local') && is_dir($photoSource)) {
            Artisan::call('property:sync-media', [
                '--source' => $photoSource,
                '--manifest' => $workspaceRoot . DIRECTORY_SEPARATOR . 'seed-assets' . DIRECTORY_SEPARATOR . 'properties' . DIRECTORY_SEPARATOR . 'manifest.json',
            ]);
        }
    }
}
