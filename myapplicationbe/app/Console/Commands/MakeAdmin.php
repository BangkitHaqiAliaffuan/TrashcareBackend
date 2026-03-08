<?php

namespace App\Console\Commands;

use App\Models\Admin;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class MakeAdmin extends Command
{
    protected $signature = 'make:admin
                            {--name= : Nama admin}
                            {--email= : Email admin}
                            {--password= : Password admin}';

    protected $description = 'Buat akun admin baru untuk Filament panel';

    public function handle(): int
    {
        $name     = $this->option('name')     ?? $this->ask('Nama admin');
        $email    = $this->option('email')    ?? $this->ask('Email admin');
        $password = $this->option('password') ?? $this->secret('Password admin');

        $validator = Validator::make(
            compact('name', 'email', 'password'),
            [
                'name'     => ['required', 'string', 'max:255'],
                'email'    => ['required', 'email', 'unique:admins,email'],
                'password' => ['required', 'string', 'min:8'],
            ]
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }
            return self::FAILURE;
        }

        $admin = Admin::create([
            'name'     => $name,
            'email'    => $email,
            'password' => Hash::make($password),
        ]);

        $this->info("✅ Admin berhasil dibuat!");
        $this->table(['Field', 'Value'], [
            ['Name',  $admin->name],
            ['Email', $admin->email],
            ['ID',    $admin->id],
        ]);

        return self::SUCCESS;
    }
}
