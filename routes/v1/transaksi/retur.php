<?php

use App\Http\Controllers\Api\Transaksi\Retur\ReturPenjualanController;
use Illuminate\Support\Facades\Route;

Route::group([
    'middleware' => 'auth:api',
    'prefix' => 'transaksi/retur'
], function () {
    Route::get('/list-retur', [ReturPenjualanController::class, 'retur']);
    Route::get('/list-penjualan', [ReturPenjualanController::class, 'index']);
    Route::post('/simpan', [ReturPenjualanController::class, 'store']);
    Route::post('/selesai', [ReturPenjualanController::class, 'selesai']);
});
