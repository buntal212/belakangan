<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Barang;
use App\Models\Pelanggan;
use App\Models\Transaksi\Pembayaranhutang\pembayaranhutang_h;
use App\Models\Transaksi\Penerimaan\Penerimaan_h;
use App\Models\Transaksi\Penjualan\HeaderCicilan;
use App\Models\Transaksi\Penjualan\HeaderPenjualan;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(): JsonResponse
    {
        $year = now()->year;
        $previousYear = $year - 1;

        $validSales = HeaderPenjualan::query()
            ->whereNotNull('flag')
            ->where('flag', '!=', '6');

        $monthlySales = (clone $validSales)
            ->whereYear('tgl', '>=', $previousYear)
            ->selectRaw('YEAR(tgl) as tahun, MONTH(tgl) as bulan, SUM(total) as subtotal')
            ->groupByRaw('YEAR(tgl), MONTH(tgl)')
            ->orderByRaw('YEAR(tgl), MONTH(tgl)')
            ->get();

        $topProducts = DB::table('detail_penjualans as detail')
            ->join('header_penjualans as header', 'header.no_penjualan', '=', 'detail.no_penjualan')
            ->leftJoin('barangs as barang', 'barang.kodebarang', '=', 'detail.kodebarang')
            ->whereNotNull('header.flag')
            ->where('header.flag', '!=', '6')
            ->whereYear('header.tgl', $year)
            ->selectRaw('detail.kodebarang, COALESCE(barang.namabarang, detail.kodebarang) as namabarang, SUM(detail.jumlah) as jumlahbarang')
            ->groupBy('detail.kodebarang', 'barang.namabarang')
            ->orderByDesc('jumlahbarang')
            ->limit(10)
            ->get();

        $statusLabels = [
            '1' => 'Pesanan',
            '2' => 'Belum Dicicil',
            '3' => 'Proses Cicilan',
            '4' => 'Dibawa Sales',
            '5' => 'Lunas',
            '6' => 'Batal',
        ];

        $statusDistribution = HeaderPenjualan::query()
            ->whereNotNull('flag')
            ->whereYear('tgl', $year)
            ->selectRaw('flag, COUNT(*) as total')
            ->groupBy('flag')
            ->get();

        $statusDistribution = $statusDistribution
            ->map(fn ($item) => [
                'status' => $statusLabels[(string) $item->flag] ?? 'Status Lain',
                'total' => (int) $item->total,
            ])
            ->values();

        $recentSales = HeaderPenjualan::query()
            ->with('pelanggan:id,nama')
            ->whereNotNull('flag')
            ->latest('tgl')
            ->limit(5)
            ->get(['id', 'no_penjualan', 'pelanggan_id', 'tgl', 'total', 'flag'])
            ->map(fn ($sale) => [
                'nomor' => $sale->no_penjualan,
                'pihak' => $sale->pelanggan?->nama ?? 'Umum',
                'keterangan' => $statusLabels[(string) $sale->flag] ?? 'Status Lain',
                'noPenjualan' => $sale->no_penjualan,
                'tanggal' => $sale->tgl,
                'pelanggan' => $sale->pelanggan?->nama ?? 'Umum',
                'total' => (float) $sale->total,
                'status' => $statusLabels[(string) $sale->flag] ?? 'Status Lain',
                'flag' => (string) $sale->flag,
            ]);

        $recentPurchases = Penerimaan_h::query()
            ->with(['suplier:kodesupl,nama', 'rinci:id,nopenerimaan,subtotalfix'])
            ->latest('tgl_faktur')
            ->latest('id')
            ->limit(5)
            ->get(['id', 'nopenerimaan', 'nofaktur', 'tgl_faktur', 'kdsupllier', 'jenis_pembayaran'])
            ->map(fn ($purchase) => [
                'nomor' => $purchase->nopenerimaan,
                'tanggal' => $purchase->tgl_faktur,
                'pihak' => $purchase->suplier?->nama ?? $purchase->kdsupllier,
                'total' => (float) $purchase->rinci->sum('subtotalfix'),
                'keterangan' => $purchase->jenis_pembayaran ?? 'Pembelian',
            ]);

        $recentDebtPayments = pembayaranhutang_h::query()
            ->with(['supplier:kodesupl,nama', 'rinci:id,notrans,total'])
            ->latest('tgl_bayar')
            ->latest('id')
            ->limit(5)
            ->get(['id', 'notrans', 'tgl_bayar', 'kdsupllier', 'cara_bayar'])
            ->map(fn ($payment) => [
                'nomor' => $payment->notrans,
                'tanggal' => $payment->tgl_bayar,
                'pihak' => $payment->supplier?->nama ?? $payment->kdsupllier,
                'total' => (float) $payment->rinci->sum('total'),
                'keterangan' => $payment->cara_bayar ?? 'Pembayaran Hutang',
            ]);

        $recentReceivablePayments = HeaderCicilan::query()
            ->with('pelanggan:id,nama')
            ->whereNotNull('nopembayaran')
            ->latest('tgl_bayar')
            ->latest('id')
            ->limit(5)
            ->get(['id', 'nopembayaran', 'tgl_bayar', 'pelanggan_id', 'jumlah', 'cara_bayar'])
            ->map(fn ($payment) => [
                'nomor' => $payment->nopembayaran,
                'tanggal' => $payment->tgl_bayar,
                'pihak' => $payment->pelanggan?->nama ?? 'Umum',
                'total' => (float) $payment->jumlah,
                'keterangan' => $payment->cara_bayar ?? 'Pembayaran Piutang',
            ]);

        return new JsonResponse([
            'tahun' => $year,
            'tahunSebelumnya' => $previousYear,
            'summary' => [
                'totalPendapatan' => (float) (clone $validSales)->whereYear('tgl', $year)->sum('total'),
                'totalPenjualan' => (clone $validSales)->whereYear('tgl', $year)->count(),
                'totalProduk' => Barang::count(),
                'totalPelanggan' => Pelanggan::count(),
            ],
            'monthlySales' => $monthlySales,
            'topProducts' => $topProducts,
            'statusDistribution' => $statusDistribution,
            'recentSales' => $recentSales,
            'recentPurchases' => $recentPurchases,
            'recentDebtPayments' => $recentDebtPayments,
            'recentReceivablePayments' => $recentReceivablePayments,
        ]);
    }
}
