<?php

namespace App\Http\Controllers\Api;

use App\Helpers\FormatingHelper;
use App\Http\Controllers\Controller;
use App\Models\Barang;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CobaController extends Controller
{
    public function list()
    {
        $data = Barang::whereNull('flaging')
        ->when(request('q') !== '' || request('q') !== null, function($x){
            $x->where('namabarang', 'like', '%' . request('q') . '%')
              ->orWhere('kodebarang','like', '%' . request('q') . '%');
        })
        ->orderBy('id', 'desc')
        ->simplePaginate(request('per_page'));
        return new JsonResponse($data);
    }

    
}
