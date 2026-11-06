<?php

use App\Http\Controllers\Api\Settings\PpnController;
use Illuminate\Support\Facades\Route;

Route::group(['middleware' => 'auth:api', 'prefix' => 'settings/ppn'], function (): void {
    Route::get('/', [PpnController::class, 'show']);
    Route::post('/', [PpnController::class, 'simpan']);
});
