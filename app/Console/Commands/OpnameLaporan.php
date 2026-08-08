<?php

   namespace App\Console\Commands;

   use Illuminate\Console\Command;
   use Illuminate\Support\Facades\DB;
   use Carbon\Carbon;

   class OpnameLaporan extends Command
   {
        protected $signature = 'stock:opnamelaporan';
        protected $description = 'Run stock opname process for the end of month';

        public function handle()
        {
            // $endOfMonth = Carbon::now()->endOfMonth()->setTime(23, 59, 59);
            // // $endOfMonth = Carbon::now()->setTime(23, 59, 59);
            // $startOfMonth = Carbon::now()->startOfMonth();
            // $currentDate = Carbon::now()->setMonth(7)->setDay(1);
            $currentDate = Carbon::now();
            if ($currentDate->day == 1 && $currentDate->hour == 0 && $currentDate->minute == 30)
            // if(true)
                {
                $endOfMonth = $currentDate->copy()->subMonth()->endOfMonth()->setTime(23, 59, 59);
                $startOfMonth = $currentDate->copy()->subMonth()->startOfMonth();
                // PENDAPATAN //
                // Penjualan Lunas (flag = 5)
                $totalLunas = DB::table('header_penjualans')
                    ->where('flag',  5)
                    ->whereBetween('tgl', [$startOfMonth . ' 00:00:00', $endOfMonth . ' 23:59:59'])
                    ->leftJoin('detail_penjualans', 'detail_penjualans.no_penjualan', '=', 'header_penjualans.no_penjualan')
                    ->sum('detail_penjualans.subtotal');

                // Penjualan Menggunakan DP (flag = 7)
                $totalDP = DB::table('header_penjualans')
                    ->where('flag', 7)
                    ->whereBetween('tgl', [$startOfMonth . ' 00:00:00', $endOfMonth . ' 23:59:59'])
                    ->sum('bayar');

                $totalPendapatan = $totalLunas + $totalDP;

                $existingDP = DB::table('opname_pendapatans')
                    ->where('akun', '0001-IN')
                    ->where('tgl_opname', $endOfMonth)
                    ->first();

                if ($totalPendapatan >= 0) {
                    if ($existingDP) {
                        DB::table('opname_pendapatans')
                            ->where('id', $existingDP->id)
                            ->update([
                                'nilai' => $totalPendapatan,
                                'updated_at' => now(),
                            ]);
                    } else {
                        DB::table('opname_pendapatans')->insert([
                            'akun' => '0001-IN',
                            'keterangan' => 'Pendapatan Langsung',
                            'tgl_opname' => $endOfMonth,
                            'nilai' => $totalPendapatan,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                } elseif ($existingDP) {
                    DB::table('opname_pendapatans')
                        ->where('id', $existingDP->id)
                        ->delete();
                }


                $totalCicilan = DB::table('pembayaran_cicilans')
                    ->whereBetween('tgl_bayar', [$startOfMonth . ' 00:00:00', $endOfMonth . ' 23:59:59'])
                    ->sum('jumlah');

                $existingCicilan = DB::table('opname_pendapatans')
                    ->where('akun', '0002-IN')
                    ->where('tgl_opname', $endOfMonth)
                    ->first();

                if ($totalCicilan >= 0) {
                    if ($existingCicilan) {
                        DB::table('opname_pendapatans')
                            ->where('id', $existingCicilan->id)
                            ->update([
                                'nilai' => $totalCicilan,
                                'updated_at' => now(),
                            ]);
                    } else {
                        DB::table('opname_pendapatans')->insert([
                            'akun' => '0002-IN',
                            'keterangan' => 'Pendapatan dari Cicilan',
                            'tgl_opname' => $endOfMonth,
                            'nilai' => $totalCicilan,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                } elseif ($existingCicilan) {
                    DB::table('opname_pendapatans')
                        ->where('id', $existingCicilan->id)
                        ->delete();
                }

                // PENJUALAN //
                $penjualanSemua =  DB::table('header_penjualans')
                    ->whereBetween('tgl', [$startOfMonth . ' 00:00:00', $endOfMonth . ' 23:59:59'])
                    ->leftJoin('detail_penjualans', 'detail_penjualans.no_penjualan', '=', 'header_penjualans.no_penjualan')
                    ->sum('detail_penjualans.subtotal');

                $returpenjualan = DB::table('header_retur_penjualans')
                    ->where('status', 1)
                    ->whereBetween('tgl', [$startOfMonth . ' 00:00:00', $endOfMonth . ' 23:59:59'])
                    ->leftJoin('detail_retur_penjualans', 'detail_retur_penjualans.header_retur_penjualan_id', '=', 'header_retur_penjualans.id')
                    ->sum('detail_retur_penjualans.subtotal');
                $totalPenjualanBersih = $penjualanSemua - $returpenjualan;
                $existingPenjualan = DB::table('opname_penjualans')
                    ->where('akun', '0001-PNJ')
                    ->where('tgl_opname', $endOfMonth)
                    ->first();

                if ($totalPenjualanBersih >= 0) {
                    if ($existingPenjualan) {
                        DB::table('opname_penjualans')
                            ->where('id', $existingPenjualan->id)
                            ->update([
                                'nilai' => $totalPenjualanBersih,
                                'updated_at' => now(),
                            ]);
                    } else {
                        DB::table('opname_penjualans')->insert([
                            'akun' => '0001-PNJ',
                            'keterangan' => 'Penjualan Bersih',
                            'tgl_opname' => $endOfMonth,
                            'nilai' => $totalPenjualanBersih,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                } elseif ($existingPenjualan) {
                    DB::table('opname_pendapatans')
                        ->where('id', $existingPenjualan->id)
                        ->delete();
                }

                // PENGELUARAN //
                $pembelianBarang = DB::table('penerimaan_h')
                    ->where('kunci', 1)
                    ->where('jenis_pembayaran', 'Cash')
                    ->whereBetween('tgl_faktur', [$startOfMonth, $endOfMonth])
                    ->leftJoin('penerimaan_r', 'penerimaan_r.nopenerimaan', '=', 'penerimaan_h.nopenerimaan')
                    ->sum('penerimaan_r.subtotalfix');

                $existingPembelian = DB::table('opname_pengeluarans')
                    ->where('akun', '0001-OUT')
                    ->where('tgl_opname', $endOfMonth)
                    ->first();

                if ($pembelianBarang >= 0) {
                    if ($existingPembelian) {
                        DB::table('opname_pengeluarans')
                            ->where('id', $existingPembelian->id)
                            ->update([
                                'nilai' => $pembelianBarang,
                                'updated_at' => now(),
                            ]);
                    } else {
                        DB::table('opname_pengeluarans')->insert([
                            'akun' => '0001-OUT',
                            'keterangan' => 'Pembelian Langsung',
                            'tgl_opname' => $endOfMonth,
                            'nilai' => $pembelianBarang,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                } elseif ($existingPembelian) {
                    DB::table('opname_pengeluarans')
                        ->where('id', $existingPembelian->id)
                        ->delete();
                }


                $bebankeluar = DB::table('transbeban_headers')
                    ->where('flaging', 1)
                    ->whereBetween('tgl', [$startOfMonth, $endOfMonth])
                    ->leftJoin('transbeban_rincis', 'transbeban_rincis.notrans', '=', 'transbeban_headers.notrans')
                    ->sum('transbeban_rincis.subtotal');

                $existingbeban = DB::table('opname_pengeluarans')
                    ->where('akun', '0002-OUT')
                    ->where('tgl_opname', $endOfMonth)
                    ->first();

                if ($bebankeluar >= 0) {
                    if ($existingbeban) {
                        DB::table('opname_pengeluarans')
                            ->where('id', $existingbeban->id)
                            ->update([
                                'nilai' => $bebankeluar,
                                'updated_at' => now(),
                            ]);
                    } else {
                        DB::table('opname_pengeluarans')->insert([
                            'akun' => '0002-OUT',
                            'keterangan' => 'Beban Pengeluaran',
                            'tgl_opname' => $endOfMonth,
                            'nilai' => $bebankeluar,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                } elseif ($existingbeban) {
                    DB::table('opname_pengeluarans')
                        ->where('id', $existingbeban->id)
                        ->delete();
                }

                $returpenjualan = DB::table('header_retur_penjualans')
                    ->where('status', 1)
                    ->whereBetween('tgl', [$startOfMonth . ' 00:00:00', $endOfMonth . ' 23:59:59'])
                    ->leftJoin('detail_retur_penjualans', 'detail_retur_penjualans.header_retur_penjualan_id', '=', 'header_retur_penjualans.id')
                    ->sum('detail_retur_penjualans.subtotal');

                $existingretur = DB::table('opname_pengeluarans')
                    ->where('akun', '0003-OUT')
                    ->where('tgl_opname', $endOfMonth)
                    ->first();

                if ($returpenjualan >= 0) {
                    if ($existingretur) {
                        DB::table('opname_pengeluarans')
                            ->where('id', $existingretur->id)
                            ->update([
                                'nilai' => $returpenjualan,
                                'updated_at' => now(),
                            ]);
                    } else {
                        DB::table('opname_pengeluarans')->insert([
                            'akun' => '0003-OUT',
                            'keterangan' => 'Retur Penjualan',
                            'tgl_opname' => $endOfMonth,
                            'nilai' => $returpenjualan,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                } elseif ($existingretur) {
                    DB::table('opname_pengeluarans')
                        ->where('id', $existingretur->id)
                        ->delete();
                }

                $Pembayaranhutang = DB::table('pembayaran_hutang_h')
                    ->whereBetween('tgl_bayar', [$startOfMonth, $endOfMonth])
                    ->leftJoin('pembayaran_hutang_r', 'pembayaran_hutang_r.notrans', '=', 'pembayaran_hutang_h.notrans')
                    ->sum('pembayaran_hutang_r.total');

                $existingBayarhutang = DB::table('opname_pengeluarans')
                    ->where('akun', '0004-OUT')
                    ->where('tgl_opname', $endOfMonth)
                    ->first();
                if ($Pembayaranhutang >= 0) {
                    if ($existingBayarhutang) {
                        DB::table('opname_pengeluarans')
                            ->where('id', $existingBayarhutang->id)
                            ->update([
                                'nilai' => $Pembayaranhutang,
                                'updated_at' => now(),
                            ]);
                    } else {
                        DB::table('opname_pengeluarans')->insert([
                            'akun' => '0004-OUT',
                            'keterangan' => 'Pembayaran Hutang',
                            'tgl_opname' => $endOfMonth,
                            'nilai' => $Pembayaranhutang,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                } elseif ($existingBayarhutang) {
                    DB::table('opname_pengeluarans')
                        ->where('id', $existingBayarhutang->id)
                        ->delete();
                }

                // PIUTANG //
                $penjualanDP= DB::table('header_penjualans')
                    ->where('flag', '!=', 5)
                    ->whereBetween('tgl', [$startOfMonth . ' 00:00:00', $endOfMonth . ' 23:59:59'])
                    ->leftJoin('detail_penjualans', 'detail_penjualans.no_penjualan', '=', 'header_penjualans.no_penjualan')
                    ->sum('detail_penjualans.subtotal');

                $piutangterbayar=DB::table('header_penjualans')
                ->where('flag', '!=', 5)
                // ->whereBetween('header_penjualans.tgl', [$startOfMonth . ' 00:00:00', $endOfMonth . ' 23:59:59'])
                ->leftJoin('pembayaran_cicilans', function ($join) use ($startOfMonth, $endOfMonth) {
                    $join->on('pembayaran_cicilans.no_penjualan', '=', 'header_penjualans.no_penjualan')
                        ->whereBetween('pembayaran_cicilans.tgl_bayar', [$startOfMonth . ' 00:00:00', $endOfMonth . ' 23:59:59']);
                })
                ->sum(DB::raw('COALESCE(pembayaran_cicilans.jumlah, 0)'));

                $totalDPx = DB::table('header_penjualans')
                    ->where('flag', '!=', 5)
                    ->whereBetween('tgl', [$startOfMonth . ' 00:00:00', $endOfMonth . ' 23:59:59'])
                    ->sum('bayar');
                $sisapiutang = $penjualanDP - $totalDPx - $piutangterbayar;
                $existingpiutang = DB::table('opname_piutangs')
                    ->where('akun', '0001-PTG')
                    ->where('tgl_opname', $endOfMonth)
                    ->first();
                $existinguangmuka = DB::table('opname_piutangs')
                    ->where('akun', '0002-PTG')
                    ->where('tgl_opname', $endOfMonth)
                    ->first();
                $existingpiutangditerima = DB::table('opname_piutangs')
                    ->where('akun', '0003-PTG')
                    ->where('tgl_opname', $endOfMonth)
                    ->first();


                if ($penjualanDP >= 0) {
                    if ($existingpiutang) {
                        DB::table('opname_piutangs')
                            ->where('id', $existingpiutang->id)
                            ->update([
                                'debit' => $penjualanDP,
                                'updated_at' => now(),
                            ]);
                    } else {
                        DB::table('opname_piutangs')->insert([
                            'akun' => '0001-PTG',
                            'keterangan' => 'Piutang Usaha',
                            'tgl_opname' => $endOfMonth,
                            'debit' => $penjualanDP,
                            'kredit' => 0,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }
                elseif ($existingpiutang) {
                    DB::table('opname_piutangs')
                        ->where('id', $existingpiutang->id)
                        ->delete();
                }

                // -- Uang Muka -- //
                if ($totalDPx >= 0) {
                    if ($existinguangmuka) {
                        DB::table('opname_piutangs')
                            ->where('id', $existinguangmuka->id)
                            ->update([
                                'kredit' => $totalDPx,
                                'updated_at' => now(),
                            ]);
                    } else {
                        DB::table('opname_piutangs')->insert([
                            'akun' => '0002-PTG',
                            'keterangan' => 'Uang Muka',
                            'tgl_opname' => $endOfMonth,
                            'debit' => 0,
                            'kredit' => $totalDPx,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }
                elseif ($existinguangmuka) {
                    DB::table('opname_piutangs')
                        ->where('id', $existinguangmuka->id)
                        ->delete();
                }

                // -- Piutang Diterima -- //
                if ($piutangterbayar >= 0) {
                    if ($existingpiutangditerima) {
                        DB::table('opname_piutangs')
                            ->where('id', $existingpiutangditerima->id)
                            ->update([
                                'kredit' => $piutangterbayar,
                                'updated_at' => now(),
                            ]);
                    } else {
                        DB::table('opname_piutangs')->insert([
                            'akun' => '0003-PTG',
                            'keterangan' => 'Piutang Diterima',
                            'tgl_opname' => $endOfMonth,
                            'debit' => 0,
                            'kredit' => $piutangterbayar,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }
                elseif ($existingpiutangditerima) {
                    DB::table('opname_piutangs')
                        ->where('id', $existingpiutangditerima->id)
                        ->delete();
                }

                // HUTANG //
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
                $hitungHutang = $hutangpembelianBarang - $Pembayaranhutang;

                $existingPembelian = DB::table('opname_hutangs')
                    ->where('akun', '0001-HTG')
                    ->where('tgl_opname', $endOfMonth)
                    ->first();
                $existingPembayaranhutang = DB::table('opname_hutangs')
                    ->where('akun', '0002-HTG')
                    ->where('tgl_opname', $endOfMonth)
                    ->first();
                // -- Hutang Pembelian -- //
                if ($hutangpembelianBarang >= 0) {
                    if ($existingPembelian) {
                        DB::table('opname_hutangs')
                            ->where('id', $existingPembelian->id)
                            ->update([
                                'debit' => $hutangpembelianBarang,
                                'updated_at' => now(),
                            ]);
                    } else {
                        DB::table('opname_hutangs')->insert([
                            'akun' => '0001-HTG',
                            'keterangan' => 'Hutang Pembelian',
                            'tgl_opname' => $endOfMonth,
                            'debit' => $hutangpembelianBarang,
                            'kredit' => 0,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                } elseif ($existingPembelian) {
                    DB::table('opname_hutangs')
                        ->where('id', $existingPembelian->id)
                        ->delete();
                }
                // -- Pembayaran Hutang -- //
                if ($Pembayaranhutang >= 0) {
                    if ($existingPembayaranhutang) {
                        DB::table('opname_hutangs')
                            ->where('id', $existingPembayaranhutang->id)
                            ->update([
                                'kredit' => $Pembayaranhutang,
                                'updated_at' => now(),
                            ]);
                    } else {
                        DB::table('opname_hutangs')->insert([
                            'akun' => '0002-HTG',
                            'keterangan' => 'Pembayaran Hutang',
                            'tgl_opname' => $endOfMonth,
                            'debit' => 0,
                            'kredit' => $Pembayaranhutang,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                } elseif ($existingPembayaranhutang) {
                    DB::table('opname_hutangs')
                        ->where('id', $existingPembayaranhutang->id)
                        ->delete();
                }

            }
            $this->info('Stock Opname Laporan successfully processed.');
        }

    }
