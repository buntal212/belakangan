<?php

namespace App\Http\Controllers\Api\Laporan;

use App\Http\Controllers\Controller;
use App\Models\Transaksi\Penjualan\HeaderPenjualan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LaporanPenjualanController extends Controller
{
    public function getData()
    {
        $awal = request('tglawal') ?: date('Y-m-01');
        $akhir = request('tglakhir') ?: date('Y-m-d');

        // Subquery retur berstatus selesai ('1') per nota penjualan
        $returPerNota = DB::table('header_retur_penjualans')
            ->where('status', '1')
            ->selectRaw('no_penjualan, SUM(total) as total_retur_selesai, SUM(subtotal) as subtotal_retur_selesai')
            ->groupBy('no_penjualan');

        $query = HeaderPenjualan::whereBetween('header_penjualans.tgl', [$awal . ' 00:00:00', $akhir . ' 23:59:59'])
            ->whereNotIn('header_penjualans.flag', ['6']) // Jangan tampilkan transaksi batal
            ->leftJoin('pelanggans', 'pelanggans.id', '=', 'header_penjualans.pelanggan_id')
            ->leftJoin('users', 'users.id', '=', 'header_penjualans.sales_id')
            ->leftJoinSub($returPerNota, 'retur_selesai', 'retur_selesai.no_penjualan', '=', 'header_penjualans.no_penjualan')
            ->when(request('sales'), function ($q, $sales) {
                $q->where('header_penjualans.sales_id', $sales);
            })
            ->when(request('jnsbayar'), function ($q, $jnsbayar) {
                $q->where('header_penjualans.flag', $jnsbayar);
            })
            ->when(request('q'), function ($q, $search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('header_penjualans.no_penjualan', 'like', '%' . $search . '%')
                        ->orWhere('header_penjualans.tgl', 'like', '%' . $search . '%')
                        ->orWhere('pelanggans.nama', 'like', '%' . $search . '%')
                        ->orWhere('users.nama', 'like', '%' . $search . '%');
                });
            })
            ->select([
                'header_penjualans.*',
                'pelanggans.nama as pelanggan',
                'users.nama as namasales',
                'users.jabatan',
                DB::raw('COALESCE(retur_selesai.total_retur_selesai, 0) as nilairetur'),
                DB::raw('GREATEST(COALESCE(header_penjualans.total, 0) - COALESCE(retur_selesai.total_retur_selesai, 0), 0) as totaldenganretur'),
                DB::raw('COALESCE(header_penjualans.total, 0) as totalxx'),
            ])
            ->with([
                'detail' => function ($query) {
                    $returLine = DB::table('detail_retur_penjualans as dr')
                        ->join('header_retur_penjualans as hr', 'hr.id', '=', 'dr.header_retur_penjualan_id')
                        ->where('hr.status', '1')
                        ->selectRaw('dr.detail_penjualan_id, SUM(dr.jumlah) as jumlah_retur, SUM(dr.subtotal) as nilai_retur')
                        ->groupBy('dr.detail_penjualan_id');

                    $query->leftJoin('barangs', 'barangs.kodebarang', '=', 'detail_penjualans.kodebarang')
                        ->leftJoinSub($returLine, 'rl', 'rl.detail_penjualan_id', '=', 'detail_penjualans.id')
                        ->select([
                            'detail_penjualans.*',
                            'barangs.namabarang',
                            'barangs.kategori',
                            'barangs.satuan_k',
                            'barangs.satuan_b',
                            'barangs.isi',
                            DB::raw('COALESCE(rl.jumlah_retur, 0) as jumlah_retur'),
                            DB::raw('COALESCE(rl.nilai_retur, 0) as nilai_retur'),
                        ]);
                },
                'detailFifo' => function ($query) {
                    $query->leftJoin('barangs', 'barangs.kodebarang', '=', 'detail_penjualan_fifos.kodebarang')
                        ->select([
                            'detail_penjualan_fifos.*',
                            'barangs.namabarang',
                            'barangs.kategori',
                            'barangs.satuan_k',
                            'barangs.satuan_b',
                            'barangs.isi',
                            'detail_penjualan_fifos.retur as jumlah_retur',
                        ]);
                },
            ])
            ->orderBy('header_penjualans.tgl', 'desc');

        $data = $query->get();

        return new JsonResponse($data);
    }
}
