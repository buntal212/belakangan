<?php

namespace App\Http\Controllers\Api\Master;

use App\Helpers\FormatingHelper;
use App\Http\Controllers\Controller;
use App\Models\Barang;
use App\Models\Imagebarang;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BarangController extends Controller
{
    public function listbarang()
    {
        // $data = Barang::whereNull('barangs.flaging')
        //     ->when(request('q') !== '' || request('q') !== null, function ($x) {
        //         $x->where('barangs.namabarang', 'like', '%' . request('q') . '%')
        //             ->orWhere('barangs.kodebarang', 'like', '%' . request('q') . '%');
        //     })
        //     ->with('images')
        //     // ->leftJoin('imagebarangs', 'barangs.kodebarang', '=', 'imagebarangs.kodebarang')
        //     // ->select('barangs.*',)
        //     // ->selectSub(function ($query) {
        //     //     $query->from('imagebarangs')
        //     //          ->selectRaw('GROUP_CONCAT(gambar)')
        //     //         ->whereColumn('imagebarangs.kodebarang', 'barangs.kodebarang');
        //     // }, 'gambar_list')
        //     // ->groupBy('barangs.kodebarang')
        //     ->orderBy('barangs.id', 'desc')
        //     ->simplePaginate(request('per_page'));

        $data = Barang::whereNull('barangs.flaging')
            ->when(request('q'), function ($query) {
                $query->where(function ($q) {
                    $q->where('barangs.namabarang', 'like', '%' . request('q') . '%')
                    ->orWhere('barangs.kodebarang', 'like', '%' . request('q') . '%');
                });
            })
            ->leftJoin('imagebarangs', function ($join) {
                $join->on('barangs.kodebarang', '=', 'imagebarangs.kodebarang')
                    ->where('imagebarangs.flag_thumbnail', '=', 1); // Hanya ambil gambar dengan flag_thumbnail = 1
            })
            ->select('barangs.*')
            ->selectRaw('
                GROUP_CONCAT(imagebarangs.gambar) as image,
                GROUP_CONCAT(imagebarangs.flag_thumbnail) as flag_thumbnail
            ')
            ->with('rincians')
            ->groupBy('barangs.id') // Group by primary key
            ->orderBy('barangs.id', 'desc')
            ->simplePaginate(request('per_page'));

        return new JsonResponse($data);
    }

    public function simpanbarang(Request $request)
    {
        $messages = [
            'rincians.*.gambar.max' => 'Ukuran Foto Tidak Boleh Lebih dari 2MB.',
            'rincians.*.gambar.image' => 'File harus berupa gambar.',
            'namabarang.required' => 'Nama Barang Wajib diisi.',
            'hargajual1.numeric' => 'Harga Pengguna Harus Angka.',
            'hargajual2.numeric' => 'Harga Toko Harus Angka.',
            'hargabeli.numeric' => 'Harga Beli Harus Angka.'
        ];

        $request->validate([
            'rincians.*.gambar' => 'nullable|image|max:2048', // Maksimal 2MB
            'namabarang' => 'required',
            'hargajual1' => 'nullable|numeric',
            'hargajual2' => 'nullable|numeric',
            'hargabeli' => 'nullable|numeric',

        ], $messages);

        if ($request->kodebarang === '' || $request->kodebarang === null)
        {
            $cek = Barang::count();
            $total = (int) $cek + (int) 1;
            $kodebarang = FormatingHelper::matkdbarang($total, 'BRG');
        } else {
            $kodebarang = $request->kodebarang;
        }

        $namagabung = $request->brand . ' ' . $request->ukuran . ' ' . $request->namabarang . ' ' . $request->kualitas;
        $simpan = Barang::updateOrCreate(
        [
            'kodebarang' => $kodebarang
        ],
        [
            'namagabung' => $request->namabarang,
            'namabarang' => $namagabung,
            'kualitas' => $request->kualitas,
            'brand' => $request->brand,
            'seri' => $request->seri,
            'satuan_b' => $request->satuan_b,
            'satuan_k' => $request->satuan_k,
            'isi' => $request->isi,
            'kategori' => $request->kategori,
            'hargajual1' => $request->hargajual1,
            'hargajual2' => $request->hargajual2,
            'hargabeli' => $request->hargabeli,
            'ukuran' => $request->ukuran,
        ]);
        if ($request->has('rincians')) {
        $hasThumbnail = false; // Flag untuk menandai apakah sudah ada thumbnail

        foreach ($request->rincians as $img) {
                if (isset($img['gambar']) && $img['gambar']->isValid()) {
                    $path = $img['gambar']->store('images', 'public');

                    // Jika flag_thumbnail = 1 dan belum ada thumbnail sebelumnya
                    if (isset($img['flag_thumbnail']) && $img['flag_thumbnail'] === '1' && !$hasThumbnail) {
                        $flagThumbnail = '1';
                        $hasThumbnail = true; // Set flag bahwa sudah ada thumbnail
                    } else {
                        $flagThumbnail = null; // Reset flag_thumbnail untuk gambar lain
                    }

                    // Simpan gambar dengan flag_thumbnail
                    $simpan->rincians()->create([
                        'kodebarang' => $simpan->kodebarang,
                        'gambar' => $path,
                        'flag_thumbnail' => $flagThumbnail,
                    ]);
                }
            }
        }

        return new JsonResponse(
                [
                    'message' => 'Data Berhasil disimpan...!!!',
                    'result' => $simpan->load('rincians')
                ], 200);

    }
    public function setThumbnail(Request $request)
    {
        // Cari gambar yang dipilih berdasarkan ID
        $img = Imagebarang::find($request->id);

        if (!$img) {
            return new JsonResponse(['message' => 'Data Tidak Ditemukan'], 500);
        }

        // Mulai transaksi database
        DB::beginTransaction();

        try {
            // Ubah flag_thumbnail ke 0 untuk semua gambar terkait barang ini
            Imagebarang::where('kodebarang', $img->kodebarang)
                ->where('flag_thumbnail', '1')
                ->update(['flag_thumbnail' => NULL]);

            // Ubah flag_thumbnail ke 1 untuk gambar yang dipilih
            $img->flag_thumbnail = '1';
            $img->save();

            // Commit transaksi
            DB::commit();

            return new JsonResponse(['message' => 'Berhasil Memilih Thumbnail'], 200);
        } catch (\Exception $e) {
            // Rollback transaksi jika terjadi error
            DB::rollBack();
            return new JsonResponse(['message' => 'Gagal Memilih Thumbnail', 'error' => $e->getMessage()], 500);
        }
    }

    public function deleteItem(Request $request)
    {
        // nyoba biar bisa push

        $header = Barang::find($request->id);
        if (!$header) {
            return new JsonResponse(['message' => 'Data Tidak Ditemukan'], 500);
        }

        $header->delete();
        foreach ($header->rincians as $image) {
            $filePath = public_path('storage/' . $image->gambar);  // Ganti dengan path yang benar

            if (file_exists($filePath)) {
                unlink($filePath);
            }
        }
        $header->rincians()->delete();

        return new JsonResponse(['message' => 'Data Sudah Dihapus'], 200);
    }

    public function deletegambar(Request $request)
    {
        // Cari gambar berdasarkan ID
        $image = Imagebarang::find($request->id);

        if (!$image) {
            return response()->json([
                'message' => 'Gambar tidak ditemukan',
            ], 404);
        }

        // Hapus file gambar dari storage (opsional)
        $filePath = public_path('storage/' . $image->gambar);  // Ganti dengan path yang benar

        if (file_exists($filePath)) {
            unlink($filePath);
        }

        // Hapus data dari database
        $image->delete();

        return response()->json([
            'message' => 'Gambar berhasil dihapus',
        ], 200);
    }
}
