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
use Illuminate\Support\Facades\Auth;
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
                        'nofaktur' => $request->nofaktur,
                        'jenis_pembayaran' => $request->pembayaran,
                        'tgl_faktur' => $request->tgl,
                        'kdsupllier' => $request->kdsuplier,
                        'user' => Auth::id(),
                        'jumlahharitempo' => $request->jumlahharitempo,
                        'tgljatuhtempo' => $request->tgljthtempo,
                    ]
                );
                // return 'wew';
                $jumlah_k = $request->isi*$request->jumlahpo;
                $hargabelisatuankecil = $request->hargaasli/$request->isi;
                $subtotal = $request->jumlahpo*$request->hargafaktur;
                $subtotalfix = $hargabelisatuankecil*$jumlah_k;
                $simpanR = Penerimaan_r::create(
                    [
                        'nopenerimaan' => $nopenerimaan,
                        'noorder' => $request->noorder,
                        'kdbarang' => $request->kdbarang,
                        'motif' => $request->motif,
                        'jumlah_b' => $request->jumlahpo,
                        'jumlah_k' => $jumlah_k,
                        'isi' => $request->isi,
                        'jumlah_rusak_b' => $request->jumlahbarangrusak,
                        'jumlah_datang_b' => $request->jumlah_datang_b,
                        'satuan_b' => $request->satuan_b,
                        'satuan_k' => $request->satuan_k,
                        'hargafaktur' => $request->hargafaktur,
                        'harga_beli_b' => $request->hargaasli,
                        'harga_beli_k' => $hargabelisatuankecil,
                        'subtotal' => $subtotal,
                        'subtotalfix' => $subtotalfix,
                        'user' => Auth::id(),
                    ]
                );

                // if($request->jumlahorder - $totalbarangdatang === 0){
                    $update = OrderPembelian_r::where('noorder', $request->noorder)->where('kdbarang', $request->kdbarang)->first();
                    $update->motif = $request->motif;
                    $update->flaging = $request->flagingx;
                    $update->save();
                // }

                $cekflagingrinci = OrderPembelian_r::where('noorder', $request->noorder)->where('flaging', null)->count();
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
        $list = Penerimaan_h::select('penerimaan_h.*','penerimaan_h.kdsupllier','suppliers.kodesupl','suppliers.nama')
        ->leftJoin('suppliers', 'penerimaan_h.kdsupllier', '=', 'suppliers.kodesupl')
        ->with(
            [
                'rinci' => function($rincipenerimaan){
                    $rincipenerimaan->with(['mbarang']);
                },
                'suplier',
                'orderheder',
                'orderheder.rinci' => function($rinci){
                    $rinci->select('orderpembelian_r.*', 'jumlahpo as jumlahpox', 'hargapo as hargafix',
                        DB::raw('(jumlahpo*hargapo) as subtotal'),
                        DB::raw('p.id as idx'),
                        DB::raw('COALESCE(SUM(p.jumlah_b), 0) as totalditerima'),
                        DB::raw('COALESCE(SUM(p.jumlah_datang_b), 0) as totalditerimabias'),
                        DB::raw('COALESCE(SUM(p.jumlah_rusak_b), 0) as totalbarangrusak'),
                        DB::raw('(jumlahpo - COALESCE(SUM(p.jumlah_b), 0) - COALESCE(SUM(p.jumlah_rusak_b), 0)) as sisajumlahbelumditerimax'),
                        DB::raw('(jumlahpo - COALESCE(SUM(p.jumlah_b), 0) - COALESCE(SUM(p.jumlah_rusak_b), 0)) as sisajumlahbelumditerima'),
                        DB::raw('\'0\' as itemrusak'))
                    ->leftJoin('penerimaan_r as p', function($join) {
                        $join->on('p.kdbarang', '=', 'orderpembelian_r.kdbarang')
                            ->on('p.noorder', '=', 'orderpembelian_r.noorder');
                    })
                    ->with(['mbarang'])
                    ->groupBy('orderpembelian_r.id', 'orderpembelian_r.noorder', 'orderpembelian_r.kdbarang',
                        'orderpembelian_r.jumlahpo', 'orderpembelian_r.satuan_b', 'orderpembelian_r.jumlahpo_k',
                        'orderpembelian_r.satuan_k', 'orderpembelian_r.isi', 'orderpembelian_r.hargapo',
                        'orderpembelian_r.total', 'orderpembelian_r.user', 'orderpembelian_r.flaging',
                        'orderpembelian_r.created_at', 'orderpembelian_r.updated_at');
                },
            ]
        )
        ->where('nopenerimaan', $nopenerimaan)
        ->orderBy('id', 'desc')
        ->get();
        return $list;
    }

    public function getList()
    {
        $from = request('from').' 00:00:00';
        $to = request('to').' 23:59:59';

        $list = Penerimaan_h::select('penerimaan_h.*','penerimaan_h.kdsupllier','suppliers.kodesupl','suppliers.nama')
        ->leftJoin('suppliers', 'penerimaan_h.kdsupllier', '=', 'suppliers.kodesupl')
        ->with(
            [
                'rinci' => function($rincipenerimaan){
                    $rincipenerimaan->with(['mbarang']);
                },
                'suplier',
                'orderheder',
                'orderheder.rinci' => function($rinci){
                    $rinci->select('orderpembelian_r.*', 'jumlahpo as jumlahpox', 'hargapo as hargafix',
                        DB::raw('(jumlahpo*hargapo) as subtotal'),
                        DB::raw('p.id as idx'),
                        DB::raw('COALESCE(SUM(p.jumlah_b), 0) as totalditerima'),
                        DB::raw('COALESCE(SUM(p.jumlah_datang_b), 0) as totalditerimabias'),
                        DB::raw('COALESCE(SUM(p.jumlah_rusak_b), 0) as totalbarangrusak'),
                        DB::raw('(jumlahpo - COALESCE(SUM(p.jumlah_b), 0) - COALESCE(SUM(p.jumlah_rusak_b), 0)) as sisajumlahbelumditerimax'),
                        DB::raw('(jumlahpo - COALESCE(SUM(p.jumlah_b), 0) - COALESCE(SUM(p.jumlah_rusak_b), 0)) as sisajumlahbelumditerima'),
                        DB::raw('\'0\' as itemrusak'))
                    ->leftJoin('penerimaan_r as p', function($join) {
                        $join->on('p.kdbarang', '=', 'orderpembelian_r.kdbarang')
                            ->on('p.noorder', '=', 'orderpembelian_r.noorder');
                    })
                    ->with(['mbarang'])
                    ->groupBy('orderpembelian_r.id', 'orderpembelian_r.noorder', 'orderpembelian_r.kdbarang',
                        'orderpembelian_r.jumlahpo', 'orderpembelian_r.satuan_b', 'orderpembelian_r.jumlahpo_k',
                        'orderpembelian_r.satuan_k', 'orderpembelian_r.isi', 'orderpembelian_r.hargapo',
                        'orderpembelian_r.total', 'orderpembelian_r.user', 'orderpembelian_r.flaging',
                        'orderpembelian_r.created_at', 'orderpembelian_r.updated_at');
                },
            ]
        )->whereBetween('penerimaan_h.created_at', [
            $from,
            $to
        ])
        ->when(request('q'), function ($query) {
            $query->where(function($q) {
                $q->where('penerimaan_h.noorder', 'like', '%' . request('q') . '%')
                  ->orWhere('suppliers.nama', 'like', '%' . request('q') . '%')
                  ->orWhere('penerimaan_h.nopenerimaan', 'like', '%' . request('q') . '%');
            });
        })
        ->orderBy('id', 'desc')
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
      //  return $cek->count();

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
                             'motif' => $value['motif'],
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

    public function hapusall(Request $request)
    {
        $cek = Penerimaan_h::where('nopenerimaan', $request->nopenerimaan)->where('kunci', '1')->count();
        if($cek > 0){
            return new JsonResponse(['message' => 'Maaf Data ini Sudah Dikunci...!!!'], 500);
        }else{
            try {
                DB::beginTransaction();

                // Hapus rincian penerimaan
                Penerimaan_r::where('nopenerimaan', $request->nopenerimaan)->delete();
                OrderPembelian_r::where('noorder', $request->noorder)->update(['flaging' => null]);
                OrderPembelian_h::where('noorder', $request->noorder)->update(['flaging' => '1']);

                // Hapus header penerimaan
                Penerimaan_h::where('nopenerimaan', $request->nopenerimaan)->delete();

                DB::commit();
                $hasil = self::getlistpenerimaanhasilbytgl($request->nopenerimaan,$request->from, $request->to,$request->q,$request->per_page);
                return new JsonResponse(['message' => 'Data berhasil dihapus','result' => $hasil], 200);
            } catch (\Exception $e) {
                DB::rollBack();
                return new JsonResponse(['message' => 'Gagal menghapus data', 'error' => $e->getMessage()], 500);
            }
        }

    }

    public static function getlistpenerimaanhasilbytgl($fromx,$tox,$qx)
    {
        $q = $qx === null ? '' : $qx;
        $from = $fromx.' 00:00:00';
        $to = $tox.' 23:59:59';
        $per_page = 15;
        $list = Penerimaan_h::select('penerimaan_h.*','penerimaan_h.kdsupllier','suppliers.kodesupl','suppliers.nama')
        ->leftJoin('suppliers', 'penerimaan_h.kdsupllier', '=', 'suppliers.kodesupl')
        ->with(
            [
                'rinci' => function($rincipenerimaan){
                    $rincipenerimaan->with(['mbarang']);
                },
                'suplier',
                'orderheder',
                'orderheder.rinci' => function($rinci){
                    $rinci->select('orderpembelian_r.*', 'jumlahpo as jumlahpox', 'hargapo as hargafix',
                        DB::raw('(jumlahpo*hargapo) as subtotal'),
                        DB::raw('p.id as idx'),
                        DB::raw('COALESCE(SUM(p.jumlah_b), 0) as totalditerima'),
                        DB::raw('COALESCE(SUM(p.jumlah_datang_b), 0) as totalditerimabias'),
                        DB::raw('COALESCE(SUM(p.jumlah_rusak_b), 0) as totalbarangrusak'),
                        DB::raw('(jumlahpo - COALESCE(SUM(p.jumlah_b), 0) - COALESCE(SUM(p.jumlah_rusak_b), 0)) as sisajumlahbelumditerimax'),
                        DB::raw('(jumlahpo - COALESCE(SUM(p.jumlah_b), 0) - COALESCE(SUM(p.jumlah_rusak_b), 0)) as sisajumlahbelumditerima'),
                        DB::raw('\'0\' as itemrusak'))
                    ->leftJoin('penerimaan_r as p', function($join) {
                        $join->on('p.kdbarang', '=', 'orderpembelian_r.kdbarang')
                            ->on('p.noorder', '=', 'orderpembelian_r.noorder');
                    })
                    ->with(['mbarang'])
                    ->groupBy('orderpembelian_r.id', 'orderpembelian_r.noorder', 'orderpembelian_r.kdbarang',
                        'orderpembelian_r.jumlahpo', 'orderpembelian_r.satuan_b', 'orderpembelian_r.jumlahpo_k',
                        'orderpembelian_r.satuan_k', 'orderpembelian_r.isi', 'orderpembelian_r.hargapo',
                        'orderpembelian_r.total', 'orderpembelian_r.user', 'orderpembelian_r.flaging',
                        'orderpembelian_r.created_at', 'orderpembelian_r.updated_at');
                },
            ]
        )->whereBetween('penerimaan_h.created_at', [
            $from,
            $to
        ])
        ->when($q, function ($query) use($q) {
            $query->where(function($x) use($q){
                $x->where('penerimaan_h.noorder', 'like', '%' . $q. '%')
                  ->orWhere('suppliers.nama', 'like', '%' . $q . '%')
                  ->orWhere('penerimaan_h.nopenerimaan', 'like', '%' . $q . '%');
            });
        })
        ->simplePaginate($per_page);
        return $list;
    }
}
