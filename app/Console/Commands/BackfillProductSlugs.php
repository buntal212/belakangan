<?php

namespace App\Console\Commands;

use App\Models\Barang;
use App\Services\ProductSlugService;
use Illuminate\Console\Command;

class BackfillProductSlugs extends Command
{
    protected $signature = 'products:backfill-slugs {--dry-run : Validate without writing changes}';
    protected $description = 'Generate canonical slugs for existing products';

    public function handle(): int
    {
        $success = $invalid = $collisions = 0;
        $seen = [];

        Barang::query()->select(['id', 'namagabung', 'kodebarang', 'slug'])->chunkById(200, function ($items) use (&$success, &$invalid, &$collisions, &$seen) {
            foreach ($items as $barang) {
                $slug = ProductSlugService::generate($barang->namagabung, $barang->kodebarang);
                if (!$slug) { $invalid++; continue; }
                if (isset($seen[$slug]) && $seen[$slug] !== $barang->id) { $collisions++; continue; }
                $seen[$slug] = $barang->id;
                $success++;
                if (!$this->option('dry-run') && $barang->slug !== $slug) {
                    $barang->forceFill(['slug' => $slug])->saveQuietly();
                }
            }
        });

        $this->info(($this->option('dry-run') ? 'Validasi' : 'Backfill') . ' selesai.');
        $this->line("Berhasil: {$success}");
        $this->line("Invalid: {$invalid}");
        $this->line("Collision: {$collisions}");
        return $invalid || $collisions ? self::FAILURE : self::SUCCESS;
    }
}
