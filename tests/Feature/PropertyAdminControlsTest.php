<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Establishment;
use App\Models\Property;
use App\Models\AdminAvailabilityBlock;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PropertyAdminControlsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_manage_property_pricing_and_availability(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = User::where('email', 'admin@afrikappart.test')->firstOrFail();
        $property = \App\Models\Property::firstOrFail();

        $this->actingAs($admin)
            ->put('/admin/properties/' . $property->id, [
                'name' => 'Appartement 401',
                'property_type' => 'villa',
                'slug' => 'appartement-401',
                'nightly_rate_xof' => 27000,
                'nightly_rate_eur' => 41.15,
                'minimum_stay' => 3,
                'max_guests' => 3,
                'status' => 'published',
                'is_published' => true,
                'active_tab' => 'general',
            ])
            ->assertRedirect('/admin/properties/' . $property->id . '/edit#general');

        $this->assertDatabaseHas('properties', ['id' => $property->id, 'property_type' => 'villa', 'nightly_rate_xof' => '27000.00', 'minimum_stay' => 3]);

        $this->actingAs($admin)
            ->post('/admin/properties/' . $property->id . '/features', [
                'name' => 'Chef à domicile',
                'description' => 'Service de cuisine personnalisé pour le séjour.',
                'cost_xof' => 15000,
                'is_active' => true,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('property_features', ['property_id' => $property->id, 'name' => 'Chef à domicile', 'cost_xof' => '15000.00']);

        $this->actingAs($admin)
            ->post('/admin/properties/' . $property->id . '/availability-blocks', [
                'start_date' => '2026-09-10',
                'end_date' => '2026-09-12',
                'reason' => 'Maintenance schedule',
                'active_tab' => 'availability',
            ])
            ->assertRedirect('/admin/properties/' . $property->id . '/edit#availability');

        $block = AdminAvailabilityBlock::query()->where('property_id', $property->id)->where('reason', 'Maintenance schedule')->firstOrFail();
        $this->assertDatabaseHas('admin_availability_blocks', ['id' => $block->id]);

        $this->actingAs($admin)
            ->delete('/admin/properties/' . $property->id . '/availability-blocks/' . $block->id, ['active_tab' => 'availability'])
            ->assertRedirect('/admin/properties/' . $property->id . '/edit#availability');

        $this->assertDatabaseMissing('admin_availability_blocks', ['id' => $block->id]);
    }

    public function test_customer_can_select_property_feature_addons_during_booking(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = User::where('email', 'admin@afrikappart.test')->firstOrFail();
        $property = \App\Models\Property::firstOrFail();

        $this->actingAs($admin)
            ->post('/admin/properties/' . $property->id . '/features', [
                'name' => 'Chef à domicile',
                'description' => 'Service de cuisine personnalisé pour le séjour.',
                'cost_xof' => 15000,
                'is_active' => true,
            ])
            ->assertRedirect();

        $feature = \App\Models\PropertyFeature::query()->where('property_id', $property->id)->firstOrFail();

        $response = $this->post('/properties/' . $property->slug . '/reserve', [
            'full_name' => 'Test Guest',
            'email' => 'extras@example.com',
            'phone' => '+22999999999',
            'country' => 'Bénin',
            'check_in' => now()->addDay()->toDateString(),
            'check_out' => now()->addDays(3)->toDateString(),
            'adults' => 2,
            'children' => 0,
            'infants' => 0,
            'selected_features' => [$feature->id],
        ]);

        $reservation = \App\Models\Reservation::query()->where('email', 'extras@example.com')->firstOrFail();

        $this->assertSame(route('checkout.show', ['reservation' => $reservation, 'token' => $reservation->checkout_token]), $response->headers->get('Location'));

        $this->assertDatabaseHas('reservation_price_lines', [
            'reservation_id' => $reservation->id,
            'label' => 'Option: Chef à domicile',
            'amount' => '15000.00',
        ]);
        $this->assertGreaterThan(0, $reservation->total_amount);
    }

    public function test_admin_can_order_property_photos_and_choose_card_cover(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = User::where('email', 'admin@afrikappart.test')->firstOrFail();
        $property = \App\Models\Property::firstOrFail();
        $property->images()->createMany([
            ['file_path' => 'uploads/test-one.jpg', 'file_name' => 'test-one.jpg', 'mime_type' => 'image/jpeg', 'sort_order' => 1],
            ['file_path' => 'uploads/test-two.jpg', 'file_name' => 'test-two.jpg', 'mime_type' => 'image/jpeg', 'sort_order' => 2],
        ]);
        $images = $property->images()->orderBy('sort_order')->get();
        $this->assertGreaterThanOrEqual(2, $images->count());

        $this->actingAs($admin)
            ->put('/admin/properties/' . $property->id . '/images', [
                'image_order' => [
                    $images[0]->id => 2,
                    $images[1]->id => 1,
                ],
                'image_tags' => [
                    $images[0]->id => 'Bedroom',
                    $images[1]->id => 'Living room',
                ],
                'cover_image_id' => $images[1]->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('property_images', ['id' => $images[0]->id, 'sort_order' => 2, 'is_cover' => false]);
        $this->assertDatabaseHas('property_images', ['id' => $images[1]->id, 'sort_order' => 1, 'is_cover' => true]);
        $this->assertDatabaseHas('property_images', ['id' => $images[1]->id, 'room_tag' => 'Living room']);
        $this->assertDatabaseHas('properties', ['id' => $property->id, 'cover_image' => $images[1]->file_path]);
    }

    public function test_admin_can_upload_property_photos(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::where('email', 'admin@afrikappart.test')->firstOrFail();
        $property = Property::firstOrFail();

        $this->actingAs($admin)
            ->post('/admin/properties/' . $property->id . '/images', [
                'photos' => [
                    UploadedFile::fake()->image('living-room.jpg', 1200, 800),
                    UploadedFile::fake()->image('bedroom.jpg', 1200, 800),
                ],
            ])
            ->assertRedirect(route('admin.properties.edit', $property) . '#photos');

        $this->assertSame(2, $property->images()->count());
        $property->refresh();
        $cover = $property->images()->where('is_cover', true)->firstOrFail();
        $this->assertStringStartsWith('uploads/properties/' . $property->slug . '/', $cover->file_path);
        $this->assertSame($cover->file_path, $property->cover_image);
    }

    public function test_admin_can_remove_a_property_photo_and_reassign_cover(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::where('email', 'admin@afrikappart.test')->firstOrFail();
        $property = Property::firstOrFail();
        $first = $property->images()->create(['file_path' => 'uploads/properties/remove-one.jpg', 'file_name' => 'remove-one.jpg', 'sort_order' => 1, 'is_cover' => true]);
        $second = $property->images()->create(['file_path' => 'uploads/properties/remove-two.jpg', 'file_name' => 'remove-two.jpg', 'sort_order' => 2]);
        $property->update(['cover_image' => $first->file_path]);

        $this->actingAs($admin)
            ->post('/admin/properties/' . $property->id . '/images', [
                '_method' => 'PUT',
                'image_order' => [$first->id => 1, $second->id => 2],
                'cover_image_id' => $first->id,
                'remove_image_id' => $first->id,
            ])
            ->assertRedirect(route('admin.properties.edit', $property) . '#photos');

        $this->assertDatabaseMissing('property_images', ['id' => $first->id]);
        $this->assertDatabaseHas('property_images', ['id' => $second->id, 'is_cover' => true]);
        $this->assertSame($second->file_path, $property->fresh()->cover_image);
    }

    public function test_admin_can_update_establishment_content_and_location(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = User::where('email', 'admin@afrikappart.test')->firstOrFail();
        $establishment = Establishment::firstOrFail();

        $this->actingAs($admin)
            ->put('/admin/establishments/' . $establishment->id, [
                'name' => 'Afrik Appart Cotonou Centre',
                'slug' => 'afrik-appart-cotonou-centre',
                'country_code' => 'BJ',
                'city' => 'Cotonou',
                'address' => 'Rue 123, Cotonou',
                'currency' => 'XOF',
                'description' => 'A welcoming serviced residence.',
                'cover_image' => 'https://example.com/establishment.jpg',
                'features' => ['Wi-Fi, Pool, Parking'],
                'latitude' => 6.3703,
                'longitude' => 2.3912,
                'google_maps_url' => 'https://maps.google.com/?q=6.3703,2.3912',
                'payment_methods' => [
                    'pay_later' => ['enabled' => '1', 'instructions' => 'Pay on arrival'],
                    'fedapay' => ['enabled' => '1', 'instructions' => 'Use mobile money'],
                ],
            ])
            ->assertRedirect('/admin/establishments');

        $establishment->refresh();
        $this->assertSame(['Wi-Fi', 'Pool', 'Parking'], $establishment->features);
        $this->assertSame('https://example.com/establishment.jpg', $establishment->cover_image);
        $this->assertSame('Rue 123, Cotonou', $establishment->address);
        $this->assertTrue($establishment->payment_methods['fedapay']['enabled']);
        $this->assertSame('Use mobile money', $establishment->payment_methods['fedapay']['instructions']);
    }

    public function test_google_maps_link_populates_missing_establishment_coordinates(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::where('email', 'admin@afrikappart.test')->firstOrFail();
        $establishment = Establishment::firstOrFail();

        $this->actingAs($admin)->put('/admin/establishments/' . $establishment->id, [
            'name' => $establishment->name,
            'slug' => $establishment->slug,
            'country_code' => $establishment->country_code,
            'currency' => $establishment->currency,
            'google_maps_url' => 'https://www.google.com/maps?q=6.3703,2.3912',
        ])->assertRedirect('/admin/establishments');

        $this->assertDatabaseHas('establishments', [
            'id' => $establishment->id,
            'latitude' => '6.3703000',
            'longitude' => '2.3912000',
        ]);
    }

    public function test_admin_can_upload_an_establishment_main_picture(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::where('email', 'admin@afrikappart.test')->firstOrFail();
        $establishment = Establishment::firstOrFail();

        $this->actingAs($admin)
            ->put('/admin/establishments/' . $establishment->id, [
                'name' => $establishment->name,
                'slug' => $establishment->slug,
                'country_code' => $establishment->country_code,
                'currency' => $establishment->currency,
                'cover_image_upload' => UploadedFile::fake()->image('establishment-cover.jpg', 1600, 900),
            ])
            ->assertRedirect('/admin/establishments');

        $establishment->refresh();
        $this->assertStringStartsWith('uploads/establishments/', $establishment->cover_image);
        $this->assertFileExists(public_path($establishment->cover_image));
    }

    public function test_admin_can_select_a_property_picture_as_establishment_main_picture(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::where('email', 'admin@afrikappart.test')->firstOrFail();
        $establishment = Establishment::firstOrFail();
        $property = Property::where('establishment_id', $establishment->id)->firstOrFail();
        $image = $property->images()->create([
            'file_path' => 'uploads/properties/shared-cover.jpg',
            'file_name' => 'shared-cover.jpg',
            'mime_type' => 'image/jpeg',
            'sort_order' => 1,
        ]);

        $this->actingAs($admin)
            ->put('/admin/establishments/' . $establishment->id, [
                'name' => $establishment->name,
                'slug' => $establishment->slug,
                'country_code' => $establishment->country_code,
                'currency' => $establishment->currency,
                'property_image_id' => $image->id,
            ])
            ->assertRedirect('/admin/establishments');

        $this->assertDatabaseHas('establishments', ['id' => $establishment->id, 'cover_image' => $image->file_path]);
    }

    public function test_home_can_filter_properties_by_establishment(): void
    {
        $this->seed(DatabaseSeeder::class);

        $other = Establishment::create([
            'name' => 'Afrik Appart Porto-Novo',
            'slug' => 'afrik-appart-porto-novo',
            'country_code' => 'BJ',
            'city' => 'Porto-Novo',
            'currency' => 'XOF',
        ]);
        Property::create([
            'establishment_id' => $other->id,
            'name' => 'Appartement Porto-Novo',
            'slug' => 'appartement-porto-novo',
            'status' => 'published',
            'is_published' => true,
            'nightly_rate_xof' => 20000,
            'nightly_rate_eur' => 30,
        ]);

        $this->get('/?establishment=' . $other->id)
            ->assertOk()
            ->assertSee('Appartement Porto-Novo')
            ->assertDontSee('Appartement 401');
    }

    public function test_admin_can_create_an_establishment(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = User::where('email', 'admin@afrikappart.test')->firstOrFail();

        $this->actingAs($admin)
            ->post('/admin/establishments', [
                'name' => 'Afrik Appart Ouidah',
                'slug' => 'afrik-appart-ouidah',
                'country_code' => 'BJ',
                'city' => 'Ouidah',
                'currency' => 'XOF',
                'features' => ['Garden, Wi-Fi'],
            ])
            ->assertRedirect('/admin/establishments');

        $this->assertDatabaseHas('establishments', ['slug' => 'afrik-appart-ouidah']);
        $this->assertSame(['Garden', 'Wi-Fi'], Establishment::where('slug', 'afrik-appart-ouidah')->firstOrFail()->features);
    }

    public function test_establishment_admin_page_links_to_filtered_properties(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::where('email', 'admin@afrikappart.test')->firstOrFail();
        $establishment = Establishment::firstOrFail();

        $this->actingAs($admin)
            ->get('/admin/establishments')
            ->assertOk()
            ->assertSee('href="' . route('admin.properties.index', ['establishment' => $establishment->id]) . '"', false);

        $this->actingAs($admin)
            ->get('/admin/properties?establishment=' . $establishment->id)
            ->assertOk()
            ->assertSee($establishment->name);
    }

    public function test_admin_workspace_links_to_establishments_without_global_menu_link(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::where('email', 'admin@afrikappart.test')->firstOrFail();

        $this->actingAs($admin)
            ->get('/admin')
            ->assertSee('href="' . route('admin.establishments.index') . '"', false);

        $this->get('/')
            ->assertDontSee('href="' . route('admin.establishments.index') . '"', false);
    }

    public function test_property_translations_are_saved_and_rendered_for_active_locale(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::where('email', 'admin@afrikappart.test')->firstOrFail();
        $property = Property::firstOrFail();

        $this->actingAs($admin)->put('/admin/properties/' . $property->id, [
            'name' => $property->name,
            'slug' => $property->slug,
            'nightly_rate_xof' => $property->nightly_rate_xof,
            'nightly_rate_eur' => $property->nightly_rate_eur,
            'minimum_stay' => $property->minimum_stay,
            'max_guests' => $property->max_guests,
            'status' => $property->status,
            'is_published' => $property->is_published,
            'translations' => [
                'fr' => ['name' => 'Maison française', 'summary' => 'Résumé français'],
                'en' => ['name' => 'English home', 'summary' => 'English summary'],
            ],
        ])->assertRedirect();

        $this->assertDatabaseHas('property_translations', ['property_id' => $property->id, 'locale' => 'en', 'name' => 'English home']);
        $admin->forceFill(['locale' => 'en'])->save();
        $this->actingAs($admin)->get('/properties')
            ->assertSee('English home')
            ->assertSee('English summary');
        $admin->forceFill(['locale' => 'fr'])->save();
        $this->actingAs($admin)->get('/properties')
            ->assertSee('Maison française')
            ->assertSee('Résumé français');
    }

    public function test_establishment_translation_is_used_in_public_filter(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::where('email', 'admin@afrikappart.test')->firstOrFail();
        $establishment = Establishment::firstOrFail();

        $this->actingAs($admin)->put('/admin/establishments/' . $establishment->id, [
            'name' => $establishment->name,
            'slug' => $establishment->slug,
            'country_code' => $establishment->country_code,
            'currency' => $establishment->currency,
            'translations' => [
                'en' => ['name' => 'English Residence', 'description' => 'An English description', 'features' => 'Wi-Fi, Pool'],
            ],
        ])->assertRedirect();

        $admin->forceFill(['locale' => 'en'])->save();
        $this->actingAs($admin)->get('/properties')->assertSee('English Residence');
    }

    public function test_customer_cannot_access_property_admin_routes(): void
    {
        $this->seed(DatabaseSeeder::class);

        $customer = User::where('email', 'guest@afrikappart.test')->firstOrFail();
        $property = \App\Models\Property::firstOrFail();

        $this->actingAs($customer)
            ->get('/admin/properties')
            ->assertForbidden();

        $this->actingAs($customer)
            ->get('/admin/properties/' . $property->id . '/edit')
            ->assertForbidden();
    }
}
