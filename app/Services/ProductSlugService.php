<?php

namespace App\Services;

use App\Models\Barang;
use Illuminate\Support\Str;

class ProductSlugService
{
    public static function generate(?string $name, ?string $code): ?string
    {
        $nameSlug = Str::slug((string) $name);
        $codeSlug = Str::slug((string) $code);

        if ($nameSlug === '' || $codeSlug === '') return null;

        return $nameSlug . '-' . $codeSlug;
    }

    public static function legacy(?string $name): string
    {
        return Str::slug((string) $name);
    }

    public static function legacyVariants(?string $name): array
    {
        $value = Str::ascii((string) $name);
        $frontendSlug = strtolower(trim((string) preg_replace('/[^a-z0-9]+/i', '-', $value), '-'));

        return array_values(array_unique(array_filter([
            self::legacy($name),
            $frontendSlug,
        ])));
    }

    public static function findLegacy(string $slug): ?Barang
    {
        $pattern = '%' . str_replace('-', '%', addcslashes($slug, '%_')) . '%';
        $matches = Barang::query()
            ->whereHas('stoks', fn ($query) => $query->where('jumlah_k', '>', 0))
            ->whereNotNull('namagabung')
            ->where('namagabung', 'like', $pattern)
            ->get(['id', 'namagabung', 'kodebarang', 'slug'])
            ->filter(fn (Barang $item) => in_array($slug, self::legacyVariants($item->namagabung), true));

        return $matches->count() === 1 ? $matches->first() : null;
    }
}
