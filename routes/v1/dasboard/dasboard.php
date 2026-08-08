<?php

use App\Http\Controllers\Api\Dasboard\dasboard;
use Illuminate\Support\Facades\Route;

Route::group([
    'middleware' => 'auth:api',
    'prefix' => 'dasboard'
], function () {
    Route::get('/penjualanbulanan', [dasboard::class, 'listpenjualand']);
    Route::get('/salestrend', [dasboard::class, 'salestrend']);
    Route::get('/fastmove10', [dasboard::class, 'fastmove10']);
});
