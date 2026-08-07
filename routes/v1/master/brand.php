<?php

use App\Http\Controllers\Api\Master\BrandsController;
use Illuminate\Support\Facades\Route;

Route::group([
    //'middleware' => 'auth:api',
    'prefix' => 'master/brand'
], function () {
    Route::get('/listdata', [BrandsController::class, 'list_data']);
    Route::post('/savedata', [BrandsController::class, 'save_data']);
    Route::post('/deletedata', [BrandsController::class, 'delete_data']);
});
