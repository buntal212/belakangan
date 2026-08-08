<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DetailPengembalianResource extends JsonResource
{
  public function toArray(Request $request): array
  {
    return [
      'id' => $this->id,
      'barang' => [
        'id' => $this->barang->id,
        'kode' => $this->barang->kode,
        'nama' => $this->barang->nama
      ],
      'qty' => $this->qty,
      'keterangan_rusak' => $this->keterangan_rusak,
      'status' => $this->status,
      'created_at' => $this->created_at,
      'updated_at' => $this->updated_at
    ];
  }
}
