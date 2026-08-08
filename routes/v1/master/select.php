<?php

use App\Http\Controllers\Api\Master\BarangController;
use App\Http\Controllers\Api\Master\SelectController;
use Illuminate\Support\Facades\Route;

Route::group([
    'middleware' => 'auth:api',
    'prefix' => 'master/select'
], function () {
    Route::get('/master-satuan-all', [SelectController::class, 'satuan_all']);
    Route::get('/master-satuan-filter', [SelectController::class, 'satuan_filter']);
    Route::get('/master-get-brand', [SelectController::class, 'get_brand']);

    Route::get('/master-get-jenis', [SelectController::class, 'get_jenis']);


    // ini untuk select yg lain
    Route::get('/master-barang-filter', [SelectController::class, 'barang_filter']);
    Route::get('/master-beban', [SelectController::class, 'selectbeban']);

});
