<?php

namespace App\Http\Controllers\Api\Transaksi\PembayaranPiutang;

use App\Helpers\FormatingHelper;
use App\Http\Controllers\Controller;
use App\Models\Transaksi\Penjualan\HeaderCicilan;
use App\Models\Transaksi\Penjualan\HeaderPenjualan;
use App\Models\Transaksi\Penjualan\PembayaranCicilan;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PembayaranPiutangController extends Controller
{
    public function index()
    {
        $from = request('from').' 00:00:00';
        $to = request('to').' 23:59:59';
        $data = HeaderCicilan::with(
            [
                'pelanggan',
                'sales',
                'cicilan'
            ]
        )
        ->whereBetween('tgl_bayar', [
            $from,
            $to
        ])
        ->when(request('q'), function ($query) {
            $query->where(function($q) {
                $q->where('header_cicilans.nopembayaran', 'like', '%' . request('q') . '%');
            });
        })
        ->whereNotNull('nopembayaran')
        ->orderBy('id', 'desc')
        ->simplePaginate(request('per_page'));
        return new JsonResponse($data);
    }

    public function listpiutang()
    {

        $data = HeaderPenjualan::with([
            'pelanggan',
            'sales',
            'keterangan',
            'detail' => function ($q) {
                $q->with(['masterBarang']);
            },
            'cicilan' => function ($q) {
                    $q->select('no_penjualan', DB::raw('sum(jumlah) as jumlah'))->groupBy('no_penjualan');
            },
            'headerRetur' => function ($q) {
                $q->leftjoin('detail_retur_penjualans', 'detail_retur_penjualans.header_retur_penjualan_id', '=', 'header_retur_penjualans.id')
                ->select('header_retur_penjualans.no_penjualan', DB::raw('sum(detail_retur_penjualans.subtotal) as subtotal'))
                ->where('status', '!=', '')
                ->groupBy('header_retur_penjualans.no_penjualan');
            },
        ])
        ->whereIn('flag', ['2', '3', '7']);
        if(request('pelanggan_id') !== '0'){
            $data = $data->where('pelanggan_id', request('pelanggan_id'));
        }else{
            $data = $data->whereNull('pelanggan_id');
        }
        $data = $data->orderBy('id', 'desc');
        $data = $data->get();
        $data = self::onlyOutstanding($data);
        return new JsonResponse($data);
    }

    public function simpan(Request $request)
    {
        if($request->nopembayaran === '' || $request->nopembayaran === null)
        {
            DB::select('call noPembayaranPiutang(@nomor)');
            $x = DB::table('counter')->select('nopembayaranpiutang')->get();
            $no = $x[0]->nopembayaranpiutang;
            $nopembayaran = FormatingHelper::nopembayaranhutang($no, 'BP');
        }else{
            $nopembayaran = $request->nopembayaran;
        }

        $tglsekarang = Carbon::now();
        $tglbayar = Carbon::parse($request->tgl);

        $jumlahhari = $tglsekarang->diffInDays($tglbayar);
        if($jumlahhari > 7){
            return new JsonResponse(['message' => 'Maaf Tidak Dapat Menambah Data...,Tanggal Bayar Lebih Dari 7 Hari...!!!'], 500);
        }

        try {
            DB::beginTransaction();
            $data = HeaderCicilan::updateOrCreate(
                [
                    'nopembayaran' => $nopembayaran,
                ],
                [
                    'pelanggan_id' => $request->pelanggan_id,
                    'tgl_bayar' => $request->tgl,
                    'cara_bayar' => $request->carabayar,
                    'keterangan' => $request->keterangan,
                    'user' => Auth::id(),
            ]);


            $datax = PembayaranCicilan::create(
                [
                    'no_penjualan' => $request->nopenjualan,
                    'header_ciclan_id' => $data->id,
                    'jumlah' => $request->total,
                     'tgl_bayar' => $request->tgl,
                    'user' => Auth::id(),
                ]
            );

            // $flagpenerimaan = Penerimaan_h::where('nopenerimaan', $request->nopenerimaan)->first();
            // $flagpenerimaan->flagingHutang = '1';
            // $flagpenerimaan->save();
            $hasil = self::getlistpembayaranbynotrans($nopembayaran);
            $newhutang = self::listhutangx($request->pelanggan_id);

            DB::commit();
            // return new JsonResponse([
            //     'message' => 'Data Berhasil Disimpan'
            // ]);

            return new JsonResponse([
                'message' => 'Data Berhasil Disimpan',
                'data' => $hasil,
                'nopenjualan' => $request->nopenjualan,
                'newhutang' => $newhutang
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

    public static function getlistpembayaranbynotrans($notrans)
    {
       $data = HeaderCicilan::with(
            [
                'cicilan' ,
                'pelanggan'
            ]
        )->where('nopembayaran', $notrans)
        ->get();
        return $data;
    }

    public static function listhutangx($pelanggan)
    {
        $data = HeaderPenjualan::with([
            'pelanggan',
            'sales',
            'detail' => function ($q) {
                $q->with(['masterBarang']);
            },
            'cicilan' => function ($q) {
                    $q->select('no_penjualan', DB::raw('sum(jumlah) as jumlah'))->groupBy('no_penjualan');
                },
            'headerRetur' => function ($q) {
                $q->leftjoin('detail_retur_penjualans', 'detail_retur_penjualans.header_retur_penjualan_id', '=', 'header_retur_penjualans.id')
                    ->select('header_retur_penjualans.no_penjualan', DB::raw('sum(detail_retur_penjualans.subtotal) as subtotal'))
                    ->where('status', '!=', '')
                    ->groupBy('header_retur_penjualans.no_penjualan');
            },
            ])
        ->whereIn('flag', ['2', '3', '7'])
        ->where('pelanggan_id', $pelanggan)
        ->orderBy('id', 'desc')
        ->get();
        return self::onlyOutstanding($data);
    }

    private static function onlyOutstanding($sales)
    {
        return $sales->filter(function ($sale) {
            $totalNota = (float) $sale->detail->sum('subtotal');
            $totalTerbayar = (float) $sale->cicilan->sum('jumlah');
            $totalRetur = (float) ($sale->headerRetur->first()?->subtotal ?? 0);
            $sisaPiutang = round($totalNota - $totalRetur - (float) $sale->bayar - $totalTerbayar, 2);

            return $sisaPiutang >= 0.01;
        })->values();
    }

    public function hapusrincian(Request $request)
    {
        $cek = PembayaranCicilan::leftjoin('header_cicilans', 'header_cicilans.id', '=', 'pembayaran_cicilans.header_ciclan_id')
        ->where('pembayaran_cicilans.id', $request->id)
        ->select('header_cicilans.tgl_bayar', 'header_cicilans.cara_bayar', 'header_cicilans.keterangan')
        ->first();
        $tglsekarang = Carbon::now();
        $tglbayar = Carbon::parse($cek->tgl_bayar);

        $jumlahhari = $tglsekarang->diffInDays($tglbayar);
        if($jumlahhari > 7){
            return new JsonResponse(['message' => 'Maaf Tidak Dapat Menghapus Data,Tanggal Bayar Lebih Dari 7 Hari...!!!'], 500);
        }
        if ($cek->cara_bayar === 'Penagihan Sales' || $cek->cara_bayar=== ''){
            return new JsonResponse(['message' => 'Maaf Tidak Dapat Menghapus Data...,Karena Tranbsaksi ini melalaui Penagihan Sales...!!!'], 500);
        }
        if($cek->keterangan === null){
            return new JsonResponse(['message' => 'Maaf Tidak Dapat Menghapus Data...,Karena Tranbsaksi ini melalaui Penagihan Sales...!!!'], 500);
        }

        // return $jumlahhari;
        //if($cek->tgl_bayar === date('Y-m-d H:i:s')){

        $nopembayaran = $request->notrans;
        try {
            DB::beginTransaction();
                $data = PembayaranCicilan::where('id', request('id'))->delete();
                $hasil = self::getlistpembayaranbynotrans($nopembayaran);
            DB::commit();
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
