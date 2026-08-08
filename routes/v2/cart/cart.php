<?php

use App\Http\Controllers\Api\v2\Product\CartController;
use Illuminate\Support\Facades\Route;

Route::group([
    'prefix' => 'cart',
    'middleware' => ['auth:sanctum'],
], function () {
    Route::get('/', [CartController::class, 'index']);
    Route::post('/', [CartController::class, 'store']);
    Route::delete('/destroy-all', [CartController::class, 'destroyAllCart']);
    Route::put('/{cart}', [CartController::class, 'update']);
    Route::delete('/{cart}', [CartController::class, 'destroy']);
});