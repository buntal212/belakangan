<?php
use App\Http\Controllers\Api\Settings\LokasiAbsenController;
use Illuminate\Support\Facades\Route;
Route::group(['middleware' => 'auth:api', 'prefix' => 'settings/lokasiabsen'], function (): void {
    Route::get('/get', [LokasiAbsenController::class, 'get']);
    Route::post('/save', [LokasiAbsenController::class, 'save']);
});
