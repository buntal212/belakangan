<?php
namespace App\Http\Controllers\Api\v5\Settings;
use App\Http\Controllers\Controller;
use App\Models\LokasiAbsen;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
class LokasiAbsenController extends Controller {
    public function show(): JsonResponse { return response()->json(['data' => LokasiAbsen::where('aktif', true)->first()]); }
    public function save(Request $request): JsonResponse {
        abort_unless(in_array(strtolower((string) $request->user()->jabatan), ['admin', 'owner'], true), 403, 'Hanya Admin atau Owner yang boleh mengubah lokasi absensi.');
        $data = $request->validate(['nama' => ['required', 'string', 'max:150'], 'latitude' => ['required', 'numeric', 'between:-90,90'], 'longitude' => ['required', 'numeric', 'between:-180,180'], 'radius_meter' => ['required', 'integer', 'min:1', 'max:10000'], 'aktif' => ['boolean']]);
        $lokasi = LokasiAbsen::updateOrCreate(['id' => 1], $data);
        return response()->json(['message' => 'Lokasi absensi berhasil disimpan.', 'data' => $lokasi]);
    }
}
