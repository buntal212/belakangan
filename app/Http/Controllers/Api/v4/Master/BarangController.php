<?php

namespace App\Http\Controllers\Api\v4\Master;

use App\Http\Controllers\Controller;
use App\Models\Barang;
use Illuminate\Http\Request;

class BarangController extends Controller
{
    public function listbarang(Request $request)
    {
        $query = Barang::query()
            ->leftJoin('stoks', 'stoks.kdbarang', '=', 'barangs.kodebarang')
            ->leftJoin('imagebarangs', function ($join) {
                $join->on('imagebarangs.kodebarang', '=', 'barangs.kodebarang')
                    ->where('imagebarangs.flag_thumbnail', 1);
            })
            ->when($request->filled('q'), function ($builder) use ($request) {
                $keyword = '%' . $request->q . '%';
                $builder->where(function ($search) use ($keyword) {
                    $search->where('barangs.namabarang', 'like', $keyword)
                        ->orWhere('barangs.namagabung', 'like', $keyword)
                        ->orWhere('barangs.kodebarang', 'like', $keyword);
                });
            })
            ->select('barangs.kodebarang', 'barangs.namabarang', 'barangs.namagabung', 'barangs.satuan_k', 'barangs.satuan_b', 'barangs.isi', 'barangs.hargajual1', 'imagebarangs.gambar AS image')
            ->selectRaw('(COALESCE(barangs.hargajual1, 0) * COALESCE(NULLIF(barangs.isi, 0), 1)) AS hargajual1besar')
            ->selectRaw('COALESCE(SUM(CASE WHEN stoks.jumlah_k > 0 THEN stoks.jumlah_k ELSE 0 END), 0) AS stok_kecil')
            ->groupBy('barangs.kodebarang', 'barangs.namabarang', 'barangs.namagabung', 'barangs.satuan_k', 'barangs.satuan_b', 'barangs.isi', 'barangs.hargajual1', 'imagebarangs.gambar')
            ->havingRaw('stok_kecil > 0')
            ->orderBy('barangs.namabarang');

        return response()->json($query->simplePaginate($request->integer('per_page', 20)));
    }
}
