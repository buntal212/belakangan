<?php

use App\Http\Controllers\Api\v2\Auth\SocialiteController;
use Illuminate\Support\Facades\Route;

Route::group([
    'prefix' => 'auth'
], function () {
    // Route untuk mendapatkan URL redirect
    Route::get('{provider}/url', [SocialiteController::class, 'getRedirectUrl']);
    
    // Route untuk redirect ke provider
    Route::get('{provider}/redirect', [SocialiteController::class, 'redirect']);
    
    // Route untuk callback dari provider
    Route::get('{provider}/callback', [SocialiteController::class, 'callback']);
});
