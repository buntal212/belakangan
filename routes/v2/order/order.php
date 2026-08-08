<?php

use App\Http\Controllers\Api\v2\Order\OrderPenjualanController;
use App\Http\Controllers\Api\v2\Product\ProductController;
use Illuminate\Support\Facades\Route;

Route::group([
    'prefix' => 'order'
], function () {

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/penjualan', [OrderPenjualanController::class, 'orderPenjualan']);
        Route::get('/penjualan/by-pelanggan', [OrderPenjualanController::class, 'getByPelanggan']);
        Route::get('/penjualan/by-sales/{sales_id}', [OrderPenjualanController::class, 'getBySales']);

        Route::get('/by-noorder', [OrderPenjualanController::class, 'getByNoOrder']);
        Route::post('/update-pembayaran', [OrderPenjualanController::class, 'updatePembayaran']);
        Route::post('/update-selesai', [OrderPenjualanController::class, 'updateSelesai']);
    });
});


