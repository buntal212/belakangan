<?php
use App\Http\Controllers\Api\v3\Auth\SalesAuthController;
use Illuminate\Support\Facades\Route;
Route::prefix('auth')->group(function () {
    Route::post('/login', [SalesAuthController::class, 'login'])->middleware('throttle:10,1');
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [SalesAuthController::class, 'logout']);
        Route::get('/me', [SalesAuthController::class, 'me']);
    });
});
