<?php

namespace App\Models\Transaksi\Penjualan;

use App\Models\Pelanggan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HeaderPenjualan extends Model
{
    use HasFactory;
    protected $guarded = ['id'];
    public function detail()
    {
        return $this->hasMany(DetailPenjualan::class, 'no_penjualan', 'no_penjualan');
    }
    public function detailFifo()
    {
        return $this->hasMany(DetailPenjualanFifo::class, 'no_penjualan', 'no_penjualan');
    }
    public function cicilan()
    {
        return $this->hasMany(PembayaranCicilan::class, 'no_penjualan', 'no_penjualan');
    }
    public function pelanggan()
    {
        return $this->belongsTo(Pelanggan::class);
    }
    public function sales()
    {
        return $this->belongsTo(User::class, 'sales_id');
    }
}
