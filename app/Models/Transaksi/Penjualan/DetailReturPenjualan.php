<?php

namespace App\Models\Transaksi\Penjualan;

use App\Models\Barang;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DetailReturPenjualan extends Model
{
    use HasFactory;

    protected $guarded = ['id'];
    public function masterBarang()
    {
        return $this->belongsTo(Barang::class, 'kodebarang', 'kodebarang');
    }

    public function headerRetur()
    {
        return $this->belongsTo(HeaderReturPenjualan::class, 'header_retur_penjualan_id');
    }

    public function detailPenjualan()
    {
        return $this->belongsTo(DetailPenjualan::class, 'detail_penjualan_id');
    }
}
