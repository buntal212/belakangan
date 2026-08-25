<?php

namespace App\Http\Controllers\Api\Laporan;

use App\Http\Controllers\Controller;
use App\Models\Absensi;
use App\Models\PermohonanAbsensi;
use App\Models\ShiftKerja;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class LaporanAbsensiController extends Controller
{
    public function getData(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tglawal' => ['required', 'date'],
            'tglakhir' => ['required', 'date', 'after_or_equal:tglawal'],
            'pegawai_id' => ['nullable', 'integer'],
            'status' => ['nullable', 'in:hadir,terlambat,izin,sakit,alpa'],
        ]);

        $rows = Absensi::query()
            ->with('user:id,nama,username,jabatan,shift')
            ->whereBetween('tanggal', [$data['tglawal'], $data['tglakhir']])
            ->whereHas('user', fn ($query) => $query->whereRaw('LOWER(username) <> ?', ['sa']))
            ->when($data['pegawai_id'] ?? null, fn ($query, $id) => $query->where('user_id', $id))
            ->orderByDesc('tanggal')->orderBy('user_id')->orderBy('waktu')
            ->get()
            ->groupBy(fn ($item) => $item->user_id . '|' . $item->tanggal->format('Y-m-d'))
            ->map(function ($items) {
                $masuk = $items->firstWhere('tipe', 'masuk');
                $pulang = $items->firstWhere('tipe', 'pulang');
                $status = 'alpa';
                if ($masuk) {
                    $status = 'hadir';
                    // users.shift menyimpan ID shift, bukan object relasi.
                    $shift = $masuk->user?->shift
                        ? ShiftKerja::find($masuk->user->shift)
                        : null;
                    if ($shift?->jam_masuk && $masuk->waktu) {
                        $jamMasuk = Carbon::parse($masuk->tanggal->format('Y-m-d') . ' ' . $shift->jam_masuk);
                        if ($masuk->waktu->greaterThan($jamMasuk)) $status = 'terlambat';
                    }
                }
                return [
                    'id' => $items->first()->id,
                    'tanggal' => $items->first()->tanggal->format('Y-m-d'),
                    'pegawai_id' => $items->first()->user_id,
                    'nama_pegawai' => $items->first()->user?->nama ?? $items->first()->user?->username,
                    'jam_masuk' => $masuk?->waktu?->format('H:i'),
                    'jam_pulang' => $pulang?->waktu?->format('H:i'),
                    'status' => $status,
                    'keterangan' => null,
                ];
            })
            ->keyBy(fn ($row) => $row['pegawai_id'] . '|' . $row['tanggal']);

        PermohonanAbsensi::query()
            ->with('pegawai:id,nama,username,jabatan')
            ->whereDate('tanggal_mulai', '<=', $data['tglakhir'])
            ->whereDate('tanggal_selesai', '>=', $data['tglawal'])
            ->when($data['pegawai_id'] ?? null, fn ($query, $id) => $query->where('user_id', $id))
            ->whereHas('pegawai', fn ($query) => $query->whereRaw('LOWER(username) <> ?', ['sa']))
            ->get()
            ->each(function ($permohonan) use ($rows, $data) {
                $mulai = Carbon::parse($permohonan->tanggal_mulai)->max(Carbon::parse($data['tglawal']));
                $selesai = Carbon::parse($permohonan->tanggal_selesai)->min(Carbon::parse($data['tglakhir']));

                for ($tanggal = $mulai->copy(); $tanggal->lte($selesai); $tanggal->addDay()) {
                    $tanggalString = $tanggal->toDateString();
                    $rows->put($permohonan->user_id . '|' . $tanggalString, [
                        'id' => 'permohonan-' . $permohonan->id . '-' . $tanggalString,
                        'tanggal' => $tanggalString,
                        'pegawai_id' => $permohonan->user_id,
                        'nama_pegawai' => $permohonan->pegawai?->nama ?? $permohonan->pegawai?->username,
                        'jam_masuk' => null,
                        'jam_pulang' => null,
                        'status' => $permohonan->jenis === 'alpha' ? 'alpa' : $permohonan->jenis,
                        'keterangan' => $permohonan->keterangan,
                    ]);
                }
            });

        $rows = $rows
            ->filter(fn ($row) => empty($data['status']) || $row['status'] === $data['status'])
            ->sortByDesc('tanggal')
            ->values();

        return response()->json(['data' => $rows, 'meta' => [
            'total' => $rows->count(),
            'tglawal' => Carbon::parse($data['tglawal'])->toDateString(),
            'tglakhir' => Carbon::parse($data['tglakhir'])->toDateString(),
        ]]);
    }
}
