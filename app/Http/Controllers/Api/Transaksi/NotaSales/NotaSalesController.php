<?php

namespace App\Http\Controllers\Api\Transaksi\NotaSales;

use App\Http\Controllers\Controller;
use App\Models\Transaksi\NotaSales\notasales_h;
use App\Models\Transaksi\NotaSales\notasales_r;
use App\Models\Transaksi\Penjualan\HeaderCicilan;
use App\Models\Transaksi\Penjualan\HeaderPenjualan;
use App\Models\Transaksi\Penjualan\PembayaranCicilan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class NotaSalesController extends Controller
{
    public function list(){

        $from = request('from');
        $to = request('to');

        $data = notasales_h::select('notasales_h.*','users.nama')
        ->leftJoin('users', 'users.id', '=', 'notasales_h.kdsales')
        ->with([
            'rinci' => function($rinci){
                $rinci->with(
                    [
                        'hederpenjualan' => function($hederpenjualan){
                            $hederpenjualan->with(['pelanggan', 'sales']);
                        }
                    ]);
            }
        ])
        ->whereBetween('tgl', [
            $from,
            $to
        ])
        ->when(request('q'), function ($query) {
            $query->where(function($q) {
                $q->where('notasales_h.notrans', 'like', '%' . request('q') . '%')
                  ->orWhere('users.nama', 'like', '%' . request('q') . '%')
                  ->orWhere('notasales_h.keterangan', 'like', '%' . request('q') . '%');
            });
        })
        ->orderBy('id', 'desc')
        ->simplePaginate(request('per_page'));
        return new JsonResponse($data);
    }

    public static function getlistbynotrans($notrans)
    {
       $data = notasales_h::with(
            [
                'rinci' => function($rinci){
                $rinci->with(
                    [
                        'hederpenjualan' => function($hederpenjualan){
                            $hederpenjualan->with(['pelanggan', 'sales']);
                        }
                    ]);
                 }
            ]
        )->where('notrans', $notrans)
        ->orderBy('id', 'desc')
        ->get();
        return $data;
    }

    public function caripiutang()
    {
        if(request('keterangan') === 'Dipinjam'){
           $data = HeaderPenjualan::with([
            'pelanggan',
            'sales',
            'detail' => function ($q) {
                $q->with(['masterBarang']);
            },
            'cicilan' => function ($q) {
                    $q->select('no_penjualan', DB::raw('sum(jumlah) as jumlah'))->groupBy('no_penjualan');
                }
            ])
            ->leftJoin('detail_retur_penjualans', 'detail_retur_penjualans.no_penjualan', '=', 'header_penjualans.no_penjualan')
            ->leftJoin('detail_penjualans', 'detail_penjualans.no_penjualan', '=', 'header_penjualans.no_penjualan')
            ->select('header_penjualans.*',
                    DB::raw('(SELECT COALESCE(SUM(subtotal), 0) FROM detail_retur_penjualans WHERE detail_retur_penjualans.no_penjualan = header_penjualans.no_penjualan) as nilairetur'),
                    DB::raw('(SELECT COALESCE(SUM(subtotal), 0) FROM detail_penjualans WHERE detail_penjualans.no_penjualan = header_penjualans.no_penjualan) - (SELECT COALESCE(SUM(subtotal), 0) FROM detail_retur_penjualans WHERE detail_retur_penjualans.no_penjualan = header_penjualans.no_penjualan) as total')
                    )
            ->where(function ($q) {
                $q->where('flag_sales', '!=', '1')
                ->orWhereNull('flag_sales');
            })
            ->whereIn('flag', ['2', '3', '7'])
            ->orderBy('tempo', 'asc')
            ->groupBy('header_penjualans.no_penjualan')
            ->havingRaw('(SELECT COALESCE(SUM(subtotal), 0) FROM detail_penjualans WHERE detail_penjualans.no_penjualan = header_penjualans.no_penjualan) - (SELECT COALESCE(SUM(subtotal), 0) FROM detail_retur_penjualans WHERE detail_retur_penjualans.no_penjualan = header_penjualans.no_penjualan) != 0')
            ->get();
            return new JsonResponse($data);
        }else{
            $data = HeaderPenjualan::leftJoin('notasales_r', 'notasales_r.notaPenjualan', '=', 'header_penjualans.no_penjualan')
            ->leftJoin('notasales_h', 'notasales_h.notrans', '=', 'notasales_r.notrans')
            ->leftJoin('detail_retur_penjualans', 'detail_retur_penjualans.no_penjualan', '=', 'header_penjualans.no_penjualan')
            ->leftJoin('detail_penjualans', 'detail_penjualans.no_penjualan', '=', 'header_penjualans.no_penjualan')
            ->select('header_penjualans.*',
                    'notasales_h.*',
                    'notasales_r.*',
                    DB::raw('(SELECT COALESCE(SUM(subtotal), 0) FROM detail_retur_penjualans WHERE detail_retur_penjualans.no_penjualan = header_penjualans.no_penjualan) as nilairetur'),
                    DB::raw('(SELECT COALESCE(SUM(subtotal), 0) FROM detail_penjualans WHERE detail_penjualans.no_penjualan = header_penjualans.no_penjualan) - (SELECT COALESCE(SUM(subtotal), 0) FROM detail_retur_penjualans WHERE detail_retur_penjualans.no_penjualan = header_penjualans.no_penjualan) as total')
                    )
            ->with([
                'pelanggan',
                'sales',
                'detail' => function ($q) {
                    $q->with(['masterBarang']);
                },
                'cicilan' => function ($q) {
                    $q->select('no_penjualan', DB::raw('sum(jumlah) as jumlah'))->groupBy('no_penjualan');
                }
                ])
                ->where(function ($q) {
                    $q->where('flag_sales','1');
                })
                ->where('notasales_h.kdsales', request('kdsales'))
                ->where('kunci', '1')
                ->groupBy('header_penjualans.no_penjualan')
                ->havingRaw('(SELECT COALESCE(SUM(subtotal), 0) FROM detail_penjualans WHERE detail_penjualans.no_penjualan = header_penjualans.no_penjualan) - (SELECT COALESCE(SUM(subtotal), 0) FROM detail_retur_penjualans WHERE detail_retur_penjualans.no_penjualan = header_penjualans.no_penjualan) != 0')
                // ->whereIn('flag', ['2', '3', '7'])
                ->orderBy('tempo', 'asc')
                ->get();
                return new JsonResponse($data);
        }

    }

    public function simpan(Request $request)
    {
        if($request->notrans === '' || $request->notrans === null)
        {
            $notrans = date('YmdHis').'/'.$request->kdsales.'/'.$request->keterangan;
        }else{
            $notrans = $request->notrans;
        }
        try {
            DB::beginTransaction();
                $simpan = notasales_h::updateOrCreate(
                    [
                    'notrans' => $notrans,
                    ],
                    [
                    'keterangan' => $request->keterangan,
                    'kdsales' => $request->kdsales,
                    'user' => Auth::id(),
                    'tgl' => date('Y-m-d H:i:s'),
                ]);

                $simpanr = notasales_r::create(
                    [
                    'notrans' => $notrans,
                    'notaPenjualan' => $request->notaPenjualan,
                    'tgljatuhtempo' => $request->tgljatuhtempo,
                    'lamatempo' => $request->lamatempo,
                    'total' => $request->total,
                    'flagbayar' => $request->bayar,
                    'user' => Auth::id(),
                    'terbayar' => $request->terbayar,
                ]
            );
            if($request->keterangan === 'Dipinjam'){
                $flagpenjualan = HeaderPenjualan::where('no_penjualan', $request->notaPenjualan)->first();
                $flagpenjualan->flag_sales = $request->keterangan === 'Dipinjam' ? '1' : null;
                $flagpenjualan->save();
            }else{
                if($request->yangakandibayar + $request->terbayar > $request->total){
                    return new JsonResponse(['message' => 'Jumlah Yang Dibayar Melebihi Total...!!!'], 500);
                }else{
                    $flagpenjualan = HeaderPenjualan::where('no_penjualan', $request->notaPenjualan)->first();
                    $flagpenjualan->flag_sales = null;
                    $flagpenjualan->save();

                    if($request->bayar === true){
                        $cari = HeaderCicilan::where('nopembayaran', $notrans)->first();
                        if($cari !== null)
                        {
                            PembayaranCicilan::create([
                                'no_penjualan' => $request->notaPenjualan,
                                // 'tgl_bayar' => $cicilan->tgl_bayar,
                                'header_ciclan_id' => $cari->id,
                                'cara_bayar' => $request->carabayarrinci,
                                'keterangan' => $request->keteranganrinci,
                                'jumlah' => $request->yangakandibayar,
                            ]);
                        }else{
                            $cicilan = HeaderCicilan::create([
                                'pelanggan_id' => $request->pelanggan_id,
                                'sales_id' => $request->kdsales,
                                'cara_bayar' => 'Penagihan Sales', // 1 = lewat penagihan sales,keterangan di tabel rincian
                                // 'jumlah' => $request->terbayar,
                                'tgl_bayar' => date('Y-m-d H:i:s'),
                                'nopembayaran' => $notrans,
                            ]);
                            PembayaranCicilan::create([
                                'no_penjualan' => $request->notaPenjualan,
                                // 'tgl_bayar' => $cicilan->tgl_bayar,
                                'header_ciclan_id' => $cicilan->id,
                                'cara_bayar' => $request->carabayarrinci,
                                'keterangan' => $request->keteranganrinci,
                                'jumlah' => $request->yangakandibayar,
                            ]);
                        }
                    }
                    if($request->yangakandibayar + $request->terbayar === $request->total)
                    {
                        $flagpenjualan = HeaderPenjualan::where('no_penjualan', $request->notaPenjualan)->first();
                        $flagpenjualan->flag = '5';
                        $flagpenjualan->save();
                    }
                }
            }


            DB::commit();
            $hasil = self::getlistbynotrans($notrans);
                return new JsonResponse([
                    'message' => 'Data Berhasil Disimpan',
                    'data' => $hasil,
                    'notrans' => $notrans,
                    'notapenjualan' => $request->notaPenjualan
                ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return new JsonResponse([
                'message' => 'Terjadi Kesalahan ' . $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ], 410);
        }
    }

    public function hapusrincian(Request $request)
    {
        $nopembayaran = $request->notrans;
        $cek = notasales_h::where('notrans', $nopembayaran)->where('kunci', '1')->count();
        if($cek > 0){
            return new JsonResponse(['message' => 'Maaf Data ini Sudah Dikunci...!!!'], 500);
        }else{
            try {
                DB::beginTransaction();
                    $cek = notasales_r::where('id', $request->id)->where('flagbayar', '1')->count();
                    if($cek > 0){
                        PembayaranCicilan::select('pembayaran_cicilans.*')
                        ->leftJoin('header_cicilans', 'header_cicilans.id', '=', 'pembayaran_cicilans.header_ciclan_id')
                        ->where('header_cicilans.nopembayaran', $nopembayaran)
                        ->where('pembayaran_cicilans.no_penjualan', $request->notaPenjualan)->delete();

                        $ceklagi = PembayaranCicilan::select('pembayaran_cicilans.*')
                        ->leftJoin('header_cicilans', 'header_cicilans.id', '=', 'pembayaran_cicilans.header_ciclan_id')
                        ->where('header_cicilans.nopembayaran', $nopembayaran)
                        ->count();
                        if($ceklagi === 0){
                            HeaderCicilan::where('nopembayaran', $nopembayaran)->delete();
                        }
                    }
                    $data = notasales_r::where('id', $request->id)->delete();
                    $flagpenjualan = HeaderPenjualan::where('no_penjualan', $request->notaPenjualan)->first();
                    $flagpenjualan->flag_sales = '1';
                    $flagpenjualan->save();

                    $flagpenjualan = HeaderPenjualan::where('no_penjualan', $request->notaPenjualan)->where('flag', '5')->count();
                    if($flagpenjualan > 0){
                        $flagpenjualan = HeaderPenjualan::where('no_penjualan', $request->notaPenjualan)->first();
                        $flagpenjualan->flag = '2';
                        $flagpenjualan->save();
                    }
                DB::commit();
                $hasil = self::getlistbynotrans($nopembayaran);
                return new JsonResponse([
                    'message' => 'Data Berhasil Disimpan',
                    'data' => $hasil
                ]);
            } catch (\Exception $e) {
                DB::rollBack();
                return new JsonResponse([
                    'message' => 'Terjadi Kesalahan ' . $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ], 410);
            }
        }
    }

    public function kunci(Request $request)
    {

            $cari = notasales_h::where('notrans', $request->notrans)->first();
            if($cari->kunci === '1'){
                return new JsonResponse(['message' => 'Maaf Data ini Sudah Dikunci...!!!'], 500);
            }else{
                $cari->kunci = '1';
                $cari->save();
                $hasil = self::getlistbynotrans($request->notrans);

                return new JsonResponse(
                [
                    'message' =>'Data Berhasil Dikunci...!!!',
                    'result' => $hasil
                ], 200);
            }


    }
}
