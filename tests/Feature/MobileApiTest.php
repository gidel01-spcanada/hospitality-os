<?php

namespace Tests\Feature;

use App\Models\Property;
use App\Models\Reservation;
use App\Models\ReservationGuest;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MobileApiTest extends TestCase
{
    use RefreshDatabase;

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

        $this->getJson('/api/v1/properties')->assertOk()->assertJsonPath('data.0.id', $property->id);
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