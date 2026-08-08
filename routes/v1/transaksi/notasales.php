<?php

use App\Http\Controllers\Api\Transaksi\NotaSales\NotaSalesController;
use Illuminate\Support\Facades\Route;

Route::group([
    'middleware' => 'auth:api',
    'prefix' => 'transaksi/notasales'
], function () {
    Route::get('/list', [NotaSalesController::class, 'list']);
    Route::get('/caripiutang', [NotaSalesController::class, 'caripiutang']);
    Route::post('/simpan', [NotaSalesController::class, 'simpan']);
    Route::post('/hapusrincian', [NotaSalesController::class, 'hapusrincian']);
    Route::post('/kunci', [NotaSalesController::class, 'kunci']);
});
