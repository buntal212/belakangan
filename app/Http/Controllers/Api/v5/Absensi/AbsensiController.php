<?php

namespace App\Http\Controllers\Api\v5\Absensi;

use App\Http\Controllers\Controller;
use App\Models\Absensi;
use App\Models\LokasiAbsen;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class AbsensiController extends Controller
{
    public function registerFace(Request $request): JsonResponse
    {
        $request->validate(['foto' => ['required', 'image', 'max:5120']]);
        $response = $this->faceService('/face/register', $request);
        if (!$response->successful()) return response()->json(['message' => $response->json('detail', 'Perekaman wajah gagal.')], $response->status());
        return response()->json(['message' => 'Wajah pegawai berhasil direkam.', 'data' => $response->json()]);
    }

    public function today(Request $request): JsonResponse
    {
        $items = Absensi::where('user_id', $request->user()->id)
            ->whereDate('tanggal', now()->toDateString())->orderBy('waktu')->get();
        return response()->json(['data' => $items]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tipe' => ['required', 'in:masuk,pulang'], 'foto' => ['required', 'image', 'max:5120'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'akurasi_lokasi' => ['required', 'numeric', 'min:0', 'max:300'],
        ]);
        if (empty($data['latitude']) || empty($data['longitude'])) return response()->json(['message' => 'Lokasi GPS wajib diaktifkan untuk melakukan absensi.'], 422);
        $lokasi = LokasiAbsen::where('aktif', true)->first();
        if ($lokasi) {
            $distance = $this->distance($lokasi->latitude, $lokasi->longitude, $data['latitude'], $data['longitude']);
            if ($distance > $lokasi->radius_meter) return response()->json(['message' => "Di luar area absensi. Jarak kamu {$distance} meter dari lokasi."], 422);
        }
        $verification = $this->faceService('/face/verify', $request);
        if (!$verification->successful() || !$verification->json('ok')) {
            return response()->json(['message' => $verification->json('detail', 'Wajah tidak cocok dengan data perekaman.')], 422);
        }
        $exists = Absensi::where('user_id', $request->user()->id)->whereDate('tanggal', now()->toDateString())->where('tipe', $data['tipe'])->exists();
        if ($exists) return response()->json(['message' => 'Absensi ' . $data['tipe'] . ' hari ini sudah tercatat.'], 422);
        $path = $request->file('foto')->store('absensi/' . now()->toDateString(), 'public');
        $absensi = Absensi::create([
            'user_id' => $request->user()->id, 'tanggal' => now()->toDateString(), 'tipe' => $data['tipe'],
            'waktu' => now(), 'foto_path' => $path, 'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null, 'akurasi_lokasi' => $data['akurasi_lokasi'] ?? null,
        ]);
        return response()->json(['message' => 'Absensi berhasil direkam.', 'data' => $absensi], 201);
    }

    private function faceService(string $endpoint, Request $request)
    {
        $file = $request->file('foto');
        $client = Http::timeout(20);
        if (config('services.face.api_key')) $client = $client->withHeaders(['X-API-Key' => config('services.face.api_key')]);
        return $client->attach(
            'file', file_get_contents($file->getRealPath()), $file->getClientOriginalName()
        )->post(rtrim(config('services.face.url'), '/') . $endpoint . '?user_id=' . $request->user()->id);
    }

    private function distance(float $lat1, float $lon1, float $lat2, float $lon2): int { $earth = 6371000; $dLat = deg2rad($lat2 - $lat1); $dLon = deg2rad($lon2 - $lon1); $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2; return (int) round($earth * 2 * atan2(sqrt($a), sqrt(1 - $a))); }

    public function history(Request $request): JsonResponse
    {
        $isAdmin = $request->user()->jabatan === 'Admin';
        $items = Absensi::with('user:id,nama,username,jabatan')->when(!$isAdmin, fn ($query) => $query->where('user_id', $request->user()->id))->latest('tanggal')->latest('waktu')->paginate($request->integer('per_page', 20));
        return response()->json($items);
    }
}
