<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderPenjualanRincian extends Model
{
    use HasFactory;

    protected $table = 'rincian_order_penjualans';

    protected $guarded = ['id'];

    protected $casts = [
        'satuans' => 'array',
    ];

    // Relasi ke order penjualan
    public function orderPenjualan()
    {
        return $this->belongsTo(OrderPenjualan::class, 'order_penjualan_id');
    }

    // Relasi ke barang (jika ada model barang)
    public function barang()
    {
        return $this->belongsTo(Barang::class, 'barang_id');
    }
}