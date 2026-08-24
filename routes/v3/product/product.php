<?php
use App\Http\Controllers\Api\v3\Product\ProductController;
use Illuminate\Support\Facades\Route;
Route::prefix('product')->group(function () {
    Route::get('/get-products', [ProductController::class, 'getProducts']);
    Route::get('/detail/{identifier}', [ProductController::class, 'getProduct']);
    Route::get('/detail-by-slug/{slug}', [ProductController::class, 'getProductBySlug'])->name('api.v3.product.detail-by-slug');
    Route::get('/filters', [ProductController::class, 'getFilters']);
});
