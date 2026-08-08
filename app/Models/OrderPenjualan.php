<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderPenjualan extends Model
{
    use HasFactory;

    protected $table = 'order_penjualans';

    protected $guarded = ['id'];

    // Relasi ke User (pelanggan)
    public function pelanggan()
    {
        return $this->belongsTo(Pelanggan::class, 'pelanggan_id');
    }

    // Relasi ke User (sales)
    public function sales()
    {
        return $this->belongsTo(User::class, 'sales_id');
    }

    // Relasi ke rincian order penjualan
    public function rincians()
    {
        return $this->hasMany(OrderPenjualanRincian::class, 'order_penjualan_id');
    }
}