<?php
use App\Http\Controllers\Api\Master\SupplierController;
use Illuminate\Support\Facades\Route;

Route::group([
    //'middleware' => 'auth:api',
    'prefix' => 'master/supplier'
], function () {
    Route::get('/list', [SupplierController::class, 'list']);
    Route::get('/alllist', [SupplierController::class, 'alllist']);
    Route::post('/simpan', [SupplierController::class, 'simpan']);
    Route::post('/hapus', [SupplierController::class, 'hapus']);
});
