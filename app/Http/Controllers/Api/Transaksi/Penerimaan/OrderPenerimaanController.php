<?php

namespace App\Http\Controllers\Api\Transaksi\Penerimaan;

use App\Helpers\FormatingHelper;
use App\Http\Controllers\Controller;
use App\Models\Transaksi\Penerimaan\OrderPembelian_h;
use App\Models\Transaksi\Penerimaan\OrderPembelian_r;
use App\Models\Transaksi\Penerimaan\Penerimaan_h;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderPenerimaanController extends Controller
{
    public function simpan(Request $request)
    {
        if($request->noorder === null)
        {
            DB::select('call noorderpembelian(@nomor)');
            $x = DB::table('counter')->select('orderbeli')->get();
            $no = $x[0]->orderbeli;

            $notrans = FormatingHelper::matorderpembelian($no, 'OR');
        }else{
            $notrans = $request->noorder;
            // $cek = OrderPembelian_h::where('noorder', $notrans)->first();
            // if($cek->flaging === '1'){
            //     return new JsonResponse(['message' => 'Maaf Data ini Sudah Dikunci...!!!'], 500);
            // }
        }

        try{
            DB::beginTransaction();
            $simpan = OrderPembelian_h::updateOrCreate(
                [
                    'noorder' => $notrans,
                ],
                [
                    'tglorder' => date('Y-m-d H:i:s'),
                    'kdsuplier' => $request->kdsuplier
                ]
            );
            $jumlahpo_k = $request->jumlah * $request->isi;
            $total = $request->jumlah * $request->harga;
            $simpanR = OrderPembelian_r::create(
                [
                    'noorder' => $notrans,
                    'kdbarang' => $request->kdbarang,
                    'jumlahpo' => $request->jumlah,
                    'satuan_b' => $request->satuan_b,
                    'jumlahpo_k' => $jumlahpo_k,
                    'satuan_k' => $request->satuan_k,
                    'isi' => $request->isi,
                    'hargapo' => $request->harga,
                    'total' => $total,
                    'user' => '',
                ]
            );

            DB::commit();
            $hasil = self::getlistorderhasil($notrans);
            return new JsonResponse(
                [
                    'message' => 'Data Berhasil Disimpan',
                    'notrans' => $notrans,
                    'result' => $hasil
                ],200);

        } catch (\Exception $e) {
            DB::rollBack();
            return new JsonResponse(['message' => 'ada kesalahan', 'error' => $e], 500);
        }
    }

    public function getlistorder()
    {
        $list = OrderPembelian_h::with(
            [
                'suplier',
                'rinci' => function($rinci){
                    $rinci->select('*',DB::raw('(jumlahpo*hargapo) as subtotal'))
                    ->with(['mbarang']);
                }
            ]
        )
        // ->orderBy('id', 'desc')
        ->simplePaginate(request('per_page'));
        return new JsonResponse($list);
    }

    public static function getlistorderhasil($notrans)
    {
        $list = OrderPembelian_h::with(
            [
                'suplier',
                'rinci'
                => function($rinci){
                    $rinci->select('*',DB::raw('(jumlahpo*hargapo) as subtotal'))
                    ->with(['mbarang']);
                }
            ]
        )
        ->where('noorder', $notrans)
        ->orderBy('id', 'desc')
        ->get();
        return $list;
    }

    public function getallbynoorder()
    {
        $list = OrderPembelian_h::with(
            [
                'suplier',
                'rinci' => function($rinci){
                    $rinci->with(['mbarang']);
                }
            ]
        )
        ->where('noorder', request('noorder'))
        ->get();
        return new JsonResponse($list);
    }

    public function hapusrincianorder(Request $request)
    {
        $cek = OrderPembelian_r::find($request->id);
        if(!$cek)
        {
            return new JsonResponse(['message' => 'data tidak ditemukan']);
        }

        $hapus = $cek->delete();
        if(!$hapus)
        {
            return new JsonResponse(['message' => 'data gagal dihapus'],500);
        }
        $hasil = self::getlistorderhasil($request->noorder);

        return new JsonResponse(
            [
                'message' => 'data berhasil dihapus',
                'result' => $hasil
            ], 200);
    }

    public function kunci(Request $request)
    {

        $cari = OrderPembelian_h::where('noorder', $request->noorder)->first();
        $cari->flaging = $request->val;

        $cari->save();

        $hasil = self::getlistorderhasil($request->noorder);

        return new JsonResponse(
            [
                'message' => $request->val === '1' ? 'Data Berhasil Dikunci...!!!' :'Data Berhasil Dibuka...!!!',
                'result' => $hasil
            ], 200);
    }

    public function getlistorderfixheder()
    {
        $data = OrderPembelian_h::with(
            [
                'suplier',
                'rinci' => function($rinci){
                    $rinci->select('*','jumlahpo as jumlahpox','hargapo as hargafix',DB::raw('(jumlahpo*hargapo) as subtotal'))
                    ->with(['mbarang']);
                },
            ]
        )->
        where('flaging', '1')
        ->get();

        return new JsonResponse($data);
    }
}
