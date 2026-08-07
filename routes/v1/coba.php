<?php

use App\Http\Controllers\Api\CobaController;
use Illuminate\Support\Facades\Route;

Route::group([
    //'middleware' => 'auth:api',
    'prefix' => 'coba/barang'
], function () {
    Route::get('/list', [CobaController::class, 'list']);
});
