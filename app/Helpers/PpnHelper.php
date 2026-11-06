<?php

namespace App\Helpers;

use App\Models\PengaturanPpn;

class PpnHelper
{
    public static function tarifAktif(): float
    {
        $pengaturan = PengaturanPpn::query()->first();

        return $pengaturan?->aktif ? (float) $pengaturan->persen : 0.0;
    }

    /**
     * Total penjualan disimpan termasuk PPN. Tarif dan nominal juga dibekukan
     * di header agar perubahan pengaturan tidak mengubah nota lama.
     *
     * @return array{subtotal: float, ppn_persen: float, ppn_nominal: float, total: float}
     */
    public static function hitung(float $subtotal, float $persen): array
    {
        $subtotal = max(0, round($subtotal, 2));
        $persen = max(0, round($persen, 2));
        $ppnNominal = round($subtotal * $persen / 100, 2);

        return [
            'subtotal' => $subtotal,
            'ppn_persen' => $persen,
            'ppn_nominal' => $ppnNominal,
            'total' => $subtotal + $ppnNominal,
        ];
    }
}
