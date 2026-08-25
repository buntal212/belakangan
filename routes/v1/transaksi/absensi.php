<?php

use App\Http\Controllers\Api\Transaksi\Absensi\PermohonanAbsensiController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:api')->prefix('transaksi/absensi')->group(function () {
    Route::get('/', [PermohonanAbsensiController::class, 'index']);
    Route::post('/', [PermohonanAbsensiController::class, 'store']);
    Route::delete('/{permohonanAbsensi}', [PermohonanAbsensiController::class, 'destroy']);
});
