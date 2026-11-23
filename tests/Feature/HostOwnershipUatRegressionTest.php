<?php

namespace Tests\Feature;

use App\Models\Establishment;
use App\Models\Property;
use App\Models\Reservation;
use App\Models\ReservationGuest;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class HostOwnershipUatRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_host_cannot_confirm_payment_for_unassigned_or_foreign_establishments(): void
    {
        Storage::fake('local');
        $this->seed(DatabaseSeeder::class);
        $owned = Property::query()->where('slug', 'appartement-401')->firstOrFail();
        $unassigned = $this->establishment($owned->establishment->tenant_id, 'unassigned');
        $foreignTenant = Tenant::query()->create(['name' => 'Foreign', 'slug' => 'foreign-uat', 'status' => 'active']);
        $foreign = $this->establishment($foreignTenant->id, 'foreign');
        $host = User::factory()->create(['role' => 'host', 'is_admin' => false, 'tenant_id' => $owned->establishment->tenant_id]);
        $host->establishments()->attach([$owned->establishment_id, $foreign->id]);

        foreach ([$unassigned, $foreign] as $establishment) {
            $reservation = $this->reservation($this->property($establishment));
            $path = 'payment-proofs/' . $reservation->id . '/existing.pdf';
            Storage::disk('local')->put($path, 'existing proof');
            $attempt = $reservation->paymentAttempts()->create([
                'provider' => 'wise',
                'provider_reference' => 'original-reference',
                'currency' => 'XOF',
                'amount' => 10000,
                'status' => 'awaiting_validation',
                'idempotency_key' => 'uat-' . $reservation->id,
                'payload' => ['payment_proof' => ['path' => $path]],
            ]);

            $response = $this->actingAs($host)->post(route('admin.reservations.confirm-offline-payment', $reservation), [
                'provider_reference' => 'unauthorized',
                'payment_proof' => UploadedFile::fake()->create('new.pdf', 10, 'application/pdf'),
            ]);
            $establishment->is($foreign) ? $response->assertNotFound() : $response->assertForbidden();

            $this->assertSame('pending_validation', $reservation->fresh()->status);
            $this->assertSame('awaiting_validation', $attempt->fresh()->status);
            $this->assertSame('original-reference', $attempt->fresh()->provider_reference);
            $this->assertSame($path, data_get($attempt->fresh()->payload, 'payment_proof.path'));
            Storage::disk('local')->assertExists($path);
            $this->assertSame(1, $reservation->paymentAttempts()->count());
            $this->assertSame(0, $reservation->receipts()->count());
            $this->assertDatabaseMissing('email_outbox', ['recipient_email' => $reservation->email]);
        }
    }

    public function test_host_can_confirm_payment_for_an_assigned_establishment(): void
    {
        Storage::fake('local');
        $this->seed(DatabaseSeeder::class);
        $property = Property::query()->where('slug', 'appartement-401')->firstOrFail();
        $host = User::factory()->create(['role' => 'host', 'is_admin' => false, 'tenant_id' => $property->establishment->tenant_id]);
        $host->establishments()->attach($property->establishment_id);
        $reservation = $this->reservation($property);

        $this->actingAs($host)->post(route('admin.reservations.confirm-offline-payment', $reservation), [
            'provider_reference' => 'host-confirmed',
            'payment_proof' => UploadedFile::fake()->create('proof.pdf', 10, 'application/pdf'),
        ])->assertRedirect();

        $this->assertSame('confirmed', $reservation->fresh()->status);
        $attempt = $reservation->paymentAttempts()->firstOrFail();
        $this->assertSame('paid', $attempt->status);
        $this->assertSame('host-confirmed', $attempt->provider_reference);
        Storage::disk('local')->assertExists(data_get($attempt->payload, 'payment_proof.path'));
        $this->assertSame(1, $reservation->receipts()->count());
    }

    public function test_host_can_only_move_an_owned_property_to_an_assigned_establishment_in_their_tenant(): void
    {
        $this->seed(DatabaseSeeder::class);
        $property = Property::query()->where('slug', 'appartement-401')->firstOrFail();
        $owned = $property->establishment;
        $assigned = $this->establishment($owned->tenant_id, 'assigned');
        $unassigned = $this->establishment($owned->tenant_id, 'unassigned');
        $foreignTenant = Tenant::query()->create(['name' => 'Foreign', 'slug' => 'foreign-uat', 'status' => 'active']);
        $foreign = $this->establishment($foreignTenant->id, 'foreign');
        $unownedProperty = $this->property($unassigned);
        $host = User::factory()->create(['role' => 'host', 'is_admin' => false, 'tenant_id' => $owned->tenant_id]);
        $host->establishments()->attach([$owned->id, $assigned->id, $foreign->id]);

        $this->actingAs($host)->put(route('admin.properties.update', $property), $this->propertyInput($property, $unassigned))
            ->assertForbidden();
        $this->assertSame($owned->id, $property->fresh()->establishment_id);
        $this->actingAs($host)->put(route('admin.properties.update', $property), $this->propertyInput($property, $foreign))
            ->assertSessionHasErrors('establishment_id');
        $this->assertSame($owned->id, $property->fresh()->establishment_id);
        $this->actingAs($host)->put(route('admin.properties.update', $unownedProperty), $this->propertyInput($unownedProperty, $assigned))
            ->assertForbidden();
        $this->assertSame($unassigned->id, $unownedProperty->fresh()->establishment_id);

        $this->actingAs($host)->put(route('admin.properties.update', $property), $this->propertyInput($property, $assigned))
            ->assertSessionHasNoErrors()
            ->assertRedirect();
        $this->assertSame($assigned->id, $property->fresh()->establishment_id);
    }

    public function test_concierge_can_confirm_payment_within_their_tenant(): void
    {
        Storage::fake('local');
        $this->seed(DatabaseSeeder::class);
        $property = Property::query()->where('slug', 'appartement-401')->firstOrFail();
        $concierge = User::factory()->create(['role' => 'concierge', 'is_admin' => false, 'tenant_id' => $property->establishment->tenant_id]);
        $reservation = $this->reservation($property);
        $foreignTenant = Tenant::query()->create(['name' => 'Foreign', 'slug' => 'foreign-uat', 'status' => 'active']);
        $foreignReservation = $this->reservation($this->property($this->establishment($foreignTenant->id, 'foreign')));

        $this->actingAs($concierge)->post(route('admin.reservations.confirm-offline-payment', $foreignReservation), [
            'payment_proof' => UploadedFile::fake()->create('proof.pdf', 10, 'application/pdf'),
        ])->assertNotFound();
        $this->assertSame('pending_validation', $foreignReservation->fresh()->status);
        $this->assertSame(0, $foreignReservation->paymentAttempts()->count());

        $this->actingAs($concierge)->post(route('admin.reservations.confirm-offline-payment', $reservation), [
            'payment_proof' => UploadedFile::fake()->create('proof.pdf', 10, 'application/pdf'),
        ])->assertRedirect();
        $this->assertSame('confirmed', $reservation->fresh()->status);
        $this->assertSame('paid', $reservation->paymentAttempts()->firstOrFail()->status);
    }

    public function test_staff_without_a_tenant_cannot_confirm_payment_but_platform_admin_can(): void
    {
        Storage::fake('local');
        $this->seed(DatabaseSeeder::class);
        $property = Property::query()->where('slug', 'appartement-401')->firstOrFail();
        $reservation = $this->reservation($property);
        $concierge = User::factory()->create(['role' => 'concierge', 'is_admin' => false, 'tenant_id' => null]);
        $platformAdmin = User::factory()->create([
            'role' => 'admin', 'is_admin' => true, 'is_platform_admin' => true, 'tenant_id' => null,
        ]);

        $this->actingAs($concierge)->post(route('admin.reservations.confirm-offline-payment', $reservation), [
            'payment_proof' => UploadedFile::fake()->create('proof.pdf', 10, 'application/pdf'),
        ])->assertForbidden();
        $this->assertSame('pending_validation', $reservation->fresh()->status);
        $this->assertSame(0, $reservation->paymentAttempts()->count());

        $this->actingAs($platformAdmin)->post(route('admin.reservations.confirm-offline-payment', $reservation), [
            'payment_proof' => UploadedFile::fake()->create('proof.pdf', 10, 'application/pdf'),
        ])->assertRedirect();
        $this->assertSame('confirmed', $reservation->fresh()->status);
    }

    private function establishment(int $tenantId, string $name): Establishment
    {
        return Establishment::query()->create([
            'tenant_id' => $tenantId,
            'name' => $name,
            'slug' => 'uat-' . $name,
            'currency' => 'XOF',
        ]);
    }

    private function property(Establishment $establishment): Property
    {
        return $establishment->properties()->create([
            'name' => 'UAT property ' . $establishment->id,
            'slug' => 'uat-property-' . $establishment->id,
            'status' => 'published',
            'is_active' => true,
        ]);
    }

    private function reservation(Property $property): Reservation
    {
        $guest = ReservationGuest::query()->create([
            'full_name' => 'UAT guest',
            'email' => 'uat-' . $property->id . '@example.com',
        ]);

        return Reservation::query()->create([
            'property_id' => $property->id,
            'guest_id' => $guest->id,
            'reservation_ref' => 'UAT-' . $property->id,
            'status' => 'pending_validation',
            'check_in' => now()->addDays(8)->toDateString(),
            'check_out' => now()->addDays(10)->toDateString(),
            'currency' => 'XOF',
            'email' => $guest->email,
            'total_amount' => 10000,
        ]);
    }

    private function propertyInput(Property $property, Establishment $destination): array
    {
        return [
            'name' => $property->name,
            'slug' => $property->slug,
            'nightly_rate_xof' => $property->nightly_rate_xof ?? 1000,
            'minimum_stay' => $property->minimum_stay ?? 1,
            'max_guests' => $property->max_guests ?? 2,
            'status' => $property->status,
            'establishment_id' => $destination->id,
        ];
    }
}
