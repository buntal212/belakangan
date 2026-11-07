<?php

namespace App\Helpers;

use App\Models\Barang;
use App\Models\OrderPenjualan;
use App\Models\Stok\stok;
use App\Models\Transaksi\Penjualan\DetailPenjualan;
use App\Models\Transaksi\Penjualan\HeaderPenjualan;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StokHelper
{
    /**
     * Hitung stok fisik global barang (dalam PCS / satuan kecil).
     */
    public static function getStokFisikGlobal(string $kodebarang, bool $lock = false): float
    {
        $query = stok::where('kdbarang', $kodebarang)
            ->where('jumlah_k', '>', 0);

        if ($lock) {
            $query->lockForUpdate();
        }

        return (float) $query->sum('jumlah_k');
    }

    /**
     * Hitung stok fisik per motif barang (dalam PCS / satuan kecil).
     */
    public static function getStokFisikMotif(string $kodebarang, ?string $motif, bool $lock = false): float
    {
        $query = stok::where('kdbarang', $kodebarang)
            ->where('jumlah_k', '>', 0);

        if ($motif !== null && $motif !== '') {
            $query->where('motif', $motif);
        } else {
            $query->where(function ($q) {
                $q->whereNull('motif')->orWhere('motif', '');
            });
        }

        if ($lock) {
            $query->lockForUpdate();
        }

        return (float) $query->sum('jumlah_k');
    }

    /**
     * Hitung alokasi dari Order Sales aktif (status 1, 2, 3) dalam PCS.
     * Order Sales tanpa motif mengalokasikan stok global barang.
     */
    public static function getAlokasiOrderSales(string $kodebarang, ?string $excludeNoOrder = null, bool $lock = false): float
    {
        $query = DB::table('rincian_order_penjualans as ro')
            ->join('order_penjualans as op', 'ro.order_penjualan_id', '=', 'op.id')
            ->join('barangs as b', 'ro.barang_id', '=', 'b.id')
            ->where('b.kodebarang', $kodebarang)
            ->whereIn('op.status_order', ['1', '2', '3'])
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('header_penjualans as p')
                    ->whereColumn('p.noorder', 'op.noorder')
                    ->whereNotNull('p.flag')
                    ->where('p.flag', '<>', '6');
            });

        if ($excludeNoOrder !== null && $excludeNoOrder !== '') {
            $query->where('op.noorder', '<>', $excludeNoOrder);
        }

        if ($lock) {
            $query->lockForUpdate();
        }

        return (float) $query->sum('ro.jumlah');
    }

    /**
     * Hitung alokasi dari Draft Kasir Mandiri (flag = null, noorder = null) dalam PCS.
     */
    public static function getAlokasiDraftKasir(
        string $kodebarang,
        ?string $motif = null,
        ?string $excludeNoPenjualan = null,
        ?int $excludeDetailId = null,
        bool $lock = false
    ): float {
        $query = DB::table('detail_penjualans as dp')
            ->join('header_penjualans as hp', 'dp.no_penjualan', '=', 'hp.no_penjualan')
            ->where('dp.kodebarang', $kodebarang)
            ->whereNull('hp.flag')
            ->whereNull('hp.noorder');

        if ($motif !== null) {
            if ($motif !== '') {
                $query->where('dp.motif', $motif);
            } else {
                $query->where(function ($q) {
                    $q->whereNull('dp.motif')->orWhere('dp.motif', '');
                });
            }
        }

        if ($excludeNoPenjualan !== null && $excludeNoPenjualan !== '') {
            $query->where('dp.no_penjualan', '<>', $excludeNoPenjualan);
        }

        if ($excludeDetailId !== null) {
            $query->where('dp.id', '<>', $excludeDetailId);
        }

        if ($lock) {
            $query->lockForUpdate();
        }

        return (float) $query->sum('dp.jumlah');
    }

    /**
     * Hitung ketersediaan stok secara terpusat (dalam PCS).
     *
     * @return array [
     *     'stok_fisik' => float,
     *     'stok_fisik_global' => float,
     *     'alokasi_order' => float,
     *     'alokasi_draft' => float,
     *     'alokasi_draft_global' => float,
     *     'total_alokasi' => float,
     *     'stok_tersedia' => float,
     *     'stok_tersedia_global' => float,
     * ]
     */
    public static function getKetersediaanStok(
        string $kodebarang,
        ?string $motif = null,
        ?string $excludeNoPenjualan = null,
        ?string $excludeNoOrder = null,
        ?int $excludeDetailId = null,
        bool $lock = false
    ): array {
        // 1. Stok Fisik Global
        $stokFisikGlobal = self::getStokFisikGlobal($kodebarang, $lock);

        // 2. Alokasi Order Sales Aktif (Global)
        $alokasiOrder = self::getAlokasiOrderSales($kodebarang, $excludeNoOrder, $lock);

        // 3. Alokasi Draft Kasir Mandiri (Global)
        $alokasiDraftGlobal = self::getAlokasiDraftKasir(
            $kodebarang,
            null,
            $excludeNoPenjualan,
            $excludeDetailId,
            $lock
        );

        // 4. Stok Tersedia Global
        $totalAlokasiGlobal = $alokasiOrder + $alokasiDraftGlobal;
        $stokTersediaGlobal = max(0.0, $stokFisikGlobal - $totalAlokasiGlobal);

        // Jika ada spesifikasi motif
        if ($motif !== null) {
            $stokFisikMotif = self::getStokFisikMotif($kodebarang, $motif, $lock);
            $alokasiDraftMotif = self::getAlokasiDraftKasir(
                $kodebarang,
                $motif,
                $excludeNoPenjualan,
                $excludeDetailId,
                $lock
            );
            $stokBebasMotifMurni = max(0.0, $stokFisikMotif - $alokasiDraftMotif);

            // Stok tersedia motif dibatasi oleh fisik motif dan stok global
            $stokTersediaMotif = min($stokBebasMotifMurni, $stokTersediaGlobal);

            return [
                'stok_fisik' => $stokFisikMotif,
                'stok_fisik_global' => $stokFisikGlobal,
                'alokasi_order' => $alokasiOrder,
                'alokasi_draft' => $alokasiDraftMotif,
                'alokasi_draft_global' => $alokasiDraftGlobal,
                'total_alokasi' => $alokasiOrder + $alokasiDraftMotif,
                'stok_tersedia' => $stokTersediaMotif,
                'stok_tersedia_global' => $stokTersediaGlobal,
            ];
        }

        return [
            'stok_fisik' => $stokFisikGlobal,
            'stok_fisik_global' => $stokFisikGlobal,
            'alokasi_order' => $alokasiOrder,
            'alokasi_draft' => $alokasiDraftGlobal,
            'alokasi_draft_global' => $alokasiDraftGlobal,
            'total_alokasi' => $totalAlokasiGlobal,
            'stok_tersedia' => $stokTersediaGlobal,
            'stok_tersedia_global' => $stokTersediaGlobal,
        ];
    }

    /**
     * Validasi ketersediaan stok saat simpanDetail (draft).
     *
     * @throws ValidationException
     */
    public static function validateKetersediaanDraft(
        string $kodebarang,
        ?string $motif,
        float $jumlahDimintaSk,
        ?string $noPenjualan = null,
        ?int $detailId = null
    ): void {
        // Akumulasi baris lain dalam draft nota yang sama
        $otherDetailsGlobalQuery = DetailPenjualan::where('no_penjualan', $noPenjualan)
            ->where('kodebarang', $kodebarang);
        if ($detailId) {
            $otherDetailsGlobalQuery->where('id', '<>', $detailId);
        }
        $existingQtyDraftGlobal = (float) $otherDetailsGlobalQuery->sum('jumlah');

        $otherDetailsMotifQuery = DetailPenjualan::where('no_penjualan', $noPenjualan)
            ->where('kodebarang', $kodebarang);
        if ($detailId) {
            $otherDetailsMotifQuery->where('id', '<>', $detailId);
        }
        if ($motif !== null && $motif !== '') {
            $otherDetailsMotifQuery->where('motif', $motif);
        } else {
            $otherDetailsMotifQuery->where(function ($q) {
                $q->whereNull('motif')->orWhere('motif', '');
            });
        }
        $existingQtyDraftMotif = (float) $otherDetailsMotifQuery->sum('jumlah');

        $totalKebutuhanMotif = $existingQtyDraftMotif + $jumlahDimintaSk;
        $totalKebutuhanGlobal = $existingQtyDraftGlobal + $jumlahDimintaSk;

        $ketersediaan = self::getKetersediaanStok(
            kodebarang: $kodebarang,
            motif: $motif,
            excludeNoPenjualan: $noPenjualan,
            excludeNoOrder: null,
            excludeDetailId: $detailId,
            lock: true
        );

        $stokTersediaMotif = (float) $ketersediaan['stok_tersedia'];
        $stokTersediaGlobal = (float) $ketersediaan['stok_tersedia_global'];

        if ($totalKebutuhanMotif > $stokTersediaMotif || $totalKebutuhanGlobal > $stokTersediaGlobal) {
            $barang = Barang::where('kodebarang', $kodebarang)->first();
            $namaBarang = $barang ? $barang->namabarang : $kodebarang;
            $satuanK = $barang ? $barang->satuan_k : 'PCS';
            $isi = (int) ($barang?->isi ?? 1);
            $satuanB = $barang ? $barang->satuan_b : 'DUS';

            $stokTersediaLimit = min($stokTersediaMotif, $stokTersediaGlobal);
            $infoTersedia = self::formatStokDisplay($stokTersediaLimit, $isi, $satuanB, $satuanK);
            $infoDiminta = self::formatStokDisplay($totalKebutuhanMotif, $isi, $satuanB, $satuanK);

            throw ValidationException::withMessages([
                'jumlah' => ["Stok tidak mencukupi untuk barang {$namaBarang} ({$kodebarang}). {$infoTersedia}, dibutuhkan: {$infoDiminta}."],
            ]);
        }
    }

    /**
     * Validasi ulang ketersediaan stok saat simpanPembayaran.
     * Alokasi milik nota yang sedang dibayar dikecualikan dari alokasi pesaing.
     *
     * @throws ValidationException
     */
    public static function validateKetersediaanPembayaran(string $noPenjualan): void
    {
        $header = HeaderPenjualan::where('no_penjualan', $noPenjualan)->first();
        $noOrder = $header?->noorder;

        $details = DetailPenjualan::where('no_penjualan', $noPenjualan)->get();
        if ($details->isEmpty()) {
            return;
        }

        $kebutuhanPerItem = [];
        $kebutuhanGlobalPerBarang = [];
        foreach ($details as $detail) {
            $motifKey = $detail->motif ?? '';
            $key = $detail->kodebarang . '|' . $motifKey;
            if (!isset($kebutuhanPerItem[$key])) {
                $kebutuhanPerItem[$key] = [
                    'kodebarang' => $detail->kodebarang,
                    'motif' => $detail->motif,
                    'total_jumlah' => 0.0,
                ];
            }
            $kebutuhanPerItem[$key]['total_jumlah'] += (float) $detail->jumlah;

            if (!isset($kebutuhanGlobalPerBarang[$detail->kodebarang])) {
                $kebutuhanGlobalPerBarang[$detail->kodebarang] = 0.0;
            }
            $kebutuhanGlobalPerBarang[$detail->kodebarang] += (float) $detail->jumlah;
        }

        // 1. Validasi kebutuhan global per barang terhadap alokasi pesaing
        foreach ($kebutuhanGlobalPerBarang as $kodebarang => $totalDibutuhkanGlobal) {
            $ketersediaan = self::getKetersediaanStok(
                kodebarang: $kodebarang,
                motif: null,
                excludeNoPenjualan: $noPenjualan,
                excludeNoOrder: $noOrder,
                lock: true
            );

            if ($totalDibutuhkanGlobal > $ketersediaan['stok_tersedia_global']) {
                $barang = Barang::where('kodebarang', $kodebarang)->first();
                $namaBarang = $barang ? $barang->namabarang : $kodebarang;
                $satuanK = $barang ? $barang->satuan_k : 'PCS';
                $isi = (int) ($barang?->isi ?? 1);
                $satuanB = $barang ? $barang->satuan_b : 'DUS';

                $infoTersedia = self::formatStokInfoFifo($ketersediaan['stok_tersedia_global'], $isi, $satuanB, $satuanK);
                $infoDiminta = self::formatStokInfoFifo($totalDibutuhkanGlobal, $isi, $satuanB, $satuanK);

                throw ValidationException::withMessages([
                    'stok' => ["Stok tidak mencukupi untuk barang {$namaBarang} ({$kodebarang}). Tersedia: {$infoTersedia}, dibutuhkan: {$infoDiminta}."],
                ]);
            }
        }

        // 2. Validasi per motif jika motif ditentukan
        foreach ($kebutuhanPerItem as $item) {
            if ($item['motif'] !== null && $item['motif'] !== '') {
                $ketersediaanMotif = self::getKetersediaanStok(
                    kodebarang: $item['kodebarang'],
                    motif: $item['motif'],
                    excludeNoPenjualan: $noPenjualan,
                    excludeNoOrder: $noOrder,
                    lock: true
                );

                if ($item['total_jumlah'] > $ketersediaanMotif['stok_tersedia']) {
                    $barang = Barang::where('kodebarang', $item['kodebarang'])->first();
                    $namaBarang = $barang ? $barang->namabarang : $item['kodebarang'];
                    $satuanK = $barang ? $barang->satuan_k : 'PCS';
                    $isi = (int) ($barang?->isi ?? 1);
                    $satuanB = $barang ? $barang->satuan_b : 'DUS';

                    $infoTersedia = self::formatStokInfoFifo($ketersediaanMotif['stok_tersedia'], $isi, $satuanB, $satuanK);
                    $infoDiminta = self::formatStokInfoFifo($item['total_jumlah'], $isi, $satuanB, $satuanK);

                    throw ValidationException::withMessages([
                        'stok' => ["Stok tidak mencukupi untuk barang {$namaBarang} ({$item['kodebarang']}). Tersedia: {$infoTersedia}, dibutuhkan: {$infoDiminta}."],
                    ]);
                }
            }
        }
    }

    /**
     * Format teks tampilan stok dalam format SB / SK untuk dropdown.
     * Contoh: "Stok: 1 DUS (8 PCS)" atau "Stok: 1 DUS lebih 2 PCS (10 PCS)".
     */
    public static function formatStokDisplay(float $jumlahK, int $isi = 1, ?string $satuanB = 'DUS', ?string $satuanK = 'PCS'): string
    {
        $isi = $isi > 0 ? $isi : 1;
        $satuanB = $satuanB ?: 'DUS';
        $satuanK = $satuanK ?: 'PCS';

        $stokB = $isi > 0 ? (int) floor($jumlahK / $isi) : 0;
        $sisaK = $isi > 0 ? (int) fmod($jumlahK, $isi) : 0;

        if ($stokB > 0) {
            $teksStok = "{$stokB} {$satuanB}" . ($sisaK > 0 ? " lebih {$sisaK} {$satuanK}" : '');
        } else {
            $teksStok = "{$sisaK} {$satuanK}";
        }

        if ($isi > 1) {
            return "Stok: {$teksStok} ({$jumlahK} {$satuanK})";
        }

        return "Stok: {$teksStok}";
    }

    /**
     * Format teks info ketersediaan stok untuk pesan error validasi / FIFO.
     * Contoh: "8 PCS (1 DUS)" atau "16 PCS (2 DUS)".
     */
    public static function formatStokInfoFifo(float $jumlahK, int $isi = 1, ?string $satuanB = 'DUS', ?string $satuanK = 'PCS'): string
    {
        $satuanK = $satuanK ?: 'PCS';
        $satuanB = $satuanB ?: 'DUS';
        $isi = $isi > 0 ? $isi : 1;

        $info = "{$jumlahK} {$satuanK}";
        if ($isi > 1 && $satuanB) {
            $dus = floor($jumlahK / $isi);
            $sisa = (int) $jumlahK % $isi;
            $info .= " ({$dus} {$satuanB}" . ($sisa > 0 ? " {$sisa} {$satuanK}" : '') . ")";
        }

        return $info;
    }
}
