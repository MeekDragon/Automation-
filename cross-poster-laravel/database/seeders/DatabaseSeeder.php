<?php
// database/seeders/DatabaseSeeder.php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Seed 1 Superadmin
        User::create([
            'name' => 'Super Admin',
            'username' => 'superadmin',
            'password' => Hash::make('superpassword'),
            'role' => 'superadmin',
            'email' => 'superadmin@example.com'
        ]);

        // 2. Seed 5 Admins
        for ($i = 1; $i <= 5; $i++) {
            User::create([
                'name' => "Admin {$i}",
                'username' => "admin{$i}",
                'password' => Hash::make('adminpassword'),
                'role' => 'admin',
                'email' => "admin{$i}@example.com"
            ]);
        }

        // 3. Seed 20 Standard Users
        for ($i = 1; $i <= 20; $i++) {
            User::create([
                'name' => "User {$i}",
                'username' => "user{$i}",
                'password' => Hash::make('userpassword'),
                'role' => 'user',
                'email' => "user{$i}@example.com"
            ]);
        }
    }
}
