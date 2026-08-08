<?php
use App\Http\Controllers\Api\Master\PelangganController;
use Illuminate\Support\Facades\Route;

Route::group([
    'middleware' => 'auth:api',
    'prefix' => 'master/pelanggan'
], function () {
    Route::get('/listpelanggan', [PelangganController::class, 'listpelanggan']);
    Route::get('/listpelangganall', [PelangganController::class, 'listpelangganall']);
    Route::post('/simpan', [PelangganController::class, 'simpan']);
    Route::post('/hapus', [PelangganController::class, 'hapus']);
});
