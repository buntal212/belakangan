<?php

use App\Http\Controllers\Api\Transaksi\PembayaranPiutang\PembayaranPiutangController;
use Illuminate\Support\Facades\Route;

Route::group([
    'middleware' => 'auth:api',
    'prefix' => 'transaksi/pembayaranpiutang'
], function () {
    Route::get('/list', [PembayaranPiutangController::class, 'index']);
    Route::get('/list-piutang', [PembayaranPiutangController::class, 'listpiutang']);
    Route::get('/listbynopembayaran', [PembayaranPiutangController::class, 'listbynopembayaran']);

    Route::post('/simpan', [PembayaranPiutangController::class, 'simpan']);
    Route::post('/hapusrincian', [PembayaranPiutangController::class, 'hapusrincian']);
});
