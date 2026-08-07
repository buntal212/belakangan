<?php

use App\Http\Controllers\Api\Transaksi\Penerimaan\OrderPenerimaanController;
use Illuminate\Support\Facades\Route;

Route::group([
    //'middleware' => 'auth:api',
    'prefix' => 'transaksi/orderpembelian'
], function () {
    Route::post('/hapusrincian', [OrderPenerimaanController::class, 'hapusrincianorder']);
    Route::post('/simpan', [OrderPenerimaanController::class, 'simpan']);
    Route::get('/getlistorder', [OrderPenerimaanController::class, 'getlistorder']);

    Route::get('/getlistorder', [OrderPenerimaanController::class, 'getlistorder']);
    Route::get('/getallbynoorder', [OrderPenerimaanController::class, 'getallbynoorder']);
    Route::post('/kunci', [OrderPenerimaanController::class, 'kunci']);

    Route::get('/getlistorderfixheder', [OrderPenerimaanController::class, 'getlistorderfixheder']);
});
