<?php

use App\Http\Controllers\Api\v2\Product\ProductController;
use Illuminate\Support\Facades\Route;

Route::group([
    'prefix' => 'product'
], function () {
  // Route untuk mendapatkan URL redirect
  Route::get('/get-products', [ProductController::class, 'getProducts']);
  Route::get('/by/{id}', [ProductController::class, 'productById']);

    Route::middleware('auth:sanctum')->group(function () {
       // Route untuk mendapatkan data user yang sedang login
        Route::get('/like/{id}', [ProductController::class, 'productLike']);

        Route::get('/whislist', [ProductController::class, 'whishlist']);
    });
});


