<?php

namespace App\Models\Transaksi\Penerimaan;

use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderPembelian_h extends Model
{
    use HasFactory;
    protected $table = 'orderpembelian_h';
    protected $guarded = ['id'];

    public function suplier()
    {
        return  $this->hasOne(Supplier::class, 'kodesupl', 'kdsuplier');
    }

    public function rinci()
    {
        return  $this->hasMany(OrderPembelian_r::class, 'noorder', 'noorder');
    }

    public function penerimaanrinci()
    {
        return  $this->hasMany(Penerimaan_r::class, 'noorder', 'noorder');
    }

}
