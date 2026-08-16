<?php

use App\Http\Controllers\Api\v3\Contact\ContactController;
use Illuminate\Support\Facades\Route;

Route::get('/contact', [ContactController::class, 'index']);
