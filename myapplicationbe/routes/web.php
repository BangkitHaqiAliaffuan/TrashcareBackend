<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\Admin;

Route::get('/', function () {
    return view('welcome');
});

// Temporary: view admins table — REMOVE after debugging
Route::get('/debug-admins', function () {
    $admins = DB::table('admins')->select('id', 'name', 'email', 'created_at')->get();
    return response()->json($admins);
});

// Temporary: force create admin — REMOVE after use
Route::get('/setup-admin', function () {
    Admin::truncate();
    $admin = Admin::create([
        'name'     => 'Super Admin',
        'email'    => 'adminbesar@gmail.com',
        'password' => Hash::make('Admin12345'),
    ]);
    return response()->json([
        'status'  => 'Admin created successfully',
        'email'   => $admin->email,
        'message' => 'Login with password: Admin12345',
    ]);
});
