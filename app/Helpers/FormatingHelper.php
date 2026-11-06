<?php

namespace App\Helpers;

use Illuminate\Support\Facades\DB;

class FormatingHelper
{
    public static function matkdbarang($n, $kode)
    {
        $has = null;
        $lbr = strlen($n);
        for ($i = 1; $i <= 5 - $lbr; $i++) {
            $has = $has . "0";
        }
        return $has . $n . "-" . $kode;
    }

    public static function matorderpembelian($n, $kode)
    {
        $has = null;
        $lbr = strlen($n);
        for ($i = 1; $i <= 7 - $lbr; $i++) {
            $has = $has . "0";
        }
        return $has . $n . "-" . date("m") . date("Y") . "-" . $kode;
    }
    public static function notaPenjualan($n, $kode)
    {
        $has = null;
        $lbr = strlen($n);
        for ($i = 1; $i <= 5 - $lbr; $i++) {
            $has = $has . "0";
        }
        return $has . $n . "-" . date("m") . date("Y") . "-" . $kode;
    }

    /**
     * Membuat nomor nota penjualan tanpa stored procedure database.
     *
     * Method ini wajib dipanggil dari dalam DB::transaction() agar lock pada
     * counter mencegah dua transaksi mendapatkan nomor yang sama.
     */
    public static function nomorNotaPenjualanBerikutnya(): string
    {
        $counter = DB::table('counter')->lockForUpdate()->first();

        if (!$counter || !isset($counter->id) || $counter->penjualan === null) {
            throw new \RuntimeException('Counter nomor penjualan tidak tersedia.');
        }

        $nomorBerikutnya = (int) $counter->penjualan + 1;

        DB::table('counter')
            ->where('id', $counter->id)
            ->update(['penjualan' => $nomorBerikutnya]);

        return self::notaPenjualan($nomorBerikutnya, 'PJL');
    }

    public static function nopenerimaan($n, $kode)
    {
        $has = null;
        $lbr = strlen($n);
        for ($i = 1; $i <= 7 - $lbr; $i++) {
            $has = $has . "0";
        }
        return $has . $n . "/" . date("m") . date("Y") . "/" . $kode;
    }

    public static function noPenyesuaian($n, $kode)
    {
        $has = null;
        $lbr = strlen($n);
        for ($i = 1; $i <= 5 - $lbr; $i++) {
            $has = $has . "0";
        }
        return $has . $n . "-" . date("m") . date("Y") . "-" . $kode;
    }

    public static function nopembayaranhutang($n, $kode)
    {
        $has = null;
        $lbr = strlen($n);
        for ($i = 1; $i <= 5 - $lbr; $i++) {
            $has = $has . "0";
        }
        return $has . $n . "-". date("d") . date("m") . date("Y") . "/" . $kode;
    }
}
