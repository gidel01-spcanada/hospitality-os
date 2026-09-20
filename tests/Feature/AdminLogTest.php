<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class AdminLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_limited_log_tail_and_customer_cannot(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_admin' => true, 'is_active' => true]);
        $path = storage_path('logs/validation.log');
        File::ensureDirectoryExists(dirname($path));
        File::put($path, "first\nsecond\nthird\n");

        try {
            $this->actingAs($admin)
                ->get(route('admin.logs', ['file' => 'validation.log', 'lines' => 50]))
                ->assertOk()
                ->assertSee('second')
                ->assertSee('third');

            $customer = User::factory()->create(['role' => 'customer', 'is_admin' => false]);
            $this->actingAs($customer)->get(route('admin.logs'))->assertForbidden();
        } finally {
            File::delete($path);
        }
    }
}
