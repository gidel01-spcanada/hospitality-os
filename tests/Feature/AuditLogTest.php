<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_object_changes_are_recorded_and_audit_page_is_admin_only(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_admin' => true, 'is_active' => true]);
        $record = User::factory()->create(['name' => 'Before change']);

        $this->actingAs($admin);
        $record->update(['name' => 'After change']);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'updated',
            'model_type' => User::class,
            'model_id' => $record->id,
            'user_id' => $admin->id,
        ]);
        $details = json_decode(AuditLog::query()->where('model_id', $record->id)->where('action', 'updated')->latest()->first()->details, true);
        $this->assertSame('Before change', $details['old']['name']);

        $updatedLog = AuditLog::query()->where('model_id', $record->id)->where('action', 'updated')->latest()->first();
        $updatedLog->update(['created_at' => Carbon::yesterday()]);
        $this->get(route('admin.audit-logs', ['date_from' => now()->addDay()->toDateString()]))
            ->assertOk()
            ->assertDontSee('After change');
        $this->get(route('admin.audit-logs', ['date_from' => Carbon::yesterday()->toDateString(), 'date_to' => now()->toDateString()]))
            ->assertOk();

        $this->get(route('admin.audit-logs'))
            ->assertOk()
            ->assertSee('After change')
            ->assertSee('Journal des modifications');

        $host = User::factory()->create(['role' => 'host', 'is_active' => true]);
        $this->actingAs($host)->get(route('admin.audit-logs'))->assertForbidden();
    }
}