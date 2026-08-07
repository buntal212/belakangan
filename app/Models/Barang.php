<?php

namespace App\Models;

use App\Models\Stok\stok;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Barang extends Model
{
    use HasFactory;
    protected $guarded = ['id'];
    public function rincians()
    {
        return $this->hasMany(Imagebarang::class, 'kodebarang', 'kodebarang');
    }
    public function stoks()
    {
        return $this->hasMany(stok::class, 'kdbarang', 'kodebarang');
    }
    public function stok()
    {
        return $this->hasOne(stok::class, 'kdbarang', 'kodebarang');
    }
}
