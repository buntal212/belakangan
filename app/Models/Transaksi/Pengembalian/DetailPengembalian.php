<?php

namespace App\Models\Transaksi\Pengembalian;

use App\Models\Barang;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DetailPengembalian extends Model
{
    use HasFactory;
    protected $guarded = ['id'];

    // Relationships
    public function header()
    {
        return $this->belongsTo(HeaderPengembalian::class, 'header_pengembalian_id');
    }

    public function barang()
    {
        return $this->belongsTo(Barang::class);
    }
}
