<?php

use App\Http\Controllers\Api\v4\Sales\PengorderanController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->prefix('sales/pengorderan')->group(function () {
    Route::get('/', [PengorderanController::class, 'riwayat']);
    Route::get('/pelanggan', [PengorderanController::class, 'pelanggan']);
    Route::post('/', [PengorderanController::class, 'simpan']);
});
