<?php

namespace App\Models\Transaksi\Beban;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transbeban_rinci extends Model
{
    use HasFactory;
    protected $table = 'transbeban_rincis';
    protected $guarded = ['id'];


}
