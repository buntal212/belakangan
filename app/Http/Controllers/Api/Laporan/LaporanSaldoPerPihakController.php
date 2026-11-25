<?php

namespace App\Http\Controllers\Api\Laporan;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LaporanSaldoPerPihakController extends Controller
{
    public function hutangPerDistributor(Request $request): JsonResponse
    {
        $totalPenerimaan = DB::table('penerimaan_r')
            ->selectRaw('nopenerimaan, SUM(subtotal) as total_hutang')
            ->groupBy('nopenerimaan');
        $pembayaranPerPenerimaan = DB::table('pembayaran_hutang_r')
            ->selectRaw('nopenerimaan, SUM(total) as total_dibayar')
            ->groupBy('nopenerimaan');

        $data = DB::table('penerimaan_h as penerimaan')
            ->leftJoin('suppliers as supplier', 'supplier.kodesupl', '=', 'penerimaan.kdsupllier')
            ->leftJoinSub($totalPenerimaan, 'nilai_hutang', fn ($join) => $join->on('nilai_hutang.nopenerimaan', '=', 'penerimaan.nopenerimaan'))
            ->leftJoinSub($pembayaranPerPenerimaan, 'pembayaran_hutang', fn ($join) => $join->on('pembayaran_hutang.nopenerimaan', '=', 'penerimaan.nopenerimaan'))
            ->whereIn('penerimaan.jenis_pembayaran', ['Hutang', 'Cash Tempo'])
            ->when($request->filled('pihak_id'), fn ($query) => $query->where('penerimaan.kdsupllier', $request->input('pihak_id')))
            ->when($request->filled('q'), function ($query) use ($request) {
                $kataKunci = $request->input('q');
                $query->where(function ($subQuery) use ($kataKunci) {
                    $subQuery->where('penerimaan.nopenerimaan', 'like', "%{$kataKunci}%")
                        ->orWhere('penerimaan.nofaktur', 'like', "%{$kataKunci}%")
                        ->orWhere('supplier.nama', 'like', "%{$kataKunci}%");
                });
            })
            ->whereRaw('ROUND(COALESCE(nilai_hutang.total_hutang, 0) - COALESCE(pembayaran_hutang.total_dibayar, 0), 2) >= 0.01')
            ->selectRaw("penerimaan.nopenerimaan as nomor, penerimaan.nofaktur, penerimaan.tgl_faktur as tanggal, penerimaan.tgljatuhtempo as jatuh_tempo, penerimaan.jenis_pembayaran, penerimaan.kdsupllier as pihak_id, COALESCE(supplier.nama, penerimaan.kdsupllier, 'Distributor tidak terdaftar') as pihak, COALESCE(nilai_hutang.total_hutang, 0) as total_tagihan, COALESCE(pembayaran_hutang.total_dibayar, 0) as total_dibayar, 0 as total_retur, GREATEST(COALESCE(nilai_hutang.total_hutang, 0) - COALESCE(pembayaran_hutang.total_dibayar, 0), 0) as sisa")
            ->orderBy('pihak')
            ->orderBy('penerimaan.tgl_faktur')
            ->get();

        return $this->response($data);
    }

    public function piutangPerPelanggan(Request $request): JsonResponse
    {
        $cicilanPerNota = DB::table('pembayaran_cicilans')
            ->selectRaw('no_penjualan, SUM(jumlah) as total_cicilan')
            ->groupBy('no_penjualan');
        $returPerNota = DB::table('header_retur_penjualans')
            ->where('status', '1')
            ->selectRaw('no_penjualan, SUM(total) as total_retur')
            ->groupBy('no_penjualan');

        $data = DB::table('header_penjualans as penjualan')
            ->leftJoin('pelanggans as pelanggan', 'pelanggan.id', '=', 'penjualan.pelanggan_id')
            ->leftJoinSub($cicilanPerNota, 'cicilan', fn ($join) => $join->on('cicilan.no_penjualan', '=', 'penjualan.no_penjualan'))
            ->leftJoinSub($returPerNota, 'retur', fn ($join) => $join->on('retur.no_penjualan', '=', 'penjualan.no_penjualan'))
            ->whereIn('penjualan.flag', ['2', '3', '7', '8'])
            ->when($request->filled('pihak_id'), function ($query) use ($request) {
                $pelangganId = $request->input('pihak_id');

                if ((string) $pelangganId === '0') {
                    $query->whereNull('penjualan.pelanggan_id');
                    return;
                }

                $query->where('penjualan.pelanggan_id', $pelangganId);
            })
            ->when($request->filled('q'), function ($query) use ($request) {
                $kataKunci = $request->input('q');
                $query->where(function ($subQuery) use ($kataKunci) {
                    $subQuery->where('penjualan.no_penjualan', 'like', "%{$kataKunci}%")
                        ->orWhere('pelanggan.nama', 'like', "%{$kataKunci}%");
                });
            })
            ->whereRaw('ROUND(COALESCE(penjualan.total, 0) - COALESCE(penjualan.bayar, 0) - COALESCE(cicilan.total_cicilan, 0) - COALESCE(retur.total_retur, 0), 2) >= 0.01')
            ->selectRaw("penjualan.no_penjualan as nomor, penjualan.tgl as tanggal, penjualan.tempo as jatuh_tempo, penjualan.flag as jenis_pembayaran, penjualan.pelanggan_id as pihak_id, COALESCE(pelanggan.nama, 'Pelanggan umum') as pihak, COALESCE(penjualan.total, 0) as total_tagihan, COALESCE(penjualan.bayar, 0) + COALESCE(cicilan.total_cicilan, 0) as total_dibayar, COALESCE(retur.total_retur, 0) as total_retur, GREATEST(COALESCE(penjualan.total, 0) - COALESCE(penjualan.bayar, 0) - COALESCE(cicilan.total_cicilan, 0) - COALESCE(retur.total_retur, 0), 0) as sisa")
            ->orderBy('pihak')
            ->orderBy('penjualan.tgl')
            ->get();

        return $this->response($data);
    }

    private function response($data): JsonResponse
    {
        $ringkasan = $data
            ->groupBy('pihak_id')
            ->map(function ($items) {
                $pertama = $items->first();

                return [
                    'pihak_id' => $pertama->pihak_id,
                    'pihak' => $pertama->pihak,
                    'jumlah_transaksi' => $items->count(),
                    'total_tagihan' => (float) $items->sum('total_tagihan'),
                    'total_dibayar' => (float) $items->sum('total_dibayar'),
                    'total_retur' => (float) $items->sum('total_retur'),
                    'total_sisa' => (float) $items->sum('sisa'),
                ];
            })
            ->sortByDesc('total_sisa')
            ->values();

        return new JsonResponse([
            'data' => $data,
            'ringkasan' => $ringkasan,
            'total_sisa' => (float) $data->sum('sisa'),
            'total_transaksi' => $data->count(),
        ]);
    }
}
