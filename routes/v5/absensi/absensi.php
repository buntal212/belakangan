<?php

use App\Http\Controllers\Api\v5\Absensi\AbsensiController;
use App\Http\Controllers\Api\v5\Settings\LokasiAbsenController;
use Illuminate\Support\Facades\Route;

Route::prefix('absensi')->middleware('auth:sanctum')->group(function () {
    Route::get('/lokasi', [LokasiAbsenController::class, 'show']);
    Route::post('/lokasi', [LokasiAbsenController::class, 'save']);
    Route::post('/wajah', [AbsensiController::class, 'registerFace']);
    Route::get('/hari-ini', [AbsensiController::class, 'today']);
    Route::post('/', [AbsensiController::class, 'store']);
    Route::get('/riwayat', [AbsensiController::class, 'history']);
});
