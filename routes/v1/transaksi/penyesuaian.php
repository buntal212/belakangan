<?php

use App\Http\Controllers\Api\Transaksi\Penjualan\PenjualanController;
use App\Http\Controllers\Api\Transaksi\Penyesuaian\PenyesuaianController;
use Illuminate\Support\Facades\Route;

Route::group([
    // 'middleware' => 'auth:api',
    'prefix' => 'transaksi/penyesuaian'
], function () {
    Route::get('/data', [PenyesuaianController::class, 'datapenyesuaian']);
    Route::get('/selectstok', [PenyesuaianController::class, 'selectstok']);
    Route::post('/save', [PenyesuaianController::class, 'save']);

});
