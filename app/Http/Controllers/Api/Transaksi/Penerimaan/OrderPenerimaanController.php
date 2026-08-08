<?php

namespace App\Http\Controllers\Api\Transaksi\Penerimaan;

use App\Helpers\FormatingHelper;
use App\Http\Controllers\Controller;
use App\Models\Transaksi\Penerimaan\OrderPembelian_h;
use App\Models\Transaksi\Penerimaan\OrderPembelian_r;
use App\Models\Transaksi\Penerimaan\Penerimaan_h;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
                    'kdsuplier' => $request->kdsuplier,
                    'user' => Auth::id(),
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
                    'user' => Auth::id(),
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
        $from = request('from').' 00:00:00';
        $to = request('to').' 23:59:59';

        $list = OrderPembelian_h::select('orderpembelian_h.*','orderpembelian_h.kdsuplier','suppliers.kodesupl','suppliers.nama')
        ->leftJoin('suppliers', 'orderpembelian_h.kdsuplier', '=', 'suppliers.kodesupl')
        ->with([
            'suplier',
            'rinci' => function($rinci){
                $rinci->select('*', DB::raw('(jumlahpo*hargapo) as subtotal'))
                ->with(['mbarang']);
            }
        ])
        ->whereBetween('orderpembelian_h.tglorder', [
            $from,
            $to
        ])
        ->when(request('q'), function ($query) {
            $query->where(function($q) {
                $q->where('orderpembelian_h.noorder', 'like', '%' . request('q') . '%')
                  ->orWhere('suppliers.nama', 'like', '%' . request('q') . '%');
            });
        })
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
                }

            ]
        )->
        where('flaging', '1')
        ->get();

        return new JsonResponse($data);
    }

    public function hapusall(Request $request)
    {
        try {
            DB::beginTransaction();

            // Hapus rincian order pembelian
            OrderPembelian_r::where('noorder', $request->noorder)->delete();

            // Hapus header penerimaan
            Penerimaan_h::where('noorder', $request->noorder)->delete();

            // Hapus header order pembelian
            OrderPembelian_h::where('noorder', $request->noorder)->delete();

            DB::commit();

            $hasil = self::getlistorderhasilbytgl($request->from, $request->to,$request->q,$request->per_page);
            // return $hasil;
            return new JsonResponse(['message' => 'Data berhasil dihapus', 'result' => $hasil], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return new JsonResponse(['message' => 'Gagal menghapus data', 'error' => $e->getMessage()], 500);
        }
    }

     public static function getlistorderhasilbytgl($fromx,$tox,$qx,$per_page)
    {
        $q = $qx === null ? '' : $qx;
        $from = $fromx.' 00:00:00';
        $to = $tox.' 23:59:59';
        $list = OrderPembelian_h::select('orderpembelian_h.*','orderpembelian_h.kdsuplier','suppliers.kodesupl','suppliers.nama')
        ->leftJoin('suppliers', 'orderpembelian_h.kdsuplier', '=', 'suppliers.kodesupl')
        ->with([
            'suplier',
            'rinci' => function($rinci){
                $rinci->select('*', DB::raw('(jumlahpo*hargapo) as subtotal'))
                ->with(['mbarang']);
            }
        ])
        ->whereBetween('orderpembelian_h.tglorder', [
            $from,
            $to
        ])
        ->when($q, function ($query ) use($q)  {
            $query->where(function($x) use($q) {
                $x->where('orderpembelian_h.noorder', 'like', '%' . $q .  '%')
                  ->orWhere('suppliers.nama', 'like', '%' . $q . '%');
            });
        })
        ->simplePaginate($per_page);
        return $list;
    }
}



