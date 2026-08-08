<?php

namespace App\Models;

use App\Models\Stok\Penyesuaian;
use App\Models\Stok\stok;
use App\Models\Transaksi\Penerimaan\Penerimaan_r;
use App\Models\Transaksi\Pengembalian\DetailPengembalian;
use App\Models\Transaksi\Penjualan\DetailPenjualanFifo;
use App\Models\Transaksi\Penjualan\DetailReturPenjualan;
use App\Models\Transaksi\Penjualan\HeaderReturPenjualan;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Barang extends Model
{
    use HasFactory;

    public function incrementViewCount()
    {
        $this->view_count = $this->view_count + 1;
        $this->save();
    }
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

    public function images()
    {
        return $this->hasMany(Imagebarang::class, 'kodebarang', 'kodebarang');
    }
    public function penjualan()
    {
        return $this->hasMany(DetailPenjualanFifo::class, 'kodebarang', 'kodebarang');
    }
    public function penyesuaian()
    {
        return $this->hasMany(Penyesuaian::class, 'kdbarang', 'kodebarang');
    }
    public function returbarang()
    {
        return $this->hasMany(DetailReturPenjualan::class, 'kodebarang', 'kodebarang');
    }

    public function pengembalian()
    {
        return $this->hasMany(DetailPengembalian::class, 'kodebarang', 'kodebarang');
    }

    public function views()
    {
       return $this->hasOne(BarangView::class);
    }
    public function likes()
    {
       return $this->hasOne(BarangLike::class);
    }

    public function scopeMostViewed($query, $limit = 10)
    {
        return $query->orderBy('view_count', 'desc')->limit($limit);

    }

    public function penerimaan()
    {
        return $this->hasMany(Penerimaan_r::class, 'kdbarang', 'kodebarang');
    }
}
