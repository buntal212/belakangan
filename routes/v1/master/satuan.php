<?php

use App\Http\Controllers\Api\Master\SatuanController;
use Illuminate\Support\Facades\Route;

Route::group([
    //'middleware' => 'auth:api',
    'prefix' => 'master/satuan'
], function () {
    Route::get('/listsatuan', [SatuanController::class, 'listsatuan']);
    Route::post('/satuansimpan', [SatuanController::class, 'satuansimpan']);
    Route::post('/hapussatuan', [SatuanController::class, 'hapussatuan']);
});
