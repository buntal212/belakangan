<?php

namespace App\Http\Controllers\Api\Transaksi\Retur;

use App\Http\Controllers\Controller;
use App\Models\KeteranganPelanggan;
use App\Models\Pelanggan;
use App\Models\Stok\stok;
use App\Models\Transaksi\Penjualan\DetailPenjualan;
use App\Models\Transaksi\Penjualan\DetailPenjualanFifo;
use App\Models\Transaksi\Penjualan\DetailReturPenjualan;
use App\Models\Transaksi\Penjualan\HeaderPenjualan;
use App\Models\Transaksi\Penjualan\HeaderReturPenjualan;
use App\Models\Transaksi\Penjualan\PembayaranCicilan;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReturPenjualanController extends Controller
{
    //
    public function retur()
    {
        $from = request('from') ? request('from') : date('Y-m-01');
        $to = request('to') ? request('to') : date('Y-m-d');
        $q = request('q') ?? false;


        $raw = HeaderReturPenjualan::with([
            'detail.masterBarang',
            'detail.detailPenjualan',
            'headerPenjualan.pelanggan',
            'headerPenjualan.sales',
            'headerPenjualan.keterangan',
            'headerPenjualan.detail',
        ])->when($q, function ($x) use ($q) {
            $x->where(function ($sub) use ($q) {
                $sub->where('no_retur', 'LIKE', '%' . $q . '%')
                    ->orWhere('no_penjualan', 'LIKE', '%' . $q . '%');
            });
        })
            ->whereBetween('tgl', [$from . ' 00:00:00', $to . ' 23:59:59'])
            ->orderBy('tgl', 'desc')
            ->paginate(request('per_page'));

        $data['data'] = collect($raw)['data'];
        $data['meta'] = collect($raw)->except('data');
        return new JsonResponse($data);
    }
    public function index()
    {

        $from = request('from') ? request('from') : date('Y-m-01');
        $to = request('to') ? request('to') : date('Y-m-d');
        $q = request('q') ?? false;
        $pelangganId = [];
        $salesId = [];
        $keteranganId = [];
        if ($q) {
            $pelangganId = Pelanggan::select('id')->where('nama', 'LIKE', '%' . $q . '%')->pluck('id')->toArray();
            $salesId = User::select('id')->where('nama', 'LIKE', '%' . $q . '%')->where('jabatan', 'Sales')->pluck('id')->toArray();
            $keteranganId = KeteranganPelanggan::select('header_penjualan_id')->where('nama', 'LIKE', '%' . $q . '%')->pluck('header_penjualan_id')->toArray();
        }
        $raw = HeaderPenjualan::when($q, function ($x) use ($q) {
            $x->where('no_penjualan', 'LIKE', '%' . $q . '%');
        })
            ->when($pelangganId, function ($x) use ($pelangganId) {
                $x->orWhereIn('pelanggan_id', $pelangganId);
            })
            ->when($salesId, function ($x) use ($salesId) {
                $x->orWhereIn('sales_id', $salesId);
            })
            ->when($keteranganId, function ($x) use ($keteranganId) {
                $x->orWhereIn('id', $keteranganId);
            })

            ->whereBetween('tgl', [$from . ' 00:00:00', $to . ' 23:59:59'])
            //->whereIn('flag', ['2', '3', '4', '5'])
            ->with([
                'pelanggan',
                'sales',
                'detail.masterBarang',
                'keterangan',
                'detailRetur' => function ($q) {
                    $q->select(
                        'header_retur_penjualans.status',
                        'detail_retur_penjualans.no_penjualan',
                        'detail_retur_penjualans.kodebarang',
                        'detail_retur_penjualans.harga_jual',
                        'detail_retur_penjualans.detail_penjualan_id',
                        DB::raw('sum(detail_retur_penjualans.jumlah) as jumlah'),
                        DB::raw('sum(detail_retur_penjualans.subtotal) as subtotal'),
                    )
                        ->leftJoin('header_retur_penjualans', 'header_retur_penjualans.id', '=', 'detail_retur_penjualans.header_retur_penjualan_id')
                        ->groupBy('detail_retur_penjualans.kodebarang', 'detail_retur_penjualans.no_penjualan', 'detail_retur_penjualans.detail_penjualan_id', 'detail_retur_penjualans.header_retur_penjualan_id', 'header_retur_penjualans.status')
                    ;
                },
                'headerRetur.detail',
                'draftRetur' => function ($q) {
                    $q->where('status', '');
                }
            ])
            ->orderBy('created_at', 'desc')
            ->paginate(request('per_page') ?? 10);
        $data['data'] = collect($raw)['data'];
        $data['meta'] = collect($raw)->except('data');

        return new JsonResponse($data);
    }
    public function store(Request $request)
    {
        $request->validate([
            'no_penjualan' => 'required',
            'harga_jual' => 'required',
            'kodebarang' => 'required',
            'retur' => 'required',
        ]);

        DB::beginTransaction();
        try {
            $headPenjualan = HeaderPenjualan::where('no_penjualan', $request->no_penjualan)
                ->lockForUpdate()
                ->first();

            if (!$headPenjualan) {
                DB::rollBack();
                return new JsonResponse(['message' => 'Data penjualan tidak ditemukan.'], 404);
            }

            $cektrans = ($headPenjualan->flag !== '2') ? 1 : 0;
            $cekpembayaran = PembayaranCicilan::where('no_penjualan', $request->no_penjualan)->count();
            if ($cektrans === 0 && $cekpembayaran > 0) {
                DB::rollBack();
                return new JsonResponse(['message' => 'Maaf Tidak Dapat Diretur...,Nota Ini Sudah Dicicil...!!!'], 500);
            }

            // Cari atau buat draft header retur
            $headRetur = HeaderReturPenjualan::where('no_penjualan', $request->no_penjualan)
                ->where('status', '')
                ->lockForUpdate()
                ->first();

            if (!$headRetur) {
                $strNo = explode('-', $request->no_penjualan);
                $count = HeaderReturPenjualan::where('no_penjualan', $request->no_penjualan)->count();
                $noretur = $strNo[0] . '-' . str_pad($count + 1, 2, '0', STR_PAD_LEFT) . '-' . date('ymd') . '-RTR';
                $headRetur = HeaderReturPenjualan::create([
                    'no_retur' => $noretur,
                    'no_penjualan' => $request->no_penjualan,
                    'tgl' => date('Y-m-d H:i:s'),
                    'status' => '',
                    'total' => 0,
                ]);
            } else {
                $noretur = $headRetur->no_retur;
            }

            // Identifikasi detail penjualan (Section A)
            $targetDetailId = $request->id ?? $request->detail_penjualan_id;
            $detailPenjualan = null;

            if (!empty($targetDetailId)) {
                $detailPenjualan = DetailPenjualan::where('id', $targetDetailId)
                    ->where('no_penjualan', $request->no_penjualan)
                    ->lockForUpdate()
                    ->first();

                if (!$detailPenjualan) {
                    throw new \Exception('Data rincian penjualan tidak ditemukan untuk ID yang diberikan.');
                }

                if ($request->filled('kodebarang') && $detailPenjualan->kodebarang !== $request->kodebarang) {
                    throw new \Exception('Kode barang tidak cocok dengan data rincian penjualan.');
                }
            } else {
                // Fallback kompatibilitas jika id tidak dikirim: pastikan identitas unik dan tidak ambigu
                $candidates = DetailPenjualan::where('no_penjualan', $request->no_penjualan)
                    ->where('kodebarang', $request->kodebarang)
                    ->lockForUpdate()
                    ->get();

                if ($candidates->count() === 0) {
                    throw new \Exception('Data rincian penjualan tidak ditemukan.');
                }

                if ($candidates->count() > 1) {
                    throw new \Exception('Identitas rincian penjualan ambigu karena terdapat lebih dari satu baris barang yang sama. Parameter id (detail_penjualan_id) wajib disertakan.');
                }

                $detailPenjualan = $candidates->first();
            }

            // Validasi Kuantitas (Section B)
            if (!is_numeric($request->retur)) {
                throw new \Exception('Jumlah retur harus berupa angka.');
            }

            $kuantitasReturSK = (float) $request->retur;
            $kuantitasTerjualSK = (float) $detailPenjualan->jumlah;

            if ($kuantitasReturSK <= 0) {
                throw new \Exception('Jumlah retur harus lebih besar dari 0.');
            }

            if ($kuantitasReturSK > $kuantitasTerjualSK) {
                throw new \Exception("Jumlah retur ({$kuantitasReturSK}) tidak boleh melebihi jumlah terjual ({$kuantitasTerjualSK}).");
            }

            // Akumulasi retur yang sudah selesai (status = '1')
            $returSelesaiSK = (float) DetailReturPenjualan::join('header_retur_penjualans', 'header_retur_penjualans.id', '=', 'detail_retur_penjualans.header_retur_penjualan_id')
                ->where('header_retur_penjualans.status', '1')
                ->where('detail_retur_penjualans.detail_penjualan_id', $detailPenjualan->id)
                ->sum('detail_retur_penjualans.jumlah');

            // Akumulasi retur di draft lain jika ada (selain draft aktif $headRetur->id)
            $returDraftLainSK = (float) DetailReturPenjualan::join('header_retur_penjualans', 'header_retur_penjualans.id', '=', 'detail_retur_penjualans.header_retur_penjualan_id')
                ->where('header_retur_penjualans.status', '')
                ->where('header_retur_penjualans.id', '!=', $headRetur->id)
                ->where('detail_retur_penjualans.detail_penjualan_id', $detailPenjualan->id)
                ->sum('detail_retur_penjualans.jumlah');

            $sisaMaksimalSK = max(0.0, round($kuantitasTerjualSK - $returSelesaiSK - $returDraftLainSK, 4));

            if ($kuantitasReturSK > $sisaMaksimalSK) {
                throw new \Exception("Jumlah retur ({$kuantitasReturSK}) melebihi sisa yang dapat diretur ({$sisaMaksimalSK}). Sudah diretur sebelumnya: {$returSelesaiSK}.");
            }

            // Perhitungan Proporsional Bersih & Retur Bertahap (Section D)
            $subtotalHistoris = (float) $detailPenjualan->subtotal;
            $diskonHistoris = (float) ($detailPenjualan->diskon ?? 0);
            $hargaJualSKHistoris = (float) $detailPenjualan->harga_jual;

            $subtotalSelesai = (float) DetailReturPenjualan::join('header_retur_penjualans', 'header_retur_penjualans.id', '=', 'detail_retur_penjualans.header_retur_penjualan_id')
                ->where('header_retur_penjualans.status', '1')
                ->where('detail_retur_penjualans.detail_penjualan_id', $detailPenjualan->id)
                ->sum('detail_retur_penjualans.subtotal');

            $diskonSelesai = (float) DetailReturPenjualan::join('header_retur_penjualans', 'header_retur_penjualans.id', '=', 'detail_retur_penjualans.header_retur_penjualan_id')
                ->where('header_retur_penjualans.status', '1')
                ->where('detail_retur_penjualans.detail_penjualan_id', $detailPenjualan->id)
                ->sum('detail_retur_penjualans.diskon');

            $sisaSubtotalHistoris = max(0.0, round($subtotalHistoris - $subtotalSelesai, 2));
            $sisaDiskonHistoris = max(0.0, round($diskonHistoris - $diskonSelesai, 2));

            $rasio = $kuantitasTerjualSK > 0 ? ($kuantitasReturSK / $kuantitasTerjualSK) : 0;

            if (round($kuantitasReturSK + $returSelesaiSK + $returDraftLainSK, 4) >= round($kuantitasTerjualSK, 4)) {
                $nilaiRetur = $sisaSubtotalHistoris;
                $diskonRetur = $sisaDiskonHistoris;
            } else {
                $nilaiRetur = min($sisaSubtotalHistoris, round($subtotalHistoris * $rasio, 2));
                $diskonRetur = min($sisaDiskonHistoris, round($diskonHistoris * $rasio, 2));
            }

            $nilaiRetur = min($sisaSubtotalHistoris, max(0.0, $nilaiRetur));
            $diskonRetur = min($sisaDiskonHistoris, max(0.0, $diskonRetur));

            // Simpan atau update rincian retur draft berdasarkan detail_penjualan_id
            $headRetur->detail()->updateOrCreate(
                [
                    'header_retur_penjualan_id' => $headRetur->id,
                    'detail_penjualan_id' => $detailPenjualan->id,
                ],
                [
                    'no_penjualan' => $request->no_penjualan,
                    'kodebarang' => $detailPenjualan->kodebarang,
                    'harga_jual' => $hargaJualSKHistoris,
                    'detail_penjualan_id' => $detailPenjualan->id,
                    'jumlah' => $kuantitasReturSK,
                    'diskon' => $diskonRetur,
                    'subtotal' => $nilaiRetur,
                ]
            );

            $total = DetailReturPenjualan::where('header_retur_penjualan_id', $headRetur->id)->sum('subtotal');
            $headRetur->update([
                'total' => max(0.0, round($total, 2)),
            ]);

            DB::commit();

            $pj = HeaderPenjualan::where('no_penjualan', $request->no_penjualan)->with([
                'pelanggan',
                'sales',
                'detail.masterBarang',
                'keterangan',
                'headerRetur.detail',
                'detailRetur' => function ($q) {
                    $q->select(
                        'header_retur_penjualans.status',
                        'detail_retur_penjualans.no_penjualan',
                        'detail_retur_penjualans.kodebarang',
                        'detail_retur_penjualans.harga_jual',
                        'detail_retur_penjualans.detail_penjualan_id',
                        DB::raw('sum(detail_retur_penjualans.jumlah) as jumlah'),
                        DB::raw('sum(detail_retur_penjualans.subtotal) as subtotal'),
                    )
                        ->leftJoin('header_retur_penjualans', 'header_retur_penjualans.id', '=', 'detail_retur_penjualans.header_retur_penjualan_id')
                        ->groupBy('detail_retur_penjualans.kodebarang', 'detail_retur_penjualans.no_penjualan', 'detail_retur_penjualans.detail_penjualan_id', 'detail_retur_penjualans.header_retur_penjualan_id', 'header_retur_penjualans.status')
                    ;
                },
                'draftRetur' => function ($q) {
                    $q->where('status', '');
                }
            ])->first();

            $headRetur->load([
                'detail.masterBarang',
            ]);

            return new JsonResponse([
                'message' => 'Data berhasil disimpan',
                'req' => $request->all(),
                'data' => $headRetur,
                'pj' => $pj,
                'noretur' => $noretur,
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return new JsonResponse([
                'message' => $e->getMessage() ?: 'Data gagal disimpan',
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
                'trace' => $e->getTrace(),
            ], 422);
        }
    }

    public function selesai(Request $request)
    {
        $request->validate([
            'no_retur' => 'required',
        ]);

        try {
            DB::beginTransaction();

            $noretur = $request->no_retur;
            $headRetur = HeaderReturPenjualan::where('no_retur', $noretur)
                ->lockForUpdate()
                ->first();

            if (!$headRetur) {
                DB::rollBack();
                return new JsonResponse([
                    'message' => 'Data tidak ditemukan, mungkin sudah selesai',
                ], 410);
            }

            if ($headRetur->status !== '') {
                DB::rollBack();
                return new JsonResponse([
                    'message' => 'Data tidak ditemukan, mungkin sudah selesai',
                ], 410);
            }

            $no_penjualan = $headRetur->no_penjualan;
            $retur = DetailReturPenjualan::where('header_retur_penjualan_id', $headRetur->id)
                ->lockForUpdate()
                ->get();

            if ($retur->isEmpty()) {
                DB::rollBack();
                return new JsonResponse([
                    'message' => 'Tidak ada rincian barang untuk diselesaikan.',
                ], 422);
            }

            /// tambah stok, ambil dari detail penjualan fifo
            foreach ($retur as $r) {
                $jumlah = (float) $r->jumlah;
                $detailFifo = DetailPenjualanFifo::where('no_penjualan', $no_penjualan)
                    ->where('kodebarang', $r->kodebarang)
                    ->lockForUpdate()
                    ->get();

                foreach ($detailFifo as $detail) {
                    if ($jumlah <= 0) {
                        break;
                    }

                    $sisaRetur = (float)$detail->jumlah - (float)$detail->retur;

                    if ($sisaRetur > 0) {
                        $returBaru = min($sisaRetur, $jumlah);
                        $detail->retur += $returBaru;
                        $detail->save();
                        $jumlah -= $returBaru;

                        // stok
                        $stok = stok::where('id', $detail->stok_id)->lockForUpdate()->first();
                        if ($stok) {
                            $jumlahStok = (int)$stok->jumlah_k + (int)$returBaru;
                            $stok->update([
                                'jumlah_k' => $jumlahStok,
                            ]);
                        }
                    }
                }
            }

            // ganti status header retur
            $headRetur->update([
                'status' => '1',
            ]);

            $headRetur->load([
                'detail.masterBarang',
            ]);

            $pj = HeaderPenjualan::where('no_penjualan', $no_penjualan)->with([
                'pelanggan',
                'sales',
                'detail.masterBarang',
                'keterangan',
                'detailRetur' => function ($q) {
                    $q->select(
                        'header_retur_penjualans.status',
                        'detail_retur_penjualans.no_penjualan',
                        'detail_retur_penjualans.kodebarang',
                        'detail_retur_penjualans.harga_jual',
                        'detail_retur_penjualans.detail_penjualan_id',
                        DB::raw('sum(detail_retur_penjualans.jumlah) as jumlah'),
                        DB::raw('sum(detail_retur_penjualans.subtotal) as subtotal'),
                    )
                        ->leftJoin('header_retur_penjualans', 'header_retur_penjualans.id', '=', 'detail_retur_penjualans.header_retur_penjualan_id')
                        ->groupBy('detail_retur_penjualans.kodebarang', 'detail_retur_penjualans.no_penjualan', 'detail_retur_penjualans.detail_penjualan_id', 'detail_retur_penjualans.header_retur_penjualan_id', 'header_retur_penjualans.status')
                    ;
                },
                'headerRetur.detail',
                'draftRetur' => function ($q) {
                    $q->where('status', '');
                }
            ])->first();

            DB::commit();
            return new JsonResponse([
                'message' => 'Data berhasil disimpan',
                'req' => $request->all(),
                'data' => $headRetur,
                'pj' => $pj,
                'retur' => $retur,
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return new JsonResponse([
                'message' => 'Data gagal disimpan',
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
                'trace' => $e->getTrace(),
            ], 410);
        }
    }
}
