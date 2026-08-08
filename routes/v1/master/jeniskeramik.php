<?php

use App\Http\Controllers\Api\Master\JeniskeramikController;
use Illuminate\Support\Facades\Route;

Route::group([
    'middleware' => 'auth:api',
    'prefix' => 'master/jeniskeramik'
], function () {
    Route::get('/listdata', [JeniskeramikController::class, 'list_data']);
    Route::post('/savedata', [JeniskeramikController::class, 'save_data']);
    Route::post('/deletedata', [JeniskeramikController::class, 'delete_data']);
});
