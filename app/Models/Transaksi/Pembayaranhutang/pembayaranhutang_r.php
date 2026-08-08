<?php

namespace App\Models\Transaksi\Pembayaranhutang;

use App\Models\Transaksi\Penerimaan\Penerimaan_h;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class pembayaranhutang_r extends Model
{
    use HasFactory;
    protected $table = 'pembayaran_hutang_r';
    protected $guarded = ['id'];

     public function penerimaan()
    {
        return  $this->hasOne(Penerimaan_h::class, 'nopenerimaan', 'nopenerimaan');
    }
}
