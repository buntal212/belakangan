<?php

namespace App\Models\Transaksi\NotaSales;

use App\Models\Transaksi\Penjualan\HeaderPenjualan;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class notasales_r extends Model
{
    use HasFactory;
    protected $table = 'notasales_r';
    protected $guarded = ['id'];

    public function hederpenjualan()
    {
        return  $this->hasOne(HeaderPenjualan ::class, 'no_penjualan', 'notaPenjualan');
    }
}
