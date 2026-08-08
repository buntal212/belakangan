<?php

use App\Http\Controllers\Api\DashboardController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:api')->get('/dashboard', [DashboardController::class, 'index']);
