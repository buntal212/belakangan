<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class StockOpnameJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    // public function handle()
    // {
    //     $endOfMonth = Carbon::now()->endOfMonth()->setTime(23, 59, 59);
    //     // $endOfMonth = Carbon::now()->setTime(23, 59, 59);
    //     $startOfMonth = Carbon::now()->startOfMonth();

    //     if (Carbon::now()->eq($endOfMonth)) {
    //     // PENDAPATAN //
    //         // Penjualan Lunas (flag = 5)
    //         $totalLunas = DB::table('header_penjualans')
    //             ->where('flag',  5)
    //             ->whereBetween('tgl', [$startOfMonth . ' 00:00:00', $endOfMonth . ' 23:59:59'])
    //             ->leftJoin('detail_penjualans', 'detail_penjualans.no_penjualan', '=', 'header_penjualans.no_penjualan')
    //             ->sum('detail_penjualans.subtotal');

    //         // Penjualan Menggunakan DP (flag = 7)
    //         $totalDP = DB::table('header_penjualans')
    //             ->where('flag', 7)
    //             ->whereBetween('tgl', [$startOfMonth . ' 00:00:00', $endOfMonth . ' 23:59:59'])
    //             ->sum('bayar');

    //         $totalPendapatan = $totalLunas + $totalDP;

    //         $existingDP = DB::table('opname_pendapatans')
    //             ->where('akun', 'Pendapatan Langsung')
    //             ->where('tgl_opname', $endOfMonth)
    //             ->first();

    //         if ($totalPendapatan > 0) {
    //             if ($existingDP) {
    //                 DB::table('opname_pendapatans')
    //                     ->where('id', $existingDP->id)
    //                     ->update([
    //                         'nilai' => $totalPendapatan,
    //                         'updated_at' => now(),
    //                     ]);
    //             } else {
    //                 DB::table('opname_pendapatans')->insert([
    //                     'akun' => 'Pendapatan Langsung',
    //                     'tgl_opname' => $endOfMonth,
    //                     'nilai' => $totalPendapatan,
    //                     'created_at' => now(),
    //                     'updated_at' => now(),
    //                 ]);
    //             }
    //         } elseif ($existingDP) {
    //             DB::table('opname_pendapatans')
    //                 ->where('id', $existingDP->id)
    //                 ->delete();
    //         }

    //         $totalCicilan = DB::table('pembayaran_cicilans')
    //             ->whereBetween('tgl_bayar', [$startOfMonth . ' 00:00:00', $endOfMonth . ' 23:59:59'])
    //             ->sum('jumlah');

    //         $existingCicilan = DB::table('opname_pendapatans')
    //             ->where('akun', 'Pendapatan dari Cicilan')
    //             ->where('tgl_opname', $endOfMonth)
    //             ->first();

    //         if ($totalCicilan > 0) {
    //             if ($existingCicilan) {
    //                 DB::table('opname_pendapatans')
    //                     ->where('id', $existingCicilan->id)
    //                     ->update([
    //                         'nilai' => $totalCicilan,
    //                         'updated_at' => now(),
    //                     ]);
    //             } else {
    //                 DB::table('opname_pendapatans')->insert([
    //                     'akun' => 'Pendapatan dari Cicilan',
    //                     'tgl_opname' => $endOfMonth,
    //                     'nilai' => $totalCicilan,
    //                     'created_at' => now(),
    //                     'updated_at' => now(),
    //                 ]);
    //             }
    //         } elseif ($existingCicilan) {
    //             DB::table('opname_pendapatans')
    //                 ->where('id', $existingCicilan->id)
    //                 ->delete();
    //         }


    //         // PENGELUARAN //
    //         $pembelianBarang = DB::table('penerimaan_h')
    //             ->where('kunci', 1)
    //             ->where('jenis_pembayaran', 'Cash')
    //             ->whereBetween('tgl_faktur', [$startOfMonth, $endOfMonth])
    //             ->leftJoin('penerimaan_r', 'penerimaan_r.nopenerimaan', '=', 'penerimaan_h.nopenerimaan')
    //             ->sum('penerimaan_r.subtotalfix');

    //         $existingPembelian = DB::table('opname_pengeluarans')
    //             ->where('akun', 'Pembelian Langsung')
    //             ->where('tgl_opname', $endOfMonth)
    //             ->first();

    //         if ($pembelianBarang > 0) {
    //             if ($existingPembelian) {
    //                 DB::table('opname_pengeluarans')
    //                     ->where('id', $existingPembelian->id)
    //                     ->update([
    //                         'nilai' => $pembelianBarang,
    //                         'updated_at' => now(),
    //                     ]);
    //             } else {
    //                 DB::table('opname_pengeluarans')->insert([
    //                     'akun' => 'Pembelian Langsung',
    //                     'tgl_opname' => $endOfMonth,
    //                     'nilai' => $pembelianBarang,
    //                     'created_at' => now(),
    //                     'updated_at' => now(),
    //                 ]);
    //             }
    //         } elseif ($existingPembelian) {
    //             DB::table('opname_pengeluarans')
    //                 ->where('id', $existingPembelian->id)
    //                 ->delete();
    //         }

    //         $bebankeluar = DB::table('transbeban_headers')
    //             ->where('flaging', 1)
    //             ->whereBetween('tgl', [$startOfMonth, $endOfMonth])
    //             ->leftJoin('transbeban_rincis', 'transbeban_rincis.notrans', '=', 'transbeban_headers.notrans')
    //             ->sum('transbeban_rincis.subtotal');

    //         $existingbeban = DB::table('opname_pengeluarans')
    //             ->where('akun', 'Beban Pengeluaran')
    //             ->where('tgl_opname', $endOfMonth)
    //             ->first();

    //         if ($bebankeluar > 0) {
    //             if ($existingbeban) {
    //                 DB::table('opname_pengeluarans')
    //                     ->where('id', $existingbeban->id)
    //                     ->update([
    //                         'nilai' => $bebankeluar,
    //                         'updated_at' => now(),
    //                     ]);
    //             } else {
    //                 DB::table('opname_pengeluarans')->insert([
    //                     'akun' => 'Beban Pengeluaran',
    //                     'tgl_opname' => $endOfMonth,
    //                     'nilai' => $bebankeluar,
    //                     'created_at' => now(),
    //                     'updated_at' => now(),
    //                 ]);
    //             }
    //         } elseif ($existingbeban) {
    //             DB::table('opname_pengeluarans')
    //                 ->where('id', $existingbeban->id)
    //                 ->delete();
    //         }

    //         $returpenjualan = DB::table('header_retur_penjualans')
    //             ->where('status', 1)
    //             ->whereBetween('tgl', [$startOfMonth . ' 00:00:00', $endOfMonth . ' 23:59:59'])
    //             ->leftJoin('detail_retur_penjualans', 'detail_retur_penjualans.header_retur_penjualan_id', '=', 'header_retur_penjualans.id')
    //             ->sum('detail_retur_penjualans.subtotal');

    //         $existingretur = DB::table('opname_pengeluarans')
    //             ->where('akun', 'Retur Penjualan')
    //             ->where('tgl_opname', $endOfMonth)
    //             ->first();

    //         if ($returpenjualan > 0) {
    //             if ($existingretur) {
    //                 DB::table('opname_pengeluarans')
    //                     ->where('id', $existingretur->id)
    //                     ->update([
    //                         'nilai' => $returpenjualan,
    //                         'updated_at' => now(),
    //                     ]);
    //             } else {
    //                 DB::table('opname_pengeluarans')->insert([
    //                     'akun' => 'Retur Penjualan',
    //                     'tgl_opname' => $endOfMonth,
    //                     'nilai' => $returpenjualan,
    //                     'created_at' => now(),
    //                     'updated_at' => now(),
    //                 ]);
    //             }
    //         } elseif ($existingretur) {
    //             DB::table('opname_pengeluarans')
    //                 ->where('id', $existingretur->id)
    //                 ->delete();
    //         }

    //         // PIUTANG //
    //         $penjualanDP= DB::table('header_penjualans')
    //             ->where('flag', '!=', 5)
    //             ->whereBetween('tgl', [$startOfMonth . ' 00:00:00', $endOfMonth . ' 23:59:59'])
    //             ->leftJoin('detail_penjualans', 'detail_penjualans.no_penjualan', '=', 'header_penjualans.no_penjualan')
    //             ->sum('detail_penjualans.subtotal');

    //         $pembayarantempo=DB::table('header_penjualans')
    //         ->where('flag', '!=', 5)
    //         // ->whereBetween('header_penjualans.tgl', [$startOfMonth . ' 00:00:00', $endOfMonth . ' 23:59:59'])
    //         ->leftJoin('pembayaran_cicilans', function ($join) use ($startOfMonth, $endOfMonth) {
    //             $join->on('pembayaran_cicilans.no_penjualan', '=', 'header_penjualans.no_penjualan')
    //                 ->whereBetween('pembayaran_cicilans.tgl_bayar', [$startOfMonth . ' 00:00:00', $endOfMonth . ' 23:59:59']);
    //         })
    //         ->sum(DB::raw('COALESCE(pembayaran_cicilans.jumlah, 0)'));

    //         $totalDPx = DB::table('header_penjualans')
    //             ->where('flag', '!=', 5)
    //             ->whereBetween('tgl', [$startOfMonth . ' 00:00:00', $endOfMonth . ' 23:59:59'])
    //             ->sum('bayar');
    //         $sisapiutang = $penjualanDP - $totalDPx - $pembayarantempo;
    //         $existingpiutang = DB::table('opname_piutangs')
    //             ->where('akun', 'Piutang Usaha')
    //             ->where('tgl_opname', $endOfMonth)
    //             ->first();


    //         if ($sisapiutang != 0) {
    //             if ($existingpiutang) {
    //                 DB::table('opname_piutangs')
    //                     ->where('id', $existingpiutang->id)
    //                     ->update([
    //                         'nilai' => $sisapiutang,
    //                         'updated_at' => now(),
    //                     ]);
    //             } else {
    //                 DB::table('opname_piutangs')->insert([
    //                     'akun' => 'Piutang Usaha',
    //                     'tgl_opname' => $endOfMonth,
    //                     'nilai' => $sisapiutang,
    //                     'created_at' => now(),
    //                     'updated_at' => now(),
    //                 ]);
    //             }
    //         }
    //          elseif ($existingpiutang) {
    //             DB::table('opname_piutangs')
    //                 ->where('id', $existingpiutang->id)
    //                 ->delete();
    //         }

    //         // HUTANG //
    //         $hutangpembelianBarang = DB::table('penerimaan_h')
    //             ->where('kunci', 1)
    //             ->where('jenis_pembayaran', 'Hutang')
    //             ->whereBetween('tgl_faktur', [$startOfMonth, $endOfMonth])
    //             ->leftJoin('penerimaan_r', 'penerimaan_r.nopenerimaan', '=', 'penerimaan_h.nopenerimaan')
    //             ->sum('penerimaan_r.subtotalfix');
    //         $Pembayaranhutang = DB::table('pembayaran_hutang_h')
    //             ->whereBetween('tgl_bayar', [$startOfMonth . ' 00:00:00', $endOfMonth . ' 23:59:59'])
    //             ->leftJoin('pembayaran_hutang_r', 'pembayaran_hutang_r.notrans', '=', 'pembayaran_hutang_h.notrans')
    //             ->sum('pembayaran_hutang_r.total');
    //         $hitungHutang = $hutangpembelianBarang - $Pembayaranhutang;

    //         $existingPembelian = DB::table('opname_hutangs')
    //             ->where('akun', 'Hutang Pembelian')
    //             ->where('tgl_opname', $endOfMonth)
    //             ->first();

    //         if ($hitungHutang > 0) {
    //             if ($existingPembelian) {
    //                 DB::table('opname_hutangs')
    //                     ->where('id', $existingPembelian->id)
    //                     ->update([
    //                         'nilai' => $hitungHutang,
    //                         'updated_at' => now(),
    //                     ]);
    //             } else {
    //                 DB::table('opname_hutangs')->insert([
    //                     'akun' => 'Hutang Pembelian',
    //                     'tgl_opname' => $endOfMonth,
    //                     'nilai' => $hitungHutang,
    //                     'created_at' => now(),
    //                     'updated_at' => now(),
    //                 ]);
    //             }
    //         } elseif ($existingPembelian) {
    //             DB::table('opname_hutangs')
    //                 ->where('id', $existingPembelian->id)
    //                 ->delete();
    //         }

    //     }
    // }
}
