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
        $this->seed(DatabaseSeeder::class);
        $host = User::factory()->create(['role' => 'host', 'is_platform_admin' => false]);

        $this->actingAs($host)->get(route('platform.tenants.create'))->assertForbidden();
        $this->actingAs($host)->post(route('platform.tenants.store'), [
            'name' => 'Blocked Tenant',
            'admin_name' => 'Blocked Admin',
            'admin_email' => 'blocked-admin@example.com',
        ])->assertForbidden();
    }
}
