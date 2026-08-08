<?php

namespace App\Http\Controllers\Api\Laporan;

use App\Http\Controllers\Controller;
use App\Models\OrderPenjualan;
use App\Models\Transaksi\Penjualan\HeaderPenjualan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LaporanPenjualanController extends Controller
{
    public function getData(){
        $awal=request('tglawal', 'Y-m-d');
        $akhir=request('tglakhir', 'Y-m-d');
        $data = HeaderPenjualan::whereBetween('header_penjualans.tgl', [$awal. ' 00:00:00', $akhir. ' 23:59:59'])
        ->leftJoin('pelanggans', 'pelanggans.id', '=', 'header_penjualans.pelanggan_id')
        ->leftJoin('users', 'users.id', '=', 'header_penjualans.sales_id')
        ->leftJoin('detail_retur_penjualans', 'detail_retur_penjualans.no_penjualan', '=', 'header_penjualans.no_penjualan')
        ->leftJoin('detail_penjualan_fifos', 'detail_penjualan_fifos.no_penjualan', '=', 'header_penjualans.no_penjualan')
        ->when(request('sales'), function($x) {
            $x->where('header_penjualans.sales_id', request('sales'));
        })
        ->when(request('jnsbayar'), function($x) {
            $x->where('header_penjualans.flag', request('jnsbayar'));
        })
        ->when(request('q'), function($x) {
            $x->where('header_penjualans.no_penjualan', 'like', '%' . request('q') . '%')
            ->orWhere('header_penjualans.tgl', 'like', '%' . request('q') . '%')
            ->orWhere('pelanggans.nama', 'like', '%' . request('q') . '%')
            ->orWhere('users.nama', 'like', '%' . request('q') . '%');
        })
        ->select(
            'header_penjualans.*',
            'pelanggans.nama as pelanggan',
            'users.nama as namasales',
            'users.jabatan',
             DB::raw('(SELECT COALESCE(SUM(subtotal), 0) FROM detail_retur_penjualans WHERE detail_retur_penjualans.no_penjualan = header_penjualans.no_penjualan) as nilairetur'),
             DB::raw('(SELECT COALESCE(SUM(subtotal), 0) FROM detail_penjualan_fifos WHERE detail_penjualan_fifos.no_penjualan = header_penjualans.no_penjualan) - (SELECT COALESCE(SUM(subtotal), 0) FROM detail_retur_penjualans WHERE detail_retur_penjualans.no_penjualan = header_penjualans.no_penjualan) as totaldenganretur'),
             DB::raw('(SELECT COALESCE(SUM(subtotal), 0) FROM detail_penjualan_fifos WHERE detail_penjualan_fifos.no_penjualan = header_penjualans.no_penjualan)  as totalxx')

        )
        ->with('detailFifo',function($query){
            $query->join('barangs', 'barangs.kodebarang', '=', 'detail_penjualan_fifos.kodebarang')
            ->leftJoin('detail_retur_penjualans', 'detail_retur_penjualans.detail_penjualan_id', '=', 'detail_penjualan_fifos.id')
            ->select(
                'detail_penjualan_fifos.*',
                'barangs.namabarang',
                'barangs.kategori',
                'barangs.satuan_k',
                'barangs.satuan_b',
                'detail_retur_penjualans.jumlah as jumlah_retur',
                'detail_retur_penjualans.subtotal as nilai_retur'
            );
        })
        ->havingRaw('(SELECT COALESCE(SUM(subtotal), 0) FROM detail_penjualan_fifos WHERE detail_penjualan_fifos.no_penjualan = header_penjualans.no_penjualan) - (SELECT COALESCE(SUM(subtotal), 0) FROM detail_retur_penjualans WHERE detail_retur_penjualans.no_penjualan = header_penjualans.no_penjualan) != 0')
        ->groupBy('header_penjualans.no_penjualan')
        ->get();
        return new JsonResponse($data);
        //  return response()->json([
        //     'nilairetur' => $data->sum('nilairetur'),
        //     'kasmasuk' => $data->sum('totaldenganretur'),
        //     'total' => $data->sum('total'),
        //     $data,
        // ]);
    }
}
