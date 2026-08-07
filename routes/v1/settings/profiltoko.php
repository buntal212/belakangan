<?php

use App\Http\Controllers\Api\Settings\ProfiltokoController;
use Illuminate\Support\Facades\Route;

Route::group([
    //'middleware' => 'auth:api',
    'prefix' => 'settings/profiltoko'
], function (): void {
    Route::get('/getprofil', [ProfiltokoController::class, 'get_profil']);
    Route::get('/dataprofil', [ProfiltokoController::class, 'dataprofil']);
    Route::post('/save', [ProfiltokoController::class, 'save']);

});
