<?php

namespace App\Http\Controllers\Api\Master;

use App\Helpers\FormatingHelper;
use App\Http\Controllers\Controller;
use App\Models\Brand;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BrandsController extends Controller
{
     public function list_data()
    {

        $data = Brand::whereNull('flaging')
        ->when(request('q') !== '' || request('q') !== null, function($x){
           $x->where('brand', 'like', '%' . request('q') . '%');
        })
        ->orderBy('id', 'desc')
        ->simplePaginate(request('per_page'));

        return new JsonResponse($data);
    }

    public function save_data(Request $request)
    {
        $cari = Brand::where('brand',$request->brand)->whereNull('flaging')->count();
        if($cari > 0)
        {
            return new JsonResponse(
                [
                    'message' => 'Data Sudah Ada',
                ],200
            );
        }

        if($request->kodebrand === '' || $request->kodebrand === null)
        {
            $cek = Brand::count();
            $total = (int) $cek + (int) 1;
            $kodebrand = FormatingHelper::matkdbarang($total,'MRK');
        }else{
            $kodebrand = $request->kodebrand;
        }
        $simpan = Brand::updateOrCreate(
            [
                'kodebrand' => $kodebrand
            ],
            [
                'brand' => $request->brand
            ]
        );

        return new JsonResponse(
            [
                'message' => 'Data Tersimpan',
                'result' => $simpan
            ],200
        );
    }

    public function delete_data(Request $request)
    {
        $updatehapus = Brand::find($request->id);
        $updatehapus->flaging = '1';
        $updatehapus->save();

        return new JsonResponse(
            [
                'message' => 'Data Sudah Dihapus',
            ],200
        );
    }
}
