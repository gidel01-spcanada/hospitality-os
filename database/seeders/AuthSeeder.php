<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AuthSeeder extends Seeder
{
    public function run(): void
    {
        User::query()->updateOrCreate(
            ['email' => 'admin@afrikappart.test'],
            [
                'name' => 'Afrik Appart Admin',
                'password' => Hash::make('Password123!'),
                'role' => 'admin',
                'is_admin' => true,
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
                'password' => Hash::make('Password123!'),
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
