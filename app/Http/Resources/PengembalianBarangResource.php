<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PengembalianBarangResource extends JsonResource
{
  public function toArray(Request $request): array
  {
    return [
      'id' => $this->id,
      'no_pengembalian' => $this->no_pengembalian,
      'tanggal' => $this->tanggal,
      'keterangan' => $this->keterangan,
      'status' => $this->status,
      'penjualan' => [
        'id' => $this->penjualan->id,
        'no_penjualan' => $this->penjualan->no_penjualan,
        'tanggal' => $this->penjualan->tanggal,
        'pelanggan' => $this->penjualan->pelanggan ? [
          'id' => $this->penjualan->pelanggan->id,
          'nama' => $this->penjualan->pelanggan->nama
        ] : null,
        'keterangan_pelanggan' => $this->penjualan->keterangan_pelanggan
      ],
      'details' => DetailPengembalianResource::collection($this->details),
      'created_by' => [
        'id' => $this->creator->id,
        'name' => $this->creator->name
      ],
      'created_at' => $this->created_at,
      'updated_at' => $this->updated_at
    ];
  }
}
