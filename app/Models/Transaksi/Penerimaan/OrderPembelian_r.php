<?php

namespace App\Models\Transaksi\Penerimaan;

use App\Models\Barang;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class OrderPembelian_r extends Model
{
    use HasFactory;
    protected $table = 'orderpembelian_r';
    protected $guarded = ['id'];

    public function mbarang()
    {
        return  $this->hasOne(Barang::class, 'kodebarang', 'kdbarang');
    }

    public function penerimaanrinci()
    {
        return  $this->hasMany(Penerimaan_r::class, 'kdbarang', 'kdbarang')
        ->select('noorder', 'kdbarang', DB::raw('SUM(jumlah_b) as total_diterima'))
        ->groupBy('kdbarang', 'noorder');;
    }
}
