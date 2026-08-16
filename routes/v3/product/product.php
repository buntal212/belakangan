<?php
use App\Http\Controllers\Api\v3\Product\ProductController;
use Illuminate\Support\Facades\Route;
Route::prefix('product')->group(function () {
    Route::get('/get-products', [ProductController::class, 'getProducts']);
    Route::get('/filters', [ProductController::class, 'getFilters']);
});
