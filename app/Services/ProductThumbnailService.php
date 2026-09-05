<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class ProductThumbnailService
{
    private const DIRECTORY = 'images/thumbnails';
    private const MAX_WIDTH = 400;
    private const MAX_HEIGHT = 400;
    private const WEBP_QUALITY = 80;

    /**
     * Membuat thumbnail WebP bila belum tersedia tanpa mengubah file original.
     *
     * @return 'generated'|'skipped'|'missing-original'|'unsupported-path'|'failed'
     */
    public function generateIfMissing(string $original): string
    {
        $originalPath = $this->storagePath($original);

        if (!$originalPath) {
            return 'unsupported-path';
        }

        $thumbnailPath = $this->thumbnailPath($originalPath);
        $disk = Storage::disk('public');

        if ($disk->exists($thumbnailPath)) {
            return 'skipped';
        }

        if (!$disk->exists($originalPath)) {
            return 'missing-original';
        }

        try {
            $this->createWebp($disk->path($originalPath), $disk->path($thumbnailPath));
            return 'generated';
        } catch (\Throwable $exception) {
            Log::error('Gagal membuat thumbnail produk.', [
                'original' => $original,
                'thumbnail' => $thumbnailPath,
                'exception' => $exception,
            ]);

            return 'failed';
        }
    }

    /** Mengembalikan URL thumbnail, atau URL original apabila thumbnail belum ada. */
    public function urlOrOriginal(?string $original): ?string
    {
        if (!$original) {
            return null;
        }

        $originalPath = $this->storagePath($original);

        if ($originalPath && Storage::disk('public')->exists($this->thumbnailPath($originalPath))) {
            return asset('storage/' . $this->thumbnailPath($originalPath));
        }

        return $original;
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

        if (!str_starts_with($path, 'images/') || str_contains($path, '..')) {
            return null;
        }

        return $path;
    }

    private function thumbnailPath(string $originalPath): string
    {
        return self::DIRECTORY . '/' . pathinfo($originalPath, PATHINFO_FILENAME) . '.webp';
    }

    private function createWebp(string $sourcePath, string $destinationPath): void
    {
        if (!function_exists('imagewebp')) {
            throw new RuntimeException('Ekstensi GD dengan dukungan WebP tidak tersedia.');
        }

        $info = @getimagesize($sourcePath);
        $mime = $info['mime'] ?? null;
        $source = match ($mime) {
            'image/jpeg' => function_exists('imagecreatefromjpeg') ? @imagecreatefromjpeg($sourcePath) : false,
            'image/png' => function_exists('imagecreatefrompng') ? @imagecreatefrompng($sourcePath) : false,
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($sourcePath) : false,
            default => false,
        };

        if (!$source) {
            throw new RuntimeException('Format gambar tidak didukung atau file tidak valid.');
        }

        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);
        $scale = min(1, self::MAX_WIDTH / $sourceWidth, self::MAX_HEIGHT / $sourceHeight);
        $width = max(1, (int) round($sourceWidth * $scale));
        $height = max(1, (int) round($sourceHeight * $scale));
        $thumbnail = imagecreatetruecolor($width, $height);

        imagealphablending($thumbnail, false);
        imagesavealpha($thumbnail, true);
        $transparent = imagecolorallocatealpha($thumbnail, 0, 0, 0, 127);
        imagefilledrectangle($thumbnail, 0, 0, $width, $height, $transparent);

        try {
            if (!imagecopyresampled($thumbnail, $source, 0, 0, 0, 0, $width, $height, $sourceWidth, $sourceHeight)) {
                throw new RuntimeException('Gagal mengubah ukuran gambar.');
            }

            $directory = dirname($destinationPath);
            if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
                throw new RuntimeException('Direktori thumbnail tidak dapat dibuat.');
            }

            if (!imagewebp($thumbnail, $destinationPath, self::WEBP_QUALITY)) {
                throw new RuntimeException('Gagal menulis file WebP.');
            }
        } finally {
            imagedestroy($thumbnail);
            imagedestroy($source);
        }
    }
}
