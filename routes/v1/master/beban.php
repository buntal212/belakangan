<?php

use App\Http\Controllers\Api\Master\BebanController;
use Illuminate\Support\Facades\Route;

Route::group([
    'middleware' => 'auth:api',
    'prefix' => 'master/beban'
], function () {
    Route::get('/listdata', [BebanController::class, 'list_data']);
    Route::post('/savedata', [BebanController::class, 'save_data']);
    Route::post('/deletedata', [BebanController::class, 'delete_data']);
});
