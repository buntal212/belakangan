<?php

namespace App\Http\Controllers\Api\Transaksi\Penjualan;

use App\Helpers\FormatingHelper;
use App\Http\Controllers\Controller;
use App\Models\Barang;
use App\Models\KeteranganPelanggan;
use App\Models\Pelanggan;
use App\Models\Stok\stok;
use App\Models\Transaksi\Penjualan\DetailPenjualan;
use App\Models\Transaksi\Penjualan\DetailPenjualanFifo;
use App\Helpers\FifoHelper;
use App\Models\Transaksi\Penjualan\HeaderPenjualan;
use App\Models\User;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PenjualanController extends Controller
{
    public function getBarangByStok()
    {
        $kodebarang = Barang::select(
            'kodebarang',
        )
            ->whereNull('flaging')
            ->where(function ($x) {
                $x->where('namabarang', 'like', '%' . request('q') . '%')
                    ->orWhere('kodebarang', 'like', '%' . request('q') . '%');
            })

            ->limit(request('limit'))
            ->pluck('kodebarang')->toArray();
        $data = stok::select(
            'kdbarang',
            DB::raw('sum(jumlah_k) as jumlah_k'),
            'harga_beli_k',
            'isi',
            'motif',
            'satuan_k',
            'satuan_b',
        )
            ->whereIn('kdbarang', $kodebarang)
            ->where('jumlah_k', '>', 0)
            ->with('barang:kodebarang,namabarang,ukuran,hargajual1,hargajual2,id')
            ->groupBy('kdbarang', 'motif')
            ->get();

        return new JsonResponse($data);
    }
    public function getBarang()
    {
        $data = Barang::select(
            'brand',
            'namabarang',
            'kodebarang',
            'id',
            'satuan_k',
            'seri',
            'isi',
            'ukuran',
            'hargajual1',
            'hargajual2',
        )
            ->whereNull('flaging')
            ->where(function ($x) {
                $x->where('namabarang', 'like', '%' . request('q') . '%')
                    ->orWhere('kodebarang', 'like', '%' . request('q') . '%');
            })
            ->with([
                'stok' => function ($q) {
                    $q->select(
                        'kdbarang',
                        DB::raw('sum(jumlah_b) as jumlah_b'),
                        DB::raw('sum(jumlah_k) as jumlah_k'),
                        'isi',
                        'satuan_b',
                        'satuan_k',
                        'harga_beli_b',
                        'harga_beli_k',
                    )
                        ->groupBy('kdbarang')
                        ->where('jumlah_k', '>', 0);
                },
            ])
            ->limit(request('limit'))
            ->get();
        return new JsonResponse($data);
    }
    public function getSales()
    {
        // temporary sebelum ada data sales
        $data = User::where('jabatan', 'Sales')->get();
        return new JsonResponse($data);
    }
    public function getPelanggan()
    {
        $data = Pelanggan::whereNull('flaging')
            ->where(function ($x) {
                $x->where('nama', 'like', '%' . request('q') . '%')
                    ->orWhere('kodeplgn', 'like', '%' . request('q') . '%')
                    ->orWhere('namabank', 'like', '%' . request('q') . '%')
                    ->orWhere('telepon', 'like', '%' . request('q') . '%')
                    ->orWhere('alamat', 'like', '%' . request('q') . '%');
            })
            ->limit(request('limit'))
            ->get();
        return new JsonResponse($data);
    }
    public function simpanDetail(Request $request)
    {
        try {
            DB::beginTransaction();
            if ($request->nota === null) {
                DB::select('call no_nota_penjualan(@nomor)');
                $x = DB::table('counter')->select('penjualan')->first();
                $no = $x->penjualan;

                $nota = FormatingHelper::notaPenjualan($no, 'PJL');
            } else {
                $nota = $request->nota;
            }
            $subtotal = ($request->jumlah * $request->harga_jual) - $request->diskon;
            $detail = DetailPenjualan::updateOrCreate(
                [
                    'no_penjualan' => $nota,
                    'kodebarang' => $request->kodebarang,
                    'motif' => $request->motif,
                    'jumlah' => $request->jumlah,
                    'isi' => $request->isi,
                ],
                [
                    'harga_jual' => $request->harga_jual,
                    'harga_beli' => $request->harga_beli,
                    'diskon' => $request->diskon,
                    'subtotal' => $subtotal
                ]
            );
            if (!$detail) {
                throw new Exception("Detail Tidak Tersimpan", 1);
            }
            $total = DetailPenjualan::where('no_penjualan', '=', $nota)->sum('subtotal');
            $totalDiskon = DetailPenjualan::where('no_penjualan', '=', $nota)->sum('diskon');
            $data = HeaderPenjualan::updateOrCreate(
                [
                    'no_penjualan' => $nota,
                ],
                [
                    'tgl' => date('Y-m-d H:i:s'),
                    'sales_id' => $request->sales_id,
                    'pelanggan_id' => $request->pelanggan_id,
                    'total' => $total,
                    'total_diskon' => $totalDiskon,
                ]
            );
            if (!$detail) {
                throw new Exception("Header Tidak Tersimpan", 1);
            }
            $header = HeaderPenjualan::find($data->id);
            $header->load([
                'detail' => function ($q) {
                    $q->with([
                        'masterBarang' => function ($x) {
                            $x->with([
                                'stok' => function ($q) {
                                    $q->select(
                                        'kdbarang',
                                        DB::raw('sum(jumlah_b) as jumlah_b'),
                                        DB::raw('sum(jumlah_k) as jumlah_k'),
                                        'isi',
                                        'satuan_b',
                                        'satuan_k',
                                        'harga_beli_b',
                                        'harga_beli_k',
                                    )
                                        ->groupBy('kdbarang')
                                        ->where('jumlah_k', '>', 0);
                                },
                            ]);
                        }
                    ]);
                },
                'sales',
                'pelanggan'
            ]);
            DB::commit();
            return new JsonResponse([
                'message' => 'Data telah disimpan',
                'detail' => $detail,
                'header' => $header,
                'nota' => $nota,
                // 'data' => $data,
                'total' => $total,
            ]);
        } catch (\Throwable $th) {
            DB::rollBack();
            return new JsonResponse([
                'message' => $th->getMessage(),
                'line' => $th->getLine(),
                'file' => $th->getFile(),
            ], 410);
        }
    }
    /**
     * list penjualan
     * jika penjualan dari hp di flag 1
     * di front end di bedakan cara edit nya
     */

    public function getListPenjualan()
    {
        $from = request('from') ? request('from') : date('Y-m-01');
        $to = request('to') ? request('to') : date('Y-m-d');
        $flag = request('flag');

        $raw = HeaderPenjualan::with([
            'pelanggan',
            // 'detailFifo.masterBarang',
            'detailFifo' => function ($q) {
                $q->select(
                    'no_penjualan',
                    'kodebarang',
                    'harga_jual',
                    DB::raw('sum(jumlah) as jumlah'),
                    DB::raw('sum(subtotal) as subtotal'),
                    DB::raw('sum(diskon) as diskon'),
                    DB::raw('sum(retur) as retur'),
                )
                    ->groupBy('kodebarang', 'no_penjualan')
                    ->with(['masterBarang'])
                ;
            },
            'detail' => function ($q) {
                $q->with([
                    'masterBarang' => function ($x) {
                        $x->with([
                            'stok' => function ($q) {
                                $q->select(
                                    'kdbarang',
                                    DB::raw('sum(jumlah_b) as jumlah_b'),
                                    DB::raw('sum(jumlah_k) as jumlah_k'),
                                    'isi',
                                    'satuan_b',
                                    'satuan_k',
                                    'harga_beli_b',
                                    'harga_beli_k',
                                )
                                    ->groupBy('kdbarang')
                                    ->where('jumlah_k', '>', 0);
                            },
                        ]);
                    }
                ]);
            },
            'sales',
            'keterangan',
            'headerRetur.detail',
            // 'detailRetur' => function ($q) {
            //     $q->select(
            //         'status',
            //         'detail_retur_penjualans.no_penjualan',
            //         'kodebarang',
            //         'harga_jual',
            //         DB::raw('sum(jumlah) as jumlah'),
            //         DB::raw('sum(subtotal) as subtotal'),
            //     )
            //         ->leftJoin('header_retur_penjualans', 'header_retur_penjualans.id', '=', 'detail_retur_penjualans.header_retur_penjualan_id')
            //         ->groupBy('kodebarang', 'detail_retur_penjualans.no_penjualan')
            //     ;
            // },
        ])
            ->when(request('q'), function ($q) {
                $pelId = Pelanggan::where('nama', 'like', '%' . request('q') . '%')->pluck('id')->toArray();
                $idFromKet = KeteranganPelanggan::where('nama', 'like', '%' . request('q') . '%')->pluck('header_penjualan_id')->toArray();
                $q->where(function ($x) use ($pelId, $idFromKet) {
                    $x->where('no_penjualan', 'like', '%' . request('q') . '%')
                        ->when(sizeof($pelId) > 0, function ($c) use ($pelId) {
                            $c->orWhereIn('pelanggan_id', $pelId);
                        })
                        ->when(sizeof($idFromKet) > 0, function ($c) use ($idFromKet) {
                            $c->orWhereIn('id', $idFromKet);
                        });
                });
            })

            ->whereBetween('tgl', [$from . ' 00:00:00', $to . ' 23:59:59'])
            ->when(request()->has('flag'), function ($q) use ($flag) {
                if ($flag != 'semua') {
                    if ($flag == 'draft') {
                        $q->whereNull('flag');
                    } else if ($flag == 'piutang') {
                        $q->whereIn('flag', ['2', '3', '4', '7', '8']);
                    } else {
                        $q->where('flag', $flag);
                    }
                }
            })
            ->orderBy('flag', 'asc')
            ->orderBy('id', 'desc')
            ->simplePaginate(request('per_page'));
        $data['data'] = collect($raw)['data'];
        $data['meta'] = collect($raw)->except('data');
        return new JsonResponse($data);
    }
    public function getListPenjualanNull()
    {
        $raw = HeaderPenjualan::with([
            'pelanggan',
            'detailFifo.masterBarang',

            'detail' => function ($q) {
                $q->with([
                    'masterBarang' => function ($x) {
                        $x->with([
                            'stok' => function ($q) {
                                $q->select(
                                    'kdbarang',
                                    DB::raw('sum(jumlah_b) as jumlah_b'),
                                    DB::raw('sum(jumlah_k) as jumlah_k'),
                                    'isi',
                                    'satuan_b',
                                    'satuan_k',
                                    'harga_beli_b',
                                    'harga_beli_k',
                                )
                                    ->groupBy('kdbarang')
                                    ->where('jumlah_k', '>', 0);
                            },
                        ]);
                    }
                ]);
            },
            'sales',
        ])
            ->where('no_penjualan', 'like', '%' . request('q') . '%')
            ->whereNull('flag')
            ->orderBy('id', 'asc')
            ->get();
        return new JsonResponse($raw);
    }
    public function hapusDetail(Request $request)
    {
        $header = HeaderPenjualan::where('no_penjualan', '=', $request->no_penjualan)
            ->first();
        $flag = $header->flag;
        if ((int)$flag > 0) return new JsonResponse(['message' => 'Tidak boleh dihapus, bukan Draft'], 410);
        // return new JsonResponse([
        //     'header' => $header,
        //     'flag' => $flag,
        // ]);
        $detail = DetailPenjualan::find($request->id);
        if (!$detail) {
            return new JsonResponse(['message' => 'Data Tidak Ditemukan'], 410);
        }
        $detail->delete();

        $allDetail = DetailPenjualan::where('no_penjualan', '=', $request->no_penjualan)->get();
        $isDeleteHeader = '0';

        if (sizeof($allDetail) == 0) {
            $header->delete();
            $isDeleteHeader = '1';
        } else {
            $total = DetailPenjualan::where('no_penjualan', '=', $request->no_penjualan)->sum('subtotal');
            $totalDiskon = DetailPenjualan::where('no_penjualan', '=', $request->no_penjualan)->sum('diskon');
            $header->update([
                'total' => $total,
                'total_diskon' => $totalDiskon,
            ]);
            $header->load([
                'detail' => function ($q) {
                    $q->with([
                        'masterBarang' => function ($x) {
                            $x->with([
                                'stok' => function ($q) {
                                    $q->select(
                                        'kdbarang',
                                        DB::raw('sum(jumlah_b) as jumlah_b'),
                                        DB::raw('sum(jumlah_k) as jumlah_k'),
                                        'isi',
                                        'satuan_b',
                                        'satuan_k',
                                        'harga_beli_b',
                                        'harga_beli_k',
                                    )
                                        ->groupBy('kdbarang')
                                        ->where('jumlah_k', '>', 0);
                                },
                            ]);
                        }
                    ]);
                },
                'sales',
                'pelanggan'
            ]);
        }

        return new JsonResponse([
            'message' => 'Data Sudah Dihapus',
            'header' => $header,
            'isDeleteHeader' => $isDeleteHeader,
        ], 200);
    }
    public function simpanTempo(Request $request)
    {
        try {
            DB::beginTransaction();
            $data = HeaderPenjualan::find($request->id);
            if (!$data) {
                return new JsonResponse(['message' => 'Gagal Menyimpan, data tidak ditemukan'], 410);
            }
            $data->update([
                'tempo' => $request->tempo,
                'tgl_kirim' => $request->tgl_kirim,
                'jml_tempo' => $request->jml_tempo,
            ]);

            DB::commit();
            return new JsonResponse([
                'message' => 'Data Sudah Disimpan',
                'data' => $data
            ], 200);
        } catch (\Throwable $th) {
            return new JsonResponse([
                'message' => 'Gagal Menyimpan, ' . $th->getMessage(),
                'line' => $th->getLine()
            ], 410);
        }
    }
    public function simpanPembayaran(Request $request)
    {

        // return new JsonResponse([
        //     'bonus' => $bonus
        // ]);
        try {
            DB::beginTransaction();
            $data = HeaderPenjualan::where('no_penjualan', $request->no_penjualan)->first();
            if (!$data) {
                return new JsonResponse(['message' => 'Gagal Menyimpan, data tidak ditemukan'], 410);
            }
            $data->update([
                'pelanggan_id' => $request->pelanggan_id,
                'bayar' => $request->bayar,
                'kembali' => $request->kembali,
                'flag' => $request->flag,
                'cara_bayar' => $request->cara_bayar,
            ]);
            if ($request->dataPelanggan['nama'] != null && $request->pelanggan_id == null) {
                KeteranganPelanggan::updateOrCreate(
                    [
                        'header_penjualan_id' => $data->id,
                    ],
                    [
                        'nama' => $request->dataPelanggan['nama'],
                        'tlp' => $request->dataPelanggan['tlp'],
                        'alamat' => $request->dataPelanggan['alamat'],
                    ]
                );
            }

            // $detail = DetailPenjualan::where('no_penjualan', $request->no_penjualan)->get();
            // $kode = $detail->pluck('kodebarang');
            // $stoks = stok::lockForUpdate()->whereIn('kdbarang', $kode)->where('jumlah_k', '>', 0)->orderBy('id', 'asc')->get();

            // Process FIFO for all details at once
            try {
                if (!DetailPenjualanFifo::where('no_penjualan', $request->no_penjualan)->exists()) $detaiPengurangan = FifoHelper::processFifo($request->no_penjualan);
            } catch (\Exception $e) {
                throw new \Exception("Error memproses FIFO: " . $e->getMessage());
            }

            $bonus = self::setBonus($request->no_penjualan, $request->flag);

            $data->load([
                'detail.masterBarang',
                // 'detailFifo.masterBarang',
                'detailFifo' => function ($q) {
                    $q->select(
                        'no_penjualan',
                        'kodebarang',
                        'harga_jual',
                        DB::raw('sum(jumlah) as jumlah'),
                        DB::raw('sum(subtotal) as subtotal'),
                        DB::raw('sum(diskon) as diskon'),
                    )
                        ->groupBy('kodebarang')
                        ->with(['masterBarang']);
                },
                'sales',
                'pelanggan',
                'keterangan'
            ]);

            DB::commit();
            return new JsonResponse([
                'message' => 'Data Pembayaran Sudah di catat',
                'data' => $data,
                'detaiPengurangan' => $detaiPengurangan,

            ]);
        } catch (\Throwable $th) {
            DB::rollBack();
            return new JsonResponse([
                'message' => $th->getMessage(),
                'line' => $th->getLine(),
                'file' => $th->getFile(),
            ], 410);
        }
    }
    public static function setBonus($no_penjualan, $flag)
    {
        // flag lunas = 5, selain itu hutang
        $header = HeaderPenjualan::where('no_penjualan', '=', $no_penjualan)
            ->first();
        $rinci = DetailPenjualan::where('no_penjualan', '=', $no_penjualan)
            ->with('masterBarang')
            ->get();
        // $data = [];
        foreach ($rinci as $key) {
            $bonusCash = (float)$key->masterBarang->bonus_cash;
            $bonusHutang = (float)$key->masterBarang->bonus_hutang;
            $bonus = $flag == '5' ? $bonusCash : $bonusHutang;
            $subBonus = (float)$key->jumlah * $bonus;

            $key->update(['sub_bonus' => $subBonus]);
            // $data[] = [
            //     'data' => $key,
            //     'bonusCash' => $bonusCash,
            //     'bonusHutang' => $bonusHutang,
            //     'bonus' => $bonus,
            //     'subBonus' => $subBonus,
            //     // 'barang' => $key->masterBarang
            // ];
        }
        $total = $rinci->sum('sub_bonus');
        $header->update(['total_bonus' => $total]);
        return [
            'header' => $header,
            'rinci' => $rinci,
            'flag' => $flag,
            'total' => $total,
        ];
    }
}
