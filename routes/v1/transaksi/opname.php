<?php

use App\Http\Controllers\Api\Transaksi\Stok\StokOpnameController;
use Illuminate\Support\Facades\Route;

Route::group([
    'middleware' => 'auth:api',
    'prefix' => 'transaksi/opname'
], function () {
    Route::get('/list', [StokOpnameController::class, 'index']);
});
