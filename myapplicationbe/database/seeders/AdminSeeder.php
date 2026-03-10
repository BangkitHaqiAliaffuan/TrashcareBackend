<?php

namespace Database\Seeders;

use App\Models\Admin;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $name     = env('ADMIN_NAME', 'Super Admin');
        $email    = env('ADMIN_EMAIL', 'superadmin@gmail.com');
        $password = env('ADMIN_PASSWORD', 'Admin@12345');

        // Delete all existing admins and recreate fresh
        // This avoids stale email conflicts when ADMIN_EMAIL changes
        Admin::truncate();

        Admin::create([
            'name'     => $name,
            'email'    => $email,
            'password' => Hash::make($password),
        ]);

        $this->command->info("Admin created: {$email}");
    }
}
