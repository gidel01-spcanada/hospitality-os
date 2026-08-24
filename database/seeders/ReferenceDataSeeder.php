<?php

namespace Database\Seeders;

use App\Models\Amenity;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ReferenceDataSeeder extends Seeder
{
    /**
     * Seed reference tables such as amenities and settings.
     */
    public function run(): void
    {
        $amenities = [
            ['name' => 'Wi‑Fi', 'slug' => 'wifi', 'category' => 'internet'],
            ['name' => 'Parking', 'slug' => 'parking', 'category' => 'parking'],
            ['name' => 'Cuisine équipée', 'slug' => 'kitchen', 'category' => 'kitchen'],
            ['name' => 'Climatisation', 'slug' => 'air-conditioning', 'category' => 'comfort'],
            ['name' => 'Piscine', 'slug' => 'pool', 'category' => 'recreation'],
            ['name' => 'Terrasse', 'slug' => 'terrace', 'category' => 'outdoor'],
            ['name' => 'Concierge', 'slug' => 'concierge', 'category' => 'service'],
            ['name' => 'Lavage', 'slug' => 'laundry', 'category' => 'service'],
        ];

        foreach ($amenities as $amenity) {
            Amenity::query()->updateOrCreate(
                ['slug' => $amenity['slug']],
                $amenity
            );
        }

        $settings = [
            ['key' => 'site_name', 'value' => 'Afrik Appart', 'type' => 'string'],
            ['key' => 'site_tagline', 'value' => 'Séjours premium pour des escapades sereines en Afrique de l’Ouest.', 'type' => 'string'],
            ['key' => 'contact_email', 'value' => 'support@afrikappart.example', 'type' => 'string'],
            ['key' => 'support_phone', 'value' => '+229 00 00 00 00', 'type' => 'string'],
            ['key' => 'default_locale', 'value' => 'fr', 'type' => 'string'],
            ['key' => 'secondary_locale', 'value' => 'en', 'type' => 'string'],
            ['key' => 'review_source_booking_url', 'value' => '', 'type' => 'string'],
            ['key' => 'review_source_google_url', 'value' => '', 'type' => 'string'],
            ['key' => 'default_currency', 'value' => 'XOF', 'type' => 'string'],
            ['key' => 'eur_to_xof_rate', 'value' => '655.957', 'type' => 'decimal'],
            ['key' => 'booking_mode', 'value' => 'instant_confirmation', 'type' => 'string'],
            ['key' => 'payment_hold_minutes', 'value' => '20', 'type' => 'integer'],
            ['key' => 'calendar_freshness_minutes', 'value' => '15', 'type' => 'integer'],
        ];

        foreach ($settings as $setting) {
            DB::table('settings')->updateOrInsert(
                ['key' => $setting['key']],
                $setting
            );
        }
    }
}
