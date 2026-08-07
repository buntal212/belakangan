<?php

use App\Helpers\FormatingHelper;
use App\Models\Barang;
use App\Models\Transaksi\Penjualan\HeaderPenjualan;
use App\Models\Transaksi\Penjualan\PembayaranCicilan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});
Route::get('/autogen', function () {

    // echo "ok45";
    $data = DB::table('barangs')->get();
    // $data = Barang::all();
    return response()->json($data);
});

Route::get('/autogenx', function () {
    return 'wewq';
});
Route::get('/test', function () {
    $data = HeaderPenjualan::find(9);
    $awal = explode('-', $data->no_penjualan);
    $count = PembayaranCicilan::where('no_penjualan', $data->no_penjualan)->count();
    $nomor = FormatingHelper::notaPenjualan($count + 1, 'CCL/' . $awal[0]);

    $ret = $nomor;
    return $ret;
});
