<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ChatController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);
Route::post('/auth/logout', [AuthController::class, 'logout'])->middleware('auth');

Route::middleware('auth')->prefix('api')->group(function () {
    Route::get('/me', fn (Request $request) => response()->json(['user' => $request->user()]));
    Route::get('/conversations', [ChatController::class, 'index']);
    Route::post('/conversations', [ChatController::class, 'store']);
    Route::post('/conversations/{conversation}/messages', [ChatController::class, 'message']);
    Route::delete('/conversations/{conversation}', [ChatController::class, 'destroy']);
});
