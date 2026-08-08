<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BarangView extends Model
{
    use HasFactory;
    protected $table = 'product_views';
    protected $fillable = ['barang_id', 'views'];
}
