<?php

use App\Http\Controllers\Api\Transaksi\Stok\stokcontroller;
use Illuminate\Support\Facades\Route;

Route::group([
    //'middleware' => 'auth:api',
    'prefix' => 'transaksi/stok'
], function () {
    Route::get('/lihatstok', [stokcontroller::class, 'lihatstok']);
});
