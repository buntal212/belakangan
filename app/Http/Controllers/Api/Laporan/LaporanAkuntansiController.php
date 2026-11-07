<?php

namespace App\Http\Controllers\Api\Laporan;

use App\Http\Controllers\Controller;
use App\Models\Laporan\OpnameHutang;
use App\Models\Laporan\OpnamePendapatan;
use App\Models\Laporan\OpnamePengeluaran;
use App\Models\Laporan\OpnamePenjualan;
use App\Models\Laporan\OpnamePiutang;
use App\Models\Stok\StokOpname;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LaporanAkuntansiController extends Controller
{
    public function Aruskas(){
        $tahun = request('tahun') ?? date('Y');
        $bulan = request('bulan') ?? date('m');

        $from = Carbon::createFromFormat('Y-m-d', "$tahun-$bulan-01")->startOfMonth()->format('Y-m-d 00:00:00');
        $to   = Carbon::createFromFormat('Y-m-d', "$tahun-$bulan-01")->endOfMonth()->format('Y-m-d 23:59:59');

        $saldoAwal = OpnamePendapatan::where('tgl_opname', '<', $from)->sum('nilai')
                - OpnamePengeluaran::where('tgl_opname', '<', $from)->sum('nilai');

        // Kas masuk
        $arusKasMasuk = OpnamePendapatan::whereBetween('tgl_opname', [$from, $to])
            ->groupBy('akun', 'keterangan')
            ->selectRaw('akun, keterangan, SUM(nilai) as total')
            ->get();
        if ($arusKasMasuk->isEmpty()) {
            $startOfMonth = Carbon::parse($from)->startOfMonth()->format('Y-m-d');
            $endOfMonth   = Carbon::parse($to)->endOfMonth()->format('Y-m-d');

            // Penjualan Tunai Langsung (Kasir)
            // Hanya menghitung kas masuk riil dari penjualan tunai tanpa kredit/cicilan
            $totalTunai = (float) DB::table('header_penjualans')
                ->whereNotIn('flag', ['6']) // bukan batal
                ->where(function ($q) {
                    $q->whereIn('cara_bayar', ['Tunai', 'cash', 'Cash'])
                        ->orWhere(function ($sub) {
                            $sub->where('flag', '5')
                                ->whereNull('tempo')
                                ->whereNotIn('no_penjualan', function ($c) {
                                    $c->select('no_penjualan')->from('pembayaran_cicilans');
                                });
                        });
                })
                ->whereBetween('tgl', [$startOfMonth . ' 00:00:00', $endOfMonth . ' 23:59:59'])
                ->sum(DB::raw('LEAST(COALESCE(bayar, 0), COALESCE(total, 0))'));

            // Uang Muka (DP) dari transaksi tempo/kredit yang diterima pada bulan berjalan
            $totalDP = (float) DB::table('header_penjualans')
                ->whereNotIn('flag', ['6'])
                ->where(function ($q) {
                    $q->whereIn('flag', ['2', '3', '4', '7', '8'])
                        ->orWhere('cara_bayar', 'Tempo')
                        ->orWhere('cara_bayar', 'Cicilan')
                        ->orWhereNotNull('tempo')
                        ->orWhereRaw('bayar < total');
                })
                ->where('bayar', '>', 0)
                ->whereBetween('tgl', [$startOfMonth . ' 00:00:00', $endOfMonth . ' 23:59:59'])
                ->sum('bayar');

            // Penerimaan Cicilan Piutang pada bulan berjalan
            $totalCicilan = (float) DB::table('pembayaran_cicilans')
                ->whereBetween('tgl_bayar', [$startOfMonth . ' 00:00:00', $endOfMonth . ' 23:59:59'])
                ->sum('jumlah');

            $totalPendapatan = $totalTunai + $totalDP;
            $totalMasuk = $totalPendapatan + $totalCicilan;

            $arusKasMasuk = [
                [
                    'akun' => '0001-IN',
                    'keterangan' => 'Pendapatan Penjualan Tunai & DP',
                    'total' => $totalPendapatan,
                ],
                [
                    'akun' => '0002-IN',
                    'keterangan' => 'Pendapatan dari Cicilan Piutang',
                    'total' => $totalCicilan,
                ],
            ];
        } else {
            // kalau ada data normal
            $totalMasuk = $arusKasMasuk->sum('total');
        }

        // Kas keluar
        $arusKasKeluar = OpnamePengeluaran::whereBetween('tgl_opname', [$from, $to])
            ->groupBy('akun', 'keterangan')
            ->selectRaw('akun, keterangan, SUM(nilai) as total')
            ->get();

        if ($arusKasKeluar->isEmpty()) {
            $startOfMonth = Carbon::parse($from)->startOfMonth()->format('Y-m-d');
            $endOfMonth   = Carbon::parse($to)->endOfMonth()->format('Y-m-d');

            $pembelianBarang = (float) DB::table('penerimaan_h')
                ->where('kunci', 1)
                ->where('jenis_pembayaran', 'Cash')
                ->whereBetween('tgl_faktur', [$startOfMonth, $endOfMonth])
                ->leftJoin('penerimaan_r', 'penerimaan_r.nopenerimaan', '=', 'penerimaan_h.nopenerimaan')
                ->sum('penerimaan_r.subtotalfix');

            $bebankeluar = (float) DB::table('transbeban_headers')
                ->where('flaging', 1)
                ->whereBetween('tgl', [$startOfMonth, $endOfMonth])
                ->leftJoin('transbeban_rincis', 'transbeban_rincis.notrans', '=', 'transbeban_headers.notrans')
                ->sum('transbeban_rincis.subtotal');

            // Retur yang memotong piutang atau menghasilkan hak pengembalian BELUM merupakan kas keluar.
            // Kas keluar hanya terjadi jika ada bukti pengeluaran kas refund riil.
            $returpenjualan = 0.0;

            $Pembayaranhutang = (float) DB::table('pembayaran_hutang_h')
                ->whereBetween('tgl_bayar', [$startOfMonth, $endOfMonth])
                ->leftJoin('pembayaran_hutang_r', 'pembayaran_hutang_r.notrans', '=', 'pembayaran_hutang_h.notrans')
                ->sum('pembayaran_hutang_r.total');

            $totalKeluar = $pembelianBarang + $bebankeluar + $returpenjualan + $Pembayaranhutang;
            $arusKasKeluar = [
                [
                    'akun' => '0001-OUT',
                    'keterangan' => 'Pembelian Langsung',
                    'total' => $pembelianBarang,
                ],
                [
                    'akun' => '0002-OUT',
                    'keterangan' => 'Beban Pengeluaran',
                    'total' => $bebankeluar,
                ],
                [
                    'akun' => '0003-OUT',
                    'keterangan' => 'Retur Penjualan (Refund Kas Riil)',
                    'total' => $returpenjualan,
                ],
                [
                    'akun' => '0004-OUT',
                    'keterangan' => 'Pembayaran Hutang Supplier',
                    'total' => $Pembayaranhutang,
                ],
            ];
        } else {
            // kalau ada data normal
            $totalKeluar = $arusKasKeluar->sum('total');
        }


        $kenaikanKas = $totalMasuk - $totalKeluar;
        $saldoAkhir = $saldoAwal + $kenaikanKas;

        return response()->json([
            'periode' => "$tahun-$bulan",
            'kasmasuk' => $arusKasMasuk,
            'total_masuk' => $totalMasuk,
            'kaskeluar' => $arusKasKeluar,
            'total_keluar' => $totalKeluar,
            'kenaikan_kas' => $kenaikanKas,
            'saldo_awal' => $saldoAwal,
            'saldo_akhir' => $saldoAkhir,
        ]);
    }
    public function Labarugi(){
        $tahun = request('tahun') ?? date('Y');
        $bulan = request('bulan') ?? date('m');

        $from = Carbon::createFromFormat('Y-m-d', "$tahun-$bulan-01")->startOfMonth()->format('Y-m-d 00:00:00');
        $to   = Carbon::createFromFormat('Y-m-d', "$tahun-$bulan-01")->endOfMonth()->format('Y-m-d 23:59:59');

        // Pendapatan memakai dasar yang sama dengan Dashboard:
        // DPP Penjualan Bruto (subtotal barang dikurangi diskon kuitansi nota, sebelum PPN)
        // dikurangi DPP Retur Penjualan Selesai (status = '1').
        $flagPenjualanValid = ['2', '3', '4', '5', '7', '8'];
        $penjualanSemua = (float) DB::table('header_penjualans as header')
            ->whereIn('header.flag', $flagPenjualanValid)
            ->whereBetween('header.tgl', [$from, $to])
            ->sum(DB::raw('GREATEST(
                CASE 
                    WHEN header.ppn_nominal > 0 THEN header.total - header.ppn_nominal
                    ELSE COALESCE(header.subtotal, header.total) - COALESCE(header.diskon_kuitansi, 0)
                END,
                0
            )'));

        $returpenjualan = (float) DB::table('header_retur_penjualans as header_retur')
            ->where('header_retur.status', '1')
            ->whereBetween('header_retur.tgl', [$from, $to])
            ->sum(DB::raw('GREATEST(
                COALESCE(
                    header_retur.dpp,
                    CASE 
                        WHEN header_retur.ppn_nominal > 0 THEN header_retur.total - header_retur.ppn_nominal
                        ELSE COALESCE(header_retur.subtotal, header_retur.total) - COALESCE(header_retur.diskon_kuitansi, 0)
                    END
                ),
                0
            )'));

        $totalPenjualan = max(0.0, round($penjualanSemua - $returpenjualan, 2));
        $penjualan = [[
            'akun' => '0001-PNJ',
            'keterangan' => 'Penjualan Bersih (tanpa PPN)',
            'total' => $totalPenjualan,
        ]];

        $hpp = (float) DB::table('header_penjualans as header')
            ->whereBetween('header.tgl', [$from, $to])
            ->whereIn('header.flag', $flagPenjualanValid)
            ->leftJoin('detail_penjualan_fifos as fifo', 'fifo.no_penjualan', '=', 'header.no_penjualan')
            ->sum(DB::raw('fifo.jumlah * fifo.harga_beli'));

        // Perhitungan HPP retur FIFO yang tepat (mencegah duplikasi batch jika barang terjual dipecah ke beberapa batch)
        $fifoCostPerBarang = DB::table('detail_penjualan_fifos')
            ->selectRaw('no_penjualan, kodebarang, SUM(jumlah * harga_beli) as total_cost, SUM(jumlah) as total_qty')
            ->groupBy('no_penjualan', 'kodebarang');

        $returhpp = (float) DB::table('header_retur_penjualans as hr')
            ->join('detail_retur_penjualans as dr', 'dr.header_retur_penjualan_id', '=', 'hr.id')
            ->joinSub($fifoCostPerBarang, 'fifo', function ($join) {
                $join->on('fifo.no_penjualan', '=', 'dr.no_penjualan')
                    ->on('fifo.kodebarang', '=', 'dr.kodebarang');
            })
            ->where('hr.status', '1')
            ->whereBetween('hr.tgl', [$from, $to])
            ->where('fifo.total_qty', '>', 0)
            ->sum(DB::raw('(dr.jumlah * (fifo.total_cost / fifo.total_qty))'));

        $nilaihpp = max(0.0, round($hpp - $returhpp, 2));
        $labaKotor = round($totalPenjualan - $nilaihpp, 2);

        //     $returpenjualan = DB::table('header_retur_penjualans')
        //         ->where('status', 1)
        //         ->whereBetween('tgl', [$from . ' 00:00:00', $to . ' 23:59:59'])
        //         ->leftJoin('detail_retur_penjualans', 'detail_retur_penjualans.header_retur_penjualan_id', '=', 'header_retur_penjualans.id')
        //         ->sum('detail_retur_penjualans.subtotal');
        //     $totalPenjualan = $penjualanSemua - $returpenjualan;

        // $lastMonth = Carbon::createFromFormat('Y-m-d', "$tahun-$bulan-01")->subMonth();
        // $persediaanAwal = StokOpname::whereYear('tgl_opname', $lastMonth->year)
        //     ->whereMonth('tgl_opname', $lastMonth->month)
        //     ->sum(DB::raw('jumlah_k * harga_beli_k'));
        // $persediaanAkhir = StokOpname::whereYear('tgl_opname', $tahun)
        //     ->whereMonth('tgl_opname', $bulan)
        //     ->sum(DB::raw('jumlah_k * harga_beli_k'));

        // $pembelianBarang = DB::table('penerimaan_h')
        //     ->where('kunci', 1)
        //     ->whereBetween('tgl_faktur', [$from, $to])
        //     ->leftJoin('penerimaan_r', 'penerimaan_r.nopenerimaan', '=', 'penerimaan_h.nopenerimaan')
        //     ->sum('penerimaan_r.subtotalfix');
        // $hpp = ($persediaanAwal + $pembelianBarang) - $persediaanAkhir;


        $beban = OpnamePengeluaran::whereBetween('tgl_opname', [$from, $to])
            ->where('akun', '=', '0002-OUT')
            ->groupBy('akun', 'keterangan')
            ->selectRaw('akun, keterangan, SUM(nilai) as total')
            ->get();
          if ($beban->isEmpty()) {
            $startOfMonth = Carbon::parse($from)->startOfMonth()->format('Y-m-d');
            $endOfMonth   = Carbon::parse($to)->endOfMonth()->format('Y-m-d');
            $bebankeluar = DB::table('transbeban_headers')
                    ->where('flaging', 1)
                    ->whereBetween('tgl', [$startOfMonth, $endOfMonth])
                    ->leftJoin('transbeban_rincis', 'transbeban_rincis.notrans', '=', 'transbeban_headers.notrans')
                    ->sum('transbeban_rincis.subtotal');
            $totalBeban = $bebankeluar;
            $beban = [
                    [
                        'akun' => '0002-OUT',
                        'keterangan' => 'Beban Pengeluaran',
                        'total' => $bebankeluar
                    ],
                ];
            } else {
                $totalBeban = $beban->sum('total');
          }

        $labaoperasional = $labaKotor - $totalBeban;
        return response()->json([
            // 'persediaan_awal' => $persediaanAwal,
            // 'persediaan_akhir' => $persediaanAkhir,
            // 'pembelian_barang' => $pembelianBarang,
            'penjualan' => $penjualan,
            'beban' => $beban,
            'total_penjualan' => $totalPenjualan,
            'total_beban' => $totalBeban,
            'hpp' => $nilaihpp,
            'returhpp' => $returhpp,
            'laba_kotor' => $labaKotor,
            'laba_operasional' => $labaoperasional,
            'penjualanSemua' => $penjualanSemua,
            'returpenjualan' => $returpenjualan
        ]);
    }

    public function hutangpiutang()
    {
        $tahun = request('tahun') ?? date('Y');
        $bulan = request('bulan') ?? date('m');

        $from = Carbon::createFromFormat('Y-m-d', "$tahun-$bulan-01")->startOfMonth()->format('Y-m-d 00:00:00');
        $to   = Carbon::createFromFormat('Y-m-d', "$tahun-$bulan-01")->endOfMonth()->format('Y-m-d 23:59:59');

        $datahutang = OpnameHutang::whereBetween('tgl_opname', [$from, $to])
            ->groupBy('akun')
            ->selectRaw('akun, keterangan, SUM(debit) as totaldebit, SUM(kredit) as totalkredit')
            ->get();

        if ($datahutang->isEmpty()) {
            $startOfMonth = Carbon::parse($from)->startOfMonth()->format('Y-m-d');
            $endOfMonth   = Carbon::parse($to)->endOfMonth()->format('Y-m-d');

            $hutangpembelianBarang = DB::table('penerimaan_h')
                ->where('kunci', 1)
                ->where('jenis_pembayaran', 'Hutang')
                ->whereBetween('tgl_faktur', [$startOfMonth, $endOfMonth])
                ->leftJoin('penerimaan_r', 'penerimaan_r.nopenerimaan', '=', 'penerimaan_h.nopenerimaan')
                ->sum('penerimaan_r.subtotalfix');
            $Pembayaranhutang = DB::table('pembayaran_hutang_h')
                ->whereBetween('tgl_bayar', [$startOfMonth, $endOfMonth])
                ->leftJoin('pembayaran_hutang_r', 'pembayaran_hutang_r.notrans', '=', 'pembayaran_hutang_h.notrans')
                ->sum('pembayaran_hutang_r.total');

            $totalDebithutang = $hutangpembelianBarang;
            $totalKredithutang = $Pembayaranhutang;
            $datahutang = [
                    [
                        'akun' => '0001-HTG',
                        'keterangan' => 'Hutang Pembelian',
                        'totaldebit' => $hutangpembelianBarang,
                        'totalkredit' => 0,
                    ],
                    [
                        'akun' => '0002-HTG',
                        'keterangan' => 'Pembayaran Hutang',
                        'totaldebit' => 0,
                        'totalkredit' => $Pembayaranhutang
                    ],
                ];

        } else {
            $totalDebithutang = $datahutang->sum('totaldebit');
            $totalKredithutang = $datahutang->sum('totalkredit');
        }


        $totalSisaHutang = $totalDebithutang - $totalKredithutang;


        $datapiutang = OpnamePiutang::whereBetween('tgl_opname', [$from, $to])
            ->groupBy('akun')
            ->selectRaw('akun, keterangan, SUM(debit) as totaldebit, SUM(kredit) as totalkredit')
            ->get();

        if ($datapiutang->isEmpty()) {
            $startOfMonth = Carbon::parse($from)->startOfMonth()->format('Y-m-d');
            $endOfMonth   = Carbon::parse($to)->endOfMonth()->format('Y-m-d');

            // Identifikasi transaksi penjualan kredit / bertempo yang dibuat pada bulan ini
            $isCreditSale = function ($q) {
                $q->whereIn('flag', ['2', '3', '4', '7', '8'])
                    ->orWhere('cara_bayar', 'Tempo')
                    ->orWhere('cara_bayar', 'Cicilan')
                    ->orWhereNotNull('tempo')
                    ->orWhereRaw('bayar < total');
            };

            // Piutang Baru yang Timbul (Total Tagihan Kredit)
            $penjualanDP = (float) DB::table('header_penjualans')
                ->whereNotIn('flag', ['6'])
                ->where($isCreditSale)
                ->whereBetween('tgl', [$startOfMonth . ' 00:00:00', $endOfMonth . ' 23:59:59'])
                ->sum('total');

            // Pembayaran Cicilan yang diterima dalam bulan ini
            $piutangterbayar = (float) DB::table('pembayaran_cicilans')
                ->whereBetween('tgl_bayar', [$startOfMonth . ' 00:00:00', $endOfMonth . ' 23:59:59'])
                ->sum('jumlah');

            // Uang Muka (DP) yang diterima saat transaksi kredit dibuat dalam bulan ini
            $totalDPx = (float) DB::table('header_penjualans')
                ->whereNotIn('flag', ['6'])
                ->where($isCreditSale)
                ->where('bayar', '>', 0)
                ->whereBetween('tgl', [$startOfMonth . ' 00:00:00', $endOfMonth . ' 23:59:59'])
                ->sum('bayar');

            // Retur Penjualan yang mengurangi Piutang dalam bulan ini
            $returPiutang = (float) DB::table('header_retur_penjualans as hr')
                ->where('hr.status', '1')
                ->whereBetween('hr.tgl', [$startOfMonth . ' 00:00:00', $endOfMonth . ' 23:59:59'])
                ->whereIn('hr.no_penjualan', function ($q) use ($isCreditSale) {
                    $q->select('no_penjualan')
                        ->from('header_penjualans')
                        ->whereNotIn('flag', ['6'])
                        ->where($isCreditSale);
                })
                ->sum('hr.total');

            $datapiutang = [
                [
                    'akun' => '0001-PTG',
                    'keterangan' => 'Piutang Usaha',
                    'totaldebit' => $penjualanDP,
                    'totalkredit' => 0,
                ],
                [
                    'akun' => '0002-PTG',
                    'keterangan' => 'Uang Muka',
                    'totaldebit' => 0,
                    'totalkredit' => $totalDPx,
                ],
                [
                    'akun' => '0003-PTG',
                    'keterangan' => 'Piutang Diterima',
                    'totaldebit' => 0,
                    'totalkredit' => $piutangterbayar,
                ],
                [
                    'akun' => '0004-PTG',
                    'keterangan' => 'Retur Penjualan',
                    'totaldebit' => 0,
                    'totalkredit' => $returPiutang,
                ],
            ];

            $totalDebitpiutang = $penjualanDP;
            $totalKreditpiutang = $piutangterbayar + $totalDPx + $returPiutang;
        } else {

            $totalDebitpiutang = $datapiutang->sum('totaldebit');
            $totalKreditpiutang = $datapiutang->sum('totalkredit');
        }
        $totalSisapiutang = max(0, $totalDebitpiutang - $totalKreditpiutang);

        return response()->json([
            'datahutang' => $datahutang,
            'debithutang' => $totalDebithutang,
            'kredithutang' => $totalKredithutang,
            'sisahutang' => $totalSisaHutang,


            'datapiutang' => $datapiutang,
            'debitpiutang' => $totalDebitpiutang,
            'kreditpiutang' => $totalKreditpiutang,
            'sisapiutang' => $totalSisapiutang,
        ]);
    }
}
