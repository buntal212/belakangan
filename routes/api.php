<?php

use App\Helpers\Routes\RouteHelper;
use App\Http\Controllers\Api\login\loginController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
//     return $request->user();
// });

// Route::get('/listbarang', [BarangController::class, 'listbarang']);

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});

Route::post('/login', [loginController::class, 'login']);
Route::middleware('auth:api')->post('/logout', [loginController::class, 'logout']);

// Routes v1 yang sudah ada
Route::prefix('v1')->group(function () {
    RouteHelper::includeRouteFiles(__DIR__ . '/v1');
});

// Tambahkan routes v2
Route::prefix('v2')->group(function () {
    RouteHelper::includeRouteFiles(__DIR__ . '/v2');
});

