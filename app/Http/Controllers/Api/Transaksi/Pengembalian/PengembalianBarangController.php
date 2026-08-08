<?php

namespace App\Http\Controllers\Api\Transaksi\Pengembalian;

use App\Http\Controllers\Controller;
use App\Models\Stok\stok;
use App\Models\Transaksi\Pengembalian\HeaderPengembalian;
use App\Models\Transaksi\Pengembalian\DetailPengembalian;
use App\Models\Barang;
use App\Models\Transaksi\Penjualan\DetailPenjualanFifo;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Exception;

class PengembalianBarangController extends Controller
{
    public function index(Request $request)
    {
        try {
            $query = HeaderPengembalian::with(['penjualan', 'details.barang', 'creator'])
                ->orderBy($request->sortBy ?? 'tanggal', $request->sortDesc ? 'desc' : 'asc');

            if ($request->status) {
                $query->where('status', $request->status);
            }

            if ($request->search) {
                $query->where(function ($q) use ($request) {
                    $q->where('no_pengembalian', 'like', "%{$request->search}%")
                        ->orWhereHas('penjualan', function ($q) use ($request) {
                            $q->where('no_penjualan', 'like', "%{$request->search}%");
                        });
                });
            }

            $pengembalians = $query->paginate($request->perPage ?? 10);

            return new JsonResponse([
                'message' => 'Data pengembalian barang berhasil diambil',
                'data' => $pengembalians
            ]);
        } catch (Exception $e) {
            return new JsonResponse([
                'message' => 'Error mengambil data pengembalian barang: ' . $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request)
    {
        // return new JsonResponse($request->all());
        $request->validate([
            'penjualan_id' => 'required|exists:header_penjualans,id',
            'keterangan' => 'required|string',
            'details' => 'required|array|min:1',
            'details.*.barang_id' => 'required|exists:barangs,id',
            'details.*.qty' => 'required|numeric|min:1',
            'details.*.keterangan_rusak' => 'required|string'
        ]);

        try {
            DB::beginTransaction();

            // Create header
            $header = HeaderPengembalian::create([
                'no_pengembalian' => 'RTN' . date('YmdHis'),
                'header_penjualan_id' => $request->penjualan_id,
                'no_penjualan' => $request->no_penjualan,
                'tanggal' => Carbon::now(),
                'keterangan' => $request->keterangan,
                'status' => 'pending',
                'created_by' => auth()->id(),
            ]);

            // Create details dan update stok
            foreach ($request->details as $detail) {
                // Create detail pengembalian
                DetailPengembalian::updateOrCreate([
                    'header_pengembalian_id' => $header->id,
                    'barang_id' => $detail['barang_id'],
                    'motif' => $detail['motif'],
                ], [
                    'kodebarang' => $detail['kodebarang'],
                    'qty' => $detail['qty'],
                    'keterangan_rusak' => $detail['keterangan_rusak'],
                    'status' => 'pending'
                ]);

                // Load barang data first
                $barang = Barang::where('kodebarang', $detail['kodebarang'])->first();
                if (!$barang) {
                    throw new Exception("Barang tidak ditemukan");
                }

                // Validate FIFO returns for this detail
                $penjualanFifos = DetailPenjualanFifo::where('no_penjualan', $request->no_penjualan)
                    ->where('kodebarang', $detail['kodebarang'])
                    ->orderBy('id', 'asc')
                    ->get();

                if ($penjualanFifos->isEmpty()) {
                    throw new Exception("Data penjualan FIFO tidak ditemukan untuk barang {$barang->namabarang}");
                }

                // Calculate total available quantity from all FIFO records
                $totalJumlah = $penjualanFifos->sum('jumlah');
                $totalRetur = $penjualanFifos->sum('retur');

                // Check if total return quantity exceeds total purchased quantity
                $sisaKuotaRetur = $totalJumlah - $totalRetur;
                if (($totalRetur + $detail['qty']) > $totalJumlah) {
                    throw new Exception("Jumlah retur melebihi jumlah pembelian untuk barang {$barang->namabarang}. Maksimal retur yang tersisa: {$sisaKuotaRetur}");
                }
            }

            DB::commit();

            $data = $header->load(['penjualan', 'details.barang']);
            return new JsonResponse([
                'message' => 'Pengembalian barang berhasil disimpan',
                'data' => $data
            ], 201);
        } catch (Exception $e) {
            DB::rollback();
            return new JsonResponse([
                'message' => 'Error menyimpan pengembalian barang: ' . $e->getMessage()
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $pengembalian = HeaderPengembalian::with([
                'penjualan',
                'details.barang',
                'creator'
            ])->findOrFail($id);

            return new JsonResponse([
                'message' => 'Detail pengembalian barang berhasil diambil',
                'data' => $pengembalian
            ]);
        } catch (Exception $e) {
            return new JsonResponse([
                'message' => 'Error mengambil detail pengembalian barang: ' . $e->getMessage()
            ], 500);
        }
    }

    public function approve($id)
    {
        try {
            DB::beginTransaction();

            $header = HeaderPengembalian::with(['details.barang', 'penjualan'])->findOrFail($id);

            // Validate FIFO returns for each detail
            foreach ($header->details as $detail) {
                // Get all FIFO records for this item in the sale
                $penjualanFifos = DetailPenjualanFifo::selectRaw('detail_penjualan_fifos.*')
                    ->leftJoin('stoks', 'stoks.id', '=', 'detail_penjualan_fifos.stok_id')
                    ->where('no_penjualan', $header->penjualan->no_penjualan)
                    ->where('kodebarang', $detail->kodebarang)
                    ->where('motif', $detail->motif)
                    ->orderBy('id', 'asc')  // Ensure we process oldest records first
                    ->get();

                if ($penjualanFifos->isEmpty()) {
                    throw new Exception("Data penjualan FIFO tidak ditemukan untuk barang {$detail->barang->namabarang}");
                }

                // Calculate total available quantity from all FIFO records
                $totalJumlah = $penjualanFifos->sum('jumlah');
                $totalRetur = $penjualanFifos->sum('retur');
                $sisaRetur = $detail->qty;

                // Check if total return quantity exceeds total purchased quantity
                $sisaKuotaRetur = $totalJumlah - $totalRetur;
                if (($totalRetur + $detail->qty) > $totalJumlah) {
                    throw new Exception("Jumlah retur melebihi jumlah pembelian untuk barang {$detail->barang->namabarang}. Maksimal retur yang tersisa: {$sisaKuotaRetur}");
                }

                // Update retur quantity in FIFO records one by one
                foreach ($penjualanFifos as $fifo) {
                    $availableForReturn = $fifo->jumlah - $fifo->retur;

                    if ($availableForReturn > 0) {
                        $returAmount = min($sisaRetur, $availableForReturn);
                        $fifo->retur += $returAmount;
                        $fifo->save();

                        $sisaRetur -= $returAmount;
                        if ($sisaRetur <= 0) break;
                    }
                }
            }

            // Update header status
            $header->update([
                'status' => 'diganti',
                'approved_by' => auth()->id(),
                'approved_at' => Carbon::now()
            ]);

            // Update detail status and process stock returns
            foreach ($header->details as $detail) {
                $detail->update(['status' => 'diganti']);

                // Get available stocks with jumlah_k > 0
                $stoks = stok::where('kdbarang', $detail->kodebarang)
                    ->where('motif', $detail->motif)
                    ->where('jumlah_k', '>', 0)
                    ->orderBy('created_at', 'asc')
                    ->get();

                // Get latest price even if stock is empty
                $lastHargaBeli = stok::where('kdbarang', $detail->kodebarang)
                    ->where('motif', $detail->motif)
                    ->orderBy('created_at', 'desc')
                    ->value('harga_beli_k') ?? 0;

                $sisaQty = $detail->qty;
                $fifoBatch = [];

                // Iterasi per record stok (FIFO)
                foreach ($stoks as $stok) {
                    if ($sisaQty <= 0) break;

                    $qtyAmbil = min($sisaQty, $stok->jumlah_k);

                    // Create FIFO record for this batch
                    $fifoBatch[] = [
                        'no_penjualan' => $header->no_pengembalian,
                        'kodebarang' => $detail->kodebarang,
                        'jumlah' => $qtyAmbil,
                        'retur' => 0,
                        'harga_beli' => $stok->harga_beli_k,
                        'harga_jual' => 0, // Set to 0 since it's a return
                        'diskon' => 0,
                        'subtotal' => 0,
                        'stok_id' => $stok->id
                    ];

                    // Update stock quantity for this batch
                    $stok->jumlah_k -= $qtyAmbil;
                    $stok->save();

                    $sisaQty -= $qtyAmbil;
                }

                // If we still have remaining qty, create record with null stok_id
                if ($sisaQty > 0) {
                    // We already have lastHargaBeli from earlier query

                    // Add record for remaining quantity
                    $fifoBatch[] = [
                        'no_penjualan' => $header->no_pengembalian,
                        'kodebarang' => $detail->kodebarang,
                        'jumlah' => $sisaQty,
                        'retur' => 0,
                        'harga_beli' => $lastHargaBeli,
                        'harga_jual' => 0,
                        'diskon' => 0,
                        'subtotal' => 0,
                        'stok_id' => null // Indicate this is a stockless return
                    ];
                }

                // Create all FIFO records for this return
                DetailPenjualanFifo::insert($fifoBatch);
            }

            DB::commit();

            return new JsonResponse([
                'message' => 'Pengembalian barang berhasil disetujui',
                'data' => $header->load(['penjualan', 'details.barang'])
            ]);
        } catch (Exception $e) {
            DB::rollback();
            return new JsonResponse([
                'message' => 'Error menyetujui pengembalian barang: ' . $e->getMessage()
            ], 500);
        }
    }

    public function reject($id)
    {
        try {
            DB::beginTransaction();

            $header = HeaderPengembalian::with('details')->findOrFail($id);

            // Update header status
            $header->update([
                'status' => 'ditolak',
                'rejected_by' => auth()->id(),
                'rejected_at' => Carbon::now()
            ]);

            // Update detail status
            foreach ($header->details as $detail) {
                $detail->update(['status' => 'ditolak']);

                // Rollback quantity in FIFO record
                // $penjualanFifo = DetailPenjualanFifo::where('no_penjualan', $header->penjualan->no_penjualan)
                //     ->where('barang_id', $detail->barang_id)
                //     ->first();

                // if ($penjualanFifo) {
                //     $penjualanFifo->retur -= $detail->qty;
                //     $penjualanFifo->save();
                // }
            }

            DB::commit();

            return new JsonResponse([
                'message' => 'Pengembalian barang berhasil ditolak',
                'data' => $header->load(['penjualan', 'details.barang'])
            ]);
        } catch (Exception $e) {
            DB::rollback();
            return new JsonResponse([
                'message' => 'Error menolak pengembalian barang: ' . $e->getMessage()
            ], 500);
        }
    }
}
