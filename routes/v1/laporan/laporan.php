<?php

use App\Http\Controllers\Api\Laporan\LaporanAkuntansiController;
use App\Http\Controllers\Api\Laporan\LaporanPenerimaanController;
use App\Http\Controllers\Api\Laporan\LaporanPengeluaranController;
use App\Http\Controllers\Api\Laporan\LaporanPenjualanController;
use Illuminate\Support\Facades\Route;

Route::group([
    'middleware' => 'auth:api',
    'prefix' => 'laporan/penerimaan'
], function () {
    Route::get('/getdata', [LaporanPenerimaanController::class, 'getData']);
});

Route::group([
    'middleware' => 'auth:api',
    'prefix' => 'laporan/penjualan'
], function () {
    Route::get('/getpenjualan', [LaporanPenjualanController::class, 'getData']);

});


Route::group([
    'middleware' => 'auth:api',
    'prefix' => 'laporan/pengeluaran'
], function () {
    Route::get('/getpengeluaran', [LaporanPengeluaranController::class, 'getData']);

});

Route::group([
    'middleware' => 'auth:api',
    'prefix' => 'laporan/aruskas'
], function () {
    Route::get('/getaruskas', [LaporanAkuntansiController::class, 'Aruskas']);

});

Route::group([
    'middleware' => 'auth:api',
    'prefix' => 'laporan/labarugi'
], function () {
    Route::get('/getlabarugi', [LaporanAkuntansiController::class, 'Labarugi']);

});

Route::group([
    'middleware' => 'auth:api',
    'prefix' => 'laporan/hutangpiutang'
], function () {
    Route::get('/gethutangpiutang', [LaporanAkuntansiController::class, 'hutangpiutang']);

});


