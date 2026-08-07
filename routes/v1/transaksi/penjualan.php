<?php

use App\Http\Controllers\Api\Transaksi\Penjualan\PenjualanController;
use Illuminate\Support\Facades\Route;

Route::group([
    //'middleware' => 'auth:api',
    'prefix' => 'transaksi/penjualan'
], function () {
    Route::get('/list-barang', [PenjualanController::class, 'getBarang']);
    Route::get('/list-sales', [PenjualanController::class, 'getSales']);
    Route::get('/list-pelanggan', [PenjualanController::class, 'getPelanggan']);
    Route::post('/simpan-detail', [PenjualanController::class, 'simpanDetail']);
    Route::post('/delete-detail', [PenjualanController::class, 'hapusDetail']);

    Route::post('/simpan-pembayaran', [PenjualanController::class, 'simpanPembayaran']);
    // list penjualan
    Route::get('/list', [PenjualanController::class, 'getListPenjualan']);
    Route::get('/list-null', [PenjualanController::class, 'getListPenjualanNull']);
});
