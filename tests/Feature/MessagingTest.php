<?php

namespace Tests\Feature;

use App\Models\Establishment;
use App\Models\MessageThread;
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

        $this->actingAs($customer)
            ->post(route('messages.store'), [
                'establishment_id' => $establishment->id,
                'body' => 'Bonjour, pouvez-vous m’aider ?',
            ])
            ->assertRedirect();

        $thread = MessageThread::query()->where('customer_id', $customer->id)->where('establishment_id', $establishment->id)->firstOrFail();

        $this->actingAs($customer)
            ->post(route('messages.store'), [
                'establishment_id' => $establishment->id,
                'body' => 'J’ai une question supplémentaire.',
            ])
            ->assertRedirect();

        $this->assertDatabaseCount('message_threads', 1);
        $this->assertDatabaseCount('messages', 2);

        $this->actingAs($staff)
            ->get(route('admin.messages.show', $thread))
            ->assertOk()
            ->assertSee('Bonjour, pouvez-vous m’aider ?', false);

        $this->actingAs($staff)
            ->post(route('admin.messages.reply', $thread), ['body' => 'Bonjour, je suis à votre disposition.'])
            ->assertRedirect();

        $this->assertDatabaseHas('messages', [
            'message_thread_id' => $thread->id,
            'sender_id' => $staff->id,
            'body' => 'Bonjour, je suis à votre disposition.',
        ]);
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