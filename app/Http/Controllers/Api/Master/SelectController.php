<?php

namespace App\Http\Controllers\Api\Master;

use App\Helpers\FormatingHelper;
use App\Http\Controllers\Controller;
use App\Models\Barang;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SelectController extends Controller
{
    public function satuan_all()
    {
       $data = DB::table('satuans')
       ->select('satuan', 'flaging')
       ->get();

       return new JsonResponse($data);
    }
    public function satuan_filter()
    {
       $data = DB::table('satuans')
        ->select('satuan', 'flaging')
        ->where('satuan', 'like', '%' . request('q') . '%')
        ->limit(request('limit'))
        ->get();

       return new JsonResponse($data);
    }

    public function barang_filter()
    {
        $data = DB::table('barangs')
        ->select('*')
        ->where('namabarang','like','%'. request('q') . '%')
        ->limit(request('limit'))
        ->get();

        return new JsonResponse($data);
    }

    public function get_brand()
    {
       $data = DB::table('brands')
       ->select('brand', 'flaging')
       ->get();

       return new JsonResponse($data);
    }
}
