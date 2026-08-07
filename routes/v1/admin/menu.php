<?php

use App\Http\Controllers\Api\Admin\MenuController;
use Illuminate\Support\Facades\Route;

Route::group([
    //'middleware' => 'auth:api',
    'prefix' => 'admin/menu'
], function () {
    Route::get('/list', [MenuController::class, 'list']);
    // Route::post('/simpanbarang', [BarangController::class, 'simpanbarang']);
});
