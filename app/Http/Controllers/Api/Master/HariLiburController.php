<?php

namespace App\Http\Controllers\Api\Master;

use App\Http\Controllers\Controller;
use App\Models\HariLibur;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HariLiburController extends Controller
{
    public function save_data(Request $request)
    {
        try{
            DB::beginTransaction();
                $simpan = HariLibur::updateOrCreate(
                    [
                        'id' => $request->id
                    ],
                    [
                        'tgl' => $request->tgl,
                        'keterangan' => $request->keterangan
                    ]
                );
            DB::commit();
            return new JsonResponse(['message' => 'DataSudah Tersimpan', 'data' => $simpan],200);
        }catch (\Exception $e){
            DB::rollBack();
            return new JsonResponse(['message' => 'ada kesalahan', 'error' => $e], 500);
        }
    }

    public function list_data()
    {
        $data = HariLibur::when(request('q') !== '' || request('q') !== null, function($x){
           $x->where('keterangan', 'like', '%' . request('q') . '%');
        })
        ->get();
        return new JsonResponse($data);
    }
}
