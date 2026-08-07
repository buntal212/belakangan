<?php

namespace App\Helpers;

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
    public static function nopenerimaan($n, $kode)
    {
        $has = null;
        $lbr = strlen($n);
        for ($i = 1; $i <= 7 - $lbr; $i++) {
            $has = $has . "0";
        }
        return $has . $n . "/" . date("m") . date("Y") . "/" . $kode;
    }
}
