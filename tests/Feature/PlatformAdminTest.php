<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_admin_can_create_a_new_tenant_with_an_invited_admin(): void
    {
        config(['platform.mode' => 'cloud']);
        $this->seed(DatabaseSeeder::class);
        $platformAdmin = User::where('email', 'admin@afrikappart.test')->firstOrFail();

        $this->actingAs($platformAdmin)->get(route('platform.tenants.index'))
            ->assertOk()
            ->assertSee(__('messages.platform.add_tenant'));

        $this->actingAs($platformAdmin)->get(route('platform.tenants.create'))->assertOk();

        $this->actingAs($platformAdmin)->post(route('platform.tenants.store'), [
            'name' => 'New Hospitality Group',
            'contact_email' => 'contact@newgroup.test',
            'admin_name' => 'New Group Admin',
            'admin_email' => 'newgroupadmin@example.com',
        ])->assertRedirect(route('platform.tenants.index'));

        $tenant = Tenant::where('name', 'New Hospitality Group')->firstOrFail();
        $this->assertSame('active', $tenant->status);
        $this->assertSame('new-hospitality-group', $tenant->slug);

        $this->assertDatabaseHas('users', [
            'email' => 'newgroupadmin@example.com',
            'tenant_id' => $tenant->id,
            'role' => 'admin',
            'is_admin' => true,
        ]);

        $this->assertDatabaseHas('email_outbox', [
            'recipient_email' => 'newgroupadmin@example.com',
            'template' => 'staff_account_invitation',
            'status' => 'queued',
        ]);
    }

    public function test_non_platform_admin_cannot_create_a_tenant(): void
    {
        config(['platform.mode' => 'cloud']);
        $this->seed(DatabaseSeeder::class);
        $host = User::factory()->create(['role' => 'host', 'is_platform_admin' => false]);

        $this->actingAs($host)->get(route('platform.tenants.create'))->assertForbidden();
        $this->actingAs($host)->post(route('platform.tenants.store'), [
            'name' => 'Blocked Tenant',
            'admin_name' => 'Blocked Admin',
            'admin_email' => 'blocked-admin@example.com',
        ])->assertForbidden();
    }

    public function test_duplicate_tenant_name_is_rejected_with_a_validation_error(): void
    {
        config(['platform.mode' => 'cloud']);
        $this->seed(DatabaseSeeder::class);
        $platformAdmin = User::where('email', 'admin@afrikappart.test')->firstOrFail();
        Tenant::query()->create([
            'name' => 'Repeated Generated Tenant',
            'slug' => 'repeated-generated-tenant',
            'status' => 'active',
        ]);

        $this->actingAs($platformAdmin)->from(route('platform.tenants.create'))->post(route('platform.tenants.store'), [
            'name' => 'Repeated Generated Tenant',
            'slug' => 'another-valid-slug',
            'admin_name' => 'Second Tenant Admin',
            'admin_email' => 'second-tenant-admin@example.com',
        ])->assertRedirect()->assertSessionHasErrors('name');

        $this->assertDatabaseMissing('tenants', ['slug' => 'another-valid-slug']);
        $this->assertDatabaseMissing('users', ['email' => 'second-tenant-admin@example.com']);
    }

    public function test_duplicate_derived_tenant_slug_is_rejected_with_a_validation_error(): void
    {
        config(['platform.mode' => 'cloud']);
        $this->seed(DatabaseSeeder::class);
        $platformAdmin = User::where('email', 'admin@afrikappart.test')->firstOrFail();
        Tenant::query()->create([
            'name' => 'Existing',
            'slug' => 'repeated-generated-tenant',
            'status' => 'active',
        ]);

        $this->actingAs($platformAdmin)->from(route('platform.tenants.create'))->post(route('platform.tenants.store'), [
            'name' => 'Repeated Generated Tenant',
            'admin_name' => 'Second Tenant Admin',
            'admin_email' => 'second-tenant-admin@example.com',
        ])->assertRedirect(route('platform.tenants.create'))->assertSessionHasErrors('slug');

        $this->assertDatabaseMissing('users', ['email' => 'second-tenant-admin@example.com']);
        $this->assertDatabaseCount('tenants', 2);
    }

    public function test_tenant_creation_is_unavailable_outside_cloud_mode(): void
    {
        config(['platform.mode' => 'on_premise']);
        $this->seed(DatabaseSeeder::class);
        $platformAdmin = User::where('email', 'admin@afrikappart.test')->firstOrFail();

        $this->actingAs($platformAdmin)->get(route('platform.tenants.index'))
            ->assertOk()
            ->assertDontSee(__('messages.platform.add_tenant'));

        $this->actingAs($platformAdmin)->get(route('platform.tenants.create'))->assertNotFound();
        $this->actingAs($platformAdmin)->post(route('platform.tenants.store'), [
            'name' => 'Blocked Onprem Tenant',
            'admin_name' => 'Blocked Admin',
            'admin_email' => 'blocked-onprem-admin@example.com',
        ])->assertNotFound();
    }
}
