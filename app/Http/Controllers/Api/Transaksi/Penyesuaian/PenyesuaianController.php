<?php

namespace App\Http\Controllers\Api\Transaksi\Penyesuaian;

use App\Helpers\FormatingHelper;
use App\Http\Controllers\Controller;
use App\Models\Stok\Penyesuaian;
use App\Models\Stok\stok;
use App\Models\Transaksi\Penerimaan\Penerimaan_h;
use App\Models\Transaksi\Penjualan\DetailPenjualanFifo;
use App\Models\Transaksi\Penjualan\HeaderPenjualan;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PenyesuaianController extends Controller
{
    public function datapenyesuaian()
    {
        $data = Penyesuaian::whereNotNull('kdbarang')
        ->join('barangs', 'barangs.kodebarang', 'penyesuaians.kdbarang')
        ->select('penyesuaians.*', 'barangs.namabarang', 'barangs.satuan_k')
        ->when(request('q'), function ($query) {
            $query->where(function ($q) {
                $q->where('barangs.namabarang', 'like', '%' . request('q') . '%')
                    ->orWhere('penyesuaians.kdbarang', 'like', '%' . request('q') . '%')
                    ->orWhere('penyesuaians.nopenyesuaian', 'like', '%' . request('q') . '%');
            });
        })
        ->orderBy('penyesuaians.id', 'desc')
        ->simplePaginate(request('per_page'));


        return new JsonResponse($data);
    }

     public function selectstok()
    {
        $data = stok::whereNotNull('kdbarang')
        ->join('barangs', 'barangs.kodebarang', 'stoks.kdbarang')
        ->select('stoks.*', 'barangs.namabarang')
        ->when(request('q'), function ($query) {
            $query->where(function ($q) {
                $q->where('barangs.namabarang', 'like', '%' . request('q') . '%')
                    ->orWhere('stoks.kdbarang', 'like', '%' . request('q') . '%');
            });
        })
        ->simplePaginate(request('per_page'));


        return new JsonResponse($data);
    }

     public function save(Request $request): JsonResponse
    {
        // Validasi input
        $messages = [
            'kdbarang.required' => 'Kode Barang tidak boleh kosong',
            'stok_id.required' => 'Stok ID tidak boleh kosong',
            'jumlah_k.required' => 'Jumlah tidak boleh kosong',
            'jumlah_k.numeric' => 'Jumlah harus berupa angka',
            'jumlah_k.not_in' => 'Jumlah tidak boleh nol',
            'tgl.required' => 'Tanggal tidak boleh kosong',
            'keterangan.required' => 'Keterangan harus diisi'
        ];

        $request->validate([
            'kdbarang' => 'required|string',
            'stok_id' => 'required',
            'jumlah_k' => 'required|numeric|not_in:0',
            'tgl' => 'required|date_format:Y-m-d',
            'keterangan' => 'required|in:Bertambah,Berkurang',
            'nopenyesuaian' => 'nullable|string',
        ], $messages);

        try {
            return DB::transaction(function () use ($request) {
                // Log input untuk debugging
                Log::info('Saving penyesuaian', ['nopenyesuaian' => $request->nopenyesuaian]);

                // Gunakan nopenyesuaian yang ada untuk mode edit, atau buat baru
                $notrans = $request->nopenyesuaian;
                if (empty($notrans)) {
                    // Gunakan lock untuk menghindari race condition
                    DB::table('counter')->lockForUpdate()->first();
                    DB::select('call nopenyesuaian(@nomor)');
                    $no = DB::table('counter')->value('nopenyesuaian');
                    if (!$no) {
                        throw new \Exception('Gagal menghasilkan nomor penyesuaian');
                    }
                    $notrans = FormatingHelper::noPenyesuaian($no, 'PNY');
                }

                // Simpan atau update Penyesuaian
                $penyesuaian = Penyesuaian::updateOrCreate(
                    ['nopenyesuaian' => $notrans],
                    [
                        'kdbarang' => $request->kdbarang,
                        'stok_id' => $request->stok_id,
                        'jumlah_k' => $request->jumlah_k,
                        'motif' => $request->motif,
                        'keterangan' => $request->keterangan,
                        'tgl' => $request->tgl,
                    ]
                );

                // Update stok
                $stok = stok::find($request->stok_id); // Sudah divalidasi oleh exists
                $stok->update([
                    'jumlah_k' => $stok->jumlah_k + $request->jumlah_k,
                ]);

                // Update FIFO (opsional)
                $fifoUpdated = DetailPenjualanFifo::where('kodebarang', $request->kdbarang)
                    ->whereNull('stok_id')
                    ->update(['stok_id' => $request->stok_id]);

                return new JsonResponse([
                    'message' => 'Data telah disimpan',
                    'result' => $penyesuaian,
                    'fifo_updated' => $fifoUpdated > 0,
                ], 200);
            });
        } catch (\Exception $e) {
            Log::error('Error in save penyesuaian: ' . $e->getMessage(), [
                'request' => $request->all(),
            ]);
            return new JsonResponse([
                'message' => 'Gagal menyimpan data',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

}
