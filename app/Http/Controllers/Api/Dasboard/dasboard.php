<?php

namespace App\Http\Controllers\Api\Dasboard;

use App\Http\Controllers\Controller;
use App\Models\Transaksi\Penjualan\HeaderPenjualan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class dasboard extends Controller
{
    public function listpenjualand()
    {
        $thn =(int) date('Y');
        $thnx = date('Y')-1;

        $data = HeaderPenjualan::select(DB::raw('month(header_penjualans.tgl) as bulan'),DB::raw('year(header_penjualans.tgl) as tahun'),DB::raw('SUM(detail_penjualans.subtotal) as subtotal'))
        ->join('detail_penjualans', 'detail_penjualans.no_penjualan', '=', 'header_penjualans.no_penjualan')
        ->groupBy(DB::raw('YEAR(header_penjualans.tgl)'),DB::raw('month(header_penjualans.tgl)'))
        ->whereYear('tgl', $thn)->orWhereYear('tgl', $thnx)->get();
        return new JsonResponse([
            'tahun' =>'Penjualan '. $thn,
            'tahunx' =>'Penjualan '. $thnx,
            'data' => $data,
        ]);
    }

    public function salestrend()
    {
        $thn =(int) date('Y');

        $data = HeaderPenjualan::select(DB::raw('month(header_penjualans.tgl) as bulan'),DB::raw('year(header_penjualans.tgl) as tahun'),DB::raw('SUM(detail_penjualans.subtotal) as subtotal'))
        ->join('detail_penjualans', 'detail_penjualans.no_penjualan', '=', 'header_penjualans.no_penjualan')
        ->groupBy(DB::raw('YEAR(header_penjualans.tgl)'),DB::raw('month(header_penjualans.tgl)'))
        ->whereYear('tgl', $thn)->get();
        return new JsonResponse([
            'tahun' =>'Penjualan '. $thn,
            'data' => $data,
        ]);
    }

    public function fastmove10()
    {
        $thn =(int) date('Y');

        $data = HeaderPenjualan::select('detail_penjualans.kodebarang','barangs.namabarang',DB::raw('SUM(detail_penjualans.jumlah) as jumlahbarang'))
        ->join('detail_penjualans', 'detail_penjualans.no_penjualan', '=', 'header_penjualans.no_penjualan')
        ->join('barangs', 'barangs.kodebarang', '=', 'detail_penjualans.kodebarang')
        ->groupBy(DB::raw('YEAR(header_penjualans.tgl)'),'detail_penjualans.kodebarang')
        ->orderBy('jumlahbarang', 'desc')
        ->limit(10)
        ->whereYear('tgl', $thn)->get();
        return new JsonResponse([
            'tahun' =>'Penjualan '. $thn,
            'data' => $data,
        ]);
    }
}
