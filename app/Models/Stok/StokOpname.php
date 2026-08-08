<?php

namespace App\Models\Stok;

use App\Models\Barang;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StokOpname extends Model
{
    use HasFactory;
    protected $guarded = ['id'];

    public function barang()
    {
        return $this->belongsTo(Barang::class, 'kdbarang', 'kodebarang');
    }
}
