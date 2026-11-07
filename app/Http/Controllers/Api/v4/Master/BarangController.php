<?php

namespace App\Http\Controllers\Api\v4\Master;

use App\Http\Controllers\Controller;
use App\Models\Barang;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BarangController extends Controller
{
    public function listbarang(Request $request)
    {
        $alokasiOrder = DB::table('rincian_order_penjualans as rincian')
            ->join('order_penjualans as order_penjualan', 'order_penjualan.id', '=', 'rincian.order_penjualan_id')
            ->whereIn('order_penjualan.status_order', ['1', '2', '3'])
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('header_penjualans as penjualan')
                    ->whereColumn('penjualan.noorder', 'order_penjualan.noorder')
                    ->whereNotNull('penjualan.flag')
                    ->where('penjualan.flag', '<>', '6');
            })
            ->select('rincian.barang_id', 'rincian.jumlah')
            ->unionAll(
                DB::table('detail_penjualans as dp')
                    ->join('header_penjualans as hp', 'dp.no_penjualan', '=', 'hp.no_penjualan')
                    ->join('barangs as b', 'dp.kodebarang', '=', 'b.kodebarang')
                    ->whereNull('hp.flag')
                    ->whereNull('hp.noorder')
                    ->select('b.id as barang_id', 'dp.jumlah')
            );

        $alokasiTotal = DB::table($alokasiOrder, 'alokasi_gabungan')
            ->select('barang_id', DB::raw('SUM(jumlah) AS stok_alokasi'))
            ->groupBy('barang_id');

        $query = Barang::query()
            ->leftJoin('stoks', 'stoks.kdbarang', '=', 'barangs.kodebarang')
            ->leftJoinSub($alokasiTotal, 'alokasi_order', function ($join) {
                $join->on('alokasi_order.barang_id', '=', 'barangs.id');
            })
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
            ->selectRaw('COALESCE(MAX(alokasi_order.stok_alokasi), 0) AS stok_alokasi')
            ->selectRaw('GREATEST(COALESCE(SUM(CASE WHEN stoks.jumlah_k > 0 THEN stoks.jumlah_k ELSE 0 END), 0) - COALESCE(MAX(alokasi_order.stok_alokasi), 0), 0) AS stok_bebas')
            ->groupBy('barangs.kodebarang', 'barangs.namabarang', 'barangs.namagabung', 'barangs.satuan_k', 'barangs.satuan_b', 'barangs.isi', 'barangs.hargajual1', 'imagebarangs.gambar')
            ->havingRaw('stok_bebas > 0')
            ->orderBy('barangs.namabarang');

        return response()->json($query->simplePaginate($request->integer('per_page', 20)));
    }
}
