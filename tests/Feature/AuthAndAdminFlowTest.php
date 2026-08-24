<?php

namespace Tests\Feature;

use App\Models\User;
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
        $this->actingAs($concierge)->get('/admin/reservations')->assertOk();
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
}
