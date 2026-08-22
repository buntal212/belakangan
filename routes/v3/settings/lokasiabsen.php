<?php

use App\Http\Controllers\Api\Settings\LokasiAbsenController;
use Illuminate\Support\Facades\Route;

Route::get('/lokasi-absen', [LokasiAbsenController::class, 'get']);