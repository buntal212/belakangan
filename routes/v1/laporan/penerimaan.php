<?php

use App\Http\Controllers\Api\Laporan\PenerimaanController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:api')
    ->get('/laporan/penerimaan/getdata', [PenerimaanController::class, 'getData']);
