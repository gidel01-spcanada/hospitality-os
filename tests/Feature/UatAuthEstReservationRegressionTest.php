<?php

namespace Tests\Feature;

use App\Models\Establishment;
use App\Models\MessageThread;
use App\Models\Property;
use App\Models\Reservation;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UatAuthEstReservationRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_host_signup_rejects_names_that_produce_empty_slugs_without_creating_accounts(): void
    {
        config(['platform.mode' => 'cloud', 'services.recaptcha.enabled' => false]);

        $this->from('/host/register')->post('/host/register', [
            'company_name' => '!!!',
            'contact_email' => 'contact@example.com',
            'name' => 'Host',
            'email' => 'host@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ])->assertRedirect('/host/register')->assertSessionHasErrors('company_name');

        $this->assertDatabaseMissing('tenants', ['name' => '!!!']);
        $this->assertDatabaseMissing('users', ['email' => 'host@example.com']);
        $this->assertGuest();
    }

    public function test_host_signup_still_transliterates_names_and_deduplicates_slugs(): void
    {
        config(['platform.mode' => 'cloud', 'services.recaptcha.enabled' => false]);

        foreach (['first@example.com', 'second@example.com'] as $email) {
            $this->post('/host/register', [
                'company_name' => 'Résidence UAT',
                'contact_email' => 'contact@example.com',
                'name' => 'Host',
                'email' => $email,
                'password' => 'Password123!',
                'password_confirmation' => 'Password123!',
            ])->assertRedirect(route('admin.dashboard'))->assertSessionHasNoErrors();
            auth()->logout();
        }

        $this->assertDatabaseHas('tenants', ['name' => 'Résidence UAT', 'slug' => 'residence-uat']);
        $this->assertDatabaseHas('tenants', ['name' => 'Résidence UAT', 'slug' => 'residence-uat-2']);
    }

    public function test_platform_tenant_creation_validates_derived_slug_and_allows_explicit_slug(): void
    {
        config(['platform.mode' => 'cloud']);
        $this->seed(DatabaseSeeder::class);
        $admin = User::where('email', 'admin@afrikappart.test')->firstOrFail();
        $payload = ['name' => '!!!', 'admin_name' => 'Owner', 'admin_email' => 'owner@example.com'];

        $this->actingAs($admin)->from(route('platform.tenants.create'))
            ->post(route('platform.tenants.store'), $payload)
            ->assertSessionHasErrors('slug');
        $this->assertDatabaseMissing('tenants', ['name' => '!!!']);
        $this->assertDatabaseMissing('users', ['email' => 'owner@example.com']);

        $this->post(route('platform.tenants.store'), $payload + ['slug' => 'valid-company'])
            ->assertRedirect(route('platform.tenants.index'))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('tenants', ['name' => '!!!', 'slug' => 'valid-company']);
    }

    public function test_platform_tenant_creation_deduplicates_derived_slugs(): void
    {
        config(['platform.mode' => 'cloud']);
        $this->seed(DatabaseSeeder::class);
        $admin = User::where('email', 'admin@afrikappart.test')->firstOrFail();
        Tenant::create(['name' => 'Existing', 'slug' => 'existing-company', 'status' => 'active']);

        $this->actingAs($admin)->post(route('platform.tenants.store'), [
            'name' => 'Existing Company', 'admin_name' => 'Owner', 'admin_email' => 'owner@example.com',
        ])->assertRedirect(route('platform.tenants.index'))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('tenants', ['name' => 'Existing Company', 'slug' => 'existing-company-2']);
    }

    public function test_establishment_form_and_store_allow_blank_or_omitted_slug(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::where('email', 'admin@afrikappart.test')->firstOrFail();

        $this->actingAs($admin)->get(route('admin.establishments.create'))->assertOk()
            ->assertSee('name="slug" value=""', false)
            ->assertDontSee('name="slug" value="" required', false);

        foreach (['Résidence Ouidah' => '', 'Another Residence' => null] as $name => $slug) {
            $payload = ['name' => $name, 'country_code' => 'BJ', 'currency' => 'XOF'];
            if ($slug !== null) {
                $payload['slug'] = $slug;
            }
            $this->post(route('admin.establishments.store'), $payload)
                ->assertRedirect(route('admin.establishments.index'))->assertSessionHasNoErrors();
        }
        $this->assertDatabaseHas('establishments', ['name' => 'Résidence Ouidah', 'slug' => 'residence-ouidah']);
        $this->assertDatabaseHas('establishments', ['name' => 'Another Residence', 'slug' => 'another-residence']);
    }

    public function test_establishment_validates_the_final_slug_on_creation_and_update(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::where('email', 'admin@afrikappart.test')->firstOrFail();
        $existing = Establishment::query()->firstOrFail();
        $payload = ['name' => $existing->name, 'country_code' => 'BJ', 'currency' => 'XOF', 'slug' => ''];

        $this->actingAs($admin)->post(route('admin.establishments.store'), $payload)
            ->assertSessionHasErrors('slug');
        $this->post(route('admin.establishments.store'), array_merge($payload, ['name' => '!!!']))
            ->assertSessionHasErrors('slug');
        $this->put(route('admin.establishments.update', $existing), array_merge($payload, ['name' => 'Updated Residence']))
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('updated-residence', $existing->fresh()->slug);

        $this->post(route('admin.establishments.store'), array_merge($payload, ['name' => 'Other', 'slug' => 'Updated Residence']))
            ->assertSessionHasErrors('slug');
    }

    public function test_admin_created_reservation_links_new_guest_and_thread_only_on_viewing(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::where('email', 'admin@afrikappart.test')->firstOrFail();
        $property = Property::where('slug', 'appartement-401')->firstOrFail();

        $this->actingAs($admin)->post(route('admin.reservations.store'), [
            'property_id' => $property->id, 'full_name' => 'New Guest', 'email' => 'new-guest@example.com',
            'check_in' => now()->addDays(30)->toDateString(), 'check_out' => now()->addDays(32)->toDateString(),
            'adults' => 1,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $reservation = Reservation::where('email', 'new-guest@example.com')->firstOrFail();
        $this->assertNull($reservation->user_id);
        $this->assertDatabaseMissing('users', ['email' => 'new-guest@example.com']);

        $this->get(route('admin.reservations.show', $reservation))->assertOk();
        $customer = User::where('email', 'new-guest@example.com')->firstOrFail();
        $this->assertSame('customer', $customer->role);
        $this->assertSame($customer->id, $reservation->fresh()->user_id);
        $this->assertDatabaseHas('message_threads', ['customer_id' => $customer->id, 'establishment_id' => $property->establishment_id]);
        $this->assertDatabaseMissing('message_threads', ['customer_id' => $admin->id, 'establishment_id' => $property->establishment_id]);
        $this->get(route('admin.reservations.show', $reservation))->assertOk();
        $this->assertSame(1, MessageThread::where('customer_id', $customer->id)->where('establishment_id', $property->establishment_id)->count());
    }

    public function test_admin_created_reservation_uses_existing_customer_for_thread(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::where('email', 'admin@afrikappart.test')->firstOrFail();
        $property = Property::where('slug', 'appartement-401')->firstOrFail();
        $customer = User::factory()->create(['email' => 'existing-guest@example.com', 'role' => 'customer']);

        $this->actingAs($admin)->post(route('admin.reservations.store'), [
            'property_id' => $property->id, 'full_name' => 'Existing Guest', 'email' => $customer->email,
            'check_in' => now()->addDays(30)->toDateString(), 'check_out' => now()->addDays(32)->toDateString(),
            'adults' => 1,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $reservation = Reservation::where('email', $customer->email)->firstOrFail();
        $this->assertNull($reservation->user_id);
        $this->get(route('admin.reservations.show', $reservation))->assertOk();
        $this->assertSame($customer->id, $reservation->fresh()->user_id);
        $this->assertDatabaseHas('message_threads', ['customer_id' => $customer->id, 'establishment_id' => $property->establishment_id]);
        $this->assertDatabaseMissing('message_threads', ['customer_id' => $admin->id, 'establishment_id' => $property->establishment_id]);
    }
}
