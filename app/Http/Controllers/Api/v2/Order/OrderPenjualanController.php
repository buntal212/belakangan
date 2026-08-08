<?php

namespace App\Http\Controllers\Api\v2\Order;

use App\Events\SendNotificationEvent;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Models\OrderPenjualan; // pastikan model sudah dibuat
use Illuminate\Support\Facades\DB;

class OrderPenjualanController extends Controller
{
    public function generateNoOrder()
    {
        do {
            $date = now()->format('ymd');
            $random = strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
            $noorder = "{$date}-{$random}-OPJ";
        } while (OrderPenjualan::where('noorder', $noorder)->exists());

        return $noorder;
    }

    public function orderPenjualan(Request $request)
    {
        // Generate noorder unik
        $noorder = $this->generateNoOrder();

        // Validasi input utama dan rincian (tglorder dihapus)
        $validated = $request->validate([
            'pelanggan_id'       => 'required|exists:pelanggans,id',
            'sales_id'           => 'required|exists:users,id',
            'total_harga'        => 'required|numeric|min:0',
            'status_pembayaran'  => 'nullable|in:1,2',
            'rincians'           => 'required|array|min:1',
            'rincians.*.barang_id' => 'required|exists:barangs,id',
            'rincians.*.jumlah'       => 'required|integer|min:1',
            'rincians.*.harga'     => 'required|numeric|min:0',
            'rincians.*.subtotal'  => 'required|numeric|min:0',
            'rincians.*.isi'  => 'required|numeric|min:1',
            'rincians.*.satuan'  => 'nullable',
            'rincians.*.satuans'  => 'nullable',
        ]);

        DB::beginTransaction();
        try {
            // Simpan order penjualan, tglorder otomatis now()
            $order = OrderPenjualan::create([
                'noorder'           => $noorder,
                'tglorder'          => now(),
                'pelanggan_id'      => $validated['pelanggan_id'],
                'sales_id'          => $validated['sales_id'],
                'total_harga'       => $validated['total_harga'],
                'status_order'      => $validated['status_order'] ?? '1',
                'status_pembayaran' => $validated['status_pembayaran'] ?? '1',
            ]);

            foreach ($validated['rincians'] as $rincian) {
                $order->rincians()->create([
                    'barang_id' => $rincian['barang_id'],
                    'jumlah'       => $rincian['jumlah'],
                    'isi'       => $rincian['isi'],
                    'harga'     => $rincian['harga'],
                    'subtotal'  => $rincian['subtotal'],
                    'satuan'  => $rincian['satuan'],
                    'satuans'  => $rincian['satuans'],
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Order penjualan & rinciannya berhasil dibuat.',
                'data'    => $order->load('rincians')
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal membuat order penjualan: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function getByPelanggan()
    {
        $user = Auth::user();
        $jabatan = $user->kodejabatan;

        $query = OrderPenjualan::query();
        $query->where(function ($query) use ($user, $jabatan) {
            if ($jabatan == 3) { // Sales
                $query->where('sales_id', $user->id);
            } else { // Pelanggan
                $query->where('pelanggan_id', $user->id);
            }
        })
            ->orderByDesc('tglorder')
            ->limit(100);
        $orders = $this->eagerLoadOrder($query)->get();


        return response()->json([
            'success' => true,
            'data' => $orders
        ]);
    }

    public function getBySales($sales_id)
    {
        $orders = OrderPenjualan::with(['rincians:order_penjualan_id,produk_id,qty,harga,subtotal'])
            ->select('id', 'noorder', 'tglorder', 'pelanggan_id', 'sales_id', 'total_harga', 'status_order', 'status_pembayaran', 'tanggal_kirim', 'tanggal_terima')
            ->where('sales_id', $sales_id)
            ->orderByDesc('tglorder')
            ->simplePaginate(20);

        return response()->json([
            'success' => true,
            'data' => $orders
        ]);
    }

    public function getByNoOrder()
    {
        $noorder = request('order');

        $order = OrderPenjualan::query();
        $order->where('noorder', $noorder);
        $data = $this->eagerLoadOrder($order)->first();

        $result = [
            'message' => 'success',
            'data' => $data
        ];
        return response()->json($result);
    }

    public function updatePembayaran(Request $request)
    {
        $noorder = $request->noorder;

        $data = OrderPenjualan::where('noorder', $noorder)->firstOrFail();

        $data->metode_bayar = $request->metode_bayar;
        $data->bayar = $request->bayar;
        $data->tempo = $request->tempo;
        $data->catatan = $request->catatan;
        $data->status_order = $request->status_order;
        $data->status_pembayaran = $request->metode_bayar === 'Kredit' ? '1' : '2';

        $data->save();

        $result = [
            'message' => 'success',
            'data' => $noorder
        ];

        $event = ['order' => $data];

        event(new SendNotificationEvent(null, 'order-penjualan', 'order-status', $event));
        return response()->json($result);
    }

    public function updateSelesai(Request $request)
    {
        $data = OrderPenjualan::find($request->id);
        $data->status_order = $request->status_order;
        $data->save();

        $event = ['order' => $data];
        event(new SendNotificationEvent(null, 'order-penjualan', 'order-status', $event));

        return response()->json($data);
    }

    protected function eagerLoadOrder($query)
    {
        return $query->select(
            'id',
            'noorder',
            'tglorder',
            'pelanggan_id',
            'sales_id',
            'total_harga',
            'status_order',
            'status_pembayaran',
            'tanggal_kirim',
            'tanggal_terima',
            'metode_bayar',
            'bayar',
            'tempo',
            'catatan'
        )->with([
            'rincians:id,order_penjualan_id,barang_id,jumlah,harga,satuan,satuans,subtotal',
            'rincians.barang:id,namabarang,isi,satuan_k,satuan_b',
            'rincians.barang.images',
            'pelanggan:id,nama',
            'sales:id,nama'
        ]);
    }
}
