<?php

namespace App\Http\Controllers\Api\Transaksi\Beban;

use App\Helpers\FormatingHelper;
use App\Http\Controllers\Controller;
use App\Models\Beban;
use App\Models\Transaksi\Beban\Transbeban_header;
use App\Models\Transaksi\Beban\Transbeban_rinci;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TransBebanController extends Controller
{
    public function list_data()
    {
        $from = request('from');
        $to = request('to');
        $list = Transbeban_header::whereBetween('transbeban_headers.tgl', [$from, $to])
        ->join('transbeban_rincis', 'transbeban_rincis.notrans', '=', 'transbeban_headers.notrans')
        ->select( 'transbeban_headers.*',
            DB::raw('sum(transbeban_rincis.subtotal) as total')
        )
        ->when(request('q'), function($query){
            $query->where('transbeban_headers.notrans', 'like', '%' . request('q') . '%')
            ->orWhere('transbeban_headers.keterangan', 'like', '%' . request('q') . '%');
        })
        ->with(['rincian'=>function($query){
            $query->join('bebans', 'bebans.kodebeban', '=', 'transbeban_rincis.kodebeban')
            ->select('transbeban_rincis.*', 'bebans.beban as beban');
        }])
        ->orderBy('id', 'desc')
        ->groupBy('notrans')
        ->simplePaginate(request('per_page'));
        return new JsonResponse($list);
    }
    public function save_data(Request $request)
    {
        if(empty($request->notrans))
        {
            DB::select('call notransbeban(@nomor)');
            $x = DB::table('counter')->select('notransbeban')->first();
            if(!$x){
                throw new \Exception('Gagal mendapatkan nomor dari prosedur notransbeban');
            }
            $nomor = (int)$x->notransbeban;
            $notrans = FormatingHelper::nopenerimaan($nomor, 'T.BEBAN');
        } else {
            $notrans = $request->notrans;
        }
        try{
            DB::beginTransaction();
            $simpan = Transbeban_header::updateOrCreate(
                [
                    'notrans' => $notrans
                ],
                [
                    'tgl' => $request->tgl,
                    'keterangan' => $request->keterangan,
                    'user' => Auth::id(),
                ]
            );
            Transbeban_rinci::create(
                [
                    'notrans' => $notrans,
                    'kodebeban' => $request->kodebeban,
                    'volume' => $request->volume,
                    'satuan' => $request->satuan,
                    'nominal'=> $request->nominal,
                    'subtotal' => $request->volume * $request->nominal
                ]
            );
            DB::commit();
            $hasil = self::getlistrincian($notrans);
            return new JsonResponse(
                [
                    'message' => 'Data Berhasil Disimpan',
                    'notrans' => $notrans,
                    'result' => $hasil
                ]
            );

        }catch (\Exception $e){
            DB::rollBack();
            return new JsonResponse(['message' => 'ada kesalahan', 'error' => $e->getMessage()], 500);
        }
    }
    public static function getlistrincian($notrans)
    {
        $list = Transbeban_header::join('transbeban_rincis', 'transbeban_rincis.notrans', '=', 'transbeban_headers.notrans')
        ->select( 'transbeban_headers.*',
            DB::raw('sum(transbeban_rincis.subtotal) as total')
        )->with([
            'rincian'=> function($data){
                $data->join('bebans', 'bebans.kodebeban', '=', 'transbeban_rincis.kodebeban')
                ->select('transbeban_rincis.*', 'bebans.beban as beban');
            }]
        )
        ->groupBy('transbeban_headers.notrans')
        ->where('transbeban_headers.notrans', $notrans)
        ->orderBy('transbeban_headers.id', 'desc')
        ->get();
        return $list;
    }

    public function kunci(Request $request)
    {
        try {
            $header = Transbeban_header::where('notrans', $request->notrans)->first();
            if ($header->flaging == '1') {
                return response()->json(['message' => 'Maaf Data ini Sudah Terkunci'], 200);
            } else {
                $header->flaging = '1';
                $header->save();

                return response()->json([
                    'message' => 'Data Berhasil Dikunci',
                    'result' => $header
                ], 200);
            }
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Terjadi Kesalahan saat Membuka Kunci',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    public function delete_data(Request $request)
    {

        try {
            DB::beginTransaction();

            // Cek apakah transaksi sudah lunas
            $header = Transbeban_header::where('notrans', $request->notrans)
                ->where('flaging', '=', 1)
                ->first();

            if ($header) {
                return new JsonResponse(['message' => 'Transaksi sudah Terkunci'], 400);
            }

            // Cek apakah rincian ada (jika id diberikan)
            if ($request->id) {
                $findrinci = Transbeban_rinci::where('id', $request->id)
                    ->where('notrans', $request->notrans)
                    ->first();

                if (!$findrinci) {
                    return new JsonResponse(['message' => 'Data rincian tidak ditemukan'], 404);
                }

                $findrinci->delete();
            }

            // Cek apakah masih ada rincian lain
            $rinciAll = Transbeban_rinci::where('notrans', $request->notrans)->get();

            if ($rinciAll->isEmpty()) {
                $header = Transbeban_header::where('notrans', $request->notrans)->first();
                if ($header) {
                    $header->delete();
                    DB::commit();
                    return new JsonResponse(['message' => 'Data transaksi berhasil dihapus'], 200);
                } else {
                    DB::commit();
                    return new JsonResponse(['message' => 'Data header tidak ditemukan'], 404);
                }
            }

            DB::commit();
            return new JsonResponse([
                'message' => 'Data rincian berhasil dihapus',
                'data' => $rinciAll
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return new JsonResponse([
                'message' => 'Terjadi kesalahan saat menghapus data',
                'error' => $e->getMessage()
            ], 500);
        }

    }
}
