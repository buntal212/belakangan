<?php

namespace App\Models;

use App\Models\Transaksi\Penjualan\HeaderCicilan;
use App\Models\Transaksi\Penjualan\HeaderPenjualan;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Pelanggan extends Model
{
    use HasFactory;
    protected $guarded = ['id'];

    public function headerPenjualan()
    {
        return $this->hasMany(HeaderPenjualan::class);
    }
    public function headerCicilan()
    {
        return $this->hasMany(HeaderCicilan::class);
    }
}
