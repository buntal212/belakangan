<?php

use App\Http\Controllers\Api\Transaksi\Penerimaan\PenerimaanController;
use Illuminate\Support\Facades\Route;

Route::group([
    //'middleware' => 'auth:api',
    'prefix' => 'transaksi/penerimaan'
], function () {
    Route::post('/simpan', [PenerimaanController::class, 'simpan']);
    Route::post('/hapusrincian', [PenerimaanController::class, 'hapus']);
    Route::get('/getpenerimaan', [PenerimaanController::class, 'getList']);

    Route::post('/kirimstok', [PenerimaanController::class, 'kirimstok']);
});
