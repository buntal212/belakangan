<?php
namespace App\Http\Controllers\Api\v3\Sales;
use App\Http\Controllers\Api\v2\Product\ProductController as V2ProductController;
use App\Models\Barang;
use Illuminate\Http\Request;

class ProductController extends V2ProductController
{
    public function getProducts(Request $request)
    {
        $query = Barang::query();
        self::selectQuery($query);
        $products = $query->orderByDesc('created_at')->paginate(min((int) $request->input('itemsPerPage', 12), 100));
        return response()->json(['success' => true, 'data' => $products, 'meta' => ['currentPage' => $products->currentPage(), 'perPage' => $products->perPage(), 'hasMorePages' => $products->hasMorePages()]]);
    }
}
