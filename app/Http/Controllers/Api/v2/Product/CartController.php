<?php
namespace App\Http\Controllers\Api\v2\Product;


use App\Http\Controllers\Controller;
use App\Models\Barang;
use App\Models\CartItem;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;



// app/Http/Controllers/CartController.php

class CartController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        $items = CartItem::with($this->eagerLoadProductStoks())
            ->where('user_id', $user->id)
            ->get();
        
            // return $items;

        return response()->json([
            'items' => $items]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'barang_id' => 'required|exists:barangs,id',
            'quantity' => 'required|integer|min:1',
            'price' => 'required|numeric',
            'subtotal' => 'required|numeric',
            'image' => 'nullable|string',
            'satuan' => 'nullable|string',
            'satuans' => 'nullable|array'
        ]);

        $user = Auth::user();

        $item = CartItem::create(
            [
                'user_id' => $user->id, 
                'barang_id' => $request->barang_id,
                'quantity' => $request->quantity, 
                'price' => $request->price,
                'subtotal' => $request->subtotal,
                'image' => $request->image,
                'satuan' => $request->satuan,
                'satuans' => $request->satuans,
            ]
        );

        return response()->json(['success' => true, 
        'item' => $item->load($this->eagerLoadProductStoks())]);
    }

    public function update(Request $request, CartItem $cart)
    {
        $request->validate([
            'quantity' => 'required|numeric',
            'price' => 'required|numeric',
            'subtotal' => 'required|numeric',
        ]);

        $item = CartItem::findOrFail($cart->id);

        $item->update([
            'quantity' => $request->quantity,
            'price' => $request->price,
            'subtotal' => $request->subtotal,
            ]
        );

        return response()->json(['success' => true]);
    }

    public function destroy(Request $request, CartItem $cart)
    {
        $user = Auth::user();
        CartItem::findOrFail($cart->id)
            ->delete();

        return response()->json(['success' => true]);
    }

    public function destroyAllCart(Request $request)
    {
        $user = Auth::user();
        CartItem::where('user_id', $user->id)->delete();

        return response()->json(['success' => true, 'message' => 'Semua item keranjang berhasil dihapus.']);
    }

    protected function eagerLoadProductStoks()
    {
        return [
            'product' => function ($query) {
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
            },
            'product.stoks' => function ($q) {
                $q->select('kdbarang', 'motif', 'jumlah_k', 'isi', 'satuan_k', 'satuan_b')
                ->where('jumlah_k', '!=', 0);
            }
        ];
    }
}
