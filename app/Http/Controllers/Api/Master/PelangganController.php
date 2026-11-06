<?php

namespace App\Http\Controllers\Api\Master;

use App\Helpers\FormatingHelper;
use App\Http\Controllers\Controller;
use App\Models\Pelanggan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PelangganController extends Controller
{
    public function listpelanggan()
    {
        $list =  Pelanggan::whereNull('flaging')
        ->when(request('q') !== '' || request('q') !== null, function($x){
            $x->where('nama', 'like', '%' . request('q') . '%')
              ->orWhere('nama_toko', 'like', '%' . request('q') . '%')
              ->orWhere('nama_owner', 'like', '%' . request('q') . '%')
              ->orWhere('telepon','like', '%' . request('q') . '%')
              ->orWhere('no_tlp','like', '%' . request('q') . '%')
              ->orWhere('no_ktp','like', '%' . request('q') . '%')
              ->orWhere('no_npwp','like', '%' . request('q') . '%')
              ->orWhere('norek','like', '%' . request('q') . '%')
              ->orWhere('alamat','like', '%' . request('q') . '%')
              ;
        })
        ->orderBy('id', 'desc')
        ->simplePaginate(request('per_page'));
        return new JsonResponse($list);
    }

    public function listpelangganall()
    {
        $list =  Pelanggan::whereNull('flaging')
        ->orderBy('nama', 'asc')
        ->get();
        return new JsonResponse($list);
    }

    public function simpan(Request $request)
    {
        $validated = $request->validate([
            'nama_toko' => ['nullable', 'string', 'max:255'],
            'nama_owner' => ['nullable', 'string', 'max:255'],
            'alamat' => ['nullable', 'string', 'max:1000'],
            'no_ktp' => ['nullable', 'string', 'max:50'],
            'no_npwp' => ['nullable', 'string', 'max:50'],
            'no_tlp' => ['nullable', 'string', 'max:50'],
            'nama' => ['nullable', 'string', 'max:255'],
            'telepon' => ['nullable', 'string', 'max:50'],
            'norek' => ['nullable', 'string', 'max:100'],
            'namabank' => ['nullable', 'string', 'max:255'],
        ]);

        if($request->kodeplgn === '' || $request->kodeplgn === null)
        {
            $cek = Pelanggan::count();
            $total = (int) $cek + (int) 1;
            $kodepelanggan = FormatingHelper::matkdbarang($total,'PLGN');
        }else{
            $kodepelanggan = $request->kodeplgn;
        }
        $namaToko = $validated['nama_toko'] ?? $validated['nama'] ?? null;
        $noTlp = $validated['no_tlp'] ?? $validated['telepon'] ?? null;
        $simpan = Pelanggan::updateOrCreate(
        [
            'kodeplgn' => $kodepelanggan
        ],
        [
            // Tetap isi kolom lama agar pilihan pelanggan pada transaksi lama tetap berjalan.
            'nama' => $namaToko,
            'nama_toko' => $namaToko,
            'nama_owner' => $validated['nama_owner'] ?? null,
            'alamat' => $validated['alamat'] ?? null,
            'telepon' => $noTlp,
            'no_tlp' => $noTlp,
            'no_ktp' => $validated['no_ktp'] ?? null,
            'no_npwp' => $validated['no_npwp'] ?? null,
            'norek' => $validated['norek'] ?? null,
            'namabank' => $validated['namabank'] ?? null,
        ]);

        return new JsonResponse(
            [
                'message' => 'Data Berhasil Disimpan...!!!',
                'result' => $simpan
            ],
            200
        );

    }

    public function hapus(Request $request)
    {
        $cari = Pelanggan::find($request->id);
        $cari->flaging = '1';
        $cari->save();

        return new JsonResponse(
            [
                'message' => 'Data Sudah Dihapus',
            ],200
        );
    }
}
