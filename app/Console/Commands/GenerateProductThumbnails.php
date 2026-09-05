<?php

namespace App\Console\Commands;

use App\Models\Barang;
use App\Models\Imagebarang;
use App\Services\ProductThumbnailService;
use Illuminate\Console\Command;

class GenerateProductThumbnails extends Command
{
    protected $signature = 'products:generate-thumbnails';
    protected $description = 'Membuat thumbnail WebP untuk gambar produk yang belum memilikinya';

    public function handle(ProductThumbnailService $thumbnails): int
    {
        $counts = ['generated' => 0, 'skipped' => 0, 'failed' => 0, 'missing-original' => 0, 'unsupported-path' => 0];
        $total = Imagebarang::query()->count() + Barang::query()->whereNotNull('image')->where('image', '!=', '')->count();
        $progress = $this->output->createProgressBar($total);
        $progress->start();

        $process = function (?string $path) use ($thumbnails, &$counts, $progress): void {
            $result = $path ? $thumbnails->generateIfMissing($path) : 'unsupported-path';
            $counts[$result] = ($counts[$result] ?? 0) + 1;
            $progress->advance();
        };

        Imagebarang::query()->select(['id', 'gambar'])->orderBy('id')->chunkById(200, function ($images) use ($process) {
            foreach ($images as $image) {
                $process($image->gambar);
            }
        });

        Barang::query()->select(['id', 'image'])->whereNotNull('image')->where('image', '!=', '')->orderBy('id')->chunkById(200, function ($products) use ($process) {
            foreach ($products as $product) {
                $process($product->image);
            }
        });

        $progress->finish();
        $this->newLine(2);
        $this->table(['Status', 'Jumlah'], collect($counts)->map(fn ($count, $status) => [$status, $count])->values()->all());

        return $counts['failed'] ? self::FAILURE : self::SUCCESS;
    }
}
