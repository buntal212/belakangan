<?php

namespace App\Http\Controllers\Api\Transaksi\Stok;

use App\Http\Controllers\Controller;
use App\Models\Barang;
use App\Models\Stok\stok;
use App\Models\Stok\StokOpname;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class StokOpnameController extends Controller
{
    //
    public function index()
    {
        $bulan = request('bulan');
        $tahun = request('tahun');
        $search = request('q');
        $kodebarang = Barang::select('kodebarang')->where('namabarang', 'like', '%' . $search . '%')->pluck('kodebarang')->toArray();
        $raw = StokOpname::where('tgl_opname', 'like', $tahun . '-' . $bulan . '%')
            ->when(sizeof($kodebarang) > 0, function ($query) use ($kodebarang) {
                return $query->whereIn('kdbarang', $kodebarang);
            })
            ->where('jumlah_k', '!=', 0)
            ->with(['barang'])
            ->paginate(request('per_page'));
        $data['data'] = collect($raw)['data'];
        $data['meta'] = collect($raw)->except('data');

        return new JsonResponse($data);
    }

    public static function storeMonthly()
    {
        $now = Carbon::now();
        $tglSekarang = Carbon::now();
        $toOpname = [];

        if ($now->day === 1) {
            $hariTerakhirBulanKemarin = $tglSekarang->subMonth()->endOfMonth()->setTime(23, 59, 59);
            $message = 'Stok opname berhasil dilakukan pada ' . $now->format('Y-m-d H:i:s') . ' dicatat sebagai tanggal opname ' . $hariTerakhirBulanKemarin->format('Y-m-d H:i:s');
            $tglOpname = $hariTerakhirBulanKemarin->format('Y-m-d H:i:s');
            $stok = stok::where('jumlah_k', '>', 0)->get();
            foreach ($stok as $s) {
                $data = $s;
                $data['created_at'] = date('Y-m-d H:i:s');
                $data['updated_at'] = date('Y-m-d H:i:s');
                $data['tgl_opname'] = $tglOpname;
                unset($data['id']);
                $toOpname[] = $data->toArray();
            }
            StokOpname::where('tgl_opname', $tglOpname)->delete();
            StokOpname::insert($toOpname);
        } else {
            $message = 'Hari ini belum tanggal 1, stok opname tidak dilakukan, sekarang tanggal ' . $now->day;
        }


        return [
            'message' => $message,
            'data' => $toOpname,
        ];
    }
}
