<?php

use App\Http\Controllers\Api\v4\Master\BarangController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->prefix('master/barang')->group(function () {
    Route::get('/listbarang', [BarangController::class, 'listbarang']);
});
