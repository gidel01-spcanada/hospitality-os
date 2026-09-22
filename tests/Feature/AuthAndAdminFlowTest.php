<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\EmailOutbox;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthAndAdminFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_and_customer_dashboard_work(): void
    {
        $this->seed(DatabaseSeeder::class);

        $response = $this->post('/register', [
            'name' => 'Amina Diallo',
            'email' => 'amina@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'locale' => 'en',
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertDatabaseHas('users', ['email' => 'amina@example.com', 'role' => 'customer', 'locale' => 'en']);
        $this->assertAuthenticated();
    }

    public function test_registration_page_includes_language_preference(): void
    {
        $this->withSession(['locale' => 'en'])
            ->get('/register')
            ->assertOk()
            ->assertSee('name="locale"', false)
            ->assertSee('Language')
            ->assertSee('English');
    }

    public function test_guest_can_switch_locale_and_only_sees_the_alternate_language(): void
    {
        $this->withSession(['locale' => 'fr'])
            ->get('/')
            ->assertSee('href="' . route('language.switch', ['locale' => 'en']) . '"', false)
            ->assertDontSee('href="' . route('language.switch', ['locale' => 'fr']) . '"', false);

        $this->withSession(['locale' => 'fr'])
            ->get('/language/en')
            ->assertRedirect('/')
            ->assertCookie('locale', 'en');

        $this->assertSame('en', session('locale'));
        $this->withCookie('locale', 'en')->get('/')->assertSee('Home');
    }

    public function test_authenticated_user_uses_saved_locale_and_sees_no_language_switcher(): void
    {
        $this->seed(DatabaseSeeder::class);

        $user = User::factory()->create(['locale' => 'en']);

        $this->actingAs($user)
            ->withSession(['locale' => 'fr'])
            ->get('/')
            ->assertSee('Home')
            ->assertDontSee('language.switch');
    }

    public function test_admin_access_is_restricted_to_admin_users(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = User::where('email', 'admin@afrikappart.test')->firstOrFail();
        $customer = User::factory()->create([
            'name' => 'Regular Guest',
            'role' => 'customer',
            'is_admin' => false,
        ]);

        $this->actingAs($admin)
            ->get('/admin')
            ->assertOk()
            ->assertSee('>1<', false);

        $this->actingAs($customer)
            ->get('/admin')
            ->assertForbidden();
    }

    public function test_admin_login_redirects_to_admin_dashboard(): void
    {
        $this->seed(DatabaseSeeder::class);

        $response = $this->post('/login', [
            'email' => 'admin@afrikappart.test',
            'password' => 'Password123!',
        ]);

        $response->assertRedirect('/admin');
        $this->assertAuthenticatedAs(User::where('email', 'admin@afrikappart.test')->firstOrFail());
    }

    public function test_admin_can_manage_user_access_and_preferences(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::where('email', 'admin@afrikappart.test')->firstOrFail();
        $customer = User::factory()->create(['role' => 'customer', 'locale' => 'fr']);

        $this->actingAs($admin)
            ->put('/admin/users/' . $customer->id, [
                'name' => 'Managed Customer',
                'email' => 'managed@example.com',
                'role' => 'admin',
                'locale' => 'en',
                'email_verified' => '1',
                'email_marketing' => '1',
            ])
            ->assertRedirect('/admin/users');

        $this->assertDatabaseHas('users', [
            'id' => $customer->id,
            'name' => 'Managed Customer',
            'email' => 'managed@example.com',
            'role' => 'admin',
            'is_admin' => true,
            'locale' => 'en',
            'email_marketing' => true,
        ]);
    }

    public function test_admin_cannot_remove_own_administrator_access(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::where('email', 'admin@afrikappart.test')->firstOrFail();

        $this->actingAs($admin)
            ->from('/admin/users/' . $admin->id . '/edit')
            ->put('/admin/users/' . $admin->id, [
                'name' => $admin->name,
                'email' => $admin->email,
                'role' => 'customer',
                'locale' => $admin->locale,
            ])
            ->assertSessionHasErrors('role');
    }

    public function test_admin_can_deactivate_user_and_deactivated_user_cannot_login(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::where('email', 'admin@afrikappart.test')->firstOrFail();
        $customer = User::factory()->create(['email' => 'inactive@example.com', 'password' => 'Password123!', 'is_active' => true]);

        $this->actingAs($admin)
            ->put('/admin/users/' . $customer->id, [
                'name' => $customer->name,
                'email' => $customer->email,
                'role' => 'customer',
                'locale' => 'fr',
                'is_active' => '0',
            ])
            ->assertRedirect('/admin/users');

        $this->assertDatabaseHas('users', ['id' => $customer->id, 'is_active' => false]);
        $this->post('/logout')->assertRedirect('/');
        $this->post('/login', ['email' => $customer->email, 'password' => 'Password123!'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_concierge_can_coordinate_reservations_but_not_manage_properties(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::where('email', 'admin@afrikappart.test')->firstOrFail();
        $concierge = User::factory()->create(['role' => 'concierge', 'is_admin' => false]);

        $this->actingAs($admin)
            ->put('/admin/users/' . $concierge->id, [
                'name' => $concierge->name,
                'email' => $concierge->email,
                'role' => 'concierge',
                'locale' => 'fr',
            ])
            ->assertRedirect('/admin/users');

        $this->actingAs($concierge)->get('/admin')->assertOk();
        $this->actingAs($concierge)->get('/admin')
            ->assertSee('href="' . route('account.profile') . '"', false)
            ->assertDontSee('href="' . route('admin.users.index') . '"', false)
            ->assertDontSee('href="' . route('admin.properties.index') . '"', false)
            ->assertDontSee('href="' . route('admin.settings') . '"', false);
        $this->actingAs($concierge)->get('/admin/reservations')->assertOk();
        $this->actingAs($concierge)->get(route('account.profile'))->assertOk();
        $this->actingAs($concierge)->get('/admin/properties')->assertForbidden();
        $this->actingAs($concierge)->get('/admin/users')->assertForbidden();
    }

    public function test_admin_can_create_a_concierge_user(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::where('email', 'admin@afrikappart.test')->firstOrFail();

        $this->actingAs($admin)
            ->post('/admin/users', [
                'name' => 'Front Desk',
                'email' => 'concierge@example.com',
                'password' => 'Password123!',
                'password_confirmation' => 'Password123!',
                'role' => 'concierge',
                'locale' => 'fr',
            ])
            ->assertRedirect('/admin/users');

        $this->assertDatabaseHas('users', ['email' => 'concierge@example.com', 'role' => 'concierge', 'is_admin' => false]);
    }

    public function test_host_can_manage_establishments_and_properties_but_not_site_configuration(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::where('email', 'admin@afrikappart.test')->firstOrFail();
        $host = User::factory()->create(['role' => 'host', 'is_admin' => false, 'tenant_id' => $admin->tenant_id]);

        $this->actingAs($host)->get('/admin')->assertOk();
        $this->actingAs($host)->get(route('admin.establishments.index'))->assertOk();
        $this->actingAs($host)->get(route('admin.properties.index'))->assertOk();
        $this->actingAs($host)->get('/admin/reservations')->assertOk();
        $this->actingAs($host)->get(route('admin.preferences'))->assertOk();

        $this->actingAs($host)->get(route('admin.users.index'))->assertForbidden();
        $this->actingAs($host)->get(route('admin.settings'))->assertForbidden();
        $this->actingAs($host)->get(route('admin.amenities.index'))->assertForbidden();
        $this->actingAs($host)->get(route('admin.reviews'))->assertForbidden();

        $this->actingAs($host)->get('/admin')
            ->assertSee('href="' . route('admin.establishments.index') . '"', false)
            ->assertSee('href="' . route('admin.properties.index') . '"', false)
            ->assertDontSee('href="' . route('admin.users.index') . '"', false)
            ->assertDontSee('href="' . route('admin.settings') . '"', false);
    }

    public function test_admin_can_create_a_host_user(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::where('email', 'admin@afrikappart.test')->firstOrFail();

        $this->actingAs($admin)
            ->post('/admin/users', [
                'name' => 'Property Host',
                'email' => 'host@example.com',
                'password' => 'Password123!',
                'password_confirmation' => 'Password123!',
                'role' => 'host',
                'locale' => 'fr',
            ])
            ->assertRedirect('/admin/users');

        $createdHost = User::where('email', 'host@example.com')->firstOrFail();
        $this->assertSame('host', $createdHost->role);
        $this->assertFalse($createdHost->is_admin);
        $this->assertNotNull($createdHost->email_verified_at);
        $this->assertDatabaseHas('email_outbox', ['recipient_email' => 'host@example.com', 'template' => 'staff_account_invitation']);
    }

    public function test_host_is_scoped_to_their_assigned_establishment_only(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::where('email', 'admin@afrikappart.test')->firstOrFail();
        $assigned = \App\Models\Establishment::query()->firstOrFail();
        $properties = \App\Models\Property::query()->where('establishment_id', $assigned->id)->orderBy('id')->take(2)->get();
        $this->assertGreaterThanOrEqual(2, $properties->count(), 'Sample data must seed at least two properties.');
        $ownProperty = $properties->first();
        $otherProperty = $properties->last();

        $other = \App\Models\Establishment::create([
            'tenant_id' => $assigned->tenant_id,
            'name' => 'Second Establishment',
            'slug' => 'second-establishment',
            'country_code' => 'BJ',
            'currency' => 'XOF',
        ]);
        $otherProperty->update(['establishment_id' => $other->id]);

        $host = User::factory()->create(['role' => 'host', 'is_admin' => false, 'tenant_id' => $admin->tenant_id]);
        $host->establishments()->sync([$assigned->id]);

        // Assigned establishment/property: allowed.
        $this->actingAs($host)->get(route('admin.establishments.edit', $assigned))->assertOk();
        $this->actingAs($host)->get(route('admin.properties.edit', $ownProperty))->assertOk();

        // Unassigned establishment/property: forbidden.
        $this->actingAs($host)->get(route('admin.establishments.edit', $other))->assertForbidden();
        $this->actingAs($host)->get(route('admin.properties.edit', $otherProperty))->assertForbidden();

        // Establishment/property lists only show the host's own establishment.
        $this->actingAs($host)->get(route('admin.establishments.index'))
            ->assertSee($assigned->name)
            ->assertDontSee($other->name);

        // Hosts cannot create new establishments; only assigned ones.
        $this->actingAs($host)->get(route('admin.establishments.create'))->assertForbidden();
        $this->actingAs($host)->post(route('admin.establishments.store'), ['name' => 'New Place'])->assertForbidden();
    }

    public function test_host_can_assign_and_remove_additional_hosts_on_their_own_establishment(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::where('email', 'admin@afrikappart.test')->firstOrFail();
        $assigned = \App\Models\Establishment::query()->firstOrFail();
        $other = \App\Models\Establishment::create([
            'tenant_id' => $assigned->tenant_id,
            'name' => 'Second Establishment',
            'slug' => 'second-establishment-hosts',
            'country_code' => 'BJ',
            'currency' => 'XOF',
        ]);

        $host = User::factory()->create(['role' => 'host', 'is_admin' => false, 'tenant_id' => $admin->tenant_id]);
        $host->establishments()->sync([$assigned->id]);

        $this->actingAs($host)
            ->post(route('admin.establishments.hosts.store', $assigned), [
                'name' => 'Co-Host',
                'email' => 'co-host@example.com',
                'password' => 'Password123!',
                'password_confirmation' => 'Password123!',
            ])
            ->assertRedirect(route('admin.establishments.edit', $assigned) . '#hosts');

        $coHost = User::where('email', 'co-host@example.com')->firstOrFail();
        $this->assertSame('host', $coHost->role);
        $this->assertNotNull($coHost->email_verified_at);
        $this->assertDatabaseHas('email_outbox', ['recipient_email' => 'co-host@example.com', 'template' => 'staff_account_invitation']);
        $this->assertTrue($coHost->establishments()->whereKey($assigned->id)->exists());

        // The newly added co-host can now manage the same establishment.
        $this->actingAs($coHost)->get(route('admin.establishments.edit', $assigned))->assertOk();

        // A host cannot add a co-host to an establishment they don't manage.
        $this->actingAs($host)
            ->post(route('admin.establishments.hosts.store', $other), [
                'name' => 'Intruder',
                'email' => 'intruder@example.com',
                'password' => 'Password123!',
                'password_confirmation' => 'Password123!',
            ])
            ->assertForbidden();

        $this->actingAs($host)
            ->delete(route('admin.establishments.hosts.destroy', [$assigned, $coHost]))
            ->assertRedirect(route('admin.establishments.edit', $assigned) . '#hosts');

        $this->assertFalse($coHost->establishments()->whereKey($assigned->id)->exists());
    }

    public function test_host_reservations_and_messages_are_restricted_to_their_establishments(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::where('email', 'admin@afrikappart.test')->firstOrFail();
        $assigned = \App\Models\Establishment::query()->firstOrFail();
        $properties = \App\Models\Property::query()->where('establishment_id', $assigned->id)->orderBy('id')->take(2)->get();
        $ownProperty = $properties->first();
        $otherProperty = $properties->last();

        $other = \App\Models\Establishment::create([
            'tenant_id' => $assigned->tenant_id,
            'name' => 'Second Establishment',
            'slug' => 'second-establishment-reservations',
            'country_code' => 'BJ',
            'currency' => 'XOF',
        ]);
        $otherProperty->update(['establishment_id' => $other->id]);

        $host = User::factory()->create(['role' => 'host', 'is_admin' => false, 'tenant_id' => $admin->tenant_id]);
        $host->establishments()->sync([$assigned->id]);

        $ownReservation = \App\Models\Reservation::query()->create([
            'property_id' => $ownProperty->id,
            'reservation_ref' => 'OWN-REF-1',
            'status' => 'confirmed',
            'check_in' => '2026-10-01',
            'check_out' => '2026-10-03',
            'email' => 'own-guest@example.com',
        ]);
        $otherReservation = \App\Models\Reservation::query()->create([
            'property_id' => $otherProperty->id,
            'reservation_ref' => 'OTHER-REF-1',
            'status' => 'confirmed',
            'check_in' => '2026-10-01',
            'check_out' => '2026-10-03',
            'email' => 'other-guest@example.com',
        ]);

        $this->actingAs($host)->get(route('admin.reservations.index'))
            ->assertSee('own-guest@example.com')
            ->assertDontSee('other-guest@example.com');

        $this->actingAs($host)->get(route('admin.reservations.show', $ownReservation))->assertOk();
        $this->actingAs($host)->get(route('admin.reservations.show', $otherReservation))->assertForbidden();

        $customer = User::factory()->create(['role' => 'customer']);
        $ownThread = \App\Models\MessageThread::create(['customer_id' => $customer->id, 'establishment_id' => $assigned->id]);
        $otherThread = \App\Models\MessageThread::create(['customer_id' => $customer->id, 'establishment_id' => $other->id]);

        $this->actingAs($host)->get(route('admin.messages.index'))
            ->assertSee(route('admin.messages.show', $ownThread), false)
            ->assertDontSee(route('admin.messages.show', $otherThread), false);

        $this->actingAs($host)->get(route('admin.messages.show', $ownThread))->assertOk();
        $this->actingAs($host)->get(route('admin.messages.show', $otherThread))->assertForbidden();
    }
}
