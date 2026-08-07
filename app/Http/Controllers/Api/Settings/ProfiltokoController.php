<?php

namespace App\Http\Controllers\Api\Settings;

use App\Http\Controllers\Controller;
use App\Models\Profil;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfiltokoController extends Controller
{
    public function get_profil()
    {
        $profil = Profil::first(); // Ambil data profil

        // if ($profil) {
        //     // Jika foto ada, kembalikan URL lengkap
        //     if ($profil->foto) {
        //         $profil->foto = asset('storage/' . $profil->foto); // Generate URL lengkap
        //     } else {
        //         $profil->foto = null; // Pastikan foto null jika tidak ada
        //     }
        // } else {
        //     // Jika tidak ada data, kembalikan objek kosong
        //     $profil = (object) [
        //         'id' => null,
        //         'namatoko' => null,
        //         'pemilik' => null,
        //         'alamat' => null,
        //         'telepon' => null,
        //         'email' => null,
        //         'bio' => null,
        //         'foto' => null,
        //     ];
        // }

        return response()->json([
            'result' => $profil
        ], 200);
    }
    public function dataprofil()
    {
        $data = Profil::get(); // Ambil data profil

        return new JsonResponse($data);
    }
   public function save(Request $request)
    {
        // Validasi dasar
        $rules = [
            'namatoko' => 'required',
            'pemilik' => 'required',
            'email' => 'required|email',
            'alamat' => 'required',
            'telepon' => 'required',
            'bio' => 'nullable',
        ];

        // Validasi khusus untuk file baru
        if ($request->input('is_new_foto') === '1') {
            $rules['foto'] = 'required|image|max:2048';
        }

        $validatedData = $request->validate($rules);

        // Data untuk disimpan
        $data = $request->only([
            'namatoko', 'pemilik', 'email',
            'alamat', 'telepon', 'bio'
        ]);

        // Handle foto dengan kondisi yang sangat jelas
        if ($request->input('is_new_foto') === '1') {
            $path = $request->file('foto')->store('profil_foto', 'public');
            $data['foto'] = $path; // Format: 'profil_foto/filename.jpg'
        } elseif ($request->filled('foto_path')) {
            // Pastikan path bersih dari /storage/ dan leading slash
            $cleanPath = ltrim(str_replace('/storage/', '', $request->foto_path), '/');
            $data['foto'] = $cleanPath;
        }
        // Jika tidak ada keduanya, foto tidak diupdate

        // Simpan data
        $profil = Profil::updateOrCreate(['id' => $request->id], $data);

        return response()->json([
            'message' => 'Data berhasil disimpan',
            'result' => $profil
        ]);
    }
}
