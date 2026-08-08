<?php

use App\Http\Controllers\Api\v2\Master\MasterController;
use App\Http\Controllers\Api\v2\Product\ProductController;
use Illuminate\Support\Facades\Route;

Route::group([
    'prefix' => 'master'
], function () {

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/sales', [MasterController::class, 'sales']);
        Route::get('/pelanggan', [MasterController::class, 'pelanggan']);
    });
});


