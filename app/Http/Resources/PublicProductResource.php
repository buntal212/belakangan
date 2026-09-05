<?php

namespace App\Http\Resources;

use App\Services\ProductThumbnailService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $mainImage = $this->image;
        if (!$mainImage && $this->relationLoaded('images')) {
            $selectedImage = $this->images->firstWhere('flag_thumbnail', 1) ?? $this->images->first();
            $mainImage = $selectedImage?->gambar;
        }

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
            'thumbnail_url' => app(ProductThumbnailService::class)->urlOrOriginal($mainImage),
            'images' => $this->whenLoaded('images', fn () => $this->images->map(fn ($image) => [
                'id' => $image->id,
                'kodebarang' => $image->kodebarang,
                'gambar' => $image->gambar,
                'thumbnail_url' => $image->thumbnail_url,
            ])->values()),
        ];
    }
}
