<?php

namespace App\Models\Transaksi\NotaSales;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class notasales_h extends Model
{
    use HasFactory;
    protected $table = 'notasales_h';
    protected $guarded = ['id'];

    public function rinci()
    {
        return  $this->hasMany(notasales_r::class, 'notrans', 'notrans');
    }
}
