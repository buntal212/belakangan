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

        // Pendapatan operasional memakai dasar yang sama dengan laba-rugi:
        // subtotal rincian sebelum PPN, dikurangi retur yang disetujui.
        $flagPenjualanValid = ['2', '3', '4', '5', '7', '8'];
        $validSales = HeaderPenjualan::query()->whereIn('flag', $flagPenjualanValid);

        $penjualanBulanan = DB::table('detail_penjualans as detail')
            ->join('header_penjualans as header', 'header.no_penjualan', '=', 'detail.no_penjualan')
            ->whereIn('header.flag', $flagPenjualanValid)
            ->whereYear('header.tgl', '>=', $previousYear)
            ->selectRaw('YEAR(header.tgl) as tahun, MONTH(header.tgl) as bulan, SUM(detail.subtotal) as subtotal')
            ->groupByRaw('YEAR(header.tgl), MONTH(header.tgl)')
            ->get();

        $returBulanan = DB::table('header_retur_penjualans as header_retur')
            ->join('detail_retur_penjualans as detail_retur', 'detail_retur.header_retur_penjualan_id', '=', 'header_retur.id')
            ->where('header_retur.status', 1)
            ->whereYear('header_retur.tgl', '>=', $previousYear)
            ->selectRaw('YEAR(header_retur.tgl) as tahun, MONTH(header_retur.tgl) as bulan, SUM(detail_retur.subtotal) as subtotal')
            ->groupByRaw('YEAR(header_retur.tgl), MONTH(header_retur.tgl)')
            ->get();

        $nilaiBulanan = [];
        foreach ($penjualanBulanan as $item) {
            $kunci = sprintf('%d-%02d', $item->tahun, $item->bulan);
            $nilaiBulanan[$kunci] = [
                'tahun' => (int) $item->tahun,
                'bulan' => (int) $item->bulan,
                'subtotal' => (float) $item->subtotal,
            ];
        }
        foreach ($returBulanan as $item) {
            $kunci = sprintf('%d-%02d', $item->tahun, $item->bulan);
            $nilaiBulanan[$kunci] ??= [
                'tahun' => (int) $item->tahun,
                'bulan' => (int) $item->bulan,
                'subtotal' => 0,
            ];
            $nilaiBulanan[$kunci]['subtotal'] -= (float) $item->subtotal;
        }
        ksort($nilaiBulanan);
        $monthlySales = collect(array_values($nilaiBulanan));

        $bulanIni = now()->month;
        $ringkasanBulanIni = $monthlySales
            ->first(fn (array $item) => $item['tahun'] === $year && $item['bulan'] === $bulanIni);
        $pendapatanBulanIni = (float) ($ringkasanBulanIni['subtotal'] ?? 0);

        $topProducts = DB::table('detail_penjualans as detail')
            ->join('header_penjualans as header', 'header.no_penjualan', '=', 'detail.no_penjualan')
            ->leftJoin('barangs as barang', 'barang.kodebarang', '=', 'detail.kodebarang')
            ->whereIn('header.flag', $flagPenjualanValid)
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

        $hariIni = now()->startOfDay();
        $batasJatuhTempo = $hariIni->copy()->addDays(7)->endOfDay();

        $cicilanPerNota = DB::table('pembayaran_cicilans')
            ->selectRaw('no_penjualan, SUM(jumlah) as total_cicilan')
            ->groupBy('no_penjualan');
        $returPerNota = DB::table('header_retur_penjualans as header_retur')
            ->join('detail_retur_penjualans as detail_retur', 'detail_retur.header_retur_penjualan_id', '=', 'header_retur.id')
            ->where('header_retur.status', 1)
            ->selectRaw('detail_retur.no_penjualan, SUM(detail_retur.subtotal) as total_retur')
            ->groupBy('detail_retur.no_penjualan');

        $piutangJatuhTempo = DB::table('header_penjualans as penjualan')
            ->leftJoin('pelanggans as pelanggan', 'pelanggan.id', '=', 'penjualan.pelanggan_id')
            ->leftJoinSub($cicilanPerNota, 'cicilan', fn ($join) => $join->on('cicilan.no_penjualan', '=', 'penjualan.no_penjualan'))
            ->leftJoinSub($returPerNota, 'retur', fn ($join) => $join->on('retur.no_penjualan', '=', 'penjualan.no_penjualan'))
            ->whereIn('penjualan.flag', ['2', '3', '4', '7', '8'])
            ->whereNotNull('penjualan.tempo')
            ->whereDate('penjualan.tempo', '<=', $batasJatuhTempo)
            ->selectRaw("penjualan.no_penjualan, penjualan.tempo as jatuh_tempo, COALESCE(pelanggan.nama, 'Pelanggan umum') as pihak, GREATEST(penjualan.total - penjualan.bayar - COALESCE(cicilan.total_cicilan, 0) - COALESCE(retur.total_retur, 0), 0) as sisa")
            ->whereRaw('COALESCE(penjualan.total, 0) - COALESCE(penjualan.bayar, 0) - COALESCE(cicilan.total_cicilan, 0) - COALESCE(retur.total_retur, 0) > 0')
            ->orderBy('penjualan.tempo')
            ->limit(8)
            ->get();

        $totalPenerimaan = DB::table('penerimaan_r')
            ->selectRaw('nopenerimaan, SUM(subtotal) as total_hutang')
            ->groupBy('nopenerimaan');
        $pembayaranPerPenerimaan = DB::table('pembayaran_hutang_r')
            ->selectRaw('nopenerimaan, SUM(total) as total_dibayar')
            ->groupBy('nopenerimaan');

        $hutangJatuhTempo = DB::table('penerimaan_h as penerimaan')
            ->leftJoin('suppliers as supplier', 'supplier.kodesupl', '=', 'penerimaan.kdsupllier')
            ->leftJoinSub($totalPenerimaan, 'nilai_hutang', fn ($join) => $join->on('nilai_hutang.nopenerimaan', '=', 'penerimaan.nopenerimaan'))
            ->leftJoinSub($pembayaranPerPenerimaan, 'pembayaran_hutang', fn ($join) => $join->on('pembayaran_hutang.nopenerimaan', '=', 'penerimaan.nopenerimaan'))
            ->where('penerimaan.jenis_pembayaran', 'Hutang')
            ->whereNotNull('penerimaan.tgljatuhtempo')
            ->whereDate('penerimaan.tgljatuhtempo', '<=', $batasJatuhTempo)
            ->selectRaw('penerimaan.nopenerimaan as nomor, penerimaan.tgljatuhtempo as jatuh_tempo, COALESCE(supplier.nama, penerimaan.kdsupllier) as pihak, GREATEST(COALESCE(nilai_hutang.total_hutang, 0) - COALESCE(pembayaran_hutang.total_dibayar, 0), 0) as sisa')
            ->whereRaw('GREATEST(COALESCE(nilai_hutang.total_hutang, 0) - COALESCE(pembayaran_hutang.total_dibayar, 0), 0) > 0')
            ->orderBy('penerimaan.tgljatuhtempo')
            ->limit(8)
            ->get();

        return new JsonResponse([
            'tahun' => $year,
            'tahunSebelumnya' => $previousYear,
            'summary' => [
                'totalPendapatan' => $pendapatanBulanIni,
                'totalPenjualan' => (clone $validSales)->whereYear('tgl', $year)->whereMonth('tgl', $bulanIni)->count(),
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
            'jatuhTempo' => [
                'batasTanggal' => $batasJatuhTempo->toDateString(),
                'piutang' => $piutangJatuhTempo,
                'hutang' => $hutangJatuhTempo,
            ],
        ]);

    }
}
