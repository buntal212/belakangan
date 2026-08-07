<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Imagebarang extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    public function barang() {
        return $this->belongsTo(Barang::class, 'kodebarang', 'kodebarang');
    }

    // protected $appends = ['gambar_array'];

    // public function getGambarArrayAttribute() {
    //     return explode(',', $this->gambar_list);
    // }
}
