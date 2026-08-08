<?php

namespace App\Models\Transaksi\Beban;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transbeban_header extends Model
{
    use HasFactory;
    protected $table = 'transbeban_headers';
    protected $guarded = ['id'];

    public function rincian()
    {
        return  $this->hasMany(Transbeban_rinci::class, 'notrans', 'notrans');
    }
}
