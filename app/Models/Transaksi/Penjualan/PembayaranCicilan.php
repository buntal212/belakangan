<?php

namespace App\Models\Transaksi\Penjualan;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PembayaranCicilan extends Model
{
    use HasFactory;
    protected $guarded = ['id'];

    public function penjualanrinci()
    {
        return $this->hasMany(DetailPenjualan::class, 'no_penjualan', 'no_penjualan');
    }
}
