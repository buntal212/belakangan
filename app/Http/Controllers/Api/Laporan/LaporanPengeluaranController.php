<?php

namespace App\Http\Controllers\Api\Laporan;

use App\Http\Controllers\Controller;
use App\Models\Transaksi\Beban\Transbeban_header;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LaporanPengeluaranController extends Controller
{
    public function getData(){
        $awal=request('tglawal', 'Y-m-d');
        $akhir=request('tglakhir', 'Y-m-d');
        $data = Transbeban_header::whereBetween('transbeban_headers.tgl', [$awal, $akhir])
        ->when(request('q'), function($x) {
            $x->where('transbeban_headers.notrans', 'like', '%' . request('q') . '%')
            ->orWhere('transbeban_headers.keterangan', 'like', '%' . request('q') . '%')
            ->orWhere('transbeban_headers.tgl', 'like', '%' . request('q') . '%');
        })
        ->join('transbeban_rincis', 'transbeban_rincis.notrans', '=', 'transbeban_headers.notrans')
        ->with('rincian', function($query) {
            $query->join('bebans','bebans.kodebeban', '=', 'transbeban_rincis.kodebeban')
                ->select('transbeban_rincis.*',
                        'bebans.beban'
            );
        })
        ->select(
            'transbeban_headers.*',
            DB::raw('sum(transbeban_rincis.subtotal) as total'),
        )
        ->groupBy('transbeban_headers.notrans')
        ->get();
        return new JsonResponse($data);
    }
}
