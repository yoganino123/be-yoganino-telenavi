<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\TodoController;

Route::post('/todos', [TodoController::class, 'store']);
Route::get('/todos', [TodoController::class, 'index']);
Route::get('/chart', [TodoController::class, 'chart']);
