<?php

namespace App\Models\Transaksi\Pembayaranhutang;

use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class pembayaranhutang_h extends Model
{
    use HasFactory;
    protected $table = 'pembayaran_hutang_h';
    protected $guarded = ['id'];

    public function rinci()
    {
        return  $this->hasMany(pembayaranhutang_r::class, 'notrans', 'notrans');
    }

    public function supplier()
    {
        return  $this->hasOne(Supplier::class, 'kodesupl', 'kdsupllier');
    }
}
