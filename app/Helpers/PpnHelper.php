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
     * Total penjualan sudah termasuk PPN. Tarif dan nominal juga dibekukan
     * di header agar perubahan pengaturan tidak mengubah nota lama.
     *
     * @return array{dpp: float, ppn_persen: float, ppn_nominal: float, total: float}
     */
    public static function hitungTermasukPpn(float $total, float $persen): array
    {
        $total = max(0, round($total, 2));
        $persen = max(0, round($persen, 2));
        $dpp = $persen > 0
            ? round($total / (1 + ($persen / 100)), 2)
            : $total;
        $ppnNominal = round($total - $dpp, 2);

        return [
            'dpp' => $dpp,
            'ppn_persen' => $persen,
            'ppn_nominal' => $ppnNominal,
            'total' => $total,
        ];
    }
}
