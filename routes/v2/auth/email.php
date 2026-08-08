<?php

use App\Http\Controllers\Api\v2\Auth\EmailAuthController;
use Illuminate\Support\Facades\Route;

Route::group([
    'prefix' => 'auth'
], function () {
    // Route untuk login dengan email
    Route::post('/email/login', [EmailAuthController::class, 'login']);
    
    // Route untuk registrasi
    Route::post('/email/register', [EmailAuthController::class, 'register']);
    
    // Route untuk set password (untuk akun social)
    Route::post('/email/set-password', [EmailAuthController::class, 'setPassword']);
    
    // Route yang memerlukan autentikasi
    Route::middleware('auth:sanctum')->group(function () {
        // Route untuk logout
        Route::post('/email/logout', [EmailAuthController::class, 'logout']);
        
        // Route untuk mendapatkan data user yang sedang login
        Route::get('/me', [EmailAuthController::class, 'me']);
    });
});
