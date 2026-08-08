<?php

namespace App\Http\Controllers\Api\Master;

use App\Helpers\FormatingHelper;
use App\Http\Controllers\Controller;
use App\Models\Beban;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BebanController extends Controller
{
    public function list_data()
    {
        $data = Beban::whereNull('flaging')
        ->when(request('q') !== '' || request('q') !== null, function($x){
           $x->where('beban', 'like', '%' . request('q') . '%');
        })
        ->orderBy('kodebeban', 'asc')
        ->simplePaginate(request('per_page'));

        return new JsonResponse($data);
    }
    public function save_data(Request $request)
    {

        $cari = Beban::where('beban',$request->beban)->whereNull('flaging')->count();
        if($cari > 0)
        {
            return new JsonResponse(
                [
                    'message' => 'Data Sudah Ada',
                ],200
            );
        }

        if(empty($request->kodebeban))
        {
            DB::select('call kodebeban(@nomor)');
            $x = DB::table('counter')->select('kodebeban')->first();

            if (!$x) {
                throw new \Exception('Gagal mendapatkan nomor dari prosedur kodebeban');
            }
            $nomer = (int)$x->kodebeban; // Gunakan nomor dari counter sebagai $total
            // $cek = Transaksi::count();
            // $total = (int) $cek + (int) 1;
            $kode = FormatingHelper::matkdbarang($nomer,'BAN');
        }
        else{
            $kode = $request->kodebeban;
        }

        $simpan = Beban:: updateOrCreate(
            [
                'kodebeban' => $kode
            ],
            [
                'beban' => $request->beban,
                'satuan' => $request->satuan
            ]
        );

        return new JsonResponse(
            [
                'message' => 'Data Tersimpan',
                'result' => $simpan
            ], 200
        );
    }
    public function delete_data(Request $request)
    {
        $updatehapus = Beban::find($request->id);
        $updatehapus->flaging = '1';
        $updatehapus->save();

        return new JsonResponse(
            [
                'message' => 'Data Sudah Dihapus',
            ],200
        );
    }
}
