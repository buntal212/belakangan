<?php

use App\Http\Controllers\Api\Master\BebanController;
use App\Http\Controllers\Api\Transaksi\Beban\TransBebanController;
use Illuminate\Support\Facades\Route;

Route::group([
    'middleware' => 'auth:api',
    'prefix' => 'transaksi/beban'
], function () {
    Route::get('/listdata', [TransBebanController::class, 'list_data']);
    Route::post('/savedata', [TransBebanController::class, 'save_data']);
    Route::post('/deletedata', [TransBebanController::class, 'delete_data']);
    Route::post('/kuncidata', [TransBebanController::class, 'kunci']);

});
