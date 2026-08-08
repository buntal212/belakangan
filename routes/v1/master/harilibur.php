<?php

use App\Http\Controllers\Api\Master\HariLiburController;
use App\Http\Controllers\Api\Master\JeniskeramikController;
use Illuminate\Support\Facades\Route;

Route::group([
    'middleware' => 'auth:api',
    'prefix' => 'master/harilibur'
], function () {
    Route::get('/listdata', [HariLiburController::class, 'list_data']);
    Route::post('/savedata', [HariLiburController::class, 'save_data']);
});
