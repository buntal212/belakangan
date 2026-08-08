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

        $allFifoBatch = [];

        foreach ($details as $detail) {
            $kodeBarang = $detail->kodebarang;
            $motif = $detail->motif;
            $jumlah = $detail->jumlah;
            $hargaJual = $detail->harga_jual;
            $diskon = $detail->diskon;

            // Get available stocks
            $stoks = stok::where('kdbarang', $kodeBarang)
                ->where('motif', $motif)
                ->where('jumlah_k', '>', 0)
                ->orderBy('created_at', 'asc')
                ->get();
            // reject if stock is less than available
            $available = $stoks->sum('jumlah_k');
            if ((float)$available < (float)$jumlah) {
                throw new Exception('Stok tidak mencukupi untuk barang ' . $kodeBarang);
            }

            // Get latest purchase price even if stock is empty
            $lastHargaBeli = stok::where('kdbarang', $kodeBarang)
                ->where('motif', $motif)
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
