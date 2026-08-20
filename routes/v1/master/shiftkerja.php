<?php

use App\Http\Controllers\Api\Master\ShiftKerjaController;
use Illuminate\Support\Facades\Route;

Route::group([
    'middleware' => 'auth:api',
    'prefix' => 'master/shiftkerja',
], function () {
    Route::get('/listdata', [ShiftKerjaController::class, 'list_data']);
    Route::post('/savedata', [ShiftKerjaController::class, 'save_data']);
    Route::post('/deletedata', [ShiftKerjaController::class, 'delete_data']);
});
