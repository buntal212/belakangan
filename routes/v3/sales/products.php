<?php
use App\Http\Controllers\Api\v3\Sales\ProductController;
use Illuminate\Support\Facades\Route;
Route::group(['prefix' => 'sales', 'middleware' => ['auth:sanctum', 'sales']], function () {
    Route::get('/products', [ProductController::class, 'getProducts']);
});
