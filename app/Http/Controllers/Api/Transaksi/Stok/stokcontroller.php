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
        $data = stok::select('stoks.*','barangs.*')
        ->join('barangs', 'stoks.kdbarang','barangs.kodebarang')
        ->where(function ($query) {
            $query->where('stoks.kdbarang', 'LIKE', '%' . request('q') . '%')
                ->orWhere('barangs.namabarang', 'LIKE', '%' . request('q') . '%');
        })->simplePaginate();

        return new JsonResponse($data);
    }
}
