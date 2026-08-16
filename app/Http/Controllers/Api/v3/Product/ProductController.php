<?php
namespace App\Http\Controllers\Api\v3\Product;
use App\Http\Controllers\Api\v2\Product\ProductController as V2ProductController;
use App\Models\Barang;
use Illuminate\Http\Request;

class ProductController extends V2ProductController
{
    public function getFilters()
    {
        $grouped = static function (string $column) {
            return Barang::query()
                ->whereHas('stoks', function ($query) {
                    $query->where('jumlah_k', '>', 0);
                })
                ->whereNotNull($column)
                ->where($column, '!=', '')
                ->groupBy($column)
                ->orderBy($column)
                ->pluck($column)
                ->values();
        };

        return response()->json([
            'success' => true,
            'data' => [
                'brands' => $grouped('brand'),
                'sizes' => $grouped('ukuran'),
                'grades' => $grouped('kualitas'),
                'types' => $grouped('kodejenis'),
            ],
        ]);
    }

    public function getProducts(Request $request)
    {
        $query = Barang::query()
            ->whereHas('stoks', function ($query) {
                $query->where('jumlah_k', '>', 0);
            })
            ->select(
                'id',
                'kodebarang',
                'namabarang AS name',
                'namagabung',
                'kualitas',
                'brand',
                'satuan_b',
                'satuan_k',
                'kategori AS category',
                'isi',
                'ukuran',
                'kodejenis',
                // Gambar utama untuk halaman depan/thumbnail.
                'image'
            )
            ->with([
                // Gambar rincian diambil dari imagebarangs melalui relasi
                // Barang::images() berdasarkan kodebarang.
                'images' => function ($query) {
                    $query->select('id', 'kodebarang', 'gambar', 'flag_thumbnail')
                        ->orderByDesc('flag_thumbnail')
                        ->orderBy('id');
                },
            ]);
        $products = $query->orderByDesc('created_at')->get();

        $toImageUrl = static function (?string $path): ?string {
            if (!$path) {
                return null;
            }

            if (filter_var($path, FILTER_VALIDATE_URL)) {
                return $path;
            }

            return asset('storage/' . ltrim($path, '/'));
        };

        $products->each(function (Barang $product) use ($toImageUrl) {
            $product->image = $toImageUrl($product->image);

            $product->images->each(function ($image) use ($toImageUrl) {
                $image->gambar = $toImageUrl($image->gambar);
            });
        });

        return response()->json(['success' => true, 'data' => ['data' => $products]]);
    }
}
