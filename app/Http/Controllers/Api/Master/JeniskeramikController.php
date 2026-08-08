<?php

namespace App\Http\Controllers\Api\Master;

use App\Helpers\FormatingHelper;
use App\Http\Controllers\Controller;
use App\Models\Jeniskeramik;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class JeniskeramikController extends Controller
{

     public function list_data()
    {

        $data = Jeniskeramik::whereNull('flaging')
        ->when(request('q') , function($x){
           $x->where('nama', 'like', '%' . request('q') . '%');
        })
        ->orderBy('nama', 'asc')
        ->simplePaginate(request('per_page'));

        return new JsonResponse($data);
    }

    public function save_data(Request $request)
    {
        $cari = Jeniskeramik::where('nama',$request->nama)->whereNull('flaging')->count();
        if($cari > 0)
        {
            return new JsonResponse(
                [
                    'message' => 'Data Sudah Ada',
                ],200
            );
        }

        if($request->kodejenis === '' || $request->kodejenis === null)
        {
            $cek = Jeniskeramik::count();
            $total = (int) $cek + (int) 1;
            $kodejenis = FormatingHelper::matkdbarang($total,'JNS');
        }else{
            $kodejenis = $request->kodejenis;
        }
        $simpan = Jeniskeramik::updateOrCreate(
            [
                'kodejenis' => $kodejenis
            ],
            [
                'nama' => $request->nama
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
        $updatehapus = Jeniskeramik::find($request->id);
        $updatehapus->flaging = '1';
        $updatehapus->save();

        return new JsonResponse(
            [
                'message' => 'Data Sudah Dihapus',
            ],200
        );
    }
}


