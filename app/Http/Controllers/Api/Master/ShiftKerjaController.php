<?php

namespace App\Http\Controllers\Api\Master;

use App\Http\Controllers\Controller;
use App\Models\ShiftKerja;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ShiftKerjaController extends Controller
{
    public function list_data(): JsonResponse
    {
        $data = ShiftKerja::query()
            ->when(request('q'), function ($query, $q) {
                $query->where(fn ($search) => $search->where('kode', 'like', "%{$q}%")
                    ->orWhere('nama', 'like', "%{$q}%"));
            })
            ->orderBy('jam_masuk')
            ->paginate((int) request('per_page', 15));

        return new JsonResponse($data);
    }

    public function save_data(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id' => ['nullable', 'integer'],
            'kode' => ['required', 'string', 'max:20', Rule::unique('shift_kerjas', 'kode')->ignore($request->id)],
            'nama' => ['required', 'string', 'max:100'],
            'jam_masuk' => ['required', 'date_format:H:i'],
            'jam_keluar' => ['required', 'date_format:H:i'],
            'toleransi' => ['nullable', 'integer', 'min:0', 'max:1440'],
            'aktif' => ['required', 'boolean'],
        ]);

        $shift = ShiftKerja::updateOrCreate(
            ['id' => $validated['id'] ?? null],
            collect($validated)->except('id')->all()
        );

        return new JsonResponse(['message' => 'Data shift berhasil disimpan', 'result' => $shift], 200);
    }

    public function delete_data(Request $request): JsonResponse
    {
        $request->validate(['id' => ['required', 'integer', 'exists:shift_kerjas,id']]);
        ShiftKerja::whereKey($request->id)->delete();

        return new JsonResponse(['message' => 'Data shift berhasil dihapus'], 200);
    }
}
