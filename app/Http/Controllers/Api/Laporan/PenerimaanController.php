<?php

namespace App\Http\Controllers\Api\Laporan;

use App\Http\Controllers\Controller;
use App\Models\Transaksi\Penerimaan\Penerimaan_h;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PenerimaanController extends Controller
{
    public function getData(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'tglawal' => ['nullable', 'date'],
            'tglakhir' => ['nullable', 'date', 'after_or_equal:tglawal'],
            'supplier' => ['nullable', 'string', 'max:100'],
            'jnsbayar' => ['nullable', 'string', 'max:50'],
        ]);

        $query = Penerimaan_h::query()
            ->with([
                'suplier:kodesupl,nama',
                'rinci' => fn ($detail) => $detail->with('mbarang:kodebarang,namabarang'),
            ])
            ->when($validated['q'] ?? null, function ($builder, $search) {
                $builder->where('nopenerimaan', 'like', "%{$search}%");
            })
            ->when($validated['tglawal'] ?? null, function ($builder, $startDate) {
                $builder->whereDate('tgl_faktur', '>=', $startDate);
            })
            ->when($validated['tglakhir'] ?? null, function ($builder, $endDate) {
                $builder->whereDate('tgl_faktur', '<=', $endDate);
            })
            ->when($validated['supplier'] ?? null, function ($builder, $supplier) {
                $builder->where('kdsupllier', $supplier);
            })
            ->when($validated['jnsbayar'] ?? null, function ($builder, $paymentType) {
                $builder->where('jenis_pembayaran', $paymentType);
            })
            ->orderBy('tgl_faktur')
            ->orderBy('nopenerimaan')
            ->get();

        $result = $query->map(function ($receipt) {
            $details = $receipt->rinci->map(fn ($detail) => [
                'id' => $detail->id,
                'kdbarang' => $detail->kdbarang,
                'namabarang' => $detail->mbarang?->namabarang ?? $detail->kdbarang,
                'motif' => $detail->motif,
                'jumlah_b' => (float) $detail->jumlah_b,
                'jumlah_k' => (float) $detail->jumlah_k,
                'jumlah_rusak_b' => (float) $detail->jumlah_rusak_b,
                'jumlah_datang_b' => (float) $detail->jumlah_datang_b,
                'satuan_b' => $detail->satuan_b,
                'satuan_k' => $detail->satuan_k,
                'hargafaktur' => (float) $detail->hargafaktur,
                'subtotalfix' => (float) $detail->subtotalfix,
            ]);

            return [
                'id' => $receipt->id,
                'nopenerimaan' => $receipt->nopenerimaan,
                'noorder' => $receipt->noorder,
                'nofaktur' => $receipt->nofaktur,
                'tgl_faktur' => $receipt->tgl_faktur,
                'kdsupllier' => $receipt->kdsupllier,
                'namasupplier' => $receipt->suplier?->nama ?? $receipt->kdsupllier,
                'jenis_pembayaran' => $receipt->jenis_pembayaran,
                'total' => (float) $details->sum('subtotalfix'),
                'rinci' => $details,
            ];
        });

        return new JsonResponse($result);
    }
}
