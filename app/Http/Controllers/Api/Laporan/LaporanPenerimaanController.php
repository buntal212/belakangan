<?php

namespace App\Http\Controllers\Api\Laporan;

use App\Http\Controllers\Controller;
use App\Models\Transaksi\Penerimaan\Penerimaan_h;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LaporanPenerimaanController extends Controller
{
    public function getData(){
        $awal=request('tglawal', 'Y-m-d');
        $akhir=request('tglakhir', 'Y-m-d');

        $data = Penerimaan_h::where('penerimaan_h.kunci', '=', '1')
        ->leftJoin('suppliers', 'suppliers.kodesupl', '=', 'penerimaan_h.kdsupllier')
        ->leftJoin('penerimaan_r', 'penerimaan_r.nopenerimaan', '=', 'penerimaan_h.nopenerimaan')
        ->whereBetween('penerimaan_h.tgl_faktur', [$awal, $akhir])
        ->when(request('supplier'), function($x) {
            $x->where('penerimaan_h.kdsupllier', request('supplier'));
        })
        ->when(request('jnsbayar'), function($x) {
            $x->where('penerimaan_h.jenis_pembayaran', request('jnsbayar'));
        })
        ->when(request('q'), function($x) {
            $x->where('penerimaan_h.nopenerimaan', 'like', '%' . request('q') . '%')
            ->orWhere('penerimaan_h.noorder', 'like', '%' . request('q') . '%')
            ->orWhere('penerimaan_h.nofaktur', 'like', '%' . request('q') . '%');
        })
        ->select(
            'penerimaan_h.*',
            'suppliers.nama as namasupplier',
            DB::raw('sum(penerimaan_r.subtotalfix) as total'),
        )
        ->with('rinci', function($query){
            $query->join('barangs', 'barangs.kodebarang', '=', 'penerimaan_r.kdbarang')
            ->select(
                'penerimaan_r.*',
                'barangs.namabarang',
                'barangs.kategori',
            );
        })
        ->groupBy('penerimaan_h.nopenerimaan')
        ->get();
        return new JsonResponse($data);
    }
}
