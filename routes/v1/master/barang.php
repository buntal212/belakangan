<?php

use App\Http\Controllers\Api\Master\BarangController;
use Illuminate\Support\Facades\Route;

Route::group([
    //'middleware' => 'auth:api',
    'prefix' => 'master/barang'
], function () {
    Route::get('/listbarang', [BarangController::class, 'listbarang']);
    Route::post('/simpanbarang', [BarangController::class, 'simpanbarang']);
    Route::post('/deletebarang', [BarangController::class, 'deleteItem']);
    Route::post('/deleteimage', [BarangController::class, 'deletegambar']);
    Route::post('/setthumbnail', [BarangController::class, 'setThumbnail']);
});
