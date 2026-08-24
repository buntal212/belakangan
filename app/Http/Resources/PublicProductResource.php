<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'kodebarang' => $this->kodebarang,
            'slug' => $this->slug,
            'namagabung' => $this->namagabung,
            'name' => $this->name ?? $this->namabarang,
            'brand' => $this->brand,
            'category' => $this->category ?? $this->kategori,
            'ukuran' => $this->ukuran,
            'kualitas' => $this->kualitas,
            'kodejenis' => $this->kodejenis,
            'satuan_b' => $this->satuan_b,
            'satuan_k' => $this->satuan_k,
            'isi' => $this->isi,
            'image' => $this->image,
            'images' => $this->whenLoaded('images', fn () => $this->images->map(fn ($image) => [
                'id' => $image->id,
                'kodebarang' => $image->kodebarang,
                'gambar' => $image->gambar,
            ])->values()),
        ];
    }
}
