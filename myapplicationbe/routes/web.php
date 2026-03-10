<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;

Route::get('/', function () {
    return view('welcome');
});

// Temporary: view admins table — REMOVE after debugging
Route::get('/debug-admins', function () {
    $admins = DB::table('admins')->select('id', 'name', 'email', 'created_at')->get();
    return response()->json($admins);
});
