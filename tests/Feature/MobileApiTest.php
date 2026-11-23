<?php

namespace Tests\Feature;

use App\Models\Property;
use App\Models\Reservation;
use App\Models\ReservationGuest;
use App\Models\Establishment;
use App\Models\MobileAccessToken;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MobileApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_mobile_login_get_returns_method_not_allowed(): void
    {
        $this->getJson('/api/v1/auth/login')->assertMethodNotAllowed();
    }

    public function test_customer_can_authenticate_and_access_the_mobile_catalog_and_reservations(): void
    {
        $this->seed(DatabaseSeeder::class);
        $customer = User::factory()->create([
            'email' => 'mobile-customer@example.com',
            'password' => Hash::make('Password123!'),
            'role' => 'customer',
            'is_active' => true,
        ]);
        $property = Property::query()->where('status', 'published')->firstOrFail();
        $guest = ReservationGuest::query()->create(['full_name' => 'Mobile Customer', 'email' => $customer->email]);
        $reservation = Reservation::query()->create([
            'property_id' => $property->id,
            'guest_id' => $guest->id,
            'user_id' => $customer->id,
            'reservation_ref' => 'AFK-MOBILE-001',
            'status' => 'pending',
            'check_in' => now()->addDay()->toDateString(),
            'check_out' => now()->addDays(3)->toDateString(),
            'adults' => 1,
            'currency' => 'XOF',
            'email' => $customer->email,
            'total_amount' => 100000,
        ]);

        $login = $this->postJson('/api/v1/auth/login', ['email' => $customer->email, 'password' => 'Password123!', 'device_name' => 'PHPUnit']);
        $login->assertCreated()->assertJsonPath('user.role', 'customer');
        $token = $login->json('token');
        $storedToken = MobileAccessToken::query()->where('user_id', $customer->id)->firstOrFail();
        $this->assertTrue($storedToken->expires_at->isFuture());
        $this->assertTrue($storedToken->expires_at->isSameDay(now()->addDays(30)));

        $this->getJson('/api/v1/properties')
            ->assertOk()
            ->assertJsonPath('data.0.id', $property->id)
            ->assertJsonPath('data.0.bedrooms', $property->bedrooms)
            ->assertJsonPath('data.0.bathrooms', $property->bathrooms)
            ->assertJsonPath('data.0.beds', $property->beds)
            ->assertJsonPath('data.0.minimum_stay', $property->minimum_stay);
        $this->withToken($token)->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.email', $customer->email);
        $this->withToken($token)->getJson('/api/v1/customer/reservations')->assertOk()->assertJsonPath('data.0.id', $reservation->id);
    }

    public function test_concierge_can_queue_a_payment_link_but_customer_cannot(): void
    {
        $this->seed(DatabaseSeeder::class);
        $property = Property::query()->where('status', 'published')->firstOrFail();
        $guest = ReservationGuest::query()->create(['full_name' => 'Guest', 'email' => 'guest@example.com']);
        $reservation = Reservation::query()->create([
            'property_id' => $property->id,
            'guest_id' => $guest->id,
            'reservation_ref' => 'AFK-MOBILE-002',
            'status' => 'pending',
            'check_in' => now()->addDay()->toDateString(),
            'check_out' => now()->addDays(3)->toDateString(),
            'adults' => 1,
            'currency' => 'XOF',
            'email' => $guest->email,
            'total_amount' => 100000,
        ]);
        $customer = User::factory()->create(['role' => 'customer', 'is_active' => true]);
        $concierge = User::factory()->create(['role' => 'concierge', 'tenant_id' => $property->establishment->tenant_id, 'is_active' => true]);

        $customerToken = $this->tokenFor($customer);
        $this->withToken($customerToken)->postJson('/api/v1/staff/reservations/' . $reservation->id . '/payment-link')->assertForbidden();

        $conciergeToken = $this->tokenFor($concierge);
        $this->withToken($conciergeToken)->postJson('/api/v1/staff/reservations/' . $reservation->id . '/payment-link')
            ->assertStatus(202)
            ->assertJsonPath('checkout_url', route('checkout.show', ['reservation' => $reservation, 'token' => $reservation->checkout_token]));
        $this->assertDatabaseHas('email_outbox', ['recipient_email' => $guest->email, 'template' => 'payment_link', 'status' => 'queued']);
    }

    public function test_mobile_logout_revokes_the_current_bearer_token(): void
    {
        $customer = User::factory()->create(['role' => 'customer', 'is_active' => true]);
        $token = $this->tokenFor($customer);

        $this->withToken($token)->postJson('/api/v1/auth/logout')->assertNoContent();
        $this->withToken($token)->getJson('/api/v1/me')->assertUnauthorized();
    }

    public function test_expired_mobile_access_tokens_are_rejected(): void
    {
        $customer = User::factory()->create(['role' => 'customer', 'is_active' => true]);
        $plainToken = 'expired-mobile-token';
        MobileAccessToken::query()->create([
            'user_id' => $customer->id,
            'name' => 'Expired device',
            'token' => hash('sha256', $plainToken),
            'expires_at' => now()->subMinute(),
        ]);

        $this->withToken($plainToken)->getJson('/api/v1/me')->assertUnauthorized();
    }

    public function test_mobile_tokens_expire_thirty_days_after_issue(): void
    {
        $customer = User::factory()->create([
            'email' => 'mobile-expiry@example.com',
            'password' => Hash::make('Password123!'),
            'role' => 'customer',
            'is_active' => true,
        ]);
        $issuedAt = now()->startOfSecond();
        $this->travelTo($issuedAt);

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => $customer->email,
            'password' => 'Password123!',
        ])->assertCreated();
        $expiresAt = $issuedAt->copy()->addDays(30);
        $token = MobileAccessToken::query()->where('user_id', $customer->id)->firstOrFail();

        $this->assertTrue($token->expires_at->equalTo($expiresAt));
        $this->travelTo($expiresAt);
        $this->withToken($login->json('token'))->getJson('/api/v1/me')->assertUnauthorized();
    }

    public function test_suspended_tenant_staff_cannot_obtain_mobile_tokens(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Suspended mobile tenant',
            'slug' => 'suspended-mobile-login',
            'status' => 'suspended',
        ]);
        $staff = User::factory()->create([
            'email' => 'suspended-staff@example.com',
            'password' => Hash::make('Password123!'),
            'role' => 'concierge',
            'tenant_id' => $tenant->id,
            'is_active' => true,
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => $staff->email,
            'password' => 'Password123!',
        ])->assertForbidden()
            ->assertJsonPath('message', 'This workspace has been suspended.');

        $this->assertDatabaseMissing('mobile_access_tokens', ['user_id' => $staff->id]);
    }

    public function test_existing_staff_token_is_denied_during_suspension_and_works_after_reactivation(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Reactivatable mobile tenant',
            'slug' => 'reactivatable-mobile',
            'status' => 'active',
        ]);
        $staff = User::factory()->create([
            'email' => 'reactivatable-staff@example.com',
            'password' => Hash::make('Password123!'),
            'role' => 'concierge',
            'tenant_id' => $tenant->id,
            'is_active' => true,
        ]);
        $token = $this->tokenFor($staff);

        $this->withToken($token)->getJson('/api/v1/me')->assertOk();

        $tenant->update(['status' => 'suspended']);
        $this->withToken($token)->getJson('/api/v1/me')
            ->assertForbidden()
            ->assertJsonPath('message', 'This workspace has been suspended.');

        $tenant->update(['status' => 'active']);
        $this->withToken($token)->getJson('/api/v1/me')->assertOk();
    }

    public function test_platform_admin_can_still_authenticate_while_associated_tenant_is_suspended(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Platform admin suspended tenant',
            'slug' => 'platform-admin-suspended',
            'status' => 'suspended',
        ]);
        $platformAdmin = User::factory()->create([
            'email' => 'platform-admin-suspended@example.com',
            'password' => Hash::make('Password123!'),
            'role' => 'admin',
            'is_admin' => true,
            'is_platform_admin' => true,
            'tenant_id' => $tenant->id,
            'is_active' => true,
        ]);

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => $platformAdmin->email,
            'password' => 'Password123!',
        ])->assertCreated();

        $this->withToken($login->json('token'))->getJson('/api/v1/me')->assertOk();
    }

    public function test_staff_api_rejects_reservations_from_another_tenant(): void
    {
        $this->seed(DatabaseSeeder::class);
        $tenantAdmin = User::where('email', 'admin@afrikappart.test')->firstOrFail();
        $foreignTenant = Tenant::query()->create(['name' => 'Mobile foreign tenant', 'slug' => 'mobile-foreign', 'status' => 'active']);
        $foreignEstablishment = Establishment::query()->create([
            'tenant_id' => $foreignTenant->id,
            'name' => 'Mobile foreign establishment',
            'slug' => 'mobile-foreign-establishment',
            'currency' => 'XOF',
        ]);
        $property = $foreignEstablishment->properties()->create([
            'name' => 'Mobile foreign property',
            'slug' => 'mobile-foreign-property',
            'status' => 'published',
            'is_active' => true,
        ]);
        $guest = ReservationGuest::query()->create(['full_name' => 'Foreign guest', 'email' => 'mobile-foreign@example.com']);
        $reservation = Reservation::query()->create([
            'property_id' => $property->id,
            'guest_id' => $guest->id,
            'reservation_ref' => 'AFK-MOBILE-FOREIGN',
            'status' => 'pending',
            'check_in' => now()->addDays(12)->toDateString(),
            'check_out' => now()->addDays(14)->toDateString(),
            'currency' => 'XOF',
            'email' => $guest->email,
            'total_amount' => 50000,
        ]);
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_admin' => true,
            'tenant_id' => $tenantAdmin->tenant_id,
            'is_active' => true,
        ]);
        $token = $this->tokenFor($admin);

        $this->withToken($token)->getJson('/api/v1/staff/reservations')
            ->assertOk()
            ->assertJsonMissing(['id' => $reservation->id]);
        $this->withToken($token)->getJson('/api/v1/staff/reservations/' . $reservation->id)->assertNotFound();
        $this->withToken($token)->patchJson('/api/v1/staff/reservations/' . $reservation->id . '/status', ['status' => 'confirmed'])->assertNotFound();
        $this->withToken($token)->postJson('/api/v1/staff/reservations/' . $reservation->id . '/payment-link')->assertNotFound();

        $this->assertSame('pending', $reservation->fresh()->status);
    }

    private function tokenFor(User $user): string
    {
        $response = $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'Password123!']);

        if (! $response->isSuccessful()) {
            $user->forceFill(['password' => Hash::make('Password123!')])->save();
            $response = $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'Password123!']);
        }

        return $response->json('token');
    }
}