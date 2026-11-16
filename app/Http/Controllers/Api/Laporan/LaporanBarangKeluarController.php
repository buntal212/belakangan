<?php

namespace App\Http\Controllers\Api\Laporan;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LaporanBarangKeluarController extends Controller
{
    /**
     * Menampilkan barang yang benar-benar keluar dari stok melalui proses FIFO
     * penjualan. Draft dan nota yang dibatalkan tidak ikut ditampilkan.
     */
    public function getData(Request $request): JsonResponse
    {
        $filter = $request->validate([
            'tglawal' => ['nullable', 'date'],
            'tglakhir' => ['nullable', 'date', 'after_or_equal:tglawal'],
            'q' => ['nullable', 'string', 'max:100'],
            'merk' => ['nullable', 'string', 'max:100'],
        ]);

        $awal = $filter['tglawal'] ?? now()->startOfMonth()->toDateString();
        $akhir = $filter['tglakhir'] ?? now()->toDateString();

        $data = DB::table('detail_penjualan_fifos as fifo')
            ->join('header_penjualans as penjualan', 'penjualan.no_penjualan', '=', 'fifo.no_penjualan')
            ->leftJoin('barangs as barang', 'barang.kodebarang', '=', 'fifo.kodebarang')
            ->leftJoin('stoks as stok', 'stok.id', '=', 'fifo.stok_id')
            ->leftJoin('pelanggans as pelanggan', 'pelanggan.id', '=', 'penjualan.pelanggan_id')
            ->leftJoin('users as sales', 'sales.id', '=', 'penjualan.sales_id')
            ->whereNotNull('penjualan.flag')
            ->where('penjualan.flag', '<>', '6')
            ->whereBetween('fifo.created_at', [
                $awal . ' 00:00:00',
                $akhir . ' 23:59:59',
            ])
            ->when($filter['merk'] ?? null, function ($query, $merk) {
                $query->where('barang.merk', $merk);
            })
            ->when($filter['q'] ?? null, function ($query, $kataKunci) {
                $query->where(function ($subQuery) use ($kataKunci) {
                    $subQuery->where('fifo.no_penjualan', 'like', "%{$kataKunci}%")
                        ->orWhere('fifo.kodebarang', 'like', "%{$kataKunci}%")
                        ->orWhere('barang.namabarang', 'like', "%{$kataKunci}%")
                        ->orWhere('pelanggan.nama', 'like', "%{$kataKunci}%");
                });
            })
            ->select([
                DB::raw('DATE(fifo.created_at) as tanggal'),
                'fifo.no_penjualan',
                'fifo.kodebarang',
                'barang.namabarang',
                DB::raw("COALESCE(barang.merk, '-') as merk"),
                DB::raw("COALESCE(stok.motif, '-') as motif"),
                DB::raw('COALESCE(barang.isi, 1) as isi'),
                DB::raw("COALESCE(barang.satuan_b, 'DUS') as satuan_b"),
                DB::raw("COALESCE(barang.satuan_k, 'PCS') as satuan_k"),
                DB::raw("COALESCE(pelanggan.nama, '-') as pelanggan"),
                DB::raw("COALESCE(sales.nama, '-') as sales"),
                DB::raw('SUM(fifo.jumlah) as jumlah_keluar'),
            ])
            ->groupBy([
                DB::raw('DATE(fifo.created_at)'),
                'fifo.no_penjualan',
                'fifo.kodebarang',
                'barang.namabarang',
                'barang.merk',
                'stok.motif',
                'barang.isi',
                'barang.satuan_b',
                'barang.satuan_k',
                'pelanggan.nama',
                'sales.nama',
            ])
            ->orderByDesc('tanggal')
            ->orderBy('fifo.no_penjualan')
            ->get();

        return new JsonResponse(['data' => $data]);
    }
}
