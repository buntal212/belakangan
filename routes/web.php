<?php

use App\Events\SendNotificationEvent;
use App\Helpers\FormatingHelper;
use App\Models\Barang;
use App\Models\Transaksi\Penjualan\HeaderPenjualan;
use App\Models\Transaksi\Penjualan\PembayaranCicilan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
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

    echo "ok45";
    // $data = DB::table('barangs')->paginate(10);
    // // $data = Barang::all();
    // return response()->json($data);
});

Route::get('/autogenx', function () {
    return 'wewq';
});
Route::get('/infophp', function () {
    phpinfo(); // Menampilkan informasi PHP
});
Route::get('/test', function () {
    $data = HeaderPenjualan::find(9);
    $awal = explode('-', $data->no_penjualan);
    $count = PembayaranCicilan::where('no_penjualan', $data->no_penjualan)->count();
    $nomor = FormatingHelper::notaPenjualan($count + 1, 'CCL/' . $awal[0]);

    $ret = $nomor;
    return $ret;
});

Route::get('/kirim-ke-test-room', function () {
    // $response = Http::post(config('services.socket.url').'/send', [
    //    'event' => 'order-status',
    //     'data' => [
    //         'pesan' => 'Order #123 telah dikonfirmasi.'
    //     ],
    //     'user_id' => null, // null = broadcast ke semua
    //     'room' => 'order-penjualan' // custom tambahkan ini
    // ]);

    // return $response->json(['message'=>'OK']);
    $data = [
        'pesan'=> 'percobaan dari lara'
    ];
    event(new SendNotificationEvent(
        null,'order-penjualan','order-status',$data));
});
