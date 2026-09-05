<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Imagebarang extends Model
{
    use HasFactory;

    protected $guarded = ['id'];
    protected $appends = ['thumbnail_url'];

    public function getThumbnailUrlAttribute(): ?string
    {
        if (!$this->gambar) {
            return null;
        }

        $originalPath = $this->storagePath($this->gambar);

        if (!$originalPath) {
            return filter_var($this->gambar, FILTER_VALIDATE_URL) ? $this->gambar : null;
        }

        $thumbnailPath = 'images/thumbnails/' . pathinfo($originalPath, PATHINFO_FILENAME) . '.webp';
        $disk = Storage::disk('public');

        if ($disk->exists($thumbnailPath)) {
            return asset($disk->url($thumbnailPath));
        }

        return asset($disk->url($originalPath));
    }

    private function storagePath(string $path): ?string
    {
        if (filter_var($path, FILTER_VALIDATE_URL)) {
            $urlPath = parse_url($path, PHP_URL_PATH);
            $storagePosition = $urlPath ? strpos($urlPath, '/storage/') : false;

            if ($storagePosition === false) {
                return null;
            }

            $path = substr($urlPath, $storagePosition + strlen('/storage/'));
        }

        $path = ltrim($path, '/');

        return $path && !str_contains($path, '..') ? $path : null;
    }

    public function barang() {
        return $this->belongsTo(Barang::class, 'kodebarang', 'kodebarang');
    }

    // protected $appends = ['gambar_array'];

    // public function getGambarArrayAttribute() {
    //     return explode(',', $this->gambar_list);
    // }
}
