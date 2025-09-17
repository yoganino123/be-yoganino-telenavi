<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/healthz', function () {
    return response()->json(['status' => 'OK', 'message' => 'Health check works']);
});
