<?php

use App\Http\Controllers\Api\Transaksi\PembayaranHutang\PembayaranHutangController;
use Illuminate\Support\Facades\Route;

Route::group([
    'middleware' => 'auth:api',
    'prefix' => 'transaksi/pembayaranhutang'
], function () {
    Route::get('/list', [PembayaranHutangController::class, 'index']);
    Route::get('/list-hutang', [PembayaranHutangController::class, 'listhutang']);
    Route::get('/listbynopembayaran', [PembayaranHutangController::class, 'listbynopembayaran']);

    Route::post('/simpan', [PembayaranHutangController::class, 'simpan']);
    Route::post('/hapusrincian', [PembayaranHutangController::class, 'hapusrincian']);
});
