<?php

namespace App\Http\Controllers\Api\Transaksi\Stok;

use App\Http\Controllers\Controller;
use App\Models\Stok\stok;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class stokcontroller extends Controller
{
    public function lihatstok()
    {
        $nilaiAsetStok = (float) stok::query()
            ->selectRaw('COALESCE(SUM(jumlah_k * harga_beli_k), 0) as total')
            ->value('total');

        $data = stok::select(
            'stoks.*',
            'barangs.*',
            'penerimaan.created_at as tanggal_penerimaan',
            'stoks.created_at as tanggal_stok'
        )
        ->join('barangs', 'stoks.kdbarang','barangs.kodebarang')
        ->leftJoin('penerimaan_h as penerimaan', 'penerimaan.nopenerimaan', '=', 'stoks.nopenerimaan')
        ->where('stoks.jumlah_k', '>', 0)
        ->where(function ($query) {
            $query->where('stoks.kdbarang', 'LIKE', '%' . request('q') . '%')
                ->orWhere('barangs.namabarang', 'LIKE', '%' . request('q') . '%');
        })->simplePaginate();

        return new JsonResponse([
            ...$data->toArray(),
            'nilai_aset_stok' => $nilaiAsetStok,
        ]);
    }
}
