<?php

namespace App\Models\Stok;

use App\Models\Barang;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class stok extends Model
{
    use HasFactory;
    protected $guarded = ['id'];

    public function mbarang()
    {
        return  $this->hasOne(Barang::class, 'kdbarang', 'kdbarang');
    }
}
