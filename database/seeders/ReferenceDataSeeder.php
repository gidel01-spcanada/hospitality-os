<?php

namespace Database\Seeders;

use App\Models\Amenity;
use App\Models\AmenityCategory;
use App\Models\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ReferenceDataSeeder extends Seeder
{
    /**
     * Seed reference tables such as amenity categories, amenities, and settings.
     */
    public function run(): void
    {
        // Seeders run outside any authenticated request, so there's no tenant context to stamp rows with automatically.
        $tenantId = Tenant::query()->firstOrCreate(['slug' => 'default'], ['name' => 'Default', 'status' => 'active'])->id;

        $categories = [
            'layout-views' => ['name_en' => 'Layout & Views', 'name_fr' => 'Agencement et vues', 'sort_order' => 10],
            'kitchen-dining' => ['name_en' => 'Kitchen & Dining', 'name_fr' => 'Cuisine et salle à manger', 'sort_order' => 20],
            'bathroom-laundry' => ['name_en' => 'Bathroom & Laundry', 'name_fr' => 'Salle de bains et blanchisserie', 'sort_order' => 30],
            'media-technology' => ['name_en' => 'Media & Technology', 'name_fr' => 'Médias et technologie', 'sort_order' => 40],
            'comfort-utilities' => ['name_en' => 'Comfort & Utilities', 'name_fr' => 'Confort et installations', 'sort_order' => 50],
            'logistics-accessibility' => ['name_en' => 'Logistics & Accessibility', 'name_fr' => 'Logistique et accessibilité', 'sort_order' => 60],
            'outdoors-wellness' => ['name_en' => 'Outdoors & Wellness', 'name_fr' => 'Extérieur et bien-être', 'sort_order' => 70],
        ];

        $categoryIds = [];
        foreach ($categories as $slug => $category) {
            $categoryIds[$slug] = AmenityCategory::query()->updateOrCreate(['slug' => $slug, 'tenant_id' => $tenantId], $category)->id;
        }

        // Booking.com's standard facility list, grouped under the categories above.
        $amenities = [
            'layout-views' => [
                ['slug' => 'balcony', 'name_en' => 'Balcony', 'name_fr' => 'Balcon'],
                ['slug' => 'terrace', 'name_en' => 'Terrace', 'name_fr' => 'Terrasse'],
                ['slug' => 'patio', 'name_en' => 'Patio', 'name_fr' => 'Patio'],
                ['slug' => 'city-view', 'name_en' => 'City view', 'name_fr' => 'Vue sur la ville'],
                ['slug' => 'garden-view', 'name_en' => 'Garden view', 'name_fr' => 'Vue sur le jardin'],
                ['slug' => 'inner-courtyard-view', 'name_en' => 'Inner courtyard view', 'name_fr' => 'Vue sur cour intérieure'],
                ['slug' => 'sea-view', 'name_en' => 'Sea view', 'name_fr' => 'Vue sur la mer'],
                ['slug' => 'landmark-view', 'name_en' => 'Landmark view', 'name_fr' => 'Vue sur un monument'],
                ['slug' => 'detached', 'name_en' => 'Detached', 'name_fr' => 'Indépendant (bâtiment)'],
                ['slug' => 'private-apartment-in-building', 'name_en' => 'Private apartment in building', 'name_fr' => 'Appartement privé dans un immeuble'],
            ],
            'kitchen-dining' => [
                ['slug' => 'kitchen', 'name_en' => 'Kitchen', 'name_fr' => 'Cuisine'],
                ['slug' => 'kitchenette', 'name_en' => 'Kitchenette', 'name_fr' => 'Kitchenette'],
                ['slug' => 'refrigerator', 'name_en' => 'Refrigerator', 'name_fr' => 'Réfrigérateur'],
                ['slug' => 'microwave', 'name_en' => 'Microwave', 'name_fr' => 'Four à micro-ondes'],
                ['slug' => 'dishwasher', 'name_en' => 'Dishwasher', 'name_fr' => 'Lave-vaisselle'],
                ['slug' => 'oven', 'name_en' => 'Oven', 'name_fr' => 'Four'],
                ['slug' => 'stovetop', 'name_en' => 'Stovetop', 'name_fr' => 'Plaque de cuisson'],
                ['slug' => 'toaster', 'name_en' => 'Toaster', 'name_fr' => 'Grille-pain'],
                ['slug' => 'electric-kettle', 'name_en' => 'Electric kettle', 'name_fr' => 'Bouilloire électrique'],
                ['slug' => 'coffee-machine', 'name_en' => 'Coffee machine', 'name_fr' => 'Machine à café'],
                ['slug' => 'dining-table', 'name_en' => 'Dining table', 'name_fr' => 'Table à manger'],
                ['slug' => 'kitchenware', 'name_en' => 'Kitchenware', 'name_fr' => 'Ustensiles de cuisine'],
                ['slug' => 'high-chair', 'name_en' => 'High chair for children', 'name_fr' => 'Chaise haute pour enfants'],
            ],
            'bathroom-laundry' => [
                ['slug' => 'private-bathroom', 'name_en' => 'Private bathroom', 'name_fr' => 'Salle de bains privative'],
                ['slug' => 'shower', 'name_en' => 'Shower', 'name_fr' => 'Douche'],
                ['slug' => 'bathtub', 'name_en' => 'Bathtub', 'name_fr' => 'Baignoire'],
                ['slug' => 'hairdryer', 'name_en' => 'Hairdryer', 'name_fr' => 'Sèche-cheveux'],
                ['slug' => 'free-toiletries', 'name_en' => 'Free toiletries', 'name_fr' => 'Articles de toilette gratuits'],
                ['slug' => 'bathrobes', 'name_en' => 'Bathrobes', 'name_fr' => 'Peignoirs'],
                ['slug' => 'slippers', 'name_en' => 'Slippers', 'name_fr' => 'Chaussons'],
                ['slug' => 'bidet', 'name_en' => 'Bidet', 'name_fr' => 'Bidet'],
                ['slug' => 'laundry', 'name_en' => 'Washing machine', 'name_fr' => 'Lave-linge'],
                ['slug' => 'clothes-dryer', 'name_en' => 'Clothes dryer', 'name_fr' => 'Sèche-linge'],
                ['slug' => 'ironing-facilities', 'name_en' => 'Ironing facilities', 'name_fr' => 'Matériel de repassage'],
                ['slug' => 'drying-rack', 'name_en' => 'Drying rack for clothing', 'name_fr' => 'Étendoir'],
            ],
            'media-technology' => [
                ['slug' => 'flat-screen-tv', 'name_en' => 'Flat-screen TV', 'name_fr' => 'Télévision à écran plat'],
                ['slug' => 'satellite-channels', 'name_en' => 'Satellite channels', 'name_fr' => 'Chaînes satellites'],
                ['slug' => 'cable-channels', 'name_en' => 'Cable channels', 'name_fr' => 'Chaînes par câble'],
                ['slug' => 'streaming-service', 'name_en' => 'Streaming service (like Netflix)', 'name_fr' => 'Service de streaming (ex. Netflix)'],
                ['slug' => 'ipad-computer', 'name_en' => 'iPad / Computer', 'name_fr' => 'iPad / Ordinateur'],
                ['slug' => 'game-console', 'name_en' => 'Game console', 'name_fr' => 'Console de jeux'],
            ],
            'comfort-utilities' => [
                ['slug' => 'air-conditioning', 'name_en' => 'Air conditioning', 'name_fr' => 'Climatisation'],
                ['slug' => 'heating', 'name_en' => 'Heating', 'name_fr' => 'Chauffage'],
                ['slug' => 'soundproofing', 'name_en' => 'Soundproofing', 'name_fr' => 'Insonorisation'],
                ['slug' => 'wifi', 'name_en' => 'Free WiFi', 'name_fr' => 'Wi-Fi gratuit'],
                ['slug' => 'fan', 'name_en' => 'Fan', 'name_fr' => 'Ventilateur'],
                ['slug' => 'fireplace', 'name_en' => 'Fireplace', 'name_fr' => 'Cheminée'],
                ['slug' => 'safe', 'name_en' => 'Safe', 'name_fr' => 'Coffre-fort'],
                ['slug' => 'desk', 'name_en' => 'Desk', 'name_fr' => 'Bureau'],
                ['slug' => 'seating-area', 'name_en' => 'Seating area', 'name_fr' => 'Coin salon'],
                ['slug' => 'sofa-bed', 'name_en' => 'Sofa bed', 'name_fr' => 'Canapé-lit'],
                ['slug' => 'wardrobe', 'name_en' => 'Wardrobe or closet', 'name_fr' => 'Armoire ou penderie'],
                ['slug' => 'hardwood-floors', 'name_en' => 'Hardwood or parquet floors', 'name_fr' => 'Parquet'],
                ['slug' => 'tile-marble-floor', 'name_en' => 'Tile/marble floor', 'name_fr' => 'Carrelage/marbre'],
                ['slug' => 'hypoallergenic', 'name_en' => 'Hypoallergenic', 'name_fr' => 'Hypoallergénique'],
                ['slug' => 'socket-near-bed', 'name_en' => 'Socket near the bed', 'name_fr' => 'Prise près du lit'],
            ],
            'logistics-accessibility' => [
                ['slug' => 'elevator-access', 'name_en' => 'Upper floors accessible by elevator', 'name_fr' => 'Étages supérieurs accessibles par ascenseur'],
                ['slug' => 'stairs-only-access', 'name_en' => 'Upper floors accessible by stairs only', 'name_fr' => 'Étages supérieurs accessibles uniquement par les escaliers'],
                ['slug' => 'wheelchair-accessible', 'name_en' => 'Entire unit wheelchair accessible', 'name_fr' => 'Logement entièrement accessible en fauteuil roulant'],
                ['slug' => 'private-entrance', 'name_en' => 'Private entrance', 'name_fr' => 'Entrée privée'],
                ['slug' => 'key-access', 'name_en' => 'Key access', 'name_fr' => 'Accès avec clé'],
                ['slug' => 'keyless-card-access', 'name_en' => 'Keyless card access', 'name_fr' => 'Accès par carte de clé'],
                ['slug' => 'parking', 'name_en' => 'Free parking', 'name_fr' => 'Parking gratuit'],
            ],
            'outdoors-wellness' => [
                ['slug' => 'outdoor-dining-area', 'name_en' => 'Outdoor dining area', 'name_fr' => 'Coin repas extérieur'],
                ['slug' => 'outdoor-furniture', 'name_en' => 'Outdoor furniture', 'name_fr' => "Mobilier d'extérieur"],
                ['slug' => 'barbecue', 'name_en' => 'Barbecue', 'name_fr' => 'Barbecue'],
                ['slug' => 'hot-tub', 'name_en' => 'Hot tub / Jacuzzi', 'name_fr' => 'Bain à remous / Jacuzzi'],
                ['slug' => 'pool', 'name_en' => 'Private pool', 'name_fr' => 'Piscine privée'],
                ['slug' => 'sauna', 'name_en' => 'Sauna', 'name_fr' => 'Sauna'],
            ],
        ];

        foreach ($amenities as $categorySlug => $categoryAmenities) {
            foreach ($categoryAmenities as $index => $amenity) {
                Amenity::query()->updateOrCreate(
                    ['slug' => $amenity['slug'], 'tenant_id' => $tenantId],
                    [
                        'category_id' => $categoryIds[$categorySlug],
                        'name_en' => $amenity['name_en'],
                        'name_fr' => $amenity['name_fr'],
                        'sort_order' => $index * 10,
                    ]
                );
            }
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
                ['key' => $setting['key'], 'tenant_id' => $tenantId],
                $setting
            );
        }
    }
}

