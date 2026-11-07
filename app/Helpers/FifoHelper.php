<?php

namespace App\Helpers;

use App\Models\Barang;
use App\Models\Stok\stok;
use App\Models\Transaksi\Penjualan\DetailPenjualan;
use App\Models\Transaksi\Penjualan\DetailPenjualanFifo;
use Carbon\Carbon;
use Exception;

class FifoHelper
{
    /**
     * Process FIFO for transactions using only transaction number
     *
     * @param string $noTransaksi Transaction number (no_penjualan/no_pengembalian)
     * @param bool $isUpdate Whether to update stok.jumlah_k
     * @return array Array of created FIFO records
     */
    public static function processFifo(string $noTransaksi, bool $isUpdate = true)
    {
        // Get all details from the transaction
        $details = DetailPenjualan::where('no_penjualan', $noTransaksi)->get();

        if ($details->isEmpty()) {
            throw new Exception("Transaction {$noTransaksi} not found or has no details");
        }

        // 0. Validasi ketersediaan stok terpusat (termasuk alokasi order sales & draft kasir mandiri pesaing)
        StokHelper::validateKetersediaanPembayaran($noTransaksi);

        // 1. Validasi ketersediaan stok untuk seluruh rincian transaksi sebelum proses pengurangan
        $kebutuhanPerBarang = [];
        foreach ($details as $detail) {
            $key = $detail->kodebarang . '|' . ($detail->motif ?? '');
            if (!isset($kebutuhanPerBarang[$key])) {
                $kebutuhanPerBarang[$key] = [
                    'kodebarang' => $detail->kodebarang,
                    'motif' => $detail->motif,
                    'total_jumlah' => 0.0,
                ];
            }
            $kebutuhanPerBarang[$key]['total_jumlah'] += (float) $detail->jumlah;
        }

        foreach ($kebutuhanPerBarang as $kebutuhan) {
            $kodeBarang = $kebutuhan['kodebarang'];
            $motif = $kebutuhan['motif'];
            $totalJumlah = $kebutuhan['total_jumlah'];

            $queryStok = stok::where('kdbarang', $kodeBarang)
                ->where('jumlah_k', '>', 0);

            if ($motif !== null && $motif !== '') {
                $queryStok->where('motif', $motif);
            }

            $stoks = $queryStok
                ->lockForUpdate()
                ->orderBy('created_at', 'asc')
                ->get();

            $available = (float) $stoks->sum('jumlah_k');
            if ($available < $totalJumlah) {
                $barang = Barang::where('kodebarang', $kodeBarang)->first();
                $namaBarang = $barang ? $barang->namabarang : $kodeBarang;
                $satuanK = $barang ? $barang->satuan_k : 'PCS';
                $isi = (int) ($barang?->isi ?? 1);
                $satuanB = $barang ? $barang->satuan_b : 'DUS';

                $infoTersedia = "{$available} {$satuanK}";
                $infoDiminta = "{$totalJumlah} {$satuanK}";
                if ($isi > 1 && $satuanB) {
                    $dusTersedia = floor($available / $isi);
                    $sisaPcsTersedia = (int) $available % $isi;
                    $infoTersedia .= " ({$dusTersedia} {$satuanB}" . ($sisaPcsTersedia > 0 ? " {$sisaPcsTersedia} {$satuanK}" : '') . ")";

                    $dusDiminta = floor($totalJumlah / $isi);
                    $sisaPcsDiminta = (int) $totalJumlah % $isi;
                    $infoDiminta .= " ({$dusDiminta} {$satuanB}" . ($sisaPcsDiminta > 0 ? " {$sisaPcsDiminta} {$satuanK}" : '') . ")";
                }

                throw new Exception("Stok tidak mencukupi untuk barang {$namaBarang} ({$kodeBarang}). Tersedia: {$infoTersedia}, dibutuhkan: {$infoDiminta}.");
            }
        }

        $allFifoBatch = [];

        foreach ($details as $detail) {
            $kodeBarang = $detail->kodebarang;
            $motif = $detail->motif;
            $jumlah = $detail->jumlah;
            $hargaJual = $detail->harga_jual;
            $diskon = $detail->diskon;

            // Order sales belum memilih motif. Dalam kondisi itu, alokasikan
            // stok dari semua motif yang tersedia secara FIFO. Jika motif
            // ditentukan, stok tetap dibatasi pada motif tersebut.
            $queryStok = stok::where('kdbarang', $kodeBarang)
                ->where('jumlah_k', '>', 0);

            if ($motif !== null && $motif !== '') {
                $queryStok->where('motif', $motif);
            }

            $stoks = $queryStok
                ->lockForUpdate()
                ->orderBy('created_at', 'asc')
                ->get();
            // reject if stock is less than available
            $available = (float) $stoks->sum('jumlah_k');
            if ($available < (float)$jumlah) {
                $barang = Barang::where('kodebarang', $kodeBarang)->first();
                $namaBarang = $barang ? $barang->namabarang : $kodeBarang;
                throw new Exception("Stok tidak mencukupi untuk barang {$namaBarang} ({$kodeBarang}).");
            }

            // Get latest purchase price even if stock is empty
            $queryHargaBeliTerakhir = stok::where('kdbarang', $kodeBarang);

            if ($motif !== null && $motif !== '') {
                $queryHargaBeliTerakhir->where('motif', $motif);
            }

            $lastHargaBeli = $queryHargaBeliTerakhir
                ->orderBy('created_at', 'desc')
                ->value('harga_beli_k') ?? 0;

            if ($lastHargaBeli === 0) {
                $barang = Barang::where('kodebarang', $kodeBarang)->first();
                throw new Exception('Belum pernah ada stok untuk ' . ($barang ? $barang->namabarang : $kodeBarang));
            }

            $sisaJumlah = $jumlah;
            $fifoBatch = [];

            // Process available stocks first
            foreach ($stoks as $stok) {
                if ($sisaJumlah <= 0) break;

                $qtyAmbil = min($sisaJumlah, $stok->jumlah_k);

                // Create FIFO record for this batch
                $fifoBatch[] = [
                    'no_penjualan' => $noTransaksi,
                    'kodebarang' => $kodeBarang,
                    'jumlah' => $qtyAmbil,
                    'retur' => 0,
                    'harga_beli' => $stok->harga_beli_k,
                    'harga_jual' => $hargaJual,
                    'diskon' => $diskon * ($qtyAmbil / $jumlah),
                    'subtotal' => ($qtyAmbil * $hargaJual) - ($diskon * ($qtyAmbil / $jumlah)),
                    'stok_id' => $stok->id,
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ];

                // Update stock quantity if needed
                if ($isUpdate) {
                    $stok->decrement('jumlah_k', $qtyAmbil);
                }

                $sisaJumlah -= $qtyAmbil;
            }

            // If we still have remaining qty, create record with null stok_id
            // if ($sisaJumlah > 0) {
            //     $fifoBatch[] = [
            //         'no_penjualan' => $noTransaksi,
            //         'kodebarang' => $kodeBarang,
            //         'jumlah' => $sisaJumlah,
            //         'retur' => 0,
            //         'harga_beli' => $lastHargaBeli,
            //         'harga_jual' => $hargaJual,
            //         'diskon' => $diskon * ($sisaJumlah / $jumlah),
            //         'subtotal' => ($sisaJumlah * $hargaJual) - ($diskon * ($sisaJumlah / $jumlah)),
            //         'stok_id' => null
            //     ];
            // }

            $allFifoBatch = array_merge($allFifoBatch, $fifoBatch);
        }

        // Create all FIFO records
        DetailPenjualanFifo::insert($allFifoBatch);

        return $allFifoBatch;
    }
}
