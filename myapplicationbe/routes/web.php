<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Temporary debug: test if POST works at all
Route::post('/debug-post', function () {
    return response()->json(['status' => 'POST works', 'session' => session()->getId()]);
});
