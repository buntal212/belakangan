<?php

namespace App\Models\Transaksi\Penjualan;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HeaderCicilan extends Model
{
    use HasFactory;
    protected $guarded = ['id'];
    public function cicilan()
    {
        return $this->hasMany(PembayaranCicilan::class);
    }
}
