<?php

namespace App\Http\Controllers\Api\Transaksi\Penerimaan;

use App\Helpers\FormatingHelper;
use App\Http\Controllers\Controller;
use App\Models\Stok\stok;
use App\Models\Transaksi\Penerimaan\OrderPembelian_h;
use App\Models\Transaksi\Penerimaan\OrderPembelian_r;
use App\Models\Transaksi\Penerimaan\Penerimaan_h;
use App\Models\Transaksi\Penerimaan\Penerimaan_r;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class PenerimaanController extends Controller
{
    public function simpan(Request $request)
    {

        $cek = Penerimaan_r::select(DB::raw('SUM(jumlah_b) as jumlahdatang'))->where('noorder', $request->noorder)->where('kdbarang', $request->kdbarang)->first();
        $totalbarangdatang = $cek->jumlahdatang + $request->jumlahpo;

        if($totalbarangdatang > $request->jumlahorder )
        {
            return new JsonResponse(['message' => 'Barang Yang Datang Melebihi Barang Yang di Pesan...!'], 500);
        }

        if($request->jumlahpo - $totalbarangdatang === 0){

        }

        if($request->nopenerimaan === '' || $request->nopenerimaan === null)
        {
            DB::select('call nopenerimaan(@nomor)');
            $x = DB::table('counter')->select('penerimaan')->get();
            $no = $x[0]->penerimaan;
            $nopenerimaan = FormatingHelper::nopenerimaan($no, 'P');
        }else{
            $nopenerimaan = $request->nopenerimaan;
        }

        try{
            DB::beginTransaction();
                $simpan = Penerimaan_h::updateOrCreate(
                    [
                        'nopenerimaan' => $nopenerimaan,
                    ],
                    [
                        'noorder' => $request->noorder,
                        'tgl' => date('Y-m-d H:i:s'),
                        'kdsupllier' => $request->kdsuplier,
                    ]
                );
                // return 'wew';
                $jumlah_k = $request->isi*$request->jumlahpo;
                $hargabelisatuankecil = $request->hargaasli/$request->jumlahpo_k;
                $subtotal = $request->jumlahpo*$request->hargafaktur;
                $subtotalfix = $hargabelisatuankecil*$request->jumlahpo_k*$request->jumlahpo;
                $simpanR = Penerimaan_r::create(
                    [
                        'nopenerimaan' => $nopenerimaan,
                        'noorder' => $request->noorder,
                        'kdbarang' => $request->kdbarang,
                        'jumlah_b' => $request->jumlahpo,
                        'jumlah_k' => $jumlah_k,
                        'isi' => $request->isi,
                        'satuan_b' => $request->satuan_b,
                        'satuan_k' => $request->satuan_k,
                        'hargafaktur' => $request->hargafaktur,
                        'harga_beli_b' => $request->hargaasli,
                        'harga_beli_k' => $hargabelisatuankecil,
                        'subtotal' => $subtotal,
                        'subtotalfix' => $subtotalfix,
                    ]
                );

                if($request->jumlahorder - $totalbarangdatang === 0){
                    $update = OrderPembelian_r::where('noorder', $request->noorder)->where('kdbarang', $request->kdbarang)->first();
                    $update->flaging = 1;
                    $update->save();
                }

                $cekflagingrinci = OrderPembelian_r::where('noorder', $request->noorder)->where('flaging','!=', '1')->count();
                if($cekflagingrinci === 0)
                {
                    $updateorderheder = OrderPembelian_h::where('noorder', $request->noorder)->first();
                    $updateorderheder->flaging = 2;
                    $updateorderheder->save();
                }


            DB::commit();
            $hasil = self::getlistpenerimaanhasil($nopenerimaan);
                return new JsonResponse([
                    'message' => 'Data Tersimpan',
                    'result' => $hasil
                ]);

        }catch (\Exception $e){
            DB::rollBack();
            return new JsonResponse(['message' => 'ada kesalahan', 'error' => $e], 500);
        }
    }

    public static function getlistpenerimaanhasil($nopenerimaan)
    {
        $list = Penerimaan_h::with(
            [
                'suplier',
                'rinci'
                => function($rinci){
                    $rinci->with(['mbarang']);
                }
            ]
        )
        ->where('nopenerimaan', $nopenerimaan)
        ->orderBy('id', 'desc')
        ->get();
        return $list;
    }

    public function getList()
    {
        $list = Penerimaan_h::with(
            [
                'rinci' => function($rincipenerimaan){
                    $rincipenerimaan->with(['mbarang']);
                },
                'suplier',
                'orderheder',
                'orderheder.rinci' => function($rinci){
                    $rinci->select('*','jumlahpo as jumlahpox','hargapo as hargafix',DB::raw('(jumlahpo*hargapo) as subtotal'))
                    ->with(['mbarang']);
                },
            ]
        )
        ->simplePaginate(request('per_page'));
        return new JsonResponse($list);
    }

    public function hapus(Request $request)
    {
        $cekorderanrinci = OrderPembelian_r::where('noorder', $request->noorder)->where('kdbarang', $request->kdbarang)->first();
        if($cekorderanrinci->flaging === '1'){
            $cekorderanrinci->flaging = '';
            $cekorderanrinci->save();
        }

        $cekorderanheder = OrderPembelian_h::where('noorder', $request->noorder)->first();
        if($cekorderanheder->flaging === '2'){
            $cekorderanheder->flaging = '1';
            $cekorderanheder->save();
        }

        $cek = Penerimaan_r::find($request->id);
        if(!$cek)
        {
            return new JsonResponse(['message' => 'data tidak ditemukan']);
        }

        $hapus = $cek->delete();
        if(!$hapus)
        {
            return new JsonResponse(['message' => 'data gagal dihapus'],500);
        }
        $hasil = self::getlistpenerimaanhasil($request->nopenerimaan);

        return new JsonResponse(
            [
                'message' => 'data berhasil dihapus',
                'result' => $hasil
            ], 200);
    }

    public function kirimstok(Request $request)
    {
        try{
            DB::beginTransaction();
                $rinci = $request->list;
                foreach ($rinci as $key => $value) {
                    stok::create(
                        [
                            'nopenerimaan' => $value['nopenerimaan'],
                            'idpenerimaan' => $value['id'],
                            'kdbarang' => $value['kdbarang'],
                            'jumlah_b' => $value['jumlah_b'],
                            'jumlah_k' => $value['jumlah_k'],
                            'isi' => $value['isi'],
                            'satuan_b' => $value['satuan_b'],
                            'satuan_k' => $value['satuan_k'],
                            'harga_beli_b' => $value['harga_beli_b'],
                            'harga_beli_k' => $value['harga_beli_k'],
                        ]
                    );
                }
                $kuncipenerimaan = Penerimaan_h::where('nopenerimaan', $request->nopenerimaan)->first();
                $kuncipenerimaan->kunci = '1';
                $kuncipenerimaan->save();
            DB::commit();
            $hasil = self::getlistpenerimaanhasil($request->nopenerimaan);
                return new JsonResponse(['message' => 'Data Berhasil Disimpan', 'result' => $hasil], 200);
        }catch (\Exception $e){
            DB::rollBack();
            return new JsonResponse(['message' => 'ada kesalahan', 'error' => $e], 500);
        }
    }
}
