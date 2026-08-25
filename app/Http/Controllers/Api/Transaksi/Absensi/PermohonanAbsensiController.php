<?php

namespace App\Http\Controllers\Api\Transaksi\Absensi;

use App\Http\Controllers\Controller;
use App\Models\PermohonanAbsensi;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PermohonanAbsensiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'jenis' => ['nullable', 'in:izin,sakit,alpha'],
        ]);

        $items = PermohonanAbsensi::query()
            ->with(['pegawai:id,nama,username,jabatan', 'pembuat:id,nama,username'])
            ->when($data['jenis'] ?? null, fn ($query, $jenis) => $query->where('jenis', $jenis))
            ->when($data['q'] ?? null, function ($query, $kata) {
                $query->whereHas('pegawai', function ($pegawai) use ($kata) {
                    $pegawai->where(function ($nama) use ($kata) {
                        $nama->where('nama', 'like', "%{$kata}%")
                            ->orWhere('username', 'like', "%{$kata}%");
                    });
                });
            })
            ->orderByDesc('tanggal_mulai')
            ->orderByDesc('id')
            ->get()
            ->each(fn ($item) => $item->setAttribute(
                'jumlah_hari',
                $item->tanggal_mulai->diffInDays($item->tanggal_selesai) + 1
            ));

        return response()->json(['data' => $items]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'id' => ['nullable', 'integer', 'exists:permohonan_absensis,id'],
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'jenis' => ['required', 'in:izin,sakit,alpha'],
            'tanggal_mulai' => ['required', 'date'],
            'tanggal_selesai' => ['required', 'date', 'after_or_equal:tanggal_mulai'],
            'keterangan' => ['required', 'string', 'max:500'],
        ]);

        $pegawai = User::findOrFail($data['user_id']);
        if (strtolower((string) $pegawai->username) === 'sa') {
            throw ValidationException::withMessages(['user_id' => 'Super admin tidak dapat dipilih sebagai pegawai.']);
        }

        $bentrok = PermohonanAbsensi::query()
            ->where('user_id', $data['user_id'])
            ->when($data['id'] ?? null, fn ($query, $id) => $query->whereKeyNot($id))
            ->whereDate('tanggal_mulai', '<=', $data['tanggal_selesai'])
            ->whereDate('tanggal_selesai', '>=', $data['tanggal_mulai'])
            ->exists();

        if ($bentrok) {
            throw ValidationException::withMessages([
                'tanggal_mulai' => 'Pegawai sudah memiliki catatan tidak masuk pada periode tersebut.',
            ]);
        }

        $item = PermohonanAbsensi::updateOrCreate(
            ['id' => $data['id'] ?? null],
            [
                'user_id' => $data['user_id'],
                'jenis' => $data['jenis'],
                'tanggal_mulai' => $data['tanggal_mulai'],
                'tanggal_selesai' => $data['tanggal_selesai'],
                'keterangan' => trim($data['keterangan']),
                'dibuat_oleh' => $request->user()->id,
            ]
        );

        return response()->json([
            'message' => $item->wasRecentlyCreated ? 'Permohonan tidak masuk berhasil disimpan.' : 'Catatan ketidakhadiran berhasil diperbarui.',
            'data' => $item->load('pegawai:id,nama,username,jabatan'),
        ], $item->wasRecentlyCreated ? 201 : 200);
    }

    public function destroy(PermohonanAbsensi $permohonanAbsensi): JsonResponse
    {
        $permohonanAbsensi->delete();

        return response()->json(['message' => 'Catatan ketidakhadiran berhasil dihapus.']);
    }
}
