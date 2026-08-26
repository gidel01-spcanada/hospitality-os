<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AuthSeeder extends Seeder
{
    public function run(): void
    {
        $adminPassword = config('services.seed.admin_password');
        $guestPassword = config('services.seed.guest_password');

        if (blank($adminPassword) || blank($guestPassword)) {
            throw new \RuntimeException('SEED_ADMIN_PASSWORD and SEED_GUEST_PASSWORD must be configured before running the auth seeder.');
        }

        // Seeders run outside any authenticated request, so there's no tenant context to stamp rows with automatically.
        $tenantId = Tenant::query()->firstOrCreate(['slug' => 'default'], ['name' => 'Default', 'status' => 'active'])->id;

        User::query()->updateOrCreate(
            ['email' => 'admin@afrikappart.test'],
            [
                'name' => 'Afrik Appart Admin',
                'password' => Hash::make($adminPassword),
                'role' => 'admin',
                'is_admin' => true,
                'tenant_id' => $tenantId,
                // The on-premise/default install's admin also gets platform-admin access,
                // since there's no meaningful distinction with a single tenant.
                'is_platform_admin' => true,
                'locale' => 'fr',
                'email_booking_updates' => true,
                'email_marketing' => false,
                'email_newsletter' => false,
                'email_verified_at' => now(),
            ]
        );

        User::query()->updateOrCreate(
            ['email' => 'guest@afrikappart.test'],
            [
                'name' => 'Guest Customer',
                'password' => Hash::make($guestPassword),
                'role' => 'customer',
                'is_admin' => false,
                'locale' => 'fr',
                'email_booking_updates' => true,
                'email_marketing' => false,
                'email_newsletter' => false,
                'email_verified_at' => now(),
            ]
        );
    }
}
