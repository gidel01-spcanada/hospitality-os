<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Amenity;
use App\Models\Establishment;
use App\Models\Property;
use App\Models\SiteReview;
use App\Models\AdminAvailabilityBlock;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PropertyAdminControlsTest extends TestCase
{
    use RefreshDatabase;

    public function test_property_copy_preview_requires_approval_and_obeys_daily_limit(): void
    {
        $this->seed(DatabaseSeeder::class);
        config([
            'services.property_copy.api_key' => 'test-key',
            'services.property_copy.daily_limit' => 1,
        ]);
        $admin = User::where('email', 'admin@afrikappart.test')->firstOrFail();
        $property = Property::firstOrFail();
        $original = $property->translations()->where('locale', 'fr')->value('description');
        $suggestion = [
            'summary_fr' => 'Résumé amélioré.',
            'description_fr' => 'Description française améliorée.',
            'summary_en' => 'Improved summary.',
            'description_en' => 'Improved English description.',
        ];
        Http::fake(['api.groq.com/*' => Http::response([
            'choices' => [['message' => ['content' => json_encode($suggestion)]]],
        ])]);

        $this->actingAs($admin)
            ->postJson(route('admin.properties.improve-copy', $property), [
                'description_fr' => 'Description originale.',
            ])
            ->assertOk()
            ->assertJsonPath('suggestion.description_fr', 'Description française améliorée.');

        $this->assertSame($original, $property->translations()->where('locale', 'fr')->value('description'));
        $this->actingAs($admin)
            ->postJson(route('admin.properties.improve-copy', $property), ['description_fr' => 'Encore.'])
            ->assertTooManyRequests();
    }

    public function test_host_cannot_improve_copy_for_an_unassigned_property(): void
    {
        $this->seed(DatabaseSeeder::class);
        $property = Property::firstOrFail();
        $host = User::factory()->create([
            'tenant_id' => $property->establishment->tenant_id,
            'role' => 'host',
            'is_admin' => false,
        ]);

        $this->actingAs($host)
            ->postJson(route('admin.properties.improve-copy', $property), ['description_fr' => 'Texte.'])
            ->assertForbidden();
    }

    public function test_admin_can_manage_property_pricing_and_availability(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = User::where('email', 'admin@afrikappart.test')->firstOrFail();
        $property = \App\Models\Property::firstOrFail();
        $amenityIds = Amenity::query()->limit(2)->pluck('id')->all();
        sort($amenityIds);

        $this->actingAs($admin)
            ->put('/admin/properties/' . $property->id, [
                'name' => 'Appartement 401',
                'property_type' => 'villa',
                'slug' => 'appartement-401',
                'nightly_rate_xof' => 27000,
                'nightly_rate_eur' => 41.15,
                'minimum_stay' => 3,
                'max_guests' => 3,
                'bedrooms' => 2,
                'bathrooms' => 1,
                'beds' => 3,
                'status' => 'published',
                'is_published' => true,
                'amenity_ids' => $amenityIds,
                'active_tab' => 'general',
            ])
            ->assertRedirect('/admin/properties/' . $property->id . '/edit#general');

        $this->assertDatabaseHas('properties', ['id' => $property->id, 'property_type' => 'villa', 'nightly_rate_xof' => '27000.00', 'minimum_stay' => 3, 'bedrooms' => 2, 'bathrooms' => 1, 'beds' => 3]);
        $this->assertSame($amenityIds, $property->fresh()->amenities()->pluck('amenities.id')->sort()->values()->all());

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

    public function test_admin_can_update_and_delete_property_features(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::where('email', 'admin@afrikappart.test')->firstOrFail();
        $property = Property::firstOrFail();
        $feature = $property->features()->create([
            'name' => 'Petit-déjeuner',
            'description' => 'Servi chaque matin.',
            'cost_xof' => 5000,
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->put(route('admin.properties.features.update', [$property, $feature]), [
                'name' => 'Petit-déjeuner complet',
                'description' => 'Servi chaque matin sur demande.',
                'cost_xof' => 7500,
                'is_active' => '0',
                'active_tab' => 'features',
            ])
            ->assertRedirect(route('admin.properties.edit', $property).'#features');

        $this->assertDatabaseHas('property_features', [
            'id' => $feature->id,
            'name' => 'Petit-déjeuner complet',
            'cost_xof' => '7500.00',
            'is_active' => false,
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.properties.features.destroy', [$property, $feature]), ['active_tab' => 'features'])
            ->assertRedirect(route('admin.properties.edit', $property).'#features');

        $this->assertDatabaseMissing('property_features', ['id' => $feature->id]);
    }

    public function test_property_feature_actions_reject_a_feature_from_another_property(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::where('email', 'admin@afrikappart.test')->firstOrFail();
        $properties = Property::query()->limit(2)->get();
        $feature = $properties[1]->features()->create([
            'name' => 'Service privé',
            'cost_xof' => 1000,
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->put(route('admin.properties.features.update', [$properties[0], $feature]), [
                'name' => 'Tentative',
                'cost_xof' => 0,
            ])
            ->assertNotFound();

        $this->actingAs($admin)
            ->delete(route('admin.properties.features.destroy', [$properties[0], $feature]))
            ->assertNotFound();
        $this->assertDatabaseHas('property_features', ['id' => $feature->id, 'name' => 'Service privé']);
    }

    public function test_admin_can_manage_amenities_from_their_own_editor_tab(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = User::where('email', 'admin@afrikappart.test')->firstOrFail();
        $property = Property::firstOrFail();
        $amenityIds = Amenity::query()->limit(3)->pluck('id')->all();
        sort($amenityIds);

        $this->actingAs($admin)
            ->get('/admin/properties/' . $property->id . '/edit')
            ->assertOk()
            ->assertSee('data-property-tab="amenities"', false);

        $this->actingAs($admin)
            ->put('/admin/properties/' . $property->id . '/amenities', [
                'amenity_ids' => $amenityIds,
                'active_tab' => 'amenities',
            ])
            ->assertRedirect('/admin/properties/' . $property->id . '/edit#amenities');

        $this->assertSame($amenityIds, $property->fresh()->amenities()->pluck('amenities.id')->sort()->values()->all());

        $this->actingAs($admin)
            ->put('/admin/properties/' . $property->id, [
                'name' => $property->name,
                'slug' => $property->slug,
                'nightly_rate_xof' => $property->nightly_rate_xof,
                'nightly_rate_eur' => $property->nightly_rate_eur,
                'minimum_stay' => $property->minimum_stay,
                'max_guests' => $property->max_guests,
                'status' => 'published',
            ])
            ->assertRedirect();

        $this->assertSame($amenityIds, $property->fresh()->amenities()->pluck('amenities.id')->sort()->values()->all());
    }

    public function test_admin_can_configure_establishment_taxes_and_pricing_calculator_propagates_them(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = User::where('email', 'admin@afrikappart.test')->firstOrFail();
        $establishment = Establishment::firstOrFail();
        $property = $establishment->properties()->firstOrFail();

        $this->actingAs($admin)
            ->put('/admin/establishments/' . $establishment->id, [
                'name' => $establishment->name,
                'slug' => $establishment->slug,
                'country_code' => $establishment->country_code,
                'currency' => 'XOF',
                'vat_percent' => 18,
                'vat_included' => '0', // Excluded VAT
                'service_fee_percent' => 10,
                'city_tax_type' => 'per_night',
                'city_tax_amount' => 1000,
                'active_tab' => 'taxes',
            ])
            ->assertRedirect('/admin/establishments/' . $establishment->id . '/edit#taxes');

        $establishment->refresh();
        $this->assertEquals('18.00', $establishment->vat_percent);
        $this->assertFalse($establishment->vat_included);
        $this->assertEquals('per_night', $establishment->city_tax_type);
        $this->assertEquals('1000.00', $establishment->city_tax_amount);

        $property = $establishment->properties()->firstOrFail();
        $checkIn = \Carbon\Carbon::parse('2026-10-01');
        $checkOut = \Carbon\Carbon::parse('2026-10-03'); // 2 nights
        $property->update(['nightly_rate_xof' => 50000]);

        $pricing = \App\Services\PricingCalculator::calculate($property, $checkIn, $checkOut, 2, 0);

        // Subtotal = 100 000, Service Fee 10% = 10 000, VAT 18% on (100 000 + 10 000) = 19 800, Local Tax = 2000 (1000 * 2)
        $this->assertEquals(100000, $pricing['subtotal']);
        $this->assertEquals(10000, $pricing['fees']);
        $this->assertEquals(21800, $pricing['taxes']); // 19 800 + 2 000
        $this->assertEquals(131800, $pricing['total_amount']);
    }

    public function test_establishment_currency_change_propagates_to_existing_properties(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = User::where('email', 'admin@afrikappart.test')->firstOrFail();
        $establishment = Establishment::firstOrFail();
        $property = $establishment->properties()->firstOrFail();

        $this->actingAs($admin)
            ->put('/admin/establishments/' . $establishment->id, [
                'name' => $establishment->name,
                'slug' => $establishment->slug,
                'country_code' => $establishment->country_code,
                'currency' => 'XAF',
            ])
            ->assertRedirect();

        $this->assertSame('XAF', $establishment->fresh()->currency);
        $this->assertSame('XAF', $property->fresh()->currency);
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
                    UploadedFile::fake()->image('living-room.jpg', 1200, 800),
                ],
            ])
            ->assertRedirect(route('admin.properties.edit', $property) . '#photos');

        $this->assertSame(2, $property->images()->count());
        $property->refresh();
        $uploadedPaths = $property->images()->pluck('file_path')->all();
        $this->assertCount(2, array_unique($uploadedPaths));
        $this->assertMatchesRegularExpression('~uploads/properties/' . preg_quote($property->slug, '~') . '/\d{17}-[a-z0-9]{12}\.jpg~', $uploadedPaths[0]);
        $cover = $property->images()->where('is_cover', true)->firstOrFail();
        $this->assertStringStartsWith('uploads/properties/' . $property->slug . '/', $cover->file_path);
        $this->assertSame($cover->file_path, $property->cover_image);

        // These photos are written to the real public disk (not a fake), so remove them here
        // instead of leaving throwaway files behind for every test run.
        $property->images()->get()->each(fn ($image) => \Illuminate\Support\Facades\File::delete(public_path($image->file_path)));
    }

    public function test_admin_can_upload_the_supplied_png_file(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::where('email', 'admin@afrikappart.test')->firstOrFail();
        $property = Property::firstOrFail();
        $sourcePath = base_path('photo/appartement 401/ChatGPT Image Jul 24, 2026, 04_22_56 PM.png');
        $temporaryPath = tempnam(sys_get_temp_dir(), 'afrikappart-upload-');

        $this->assertNotFalse($temporaryPath);
        copy($sourcePath, $temporaryPath);

        $response = $this->actingAs($admin)
            ->post('/admin/properties/' . $property->id . '/images', [
                'photos' => [new UploadedFile($temporaryPath, basename($sourcePath), 'image/png', null, true)],
            ]);

        $response->assertRedirect(route('admin.properties.edit', $property) . '#photos');
        $this->assertSame(1, $property->images()->count());
        $property->images()->each(fn ($image) => \Illuminate\Support\Facades\File::delete(public_path($image->file_path)));
    }

    public function test_admin_cannot_upload_a_property_photo_larger_than_three_megabytes(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::where('email', 'admin@afrikappart.test')->firstOrFail();
        $property = Property::firstOrFail();

        $response = $this->actingAs($admin)
            ->from(route('admin.properties.edit', $property))
            ->post('/admin/properties/' . $property->id . '/images', [
                'photos' => [UploadedFile::fake()->image('oversized.jpg')->size(3073)],
            ]);

        $response->assertRedirect(route('admin.properties.edit', $property));
        $response->assertSessionHasErrors([
            'photos.0' => 'Chaque photo ne doit pas dépasser 3 Mo.',
        ]);
        $this->assertSame(0, $property->images()->count());
    }

    public function test_admin_is_told_which_photos_exceed_the_combined_three_megabyte_upload_limit(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::where('email', 'admin@afrikappart.test')->firstOrFail();
        $property = Property::firstOrFail();

        $response = $this->actingAs($admin)
            ->post('/admin/properties/' . $property->id . '/images', [
                'photos' => [
                    UploadedFile::fake()->image('first.jpg')->size(2048),
                    UploadedFile::fake()->image('second.jpg')->size(2048),
                ],
            ]);

        $response->assertRedirect(route('admin.properties.edit', $property) . '#photos');
        $response->assertSessionHasErrors('photos');
        $response->assertSessionHasErrors('photos', 'second.jpg');
        $this->assertSame(1, $property->images()->count());

        $property->images()->each(fn ($image) => \Illuminate\Support\Facades\File::delete(public_path($image->file_path)));
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
        config()->set('services.fedapay.environment', 'production');
        config()->set('services.fedapay.public_key', 'pk_live_test');
        config()->set('services.fedapay.secret_key', 'sk_live_test');
        config()->set('services.cinetpay.environment', 'production');
        config()->set('services.cinetpay.site_id', '123456');
        config()->set('services.cinetpay.api_key', 'cinetpay_live_test');
        config()->set('services.mpesa.environment', 'production');
        config()->set('services.mpesa.consumer_key', 'mpesa_consumer_key');
        config()->set('services.mpesa.consumer_secret', 'mpesa_consumer_secret');
        config()->set('services.mpesa.shortcode', '174379');
        config()->set('services.mpesa.passkey', 'mpesa_passkey');

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
                'active_tab' => 'online',
                'payment_methods' => [
                    'pay_later' => ['enabled' => '1', 'instructions' => 'Pay on arrival'],
                    'fedapay' => ['enabled' => '1', 'mode' => 'production', 'instructions' => 'Use mobile money'],
                    'cinetpay' => ['enabled' => '1', 'mode' => 'production', 'instructions' => 'Pay with mobile money or card'],
                    'mpesa' => ['enabled' => '1', 'mode' => 'production', 'instructions' => 'Pay through M-Pesa STK Push'],
                ],
            ])
            ->assertRedirect(route('admin.establishments.edit', $establishment) . '#online');

        $establishment->refresh();
        $this->assertSame(['Wi-Fi', 'Pool', 'Parking'], $establishment->features);
        $this->assertSame('https://example.com/establishment.jpg', $establishment->cover_image);
        $this->assertSame('Rue 123, Cotonou', $establishment->address);
        $this->assertTrue($establishment->payment_methods['fedapay']['enabled']);
        $this->assertSame('production', $establishment->payment_methods['fedapay']['mode']);
        $this->assertSame('Use mobile money', $establishment->payment_methods['fedapay']['instructions']);
        $this->assertTrue($establishment->payment_methods['cinetpay']['enabled']);
        $this->assertSame('production', $establishment->payment_methods['cinetpay']['mode']);
        $this->assertTrue($establishment->payment_methods['mpesa']['enabled']);
        $this->assertSame('production', $establishment->payment_methods['mpesa']['mode']);
    }

    public function test_establishment_editor_has_section_tabs_and_a_country_selector(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::where('email', 'admin@afrikappart.test')->firstOrFail();
        $establishment = Establishment::firstOrFail();

        $this->actingAs($admin)
            ->get(route('admin.establishments.edit', $establishment))
            ->assertOk()
            ->assertSee('data-property-tab="general"', false)
            ->assertSee('data-property-tab="online"', false)
            ->assertSee('data-property-tab="translations"', false)
            ->assertSee('data-property-tab="payments"', false)
            ->assertSee('<select id="country_code" name="country_code" required>', false)
            ->assertSee('<option value="BJ" selected>', false)
            ->assertSee('<select id="currency" name="currency" required>', false)
            ->assertSee('<option value="XOF" selected>', false)
            ->assertSee('value="NGN"', false)
            ->assertSee('value="EUR"', false)
            ->assertSee('value="USD"', false)
            ->assertSee('value="GBP"', false)
            ->assertSee('value="CAD"', false);
    }

    public function test_establishment_editor_has_a_reviews_tab(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::where('email', 'admin@afrikappart.test')->firstOrFail();
        $establishment = Establishment::firstOrFail();

        $this->actingAs($admin)
            ->get(route('admin.establishments.edit', $establishment))
            ->assertOk()
            ->assertSee('data-property-tab="reviews"', false)
            ->assertSee('data-property-panel="reviews"', false);
    }

    public function test_admin_can_add_a_review_scoped_to_an_establishment(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::where('email', 'admin@afrikappart.test')->firstOrFail();
        $establishment = Establishment::firstOrFail();
        $property = $establishment->properties()->firstOrFail();

        $this->actingAs($admin)
            ->post(route('admin.establishments.reviews.store', $establishment), [
                'source' => 'google',
                'reviewer_name' => 'Chidi O.',
                'property_id' => $property->id,
                'rating' => 5,
                'review_text' => 'Superbe séjour.',
                'reviewed_at' => now()->toDateString(),
                'is_active' => '1',
            ])
            ->assertRedirect(route('admin.establishments.edit', $establishment) . '#reviews');

        $this->assertDatabaseHas('site_reviews', [
            'establishment_id' => $establishment->id,
            'reviewer_name' => 'Chidi O.',
            'source' => 'google',
            'rating' => 5,
            'property_id' => $property->id,
        ]);
    }

    public function test_admin_can_update_and_delete_a_review_scoped_to_an_establishment(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::where('email', 'admin@afrikappart.test')->firstOrFail();
        $establishment = Establishment::firstOrFail();

        // Stamp tenant_id explicitly since this bypasses the request-scoped CurrentTenant middleware.
        $review = $establishment->reviews()->create([
            'tenant_id' => $establishment->tenant_id,
            'source' => 'booking',
            'reviewer_name' => 'Amina D.',
            'rating' => 5,
            'reviewed_at' => now(),
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->put(route('admin.establishments.reviews.update', [$establishment, $review]), [
                'source' => 'airbnb',
                'reviewer_name' => 'Amina D. Modifiée',
                'rating' => 4,
                'reviewed_at' => now()->toDateString(),
                'is_active' => '1',
            ])
            ->assertRedirect(route('admin.establishments.edit', $establishment) . '#reviews');

        $this->assertDatabaseHas('site_reviews', [
            'id' => $review->id,
            'source' => 'airbnb',
            'reviewer_name' => 'Amina D. Modifiée',
            'rating' => 4,
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.establishments.reviews.destroy', [$establishment, $review]))
            ->assertRedirect(route('admin.establishments.edit', $establishment) . '#reviews');

        $this->assertDatabaseMissing('site_reviews', ['id' => $review->id]);
    }

    public function test_admin_can_import_reviews_for_an_establishment_in_multiple_formats(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::where('email', 'admin@afrikappart.test')->firstOrFail();
        $establishment = Establishment::firstOrFail();

        $genericCsv = implode("\n", [
            'reviewer_name,source,rating,review_text,source_url',
            'Kofi A.,google,5,Très bien,https://maps.google.com/1',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.establishments.reviews.import', $establishment), [
                'format' => 'generic',
                'csv' => $genericCsv,
            ])
            ->assertRedirect(route('admin.establishments.edit', $establishment) . '#reviews');

        $this->assertDatabaseHas('site_reviews', [
            'establishment_id' => $establishment->id,
            'reviewer_name' => 'Kofi A.',
            'source' => 'google',
        ]);

        $bookingCsv = implode("\n", [
            '"Date","Nom du client","Numéro","Titre","Positif","Négatif","Note"',
            '"2026-09-09 15:17:26","KOFFI","6659892745","","Très propre","","10"',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.establishments.reviews.import', $establishment), [
                'format' => 'booking',
                'csv' => $bookingCsv,
            ])
            ->assertRedirect(route('admin.establishments.edit', $establishment) . '#reviews');

        $this->assertDatabaseHas('site_reviews', [
            'establishment_id' => $establishment->id,
            'reviewer_name' => 'KOFFI',
            'source' => 'booking',
            'rating' => 5,
        ]);

        $airbnbCsv = implode("\n", [
            'Date,Reviewer,Overall rating,Public review',
            'Jan 1 2026,Jane Doe,5,Great stay',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.establishments.reviews.import', $establishment), [
                'format' => 'airbnb',
                'csv' => $airbnbCsv,
            ])
            ->assertRedirect(route('admin.establishments.edit', $establishment) . '#reviews');

        $this->assertDatabaseHas('site_reviews', [
            'establishment_id' => $establishment->id,
            'reviewer_name' => 'Jane Doe',
            'source' => 'airbnb',
            'rating' => 5,
        ]);

        $googleCsv = implode("\n", [
            'Review Date,Reviewer Name,Star Rating,Comment',
            '2026-01-02,John Smith,4,Nice place',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.establishments.reviews.import', $establishment), [
                'format' => 'google',
                'csv' => $googleCsv,
            ])
            ->assertRedirect(route('admin.establishments.edit', $establishment) . '#reviews');

        $this->assertDatabaseHas('site_reviews', [
            'establishment_id' => $establishment->id,
            'reviewer_name' => 'John Smith',
            'source' => 'google',
            'rating' => 4,
        ]);

        $reviewCountBeforeRepeat = SiteReview::query()
            ->where('establishment_id', $establishment->id)
            ->where('source', 'google')
            ->where('reviewer_name', 'John Smith')
            ->count();

        $this->actingAs($admin)
            ->post(route('admin.establishments.reviews.import', $establishment), [
                'format' => 'google',
                'csv' => $googleCsv,
            ])
            ->assertRedirect(route('admin.establishments.edit', $establishment) . '#reviews');

        $this->assertSame($reviewCountBeforeRepeat, SiteReview::query()
            ->where('establishment_id', $establishment->id)
            ->where('source', 'google')
            ->where('reviewer_name', 'John Smith')
            ->count());
    }

    public function test_establishment_review_import_requires_csv_content(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::where('email', 'admin@afrikappart.test')->firstOrFail();
        $establishment = Establishment::firstOrFail();

        $this->actingAs($admin)
            ->post(route('admin.establishments.reviews.import', $establishment), [
                'format' => 'generic',
                'csv' => '',
            ])
            ->assertSessionHasErrors('import_file');
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
        ])->assertRedirect(route('admin.establishments.edit', $establishment) . '#general');

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
            ->assertRedirect(route('admin.establishments.edit', $establishment) . '#general');

        $establishment->refresh();
        $this->assertStringStartsWith('uploads/establishments/', $establishment->cover_image);
        $this->assertFileExists(public_path($establishment->cover_image));

        // Written to the real public disk (not a fake), so clean it up rather than leaving it behind.
        \Illuminate\Support\Facades\File::delete(public_path($establishment->cover_image));
    }

    public function test_establishment_upload_limit_failure_has_a_clear_message(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::where('email', 'admin@afrikappart.test')->firstOrFail();
        $establishment = Establishment::firstOrFail();
        $temporaryPath = tempnam(sys_get_temp_dir(), 'afrikappart-upload-');

        $this->assertNotFalse($temporaryPath);

        $response = $this->actingAs($admin)
            ->from(route('admin.establishments.edit', $establishment))
            ->put('/admin/establishments/' . $establishment->id, [
                'name' => $establishment->name,
                'slug' => $establishment->slug,
                'country_code' => $establishment->country_code,
                'currency' => $establishment->currency,
                'cover_image_upload' => new UploadedFile($temporaryPath, 'cover.png', 'image/png', UPLOAD_ERR_INI_SIZE, true),
            ]);

        $response->assertRedirect(route('admin.establishments.edit', $establishment));
        $response->assertSessionHasErrors([
            'cover_image_upload' => 'La photo n’a pas pu être téléversée. Vérifiez qu’elle ne dépasse pas 3 Mo, puis réessayez.',
        ]);
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
            ->assertRedirect(route('admin.establishments.edit', $establishment) . '#general');

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
            ->assertRedirect(route('properties.index', ['establishment' => $other->id]));

        $this->get('/properties?establishment=' . $other->id)
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
