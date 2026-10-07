<?php

namespace Tests\Feature;

use App\Models\Establishment;
use App\Models\MessageThread;
use App\Models\Property;
use App\Models\Reservation;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MessagingTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_and_staff_share_one_establishment_thread(): void
    {
        $this->seed(DatabaseSeeder::class);

        $customer = User::factory()->create([
            'role' => 'customer',
            'locale' => 'fr',
        ]);
        $staff = User::query()->whereIn('role', ['admin', 'concierge'])->firstOrFail();
        $establishment = Establishment::query()->firstOrFail();
        $property = Property::query()->where('establishment_id', $establishment->id)->firstOrFail();
        $reservation = Reservation::query()->create([
            'user_id' => $customer->id,
            'property_id' => $property->id,
            'reservation_ref' => 'MESSAGE-RESERVATION-1',
            'status' => 'confirmed',
            'check_in' => '2027-02-01',
            'check_out' => '2027-02-03',
            'adults' => 1,
            'currency' => 'XOF',
            'email' => $customer->email,
            'total_amount' => 50000,
        ]);
        $newerReservation = Reservation::query()->create([
            'user_id' => $customer->id,
            'property_id' => $property->id,
            'reservation_ref' => 'MESSAGE-RESERVATION-2',
            'status' => 'confirmed',
            'check_in' => '2027-03-01',
            'check_out' => '2027-03-03',
            'adults' => 1,
            'currency' => 'XOF',
            'email' => $customer->email,
            'total_amount' => 50000,
        ]);

        $this->actingAs($customer)
            ->post(route('messages.store'), [
                'establishment_id' => $establishment->id,
                'reservation_id' => $reservation->id,
                'body' => 'Bonjour, pouvez-vous m’aider ?',
            ])
            ->assertRedirect(route('messages.show', MessageThread::query()->where('customer_id', $customer->id)->where('establishment_id', $establishment->id)->firstOrFail()));

        $thread = MessageThread::query()->where('customer_id', $customer->id)->where('establishment_id', $establishment->id)->firstOrFail();

        $this->actingAs($customer)
            ->post(route('messages.store'), [
                'establishment_id' => $establishment->id,
                'body' => 'J’ai une question supplémentaire.',
            ])
            ->assertRedirect(route('messages.index'));

        $this->assertDatabaseCount('message_threads', 1);
        $this->assertDatabaseCount('messages', 2);

        $this->actingAs($staff)
            ->get(route('admin.messages.show', $thread))
            ->assertOk()
            ->assertSee(route('admin.reservations.show', $reservation), false)
            ->assertDontSee(route('admin.reservations.show', $newerReservation), false)
            ->assertSee($reservation->reservation_ref)
            ->assertSee('Bonjour, pouvez-vous m’aider ?', false);

        $this->actingAs($customer)
            ->get(route('messages.show', $thread))
            ->assertOk()
            ->assertSee('class="portal-conversation-list"', false)
            ->assertSee('aria-current="page"', false)
            ->assertSee(route('dashboard.reservations.show', $reservation), false)
            ->assertDontSee(route('dashboard.reservations.show', $newerReservation), false)
            ->assertSee(__('messages.admin.customer'))
            ->assertSee(__('messages.messages.return_to_reservation', ['reference' => $reservation->reservation_ref]));

        $this->actingAs($staff)
            ->post(route('admin.messages.reply', $thread), ['body' => 'Bonjour, je suis à votre disposition.'])
            ->assertRedirect(route('admin.messages.index'));

        $this->assertDatabaseHas('messages', [
            'message_thread_id' => $thread->id,
            'sender_id' => $staff->id,
            'body' => 'Bonjour, je suis à votre disposition.',
        ]);
        $payload = \App\Models\EmailOutbox::where('template', 'message_received')->where('recipient_email', $customer->email)->latest('id')->firstOrFail()->payload;
        $this->assertTrue($payload['from_establishment']);
        $this->assertSame($reservation->reservation_ref, $payload['reservation_context']['reference']);
        $this->assertSame(route('dashboard.reservations.show', $reservation), $payload['reservation_url']);
        $html = view('emails.message-received', $payload)->render();
        $this->assertStringContainsString($reservation->reservation_ref, $html);
        $this->assertStringNotContainsString($newerReservation->reservation_ref, $html);
        $this->assertStringContainsString(__('messages.transactional.message_context', ['sender' => $staff->name, 'establishment' => $payload['establishment_name'], 'brand' => \App\Support\PlatformBrand::name()]), html_entity_decode($html));
        $this->assertStringContainsString(__('messages.transactional.greeting', ['name' => \Illuminate\Support\Str::before($customer->name, ' ')]), $html);
        $this->assertStringContainsString(__('messages.transactional.message_forwarded_notice', ['brand' => \App\Support\PlatformBrand::name()]), html_entity_decode($html));
        $this->artisan('messages:send-email-notifications')->assertExitCode(0);
        $this->assertSame('sent', \App\Models\EmailOutbox::where('template', 'message_received')->where('recipient_email', $customer->email)->latest('id')->firstOrFail()->status);
    }

    public function test_message_notifications_respect_host_scope_preferences_and_reservation_ownership(): void
    {
        $tenant = \App\Models\Tenant::firstOrCreate(['slug' => 'default'], ['name' => 'Default', 'status' => 'active']);
        $establishment = Establishment::create(['tenant_id' => $tenant->id, 'name' => 'Message residence', 'slug' => 'message-residence']);
        $property = $establishment->properties()->create(['name' => 'Message suite', 'slug' => 'message-suite']);
        $customer = User::factory()->create(['role' => 'customer', 'email_message_updates' => true]);
        $assignedHost = User::factory()->create(['role' => 'host', 'tenant_id' => $tenant->id, 'email_message_updates' => true]);
        $unassignedHost = User::factory()->create(['role' => 'host', 'tenant_id' => $tenant->id, 'email_message_updates' => true]);
        $mutedHost = User::factory()->create(['role' => 'host', 'tenant_id' => $tenant->id, 'email_message_updates' => false]);
        $assignedHost->establishments()->attach($establishment);
        $mutedHost->establishments()->attach($establishment);
        $thread = MessageThread::create(['customer_id' => $customer->id, 'establishment_id' => $establishment->id]);
        $service = app(\App\Services\MessageEmailService::class);
        $body = '<script>alert("private")</script>';
        $service->queueForMessage($thread, $customer, $body);
        $this->assertDatabaseMissing('email_outbox', ['recipient_email' => $unassignedHost->email]);
        $this->assertDatabaseMissing('email_outbox', ['recipient_email' => $mutedHost->email]);
        $notification = \App\Models\EmailOutbox::where('recipient_email', $assignedHost->email)->firstOrFail();
        $this->assertNull($notification->payload['reservation_context']);
        $html = view('emails.message-received', $notification->payload)->render();
        $this->assertStringNotContainsString($body, $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $otherCustomer = User::factory()->create();
        $foreignReservation = Reservation::create([
            'user_id' => $otherCustomer->id, 'property_id' => $property->id, 'reservation_ref' => 'PRIVATE-OTHER-BOOKING',
            'email' => $otherCustomer->email, 'check_in' => now()->addDays(3), 'check_out' => now()->addDays(5),
            'adults' => 1, 'currency' => 'EUR', 'total_amount' => 250, 'status' => 'confirmed',
        ]);
        $thread->messages()->create(['sender_id' => $customer->id, 'reservation_id' => $foreignReservation->id, 'body' => $body]);
        $service->queueForMessage($thread, $assignedHost, 'Reply to the customer');
        $payload = \App\Models\EmailOutbox::where('recipient_email', $customer->email)->latest('id')->firstOrFail()->payload;
        $this->assertNull($payload['reservation_context']);
        $this->assertNull($payload['reservation_url']);
        $this->assertStringNotContainsString('PRIVATE-OTHER-BOOKING', view('emails.message-received', $payload)->render());
        $customer->update(['email_message_updates' => false]);
        $service->queueForMessage($thread, $assignedHost, 'Muted notification');
        $this->assertSame(1, \App\Models\EmailOutbox::where('recipient_email', $customer->email)->count());
    }

    public function test_customer_cannot_read_another_customers_thread(): void
    {
        $this->seed(DatabaseSeeder::class);

        $customer = User::factory()->create(['role' => 'customer']);
        $otherCustomer = User::factory()->create(['role' => 'customer']);
        $establishment = Establishment::query()->firstOrFail();
        $thread = MessageThread::query()->create([
            'customer_id' => $otherCustomer->id,
            'establishment_id' => $establishment->id,
        ]);

        $this->actingAs($customer)
            ->get(route('messages.show', $thread))
            ->assertForbidden();
    }
}