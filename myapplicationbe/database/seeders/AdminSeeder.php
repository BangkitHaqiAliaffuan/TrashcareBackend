<?php

namespace Database\Seeders;

use App\Models\Admin;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $admins = [
            [
                'name'     => env('ADMIN_NAME', 'Super Admin'),
                'email'    => env('ADMIN_EMAIL', 'superadmin@gmail.com'),
                'password' => Hash::make(env('ADMIN_PASSWORD', 'Admin@12345')),
            ],
        ];

        foreach ($admins as $data) {
            Admin::updateOrCreate(
                ['email' => $data['email']],
                $data
            );
        }

        $this->command->info('Admin seeded: ' . env('ADMIN_EMAIL', 'admin@trashcare.com'));
    }
}
