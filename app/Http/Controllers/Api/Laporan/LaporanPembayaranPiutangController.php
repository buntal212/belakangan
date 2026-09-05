<?php

namespace App\Http\Controllers\Api\Laporan;

use Barryvdh\DomPDF\Facade\Pdf;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LaporanPembayaranPiutangController extends Controller
{
    public function getData(Request $request): JsonResponse
    {
        [$data, $totalPembayaran] = $this->laporan($request);

        return new JsonResponse([
            'data' => $data,
            'meta' => [
                'total' => $data->count(),
                'total_pembayaran' => (float) $totalPembayaran,
            ],
        ]);
    }

    public function downloadPdf(Request $request)
    {
        ini_set('memory_limit', '512M');
        set_time_limit(120);
        $mulai = microtime(true);
        Log::info('PDF REQUEST START', [
            'tglawal' => $request->input('tglawal'),
            'tglakhir' => $request->input('tglakhir'),
            'jenis_tanggal' => $request->input('jenis_tanggal', 'hutang'),
            'pelanggan_id' => $request->input('pelanggan_id'),
        ]);
        DB::flushQueryLog();
        DB::enableQueryLog();

        try {
            [$data, $totalPembayaran, $filters] = $this->laporan($request, true);
            $queryLog = DB::getQueryLog();
            DB::disableQueryLog();

            $jumlahTransaksi = $data->count();
            $durasiQuery = array_sum(array_column($queryLog, 'time')) / 1000;
            Log::info('PDF pembayaran piutang: data siap', [
                'jumlah_transaksi' => $jumlahTransaksi,
                'jumlah_query' => count($queryLog),
                'durasi_query_detik' => round($durasiQuery, 3),
                'memory_sebelum_pdf_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
            ]);

            $transaksi = $data->groupBy('no_penjualan')->map(function (Collection $rincian) {
                $nota = $rincian->first();

                return [
                    'no_penjualan' => $nota->no_penjualan,
                    'nama_pelanggan' => $nota->nama_pelanggan,
                    'tgl_hutang' => $nota->tgl_hutang,
                    'total_nota' => (float) $nota->total_nota,
                    'total_dibayar_nota' => (float) $nota->total_dibayar_nota,
                    'sisa_piutang_nota' => (float) $nota->sisa_piutang_nota,
                    'rincian' => $rincian->map(fn ($item) => [
                        'tgl_bayar' => $item->tgl_bayar,
                        'nopembayaran' => $item->nopembayaran,
                        'cara_bayar' => $item->cara_bayar,
                        'keterangan' => $item->keterangan,
                        'jumlah' => (float) $item->jumlah,
                    ])->values()->all(),
                ];
            })->values()->all();
            unset($data, $queryLog);

            $pdf = Pdf::loadView('pdf.laporan-pembayaran-piutang', [
                'transaksi' => $transaksi,
                'totalPembayaran' => $totalPembayaran,
                'jumlahTransaksi' => $jumlahTransaksi,
                'periodeAwal' => $filters['tglawal'] ?? null,
                'periodeAkhir' => $filters['tglakhir'] ?? null,
                'jenisTanggal' => $filters['jenis_tanggal'] ?? 'hutang',
                'tanggalCetak' => Carbon::now(),
            ])->setPaper('a4', 'portrait')->setOption('defaultFont', 'Helvetica');

            $output = $pdf->output();
            Log::info('PDF pembayaran piutang: selesai', [
                'jumlah_transaksi' => $jumlahTransaksi,
                'durasi_total_detik' => round(microtime(true) - $mulai, 3),
                'memory_peak_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
                'ukuran_pdf_kb' => round(strlen($output) / 1024, 2),
            ]);

            $awal = $filters['tglawal'] ?? 'semua';
            $akhir = $filters['tglakhir'] ?? 'semua';
            $namaFile = "laporan-pembayaran-piutang-{$awal}-sampai-{$akhir}.pdf";
            Log::info('PDF RESPONSE READY', [
                'size' => strlen($output),
                'memory_peak_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
            ]);

            return response($output, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => "attachment; filename={$namaFile}",
                'Content-Length' => strlen($output),
            ]);
        } catch (\Throwable $error) {
            DB::disableQueryLog();
            Log::error('PDF pembayaran piutang: gagal', [
                'pesan' => $error->getMessage(),
                'durasi_total_detik' => round(microtime(true) - $mulai, 3),
                'memory_peak_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
            ]);

            throw $error;
        }
    }

    private function laporan(Request $request, bool $denganFilter = false): array
    {
        $filters = $request->validate([
            'tglawal' => ['nullable', 'date'],
            'tglakhir' => ['nullable', 'date', 'after_or_equal:tglawal'],
            'jenis_tanggal' => ['nullable', 'in:pembayaran,hutang'],
            'q' => ['nullable', 'string', 'max:100'],
            'no_penjualan' => ['nullable', 'string', 'max:100'],
            'pelanggan_id' => ['nullable', 'integer', 'min:1'],
        ]);

        $pembayaranPerNota = DB::table('pembayaran_cicilans as pembayaran_per_nota')
            ->selectRaw('pembayaran_per_nota.no_penjualan, SUM(pembayaran_per_nota.jumlah) as total_cicilan_nota')
            ->groupBy('pembayaran_per_nota.no_penjualan');

        $pembayaranCicilan = DB::table('pembayaran_cicilans')
            ->leftJoin('header_cicilans', 'header_cicilans.id', '=', 'pembayaran_cicilans.header_ciclan_id')
            ->leftJoin('pelanggans', 'pelanggans.id', '=', 'header_cicilans.pelanggan_id')
            ->leftJoin('header_penjualans', 'header_penjualans.no_penjualan', '=', 'pembayaran_cicilans.no_penjualan')
            ->leftJoinSub($pembayaranPerNota, 'pembayaran_per_nota', function ($join) {
                $join->on('pembayaran_per_nota.no_penjualan', '=', 'pembayaran_cicilans.no_penjualan');
            })
            ->select([
                'pembayaran_cicilans.id',
                'pembayaran_cicilans.no_penjualan',
                'pembayaran_cicilans.tgl_bayar',
                'pembayaran_cicilans.jumlah',
                'header_cicilans.nopembayaran',
                'header_cicilans.cara_bayar',
                'header_cicilans.keterangan',
                'header_cicilans.pelanggan_id',
                'pelanggans.nama as nama_pelanggan',
                'header_penjualans.tgl as tgl_hutang',
                DB::raw('COALESCE(header_penjualans.total, 0) as total_nota'),
                DB::raw('COALESCE(header_penjualans.bayar, 0) + COALESCE(pembayaran_per_nota.total_cicilan_nota, 0) as total_dibayar_nota'),
                DB::raw('GREATEST(COALESCE(header_penjualans.total, 0) - COALESCE(header_penjualans.bayar, 0) - COALESCE(pembayaran_per_nota.total_cicilan_nota, 0), 0) as sisa_piutang_nota'),
            ]);

        $pembayaranAwal = DB::table('header_penjualans')
            ->leftJoin('pelanggans', 'pelanggans.id', '=', 'header_penjualans.pelanggan_id')
            ->leftJoinSub($pembayaranPerNota, 'pembayaran_per_nota', function ($join) {
                $join->on('pembayaran_per_nota.no_penjualan', '=', 'header_penjualans.no_penjualan');
            })
            ->where('header_penjualans.bayar', '>', 0)
            ->select([
                DB::raw("CONCAT('pembayaran-awal-', header_penjualans.id) as id"),
                'header_penjualans.no_penjualan',
                'header_penjualans.tgl as tgl_bayar',
                'header_penjualans.bayar as jumlah',
                DB::raw('NULL as nopembayaran'),
                'header_penjualans.cara_bayar',
                DB::raw("'Pembayaran awal (DP)' as keterangan"),
                'header_penjualans.pelanggan_id',
                'pelanggans.nama as nama_pelanggan',
                'header_penjualans.tgl as tgl_hutang',
                DB::raw('COALESCE(header_penjualans.total, 0) as total_nota'),
                DB::raw('COALESCE(header_penjualans.bayar, 0) + COALESCE(pembayaran_per_nota.total_cicilan_nota, 0) as total_dibayar_nota'),
                DB::raw('GREATEST(COALESCE(header_penjualans.total, 0) - COALESCE(header_penjualans.bayar, 0) - COALESCE(pembayaran_per_nota.total_cicilan_nota, 0), 0) as sisa_piutang_nota'),
            ]);

        $query = DB::query()
            ->fromSub($pembayaranCicilan->unionAll($pembayaranAwal), 'laporan_piutang')
            ->when(
                $filters['tglawal'] ?? null,
                fn ($query, $tanggal) => $query->whereDate(
                    ($filters['jenis_tanggal'] ?? 'hutang') === 'hutang'
                        ? 'laporan_piutang.tgl_hutang'
                        : 'laporan_piutang.tgl_bayar',
                    '>=',
                    $tanggal,
                ),
            )
            ->when(
                $filters['tglakhir'] ?? null,
                fn ($query, $tanggal) => $query->whereDate(
                    ($filters['jenis_tanggal'] ?? 'hutang') === 'hutang'
                        ? 'laporan_piutang.tgl_hutang'
                        : 'laporan_piutang.tgl_bayar',
                    '<=',
                    $tanggal,
                ),
            )
            ->when($filters['pelanggan_id'] ?? null, fn ($query, $pelangganId) => $query->where('laporan_piutang.pelanggan_id', $pelangganId))
            ->when($filters['no_penjualan'] ?? null, fn ($query, $noPenjualan) => $query->where('laporan_piutang.no_penjualan', $noPenjualan))
            ->when($filters['q'] ?? null, function ($query, $kataKunci) {
                $query->where(function ($query) use ($kataKunci) {
                    $query->where('laporan_piutang.nopembayaran', 'like', "%{$kataKunci}%")
                        ->orWhere('laporan_piutang.no_penjualan', 'like', "%{$kataKunci}%")
                        ->orWhere('laporan_piutang.nama_pelanggan', 'like', "%{$kataKunci}%");
                });
            })
            ->orderByDesc('laporan_piutang.tgl_bayar')
            ->orderByDesc('laporan_piutang.id');

        $totalPembayaran = (clone $query)->sum('laporan_piutang.jumlah');
        $data = $query->get();

        return $denganFilter ? [$data, $totalPembayaran, $filters] : [$data, $totalPembayaran];
    }
}
