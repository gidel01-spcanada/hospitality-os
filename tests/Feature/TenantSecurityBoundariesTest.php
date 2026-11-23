<?php

namespace Tests\Feature;

use App\Models\Amenity;
use App\Models\AmenityCategory;
use App\Models\Establishment;
use App\Models\Property;
use App\Models\Reservation;
use App\Models\ReservationGuest;
use App\Models\SiteReview;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class TenantSecurityBoundariesTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_admin_cannot_open_or_mutate_foreign_tenant_records(): void
    {
        $this->seed(DatabaseSeeder::class);
        $seededAdmin = User::where('email', 'admin@afrikappart.test')->firstOrFail();
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_admin' => true,
            'is_platform_admin' => false,
            'tenant_id' => $seededAdmin->tenant_id,
        ]);
        $tenant = Tenant::query()->create(['name' => 'Foreign tenant', 'slug' => 'foreign-tenant', 'status' => 'active']);
        $establishment = Establishment::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Foreign establishment',
            'slug' => 'foreign-establishment',
            'currency' => 'XOF',
        ]);
        $property = $establishment->properties()->create([
            'name' => 'Foreign property',
            'slug' => 'foreign-property',
            'status' => 'published',
            'is_active' => true,
        ]);
        $category = AmenityCategory::query()->create([
            'tenant_id' => $tenant->id,
            'name_en' => 'Foreign category',
            'name_fr' => 'Catégorie étrangère',
            'slug' => 'foreign-category',
        ]);
        $amenity = Amenity::query()->create([
            'tenant_id' => $tenant->id,
            'category_id' => $category->id,
            'name_en' => 'Foreign amenity',
            'name_fr' => 'Équipement étranger',
            'slug' => 'foreign-amenity',
        ]);
        $foreignUser = User::factory()->create(['role' => 'host', 'tenant_id' => $tenant->id]);
        $review = SiteReview::query()->create([
            'tenant_id' => $tenant->id,
            'establishment_id' => $establishment->id,
            'reviewer_name' => 'Foreign reviewer',
            'rating' => 5,
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.properties.edit', $property))
            ->assertNotFound();
        $this->actingAs($admin)
            ->put(route('admin.properties.update', $property), [
                'name' => 'Changed by foreign admin',
                'slug' => 'foreign-property',
                'nightly_rate_xof' => 1000,
                'minimum_stay' => 1,
                'max_guests' => 2,
                'status' => 'published',
            ])
                ->assertNotFound();
        $this->actingAs($admin)->get(route('admin.properties.calendar', $property))->assertNotFound();
        $this->actingAs($admin)->get(route('admin.properties.calendar.export', $property))->assertNotFound();
        $this->actingAs($admin)->get(route('admin.establishments.edit', $establishment))->assertNotFound();
        $this->actingAs($admin)->get(route('admin.amenities.categories.show', $category))->assertNotFound();
        $this->actingAs($admin)->get(route('admin.amenities.edit', $amenity))->assertNotFound();
        $this->actingAs($admin)->get(route('admin.users.edit', $foreignUser))->assertNotFound();
        $this->actingAs($admin)->put(route('admin.reviews.update', $review), ['is_active' => false])->assertNotFound();

        $this->assertSame('Foreign property', $property->fresh()->name);
        $this->assertTrue($review->fresh()->is_active);
    }

    public function test_host_cannot_reassign_property_or_confirm_payment_outside_assigned_establishments(): void
    {
        $this->seed(DatabaseSeeder::class);
        $property = Property::query()->where('slug', 'appartement-401')->firstOrFail();
        $establishment = $property->establishment;
        $otherEstablishment = Establishment::query()->create([
            'tenant_id' => $establishment->tenant_id,
            'name' => 'Unassigned establishment',
            'slug' => 'unassigned-establishment',
            'currency' => 'XOF',
        ]);
        $host = User::factory()->create(['role' => 'host', 'tenant_id' => $establishment->tenant_id]);
        $host->establishments()->attach($establishment);
        $guest = ReservationGuest::query()->create(['full_name' => 'Guest', 'email' => 'foreign-payment@example.com']);
        $otherProperty = $otherEstablishment->properties()->create([
            'name' => 'Unassigned property',
            'slug' => 'unassigned-property',
            'status' => 'published',
            'is_active' => true,
        ]);
        $reservation = Reservation::query()->create([
            'property_id' => $otherProperty->id,
            'guest_id' => $guest->id,
            'reservation_ref' => 'AFK-UNASSIGNED-PAYMENT',
            'status' => 'pending_validation',
            'check_in' => now()->addDays(8)->toDateString(),
            'check_out' => now()->addDays(10)->toDateString(),
            'currency' => 'XOF',
            'email' => $guest->email,
            'total_amount' => 10000,
        ]);

        $this->actingAs($host)->put(route('admin.properties.update', $property), [
            'name' => $property->name,
            'slug' => $property->slug,
            'nightly_rate_xof' => $property->nightly_rate_xof,
            'minimum_stay' => $property->minimum_stay,
            'max_guests' => $property->max_guests,
            'status' => $property->status,
            'establishment_id' => $otherEstablishment->id,
        ])->assertForbidden();

        $this->actingAs($host)->post(route('admin.reservations.confirm-offline-payment', $reservation), [
            'payment_proof' => UploadedFile::fake()->create('proof.pdf', 10, 'application/pdf'),
        ])->assertForbidden();

        $this->assertSame($establishment->id, $property->fresh()->establishment_id);
        $this->assertSame('pending_validation', $reservation->fresh()->status);
    }

    public function test_tenant_user_search_and_role_filters_are_applied_within_tenant_scope(): void
    {
        $this->seed(DatabaseSeeder::class);
        $seededAdmin = User::where('email', 'admin@afrikappart.test')->firstOrFail();
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_admin' => true,
            'is_platform_admin' => false,
            'tenant_id' => $seededAdmin->tenant_id,
        ]);
        $ownMatch = User::factory()->create([
            'name' => 'TenantSearchUnique own host',
            'role' => 'host',
            'tenant_id' => $admin->tenant_id,
        ]);
        User::factory()->create([
            'name' => 'TenantSearchUnique admin',
            'role' => 'admin',
            'tenant_id' => $admin->tenant_id,
        ]);
        $foreignTenant = Tenant::query()->create(['name' => 'Search tenant', 'slug' => 'search-tenant', 'status' => 'active']);
        $foreignMatch = User::factory()->create([
            'name' => 'TenantSearchUnique foreign host',
            'role' => 'host',
            'tenant_id' => $foreignTenant->id,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.users.index', [
            'search' => 'TenantSearchUnique',
            'role' => 'host',
        ]))->assertOk();

        $response->assertSee($ownMatch->name)->assertDontSee($foreignMatch->name);
        $this->assertSame([$ownMatch->id], $response->viewData('users')->getCollection()->pluck('id')->all());
    }
}
