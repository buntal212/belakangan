<?php
namespace App\Http\Controllers\Api\v3\Product;
use App\Http\Controllers\Api\v2\Product\ProductController as V2ProductController;
use App\Models\Barang;
use Illuminate\Http\Request;

class ProductController extends V2ProductController
{
    public function getProduct(string $identifier)
    {
        $product = Barang::query()
            ->whereHas('stoks', fn ($query) => $query->where('jumlah_k', '>', 0))
            ->where(fn ($query) => $query->where('id', $identifier)->orWhere('kodebarang', $identifier))
            ->with(['images' => fn ($query) => $query->select('id', 'kodebarang', 'gambar', 'flag_thumbnail')->orderByDesc('flag_thumbnail')->orderBy('id')])
            ->first();

        if (!$product) return response()->json(['success' => false, 'message' => 'Produk tidak ditemukan'], 404);

        $product->image = filter_var($product->image, FILTER_VALIDATE_URL) ? $product->image : ($product->image ? asset('storage/' . ltrim($product->image, '/')) : null);
        $product->images->each(function ($image) {
            $image->gambar = filter_var($image->gambar, FILTER_VALIDATE_URL) ? $image->gambar : asset('storage/' . ltrim($image->gambar, '/'));
        });

        return response()->json(['success' => true, 'data' => $product]);
    }

    public function getProductBySlug(string $slug)
    {
        $product = Barang::query()
            ->whereHas('stoks', fn ($query) => $query->where('jumlah_k', '>', 0))
            ->where('slug', urldecode($slug))
            ->with(['images' => fn ($query) => $query->select('id', 'kodebarang', 'gambar', 'flag_thumbnail')->orderByDesc('flag_thumbnail')->orderBy('id')])
            ->first();
        if (!$product) return response()->json(['success' => false, 'message' => 'Produk tidak ditemukan'], 404);
        $product->image = filter_var($product->image, FILTER_VALIDATE_URL) ? $product->image : ($product->image ? asset('storage/' . ltrim($product->image, '/')) : null);
        $product->images->each(fn ($image) => $image->gambar = filter_var($image->gambar, FILTER_VALIDATE_URL) ? $image->gambar : asset('storage/' . ltrim($image->gambar, '/')));
        return response()->json(['success' => true, 'data' => $product]);
    }

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

    // public function getProducts(Request $request)
    // {
    //     $query = Barang::query()
    //         ->whereHas('stoks', function ($query) {
    //             $query->where('jumlah_k', '>', 0);
    //         })
    //         ->select(
    //             'id',
    //             'kodebarang',
    //             'namabarang AS name',
    //             'namagabung',
    //             'kualitas',
    //             'brand',
    //             'satuan_b',
    //             'satuan_k',
    //             'kategori AS category',
    //             'isi',
    //             'ukuran',
    //             'kodejenis',
    //             // Gambar utama untuk halaman depan/thumbnail.
    //             'image'
    //         )
    //         ->with([
    //             // Gambar rincian diambil dari imagebarangs melalui relasi
    //             // Barang::images() berdasarkan kodebarang.
    //             'images' => function ($query) {
    //                 $query->select('id', 'kodebarang', 'gambar', 'flag_thumbnail')
    //                     ->orderByDesc('flag_thumbnail')
    //                     ->orderBy('id');
    //             },
    //         ]);
    //     $products = $query->orderByDesc('created_at')->get();

    //     $toImageUrl = static function (?string $path): ?string {
    //         if (!$path) {
    //             return null;
    //         }

    //         if (filter_var($path, FILTER_VALIDATE_URL)) {
    //             return $path;
    //         }

    //         return asset('storage/' . ltrim($path, '/'));
    //     };

    //     $products->each(function (Barang $product) use ($toImageUrl) {
    //         $product->image = $toImageUrl($product->image);

    //         $product->images->each(function ($image) use ($toImageUrl) {
    //             $image->gambar = $toImageUrl($image->gambar);
    //         });
    //     });

    //     return response()->json(['success' => true, 'data' => ['data' => $products]]);
    // }

    public function getProducts(Request $request)
    {
        $perPage = (int) $request->input('per_page', 24);

        // Batasi supaya client tidak bisa minta ribuan data sekaligus
        $perPage = max(1, min($perPage, 100));

        $search = trim((string) $request->input('search', ''));

        $query = Barang::query()
            ->whereHas('stoks', function ($query) {
                $query->where('jumlah_k', '>', 0);
            })

            // Pencarian
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('namabarang', 'like', "%{$search}%")
                        ->orWhere('namagabung', 'like', "%{$search}%")
                        ->orWhere('kodebarang', 'like', "%{$search}%")
                        ->orWhere('brand', 'like', "%{$search}%")
                        ->orWhere('kategori', 'like', "%{$search}%")
                        ->orWhere('ukuran', 'like', "%{$search}%");
                });
            })

            ->select(
                'id',
                'kodebarang',
                'slug',
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
                'image',
                'created_at'
            )

            ->with([
                'images' => function ($query) {
                    $query->select(
                        'id',
                        'kodebarang',
                        'gambar',
                        'flag_thumbnail'
                    )
                    ->orderByDesc('flag_thumbnail')
                    ->orderBy('id');
                },
            ])

            ->orderByDesc('created_at');

        $products = $query->paginate($perPage);

        $toImageUrl = static function (?string $path): ?string {
            if (!$path) {
                return null;
            }

            if (filter_var($path, FILTER_VALIDATE_URL)) {
                return $path;
            }

            return asset('storage/' . ltrim($path, '/'));
        };

        $products->getCollection()->each(function (Barang $product) use ($toImageUrl) {
            $product->image = $toImageUrl($product->image);

            $product->images->each(function ($image) use ($toImageUrl) {
                $image->gambar = $toImageUrl($image->gambar);
            });
        });

        return response()->json([
            'success' => true,
            'data' => $products,
        ]);
    }
}
