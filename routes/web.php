<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json(['status' => 'OK', 'message' => 'Welcome']);
});

Route::get('/healthz', function () {
    return response()->json(['status' => 'OK', 'message' => 'Health check works']);
});
