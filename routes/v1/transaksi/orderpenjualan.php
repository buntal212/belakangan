<?php

use App\Http\Controllers\Api\Transaksi\OrderPenjualan\OrderPenjualanController;
use Illuminate\Support\Facades\Route;

Route::group([
    'middleware' => 'auth:api',
    'prefix' => 'orderpenjualan'
], function () {
    Route::get('/list', [OrderPenjualanController::class, 'index']);
    Route::post('/update-rincian', [OrderPenjualanController::class, 'updateRincian']);
    Route::post('/delete-rincian', [OrderPenjualanController::class, 'deleteRincian']);
    Route::post('/update-status', [OrderPenjualanController::class, 'updateStatus']);
});
