<?php

namespace App\Http\Controllers\Api\Settings;

use App\Http\Controllers\Controller;
use App\Models\PengaturanPpn;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PpnController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json([
            'result' => PengaturanPpn::first() ?? new PengaturanPpn([
                'aktif' => false,
                'persen' => 0,
            ]),
        ]);
    }

    public function simpan(Request $request): JsonResponse
    {
        $this->pastikanAdmin($request);

        $data = $request->validate([
            'aktif' => ['required', 'boolean'],
            'persen' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        if ($data['aktif'] && (float) $data['persen'] <= 0) {
            return response()->json([
                'message' => 'Tarif PPN harus lebih dari 0% saat PPN diaktifkan.',
            ], 422);
        }

        $pengaturan = PengaturanPpn::updateOrCreate(['id' => 1], $data);

        return response()->json([
            'message' => 'Pengaturan PPN berhasil disimpan.',
            'result' => $pengaturan,
        ]);
    }

    private function pastikanAdmin(Request $request): void
    {
        $user = $request->user();
        $jabatan = strtolower(trim((string) $user?->jabatan));
        $boleh = strtolower((string) $user?->username) === 'sa'
            || in_array($jabatan, ['admin', 'super admin', 'superadmin', 'owner'], true);

        abort_unless($boleh, 403, 'Hanya Admin atau Owner yang dapat mengubah pengaturan PPN.');
    }
}
