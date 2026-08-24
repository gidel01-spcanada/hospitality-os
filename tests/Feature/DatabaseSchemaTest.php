<?php

namespace Tests\Feature;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DatabaseSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_core_database_tables_exist(): void
    {
        $tables = [
            'establishments',
            'properties',
            'amenities',
            'property_amenities',
            'property_images',
            'rate_rules',
            'reservation_guests',
            'reservations',
            'payment_attempts',
            'currency_configs',
            'admin_availability_blocks',
            'external_calendar_feeds',
            'external_calendar_events',
            'settings',
            'email_outbox',
            'audit_logs',
        ];

        foreach ($tables as $table) {
            $this->assertTrue(Schema::hasTable($table), "Missing table: {$table}");
        }
    }

    public function test_seed_data_initializes_reference_records(): void
    {
        Artisan::call('db:seed', ['--class' => DatabaseSeeder::class]);

        $this->assertDatabaseHas('settings', ['key' => 'site_name', 'value' => 'Afrik Appart']);
        $this->assertDatabaseHas('amenities', ['slug' => 'wifi']);
        $this->assertDatabaseHas('properties', ['slug' => 'appartement-401']);
    }
}
