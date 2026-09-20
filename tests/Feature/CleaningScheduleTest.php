<?php

namespace Tests\Feature;

use App\Models\CleaningScheduleShare;
use App\Models\CleaningVisit;
use App\Models\Establishment;
use App\Models\Property;
use App\Models\Reservation;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CleaningScheduleTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_generates_one_cleaning_visit_per_eligible_departure_without_duplicates(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::query()->where('email', 'admin@afrikappart.test')->firstOrFail();
        $property = Property::query()->firstOrFail();
        $secondProperty = Property::query()->whereKeyNot($property->id)->firstOrFail();
        $eligible = Reservation::query()->create([
            'property_id' => $property->id, 'reservation_ref' => 'CLEAN-GENERATE-1',
            'status' => 'confirmed', 'check_in' => '2034-05-04', 'check_out' => '2034-05-08',
            'adults' => 2, 'currency' => 'XOF', 'email' => 'generate-cleaning@example.com', 'total_amount' => 100,
        ]);
        $secondEligible = Reservation::query()->create([
            'property_id' => $secondProperty->id, 'reservation_ref' => 'CLEAN-GENERATE-2',
            'status' => 'checked_in', 'check_in' => '2034-05-03', 'check_out' => '2034-05-08',
            'adults' => 1, 'currency' => 'XOF', 'email' => 'generate-cleaning-2@example.com', 'total_amount' => 100,
        ]);
        Reservation::query()->create([
            'property_id' => $property->id, 'reservation_ref' => 'CLEAN-PENDING-1',
            'status' => 'pending', 'check_in' => '2034-05-05', 'check_out' => '2034-05-09',
            'adults' => 1, 'currency' => 'XOF', 'email' => 'pending-cleaning@example.com', 'total_amount' => 100,
        ]);
        $payload = [
            'mode' => 'month', 'date' => '2034-05-01', 'assignee_name' => 'Équipe A',
            'scheduled_time' => '11:30', 'duration_minutes' => 90, 'instructions' => 'Préparer le linge.',
        ];

        $this->actingAs($admin)->post(route('admin.cleaning.generate'), $payload)
            ->assertRedirect()->assertSessionHas('success', '2 visites créées depuis les départs.');
        $this->assertDatabaseHas('cleaning_visits', [
            'reservation_id' => $eligible->id, 'assignee_name' => 'Équipe A',
            'scheduled_at' => '2034-05-08 11:30:00', 'duration_minutes' => 90,
        ]);
        $this->assertDatabaseHas('cleaning_visits', [
            'reservation_id' => $secondEligible->id, 'assignee_name' => 'Équipe A',
            'scheduled_at' => '2034-05-08 13:00:00', 'duration_minutes' => 90,
        ]);
        $this->assertDatabaseCount('cleaning_visits', 2);

        $this->actingAs($admin)->post(route('admin.cleaning.generate'), $payload)
            ->assertRedirect()->assertSessionHas('success', 'Aucun nouveau départ à planifier.');
        $this->assertDatabaseCount('cleaning_visits', 2);
    }

    public function test_manual_visit_rejects_an_overlapping_assignment(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::query()->where('email', 'admin@afrikappart.test')->firstOrFail();
        $properties = Property::query()->limit(2)->get();
        CleaningVisit::query()->create([
            'property_id' => $properties[0]->id, 'created_by' => $admin->id,
            'assignee_name' => 'Awa Ménage', 'scheduled_at' => '2034-06-10 10:00:00',
            'duration_minutes' => 120, 'status' => 'scheduled',
        ]);

        $this->actingAs($admin)->post(route('admin.cleaning.store'), [
            'property_id' => $properties[1]->id, 'assignee_name' => 'awa ménage',
            'scheduled_at' => '2034-06-10 11:00:00', 'duration_minutes' => 60,
            'status' => 'scheduled',
        ])->assertSessionHasErrors(['scheduled_at']);
        $this->assertDatabaseCount('cleaning_visits', 1);

        $this->actingAs($admin)->post(route('admin.cleaning.store'), [
            'property_id' => $properties[1]->id, 'assignee_name' => 'Awa Ménage',
            'scheduled_at' => '2034-06-10 12:00:00', 'duration_minutes' => 60,
            'status' => 'scheduled',
        ])->assertRedirect();
        $this->assertDatabaseCount('cleaning_visits', 2);
    }

    public function test_admin_manages_and_shares_a_privacy_safe_cleaning_schedule(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::query()->where('email', 'admin@afrikappart.test')->firstOrFail();
        $property = Property::query()->firstOrFail();
        $reservation = Reservation::query()->create([
            'property_id' => $property->id,
            'reservation_ref' => 'CLEAN-PRIVATE-GUEST',
            'status' => 'confirmed',
            'check_in' => '2033-03-01',
            'check_out' => '2033-03-05',
            'adults' => 2,
            'currency' => 'XOF',
            'email' => 'private-guest@example.com',
            'total_amount' => 100000,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.cleaning.store'), [
                'property_id' => $property->id,
                'reservation_id' => $reservation->id,
                'assignee_name' => 'Awa Ménage',
                'scheduled_at' => '2033-03-05 11:00:00',
                'duration_minutes' => 90,
                'status' => 'scheduled',
                'instructions' => 'Changer le linge et vérifier la cuisine.',
                'mode' => 'week',
                'date' => '2033-03-05',
            ])->assertRedirect();

        $visit = CleaningVisit::query()->firstOrFail();
        $this->actingAs($admin)
            ->get(route('admin.cleaning.index', ['mode' => 'month', 'date' => '2033-03-01']))
            ->assertOk()->assertSee('Awa Ménage')->assertSee($property->name);

        $this->actingAs($admin)
            ->put(route('admin.cleaning.update', $visit), [
                'property_id' => $property->id,
                'reservation_id' => $reservation->id,
                'assignee_name' => 'Awa Ménage',
                'scheduled_at' => '2033-03-05 12:00:00',
                'duration_minutes' => 120,
                'status' => 'completed',
                'instructions' => 'Visite terminée.',
            ])->assertRedirect();
        $this->assertDatabaseHas('cleaning_visits', ['id' => $visit->id, 'status' => 'completed', 'duration_minutes' => 120]);

        $this->actingAs($admin)
            ->post(route('admin.cleaning.share'), [
                'mode' => 'month', 'date' => '2033-03-01',
                'expires_in_days' => 7, 'assignee' => 'Awa Ménage',
            ])
            ->assertRedirect()->assertSessionHas('cleaning_share_url');
        $share = CleaningScheduleShare::query()->firstOrFail();
        $this->assertEquals(7, now()->startOfDay()->diffInDays($share->expires_at->startOfDay()));
        $this->assertSame('Awa Ménage', $share->assignee_name);
        $otherAssigneeVisit = CleaningVisit::query()->create([
            'property_id' => $property->id, 'assignee_name' => 'Fatou Ménage',
            'scheduled_at' => '2033-03-06 14:00:00', 'duration_minutes' => 60, 'status' => 'scheduled',
        ]);

        $this->get(route('cleaning.public', $share->token))
            ->assertOk()
            ->assertSee('Awa Ménage')
            ->assertDontSee('Fatou Ménage')
            ->assertSee('Visite terminée.')
            ->assertDontSee('private-guest@example.com')
            ->assertDontSee('CLEAN-PRIVATE-GUEST');
        $visit->update(['status' => 'scheduled']);
        $this->post(route('cleaning.public.status', [$share->token, $visit]), ['status' => 'in_progress'])
            ->assertRedirect(route('cleaning.public', $share->token));
        $visit->refresh();
        $this->assertSame('in_progress', $visit->status);
        $this->assertNotNull($visit->started_at);
        $startedAt = $visit->started_at->format('Y-m-d H:i:s');
        $this->post(route('cleaning.public.status', [$share->token, $visit]), ['status' => 'completed'])
            ->assertRedirect(route('cleaning.public', $share->token));
        $visit->refresh();
        $this->assertSame('completed', $visit->status);
        $this->assertSame($startedAt, $visit->started_at->format('Y-m-d H:i:s'));
        $this->assertNotNull($visit->completed_at);
        $this->post(route('cleaning.public.status', [$share->token, $visit]), ['status' => 'cancelled'])
            ->assertSessionHasErrors(['status']);
        $this->assertSame('completed', $visit->fresh()->status);
        $outsideProperty = Property::query()->whereKeyNot($property->id)->firstOrFail();
        $outsideVisit = CleaningVisit::query()->create([
            'property_id' => $outsideProperty->id, 'assignee_name' => 'Autre équipe',
            'scheduled_at' => '2033-03-06 10:00:00', 'duration_minutes' => 60, 'status' => 'scheduled',
        ]);
        $share->update(['property_ids' => [$property->id]]);
        $this->post(route('cleaning.public.status', [$share->token, $outsideVisit]), ['status' => 'completed'])
            ->assertNotFound();
        $this->assertSame('scheduled', $outsideVisit->fresh()->status);
        $this->post(route('cleaning.public.status', [$share->token, $otherAssigneeVisit]), ['status' => 'completed'])
            ->assertNotFound();
        $this->assertSame('scheduled', $otherAssigneeVisit->fresh()->status);
        $this->get(route('cleaning.public.pdf', $share->token))
            ->assertOk()->assertHeader('content-type', 'application/pdf');

        $share->update(['expires_at' => now()->subMinute()]);
        $this->get(route('cleaning.public', $share->token))->assertNotFound();
        $this->post(route('cleaning.public.status', [$share->token, $visit]), ['status' => 'in_progress'])->assertNotFound();
        $share->update(['expires_at' => now()->addDay()]);

        $this->actingAs($admin)->delete(route('admin.cleaning.shares.revoke', $share))->assertRedirect();
        $this->get(route('cleaning.public', $share->token))->assertNotFound();

        $this->actingAs($admin)->delete(route('admin.cleaning.destroy', $visit))->assertRedirect();
        $this->assertDatabaseMissing('cleaning_visits', ['id' => $visit->id]);
    }

    public function test_host_is_scoped_and_concierge_can_view_and_plan(): void
    {
        $this->seed(DatabaseSeeder::class);
        $property = Property::query()->firstOrFail();
        $tenantId = $property->establishment->tenant_id;
        $otherEstablishment = Establishment::query()->create([
            'tenant_id' => $tenantId, 'name' => 'Autre résidence', 'slug' => 'autre-residence',
            'country_code' => 'BJ', 'currency' => 'XOF',
        ]);
        $otherProperty = Property::query()->create([
            'establishment_id' => $otherEstablishment->id, 'name' => 'Logement caché',
            'slug' => 'logement-cache-menage', 'property_type' => 'apartment', 'status' => 'published',
            'is_published' => true, 'is_active' => true, 'currency' => 'XOF', 'nightly_rate_xof' => 10000,
            'minimum_stay' => 1, 'max_guests' => 2, 'bedrooms' => 1, 'bathrooms' => 1, 'beds' => 1,
        ]);
        $host = User::factory()->create(['tenant_id' => $tenantId, 'role' => 'host', 'is_admin' => false]);
        $host->establishments()->sync([$property->establishment_id]);
        $concierge = User::factory()->create(['tenant_id' => $tenantId, 'role' => 'concierge', 'is_admin' => false]);

        $this->actingAs($host)->get(route('admin.cleaning.index'))
            ->assertOk()->assertSee($property->name)->assertDontSee($otherProperty->name);
        $this->actingAs($host)->post(route('admin.cleaning.store'), $this->visitPayload($otherProperty))->assertForbidden();
        $this->actingAs($host)->post(route('admin.cleaning.generate'), [
            'mode' => 'month', 'date' => '2033-04-01', 'property' => $otherProperty->id,
            'assignee_name' => 'Équipe ménage', 'scheduled_time' => '11:00', 'duration_minutes' => 120,
        ])->assertForbidden();
        $this->actingAs($host)->post(route('admin.cleaning.share'), [
            'mode' => 'month', 'date' => '2033-04-01', 'expires_in_days' => 30, 'assignee' => '',
        ])->assertRedirect();
        $hostShare = CleaningScheduleShare::query()->where('created_by', $host->id)->firstOrFail();
        $expectedPropertyIds = Property::query()->where('establishment_id', $property->establishment_id)->pluck('id')->all();
        $this->assertEqualsCanonicalizing($expectedPropertyIds, $hostShare->property_ids);
        $this->assertNotContains($otherProperty->id, $hostShare->property_ids);

        $this->actingAs($concierge)->get(route('admin.cleaning.index'))->assertOk()->assertSee($otherProperty->name);
        $this->actingAs($concierge)->post(route('admin.cleaning.store'), $this->visitPayload($property))->assertRedirect();

        $customer = User::factory()->create(['tenant_id' => null, 'role' => 'customer', 'is_admin' => false]);
        $this->actingAs($customer)->get(route('admin.cleaning.index'))->assertForbidden();
    }

    public function test_admin_schedule_marks_past_scheduled_visits_as_overdue(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::query()->where('email', 'admin@afrikappart.test')->firstOrFail();
        $property = Property::query()->firstOrFail();
        CleaningVisit::query()->create([
            'property_id' => $property->id, 'assignee_name' => 'Équipe retard',
            'scheduled_at' => now()->subHours(2), 'duration_minutes' => 60, 'status' => 'scheduled',
        ]);

        $this->actingAs($admin)->get(route('admin.cleaning.index', ['date' => now()->toDateString()]))
            ->assertOk()->assertSee(__('messages.cleaning.overdue'));
    }

    private function visitPayload(Property $property): array
    {
        return [
            'property_id' => $property->id,
            'assignee_name' => 'Équipe ménage',
            'scheduled_at' => '2033-04-10 10:00:00',
            'duration_minutes' => 120,
            'status' => 'scheduled',
        ];
    }
}
