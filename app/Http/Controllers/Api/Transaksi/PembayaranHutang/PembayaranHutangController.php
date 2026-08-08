<?php

namespace App\Http\Controllers\Api\Transaksi\PembayaranHutang;

use App\Helpers\FormatingHelper;
use App\Http\Controllers\Controller;
use App\Models\Transaksi\Pembayaranhutang\pembayaranhutang_h;
use App\Models\Transaksi\Pembayaranhutang\pembayaranhutang_r;
use App\Models\Transaksi\Penerimaan\Penerimaan_h;
use Illuminate\Database\Eloquent\Casts\Json;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PembayaranHutangController extends Controller
{
    public function index()
    {
        $from = request('from');
        $to = request('to');
        $data = pembayaranhutang_h::with(
            [
                'rinci' => function($rinci){
                    $rinci->with(['penerimaan']);
                },
                'supplier'
            ]
        )->whereBetween('tgl_bayar', [
            $from,
            $to
        ])
        ->when(request('q'), function ($query) {
            $query->where(function($q) {
                $q->where('pembayaran_hutang_h.notrans', 'like', '%' . request('q') . '%');
            });
        })
        ->orderBy('id', 'desc')
        ->simplePaginate(request('per_page'));
        return new JsonResponse($data);
    }

    public function listhutangbynopembayaran()
    {
        // return request('nopenerimaan');
        $data = pembayaranhutang_h::with(
            [
                'supplier',
                'rinci' => function($rinci){
                    $rinci->with(['penerimaan']);
                }
            ]
        )
        ->where('nopenerimaan', request('nopenerimaan'))
        ->get();
        return new JsonResponse($data);
    }

    public function listhutang()
    {

        $data = Penerimaan_h::with([
            'suplier',
            'rinci' => function($rinci){
                $rinci->with(['mbarang']);
            },
            'rincianpembayaranhutang' => function($rincianpembayaranhutang){
                $rincianpembayaranhutang->select('nopenerimaan', DB::raw('sum(total) as totalbayar'))->groupBy('nopenerimaan');
            }
        ])
        ->where('jenis_pembayaran', 'Hutang')
        // ->where('flagingHutang', null)->orwhere('flagingHutang', '')
        ->where('kdsupllier', request('kdsupplier'))
        ->orderBy('tgljatuhtempo', 'asc')
        ->get();
        return new JsonResponse($data);
    }

    public function simpan(Request $request)
    {
        if($request->nopembayaran === '' || $request->nopembayaran === null)
        {
            DB::select('call noPembayaranHutang(@nomor)');
            $x = DB::table('counter')->select('nopembayaranhutang')->get();
            $no = $x[0]->nopembayaranhutang;
            $nopembayaran = FormatingHelper::nopembayaranhutang($no, 'BH');
        }else{
            $nopembayaran = $request->nopembayaran;
        }
        try {
            DB::beginTransaction();
            $data = pembayaranhutang_h::updateOrCreate(
                [
                    'notrans' => $nopembayaran,
                ],
                [
                    'tgl_bayar' => $request->tgl,
                    'kdsupllier' => $request->kdsuplier,
                    'cara_bayar' => $request->carabayar,
                    'keterangan' => $request->keterangan,
                    'user' => Auth::id(),
            ]);

            $datax = pembayaranhutang_r::create(
                [
                    'notrans' => $nopembayaran,
                    'nopenerimaan' => $request->nopenerimaan,
                    'total' => $request->total,
                    'user' => Auth::id(),
                ]
            );

            // $flagpenerimaan = Penerimaan_h::where('nopenerimaan', $request->nopenerimaan)->first();
            // $flagpenerimaan->flagingHutang = '1';
            // $flagpenerimaan->save();
            DB::commit();
            $hasil = self::getlistpembayaranbynotrans($nopembayaran);
            $newhutang = self::listhutangx($request->kdsuplier);
            return new JsonResponse([
                'message' => 'Data Berhasil Disimpan',
                'data' => $hasil,
                'nopenerimaan' => $request->nopenerimaan,
                'newhutang' => $newhutang
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return new JsonResponse([
                'message' => 'Terjadi Kesalahan ' . $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ], 410);
        }
    }

    public static function getlistpembayaranbynotrans($notrans)
    {
       $data = pembayaranhutang_h::with(
            [
                'rinci' => function($rinci){
                    $rinci->with(['penerimaan']);
                },
                'supplier'
            ]
        )->where('notrans', $notrans)
        ->get();
        return $data;
    }

    public static function listhutangx($kdsupplier)
    {
        $data = Penerimaan_h::with([
            'suplier',
            'rinci' => function($rinci){
                $rinci->with(['mbarang']);
            },
            'rincianpembayaranhutang' => function($rincianpembayaranhutang){
                $rincianpembayaranhutang->select('nopenerimaan', DB::raw('sum(total) as totalbayar'))->groupBy('nopenerimaan');
            }
        ])
        ->where('jenis_pembayaran', 'Hutang')
        // ->where('flagingHutang', null)->orwhere('flagingHutang', '')
        ->where('kdsupllier', $kdsupplier)
        ->orderBy('tgljatuhtempo', 'asc')
        ->get();
        return $data;
    }

    public function hapusrincian(Request $request)
    {
        $nopembayaran = $request->notrans;
        try {
            DB::beginTransaction();
                $data = pembayaranhutang_r::where('id', request('id'))->delete();

            DB::commit();
            $hasil = self::getlistpembayaranbynotrans($nopembayaran);
             return new JsonResponse([
                'message' => 'Data Berhasil Disimpan',
                'data' => $hasil
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return new JsonResponse([
                'message' => 'Terjadi Kesalahan ' . $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ], 410);
        }
    }

}
