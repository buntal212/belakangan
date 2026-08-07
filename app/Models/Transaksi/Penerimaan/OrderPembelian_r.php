<?php

namespace App\Models\Transaksi\Penerimaan;

use App\Models\Barang;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderPembelian_r extends Model
{
    use HasFactory;
    protected $table = 'orderpembelian_r';
    protected $guarded = ['id'];

    public function mbarang()
    {
        return  $this->hasOne(Barang::class, 'kodebarang', 'kdbarang');
    }
}
