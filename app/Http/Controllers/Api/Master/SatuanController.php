<?php

namespace App\Http\Controllers\Api\Master;

use App\Helpers\FormatingHelper;
use App\Http\Controllers\Controller;
use App\Models\Satuan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SatuanController extends Controller
{
    public function listsatuan()
    {

        $data = Satuan::whereNull('flaging')
        ->when(request('q') !== '' || request('q') !== null, function($x){
           $x->where('satuan', 'like', '%' . request('q') . '%');
        })
        ->orderBy('id', 'desc')
        ->simplePaginate(request('per_page'));

        return new JsonResponse($data);
    }

    public function satuansimpan(Request $request)
    {
        $cari = Satuan::where('satuan',$request->satuan)->whereNull('flaging')->count();
        if($cari > 0)
        {
            return new JsonResponse(
                [
                    'message' => 'Data Sudah Ada',
                ],200
            );
        }

        if($request->kodesatuan === '' || $request->kodesatuan === null)
        {
            $cek = Satuan::count();
            $total = (int) $cek + (int) 1;
            $kodesatuan = FormatingHelper::matkdbarang($total,'ST');
        }else{
            $kodesatuan = $request->kodesatuan;
        }
        $simpan = Satuan::updateOrCreate(
            [
                'kodesatuan' => $kodesatuan
            ],
            [
                'satuan' => $request->satuan
            ]
        );

        return new JsonResponse(
            [
                'message' => 'Data Tersimpan',
                'result' => $simpan
            ],200
        );
    }

    public function hapussatuan(Request $request)
    {
        $updatehapus = Satuan::find($request->id);
        $updatehapus->flaging = '1';
        $updatehapus->save();

        return new JsonResponse(
            [
                'message' => 'Data Sudah Dihapus',
            ],200
        );
    }
}
