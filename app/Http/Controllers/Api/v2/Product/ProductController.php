<?php

namespace App\Http\Controllers\Api\v2\Product;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Models\Barang;
use App\Models\Product;
use App\Models\Wishlist;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Validator;

class ProductController extends Controller
{
    /**
     * Get products with pagination and filtering
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function getProducts(Request $request)
    {
        try {
            // Validate request parameters
            $validator = Validator::make($request->all(), [
                'currentPage' => 'nullable|integer|min:1',
                'itemsPerPage' => 'nullable|integer|min:1|max:100',
                'sortBy' => 'nullable|string|in:newest,price_asc,price_desc,popularity,rating',
                'filters' => 'nullable|array',
                'filters.categories' => 'nullable|array',
                'filters.materials' => 'nullable|array',
                'filters.sizes' => 'nullable|array',
                'filters.colors' => 'nullable|array',
                'filters.priceRange' => 'nullable|array|size:2',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Get parameters with defaults
            $currentPage = $request->input('currentPage', 1);
            $itemsPerPage = $request->input('itemsPerPage', 12);
            $sortBy = $request->input('sortBy', 'newest');
            $filters = $request->input('filters', [
                'categories' => [],
                'materials' => [],
                'sizes' => [],
                'colors' => [],
                'priceRange' => [0, 1000000]
            ]);

            // Generate cache key based on request parameters
            $cacheKey = 'products_' . md5(json_encode([
                'page' => $currentPage,
                'perPage' => $itemsPerPage,
                'sort' => $sortBy,
                'filters' => $filters
            ]));

            // Try to get from cache first (5 minutes)
            // return Cache::remember($cacheKey, 300, function () use ($currentPage, $itemsPerPage, $sortBy, $filters) {
                $query = Barang::query();

                // Apply filters
                // if (!empty($filters['categories'])) {
                //     $query->whereIn('category_id', $filters['categories']);
                // }

                // if (!empty($filters['materials'])) {
                //     $query->whereIn('material_id', $filters['materials']);
                // }

                // if (!empty($filters['sizes'])) {
                //     $query->whereHas('sizes', function ($q) use ($filters) {
                //         $q->whereIn('size_id', $filters['sizes']);
                //     });
                // }

                // if (!empty($filters['colors'])) {
                //     $query->whereHas('colors', function ($q) use ($filters) {
                //         $q->whereIn('color_id', $filters['colors']);
                //     });
                // }

                // if (!empty($filters['priceRange']) && count($filters['priceRange']) === 2) {
                //     $query->whereBetween('price', $filters['priceRange']);
                // }

                // Apply sorting
                switch ($sortBy) {
                    case 'newest':
                        $query->orderBy('created_at', 'desc');
                        break;
                    // case 'price_asc':
                    //     $query->orderBy('price', 'asc');
                    //     break;
                    // case 'price_desc':
                    //     $query->orderBy('price', 'desc');
                    //     break;
                    // case 'popularity':
                    //     $query->orderBy('view_count', 'desc');
                    //     break;
                    // case 'rating':
                    //     $query->orderBy('average_rating', 'desc');
                    //     break;
                    default:
                        $query->orderBy('created_at', 'desc');
                }

                self::selectQuery($query);

                // Use simplePaginate for better performance
                $products = $query->simplePaginate($itemsPerPage, ['*'], 'page', $currentPage);

                return response()->json([
                    'success' => true,
                    'data' => $products,
                    'meta' => [
                        'currentPage' => $products->currentPage(),
                        'perPage' => $itemsPerPage,
                        'hasMorePages' => $products->hasMorePages()
                    ]
                ]);
            // });
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve products',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function productById($id)
    {
        // Increment view count in Redis
        Redis::connection('views')->incr("views:barang:{$id}");
        $query = Barang::query();
            self::selectQuery($query);
        $data = $query->findOrFail($id);

        return response()->json($data);
    }
    public function productLike($id)
    {
        Log::info('productLike called');
        // Increment view count in Redis
        Redis::connection('likes')->incr("likes:barang:{$id}");
        // $query = Barang::query();
        //     self::selectQuery($query);
        // $data = $query->findOrFail($id);

        Log::info('Auth::user()', [Auth::user()]);

        if (Auth::user()) {
            $list = Wishlist::firstOrCreate([
                'barang_id'=> $id,
                'user_id'=> Auth::user()->id
            ]);

            return response()->json($list);
        } else {
            Log::error('User not authenticated');
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

    public static function selectQuery($query)
    {
       $query->select(
            'id', 'kodebarang', 
            'namabarang AS name', 
            'namagabung', 
            'kualitas', 
            'brand', 
            'satuan_b', 
            'satuan_k', 
            'kategori AS category', 
            'isi', 
            'hargajual1 AS price',
            'hargajual2', 
            'ukuran', 
            'kodejenis',
            'image'
        );


        $query->with(['images:kodebarang,gambar,flag_thumbnail', 'views:barang_id,views', 'likes:barang_id,likes',
            'stoks'=> function($q){
                $q->select(
                    'kdbarang','motif','jumlah_k','isi','satuan_k','satuan_b'
                )->where('jumlah_k','!=',0);
            }
        ]);
        return $query;
    }

    public function whishlist()
    {
        $user = Auth::user();
        $query = Wishlist::query();
        $query->with(['barang'=>function($q){
            self::selectQuery($q);
        }]);
        $query->where('user_id', $user->id);
        $data = $query->limit(100)->get();

        return response()->json($data);
    }
}
