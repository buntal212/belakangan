<?php

namespace App\Http\Controllers\Api\Transaksi\Penjualan;

use App\Helpers\FormatingHelper;
use App\Helpers\PpnHelper;
use App\Http\Controllers\Controller;
use App\Models\Barang;
use App\Models\KeteranganPelanggan;
use App\Models\OrderPenjualan;
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
use Illuminate\Validation\ValidationException;

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
        $data = User::query()
            ->where(function ($query) {
                $query->whereRaw('LOWER(jabatan) LIKE ?', ['%sales%'])
                    ->orWhere('kodejabatan', 3);
            })
            ->orderBy('nama')
            ->get();
        return new JsonResponse($data);
    }
    public function getPelanggan()
    {
        $data = Pelanggan::whereNull('flaging')
            ->where(function ($x) {
                $x->where('nama', 'like', '%' . request('q') . '%')
                    ->orWhere('nama_toko', 'like', '%' . request('q') . '%')
                    ->orWhere('nama_owner', 'like', '%' . request('q') . '%')
                    ->orWhere('kodeplgn', 'like', '%' . request('q') . '%')
                    ->orWhere('namabank', 'like', '%' . request('q') . '%')
                    ->orWhere('telepon', 'like', '%' . request('q') . '%')
                    ->orWhere('no_tlp', 'like', '%' . request('q') . '%')
                    ->orWhere('alamat', 'like', '%' . request('q') . '%');
            })
            ->limit(request('limit'))
            ->get();
        return new JsonResponse($data);
    }
    public function simpanDetail(Request $request)
    {
        try {
            $validated = $request->validate([
                'id' => ['nullable', 'integer', 'exists:detail_penjualans,id'],
                'nota' => ['nullable', 'string', 'max:100'],
                'kodebarang' => ['required', 'string', 'exists:barangs,kodebarang'],
                'motif' => ['nullable', 'string', 'max:100'],
                'satuan' => ['nullable', 'string', 'in:SB,SK,sb,sk'],
                'jumlah_b' => ['nullable', 'numeric', 'min:0'],
                'jumlahB' => ['nullable', 'numeric', 'min:0'],
                'jumlah_k' => ['nullable', 'numeric', 'min:0'],
                'jumlahK' => ['nullable', 'numeric', 'min:0'],
                'jumlah' => ['nullable', 'numeric', 'min:0'],
                'isi' => ['required', 'numeric', 'gt:0'],
                'harga_jual' => ['required', 'numeric', 'min:0'],
                'harga_jual_b' => ['nullable', 'numeric', 'min:0'],
                'hargaJualB' => ['nullable', 'numeric', 'min:0'],
                'harga_beli' => ['nullable', 'numeric', 'min:0'],
                'diskon' => ['nullable', 'numeric', 'min:0'],
                'pelanggan_id' => ['nullable', 'integer', 'exists:pelanggans,id'],
                'sales_id' => ['nullable', 'integer', 'exists:users,id'],
            ]);

            $hasJumlahB = $request->has('jumlah_b') || $request->has('jumlahB');
            $hasJumlahK = $request->has('jumlah_k') || $request->has('jumlahK');
            $jumB = (float) ($request->input('jumlah_b') ?? $request->input('jumlahB') ?? 0);
            $jumK = (float) ($request->input('jumlah_k') ?? $request->input('jumlahK') ?? 0);

            if ($jumB > 0 && $jumK > 0) {
                throw ValidationException::withMessages([
                    'satuan' => ['Pilih salah satu satuan: Jumlah (SB) atau Jumlah (SK), tidak boleh diisi bersamaan.'],
                ]);
            }

            $isi = (float) $validated['isi'];
            if ($isi <= 0) {
                throw ValidationException::withMessages([
                    'isi' => ['Isi kemasan harus lebih dari 0.'],
                ]);
            }

            $satuanParam = strtoupper((string) ($request->input('satuan') ?? ''));

            if ($jumB > 0) {
                $satuanAktif = 'SB';
                $jumlahSatuanAktif = $jumB;
                $jumlahAllSk = round($jumB * $isi, 4);
                $hargaBInput = (float) ($request->input('harga_jual_b') ?? $request->input('hargaJualB') ?? 0);
                $hargaSatuanAktif = $hargaBInput > 0 ? $hargaBInput : round((float) $validated['harga_jual'] * $isi, 2);
                $hargaJualSk = round($hargaSatuanAktif / $isi, 4);
            } elseif ($jumK > 0) {
                $satuanAktif = 'SK';
                $jumlahSatuanAktif = $jumK;
                $jumlahAllSk = $jumK;
                $hargaSatuanAktif = (float) $validated['harga_jual'];
                $hargaJualSk = (float) $validated['harga_jual'];
            } else {
                // Fallback untuk payload yang tidak menyertakan jumlah_b atau jumlah_k secara spesifik
                $fallbackJumlah = (float) ($validated['jumlah'] ?? 0);
                if ($fallbackJumlah <= 0) {
                    throw ValidationException::withMessages([
                        'jumlah' => ['Kuantitas pembelian harus lebih dari 0.'],
                    ]);
                }

                if ($satuanParam === 'SB') {
                    $satuanAktif = 'SB';
                    if ($fallbackJumlah >= $isi && fmod($fallbackJumlah, $isi) == 0.0) {
                        $jumlahSatuanAktif = $fallbackJumlah / $isi;
                        $jumlahAllSk = $fallbackJumlah;
                    } else {
                        $jumlahSatuanAktif = $fallbackJumlah;
                        $jumlahAllSk = round($fallbackJumlah * $isi, 4);
                    }
                    $hargaBInput = (float) ($request->input('harga_jual_b') ?? $request->input('hargaJualB') ?? 0);
                    $hargaSatuanAktif = $hargaBInput > 0 ? $hargaBInput : round((float) $validated['harga_jual'] * $isi, 2);
                    $hargaJualSk = round($hargaSatuanAktif / $isi, 4);
                } else {
                    // Satuan eksplisit SK atau tidak ditentukan (default aman ke SK).
                    // Jangan menebak SB hanya karena jumlah kelipatan kemasan!
                    // Pembelian 10 pcs dengan isi kemasan 10 tetap SK.
                    $satuanAktif = 'SK';
                    $jumlahSatuanAktif = $fallbackJumlah;
                    $jumlahAllSk = $fallbackJumlah;
                    $hargaSatuanAktif = (float) $validated['harga_jual'];
                    $hargaJualSk = (float) $validated['harga_jual'];
                }
            }

            $diskonPerSatuan = (float) ($validated['diskon'] ?? 0);

            if ($diskonPerSatuan < 0) {
                throw ValidationException::withMessages([
                    'diskon' => ['Diskon tidak boleh kurang dari 0.'],
                ]);
            }

            if ($hargaSatuanAktif > 0 && $diskonPerSatuan > $hargaSatuanAktif) {
                throw ValidationException::withMessages([
                    'diskon' => ['Diskon per satuan tidak boleh melebihi harga satuan.'],
                ]);
            }

            $totalDiskon = round($jumlahSatuanAktif * $diskonPerSatuan, 2);
            $subtotal = max(0.0, round(($jumlahSatuanAktif * $hargaSatuanAktif) - $totalDiskon, 2));

            DB::beginTransaction();
            $headerTersimpan = null;
            if (empty($validated['nota'])) {
                $nota = FormatingHelper::nomorNotaPenjualanBerikutnya();
            } else {
                $nota = $validated['nota'];
                $headerTersimpan = HeaderPenjualan::where('no_penjualan', $nota)->lockForUpdate()->first();

                if ($headerTersimpan?->noorder) {
                    throw ValidationException::withMessages([
                        'nota' => ['Rincian penjualan dari order sales tidak dapat diubah.'],
                    ]);
                }
            }

            $matchAttributes = [
                'no_penjualan' => $nota,
                'kodebarang' => $validated['kodebarang'],
                'motif' => $validated['motif'] ?? null,
                'jumlah' => $jumlahAllSk,
                'isi' => $isi,
            ];
            $updateAttributes = [
                'harga_jual' => $hargaJualSk,
                'harga_beli' => $validated['harga_beli'] ?? 0,
                'diskon' => $totalDiskon,
                'subtotal' => $subtotal,
            ];

            if ($request->filled('id')) {
                $detail = DetailPenjualan::where('id', $request->id)
                    ->where('no_penjualan', $nota)
                    ->first();
                if ($detail) {
                    $detail->update(array_merge($matchAttributes, $updateAttributes));
                }
            }
            if (!isset($detail) || !$detail) {
                $detail = DetailPenjualan::updateOrCreate($matchAttributes, $updateAttributes);
            }
            if (!$detail) {
                throw new Exception("Detail Tidak Tersimpan", 1);
            }
            $subtotalPenjualan = (float) DetailPenjualan::where('no_penjualan', '=', $nota)->sum('subtotal');
            $totalDiskon = DetailPenjualan::where('no_penjualan', '=', $nota)->sum('diskon');
            $ppnPersen = $headerTersimpan ? (float) $headerTersimpan->ppn_persen : PpnHelper::tarifAktif();
            $nilaiPenjualan = PpnHelper::hitung($subtotalPenjualan, $ppnPersen);
            $data = HeaderPenjualan::updateOrCreate(
                [
                    'no_penjualan' => $nota,
                ],
                [
                    'tgl' => date('Y-m-d H:i:s'),
                    'sales_id' => $validated['sales_id'] ?? null,
                    'pelanggan_id' => $validated['pelanggan_id'] ?? null,
                    ...$nilaiPenjualan,
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
                'total' => $nilaiPenjualan['total'],
            ]);
        } catch (ValidationException $exception) {
            DB::rollBack();
            throw $exception;
        } catch (\Throwable $th) {
            DB::rollBack();
            report($th);

            return new JsonResponse([
                'message' => 'Gagal menyimpan rincian penjualan. Silakan coba lagi.',
            ], 500);
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
            'orderSumber:noorder,sistem_bayar,cara_bayar,down_payment,total_harga,status_pembayaran',
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
            'orderSumber:noorder,sistem_bayar,cara_bayar,down_payment,total_harga,status_pembayaran',
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
            'orderSumber:noorder,sistem_bayar,cara_bayar,down_payment,total_harga,status_pembayaran',
        ])
            ->where('no_penjualan', 'like', '%' . request('q') . '%')
            ->whereNull('flag')
            ->orderBy('id', 'asc')
            ->get();
        return new JsonResponse($raw);
    }
    public function hapusDetail(Request $request)
    {
        $validated = $request->validate([
            'id' => ['required', 'integer', 'exists:detail_penjualans,id'],
            'no_penjualan' => ['required', 'string', 'exists:header_penjualans,no_penjualan'],
        ]);

        $hasil = DB::transaction(function () use ($validated) {
            $header = HeaderPenjualan::where('no_penjualan', $validated['no_penjualan'])
                ->lockForUpdate()
                ->firstOrFail();

            if ($header->noorder) {
                throw ValidationException::withMessages([
                    'no_penjualan' => ['Rincian penjualan dari order sales tidak dapat dihapus.'],
                ]);
            }

            if ($header->flag !== null) {
                throw ValidationException::withMessages([
                    'no_penjualan' => ['Rincian hanya dapat dihapus dari penjualan draf.'],
                ]);
            }

            $detail = DetailPenjualan::whereKey($validated['id'])
                ->where('no_penjualan', $header->no_penjualan)
                ->lockForUpdate()
                ->first();

            if (!$detail) {
                throw ValidationException::withMessages([
                    'id' => ['Rincian tidak ditemukan pada nota penjualan tersebut.'],
                ]);
            }

            $detail->delete();
            $masihAdaRincian = DetailPenjualan::where('no_penjualan', $header->no_penjualan)->exists();

            if (!$masihAdaRincian) {
                $header->delete();
                return ['header' => null, 'isDeleteHeader' => '1'];
            }

            $subtotalPenjualan = (float) DetailPenjualan::where('no_penjualan', $header->no_penjualan)->sum('subtotal');
            $nilaiPenjualan = PpnHelper::hitung($subtotalPenjualan, (float) $header->ppn_persen);
            $header->update([
                ...$nilaiPenjualan,
                'total_diskon' => DetailPenjualan::where('no_penjualan', $header->no_penjualan)->sum('diskon'),
            ]);

            return ['header' => $header->fresh(['detail.masterBarang', 'sales', 'pelanggan']), 'isDeleteHeader' => '0'];
        });

        return new JsonResponse([
            'message' => 'Data sudah dihapus.',
            'header' => $hasil['header'],
            'isDeleteHeader' => $hasil['isDeleteHeader'],
        ]);
    }
    public function simpanTempo(Request $request)
    {
        try {
            $validated = $request->validate([
                'id' => ['required', 'integer', 'exists:header_penjualans,id'],
                'tempo' => ['nullable', 'date_format:Y-m-d'],
                'tgl_kirim' => ['nullable', 'date_format:Y-m-d'],
                'jml_tempo' => ['required', 'integer', 'min:0', 'max:3650'],
            ]);

            $dataAwal = HeaderPenjualan::findOrFail($validated['id']);
            $data = DB::transaction(function () use ($validated, $dataAwal) {
                // Mengunci order lebih dahulu agar urutan lock sama dengan pembatalan/status order.
                $order = $dataAwal->noorder
                    ? OrderPenjualan::where('noorder', $dataAwal->noorder)->lockForUpdate()->first()
                    : null;
                $data = HeaderPenjualan::whereKey($validated['id'])->lockForUpdate()->firstOrFail();

                if ($data->flag === null || $data->flag === '6') {
                    throw ValidationException::withMessages([
                        'id' => ['Tempo hanya dapat diatur pada penjualan yang sudah diproses.'],
                    ]);
                }

                if ($data->noorder && (!$order || in_array((string) $order->status_order, ['9', '6'], true))) {
                    throw ValidationException::withMessages([
                        'id' => ['Order sumber sudah tidak dapat diubah.'],
                    ]);
                }

                $perubahan = [
                    'tempo' => $validated['tempo'],
                    'tgl_kirim' => $validated['tgl_kirim'],
                    'jml_tempo' => $validated['jml_tempo'],
                ];
                $data->update($perubahan);

                if ($order) {
                    $order->update([
                        'tempo' => $validated['tempo'],
                        'tanggal_kirim' => $validated['tgl_kirim'],
                    ]);
                }

                return $data->fresh();
            });

            return new JsonResponse([
                'message' => 'Data Sudah Disimpan',
                'data' => $data
            ], 200);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (\Throwable $th) {
            report($th);

            return new JsonResponse([
                'message' => 'Gagal menyimpan pengaturan tempo. Silakan coba lagi.',
            ], 500);
        }
    }
    public function simpanPembayaran(Request $request)
    {
        try {
            $validated = $request->validate([
                'no_penjualan' => ['required', 'string', 'exists:header_penjualans,no_penjualan'],
                'pelanggan_id' => ['nullable', 'integer', 'exists:pelanggans,id'],
                'flag' => ['required', 'in:2,5,7,8'],
                'cara_bayar' => ['nullable', 'in:cash,transfer,'],
                'bayar' => ['required', 'numeric', 'min:0'],
                'diskon_kuitansi' => ['nullable', 'numeric', 'min:0'],
                'kena_ppn' => ['nullable', 'boolean'],
                'dataPelanggan' => ['nullable', 'array'],
                'dataPelanggan.nama' => ['nullable', 'string', 'max:100'],
                'dataPelanggan.tlp' => ['nullable', 'string', 'max:50'],
                'dataPelanggan.alamat' => ['nullable', 'string', 'max:500'],
            ]);

            DB::beginTransaction();
            $headerAwal = HeaderPenjualan::where('no_penjualan', $validated['no_penjualan'])->first();
            if (!$headerAwal) {
                throw ValidationException::withMessages([
                    'no_penjualan' => ['Nota penjualan tidak ditemukan.'],
                ]);
            }

            // Status order selalu mengunci order lebih dahulu, maka pembayaran
            // menggunakan urutan yang sama untuk mencegah deadlock.
            $order = $headerAwal->noorder
                ? OrderPenjualan::where('noorder', $headerAwal->noorder)->lockForUpdate()->first()
                : null;
            $data = HeaderPenjualan::where('no_penjualan', $validated['no_penjualan'])
                ->lockForUpdate()
                ->first();
            if (!$data) {
                throw ValidationException::withMessages([
                    'no_penjualan' => ['Nota penjualan tidak ditemukan.'],
                ]);
            }

            if ($data->flag !== null && $data->flag !== '6') {
                throw ValidationException::withMessages([
                    'no_penjualan' => ['Pembayaran untuk nota ini sudah diproses.'],
                ]);
            }

            if ($data->flag === '6') {
                throw ValidationException::withMessages([
                    'no_penjualan' => ['Penjualan yang dibatalkan tidak dapat dibayar.'],
                ]);
            }

            $subtotalBarang = (float) DetailPenjualan::where('no_penjualan', $data->no_penjualan)->sum('subtotal');
            $diskonNota = isset($validated['diskon_kuitansi']) ? (float) $validated['diskon_kuitansi'] : (float) ($data->diskon_kuitansi ?? 0);

            if ($diskonNota < 0) {
                throw ValidationException::withMessages([
                    'diskon_kuitansi' => ['Diskon nota tidak boleh kurang dari 0.'],
                ]);
            }

            if ($diskonNota > $subtotalBarang) {
                throw ValidationException::withMessages([
                    'diskon_kuitansi' => ['Diskon nota tidak boleh lebih besar dari subtotal barang.'],
                ]);
            }

            $dpp = max(0.0, round($subtotalBarang - $diskonNota, 2));

            $kenaPpn = $request->has('kena_ppn')
                ? filter_var($request->input('kena_ppn'), FILTER_VALIDATE_BOOLEAN)
                : ((float) $data->ppn_persen > 0 || PpnHelper::tarifAktif() > 0);

            if ($kenaPpn) {
                $ppnPersen = (float) ($data->ppn_persen > 0 ? $data->ppn_persen : PpnHelper::tarifAktif());
            } else {
                $ppnPersen = 0.0;
            }

            $nilaiPenjualan = PpnHelper::hitung($dpp, $ppnPersen);
            $ppnNominal = $nilaiPenjualan['ppn_nominal'];
            $total = $nilaiPenjualan['total'];
            $totalDiskonDetail = (float) DetailPenjualan::where('no_penjualan', $data->no_penjualan)->sum('diskon');

            $bayar = (float) $validated['bayar'];
            $flag = (string) $validated['flag'];

            if ($data->noorder) {
                if (!$order || in_array((string) $order->status_order, ['9', '5', '6'], true)) {
                    throw ValidationException::withMessages([
                        'no_penjualan' => ['Order sumber sudah tidak dapat dibayar.'],
                    ]);
                }

                if ((string) $order->status_order !== '3') {
                    throw ValidationException::withMessages([
                        'no_penjualan' => ['Order sumber belum siap diproses sebagai pembayaran.'],
                    ]);
                }

                if (isset($validated['pelanggan_id']) && (int) $validated['pelanggan_id'] !== (int) $order->pelanggan_id) {
                    throw ValidationException::withMessages([
                        'pelanggan_id' => ['Pelanggan penjualan dari order tidak dapat diubah.'],
                    ]);
                }

                $sistemBayar = strtolower((string) $order->sistem_bayar);
                $flagYangDiharapkan = match ($sistemBayar) {
                    'lunas' => '5',
                    'dp' => '7',
                    'tempo' => '2',
                    default => null,
                };

                if ($flagYangDiharapkan !== null && $flag !== $flagYangDiharapkan) {
                    throw ValidationException::withMessages([
                        'flag' => ['Metode pembayaran harus mengikuti pilihan pada order sumber.'],
                    ]);
                }

                if (in_array($sistemBayar, ['lunas', 'dp'], true)
                    && ($validated['cara_bayar'] ?? null) !== $order->cara_bayar) {
                    throw ValidationException::withMessages([
                        'cara_bayar' => ['Cara bayar harus mengikuti pilihan pada order sumber.'],
                    ]);
                }

                if ($sistemBayar === 'dp' && abs($bayar - (float) $order->down_payment) > 0.01) {
                    throw ValidationException::withMessages([
                        'bayar' => ['Nominal DP harus sesuai dengan nominal pada order sumber.'],
                    ]);
                }

                if ($sistemBayar === 'tempo' && $bayar != 0.0) {
                    throw ValidationException::withMessages([
                        'bayar' => ['Order tempo tidak menerima pembayaran awal.'],
                    ]);
                }
            }

            if ($flag === '5' && $bayar < $total) {
                throw ValidationException::withMessages([
                    'bayar' => ['Pembayaran tunai harus minimal sebesar total penjualan.'],
                ]);
            }

            if (in_array($flag, ['7', '8'], true) && ($bayar < 0 || ($total > 0 && $bayar >= $total))) {
                throw ValidationException::withMessages([
                    'bayar' => ['Nominal down payment harus kurang dari total penjualan.'],
                ]);
            }

            if ($flag === '2' && $bayar != 0.0) {
                throw ValidationException::withMessages([
                    'bayar' => ['Pembayaran tempo tidak menerima pembayaran awal.'],
                ]);
            }

            if ($flag !== '2' && empty($validated['cara_bayar'])) {
                throw ValidationException::withMessages([
                    'cara_bayar' => ['Cara bayar wajib dipilih.'],
                ]);
            }

            $kembali = $flag === '5' ? max(0.0, round($bayar - $total, 2)) : 0.0;
            $data->update([
                'pelanggan_id' => $order ? $order->pelanggan_id : ($validated['pelanggan_id'] ?? null),
                'subtotal' => $dpp,
                'diskon_kuitansi' => $diskonNota,
                'total_diskon' => $totalDiskonDetail,
                'ppn_persen' => $ppnPersen,
                'ppn_nominal' => $ppnNominal,
                'total' => $total,
                'bayar' => $bayar,
                'kembali' => $kembali,
                'flag' => $flag,
                'cara_bayar' => $flag === '2' ? '' : $validated['cara_bayar'],
            ]);
            $dataPelanggan = $validated['dataPelanggan'] ?? [];
            if (!$order && !empty($dataPelanggan['nama']) && empty($validated['pelanggan_id'])) {
                KeteranganPelanggan::updateOrCreate(
                    [
                        'header_penjualan_id' => $data->id,
                    ],
                    [
                        'nama' => $dataPelanggan['nama'],
                        'tlp' => $dataPelanggan['tlp'] ?? null,
                        'alamat' => $dataPelanggan['alamat'] ?? null,
                    ]
                );
            }

            // $detail = DetailPenjualan::where('no_penjualan', $request->no_penjualan)->get();
            // $kode = $detail->pluck('kodebarang');
            // $stoks = stok::lockForUpdate()->whereIn('kdbarang', $kode)->where('jumlah_k', '>', 0)->orderBy('id', 'asc')->get();

            // Process FIFO for all details at once
            $detaiPengurangan = [];
            try {
                if (!DetailPenjualanFifo::where('no_penjualan', $validated['no_penjualan'])->exists()) {
                    $detaiPengurangan = FifoHelper::processFifo($validated['no_penjualan']);
                }
            } catch (\Exception $e) {
                throw new \Exception("Error memproses FIFO: " . $e->getMessage());
            }

            $bonus = self::setBonus($validated['no_penjualan'], $flag);

            if ($order) {
                $order->update([
                    'status_order' => '4',
                    'status_pembayaran' => $flag === '5' ? '2' : '1',
                    'bayar' => $bayar,
                    'cara_bayar' => $flag === '2' ? null : $validated['cara_bayar'],
                ]);
            }

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
                'keterangan',
                'orderSumber:noorder,sistem_bayar,cara_bayar,down_payment,total_harga,status_pembayaran'
            ]);

            DB::commit();
            return new JsonResponse([
                'message' => 'Data Pembayaran Sudah di catat',
                'data' => $data,
                'detaiPengurangan' => $detaiPengurangan,

            ]);
        } catch (ValidationException $exception) {
            DB::rollBack();
            throw $exception;
        } catch (\Throwable $th) {
            DB::rollBack();
            report($th);

            return new JsonResponse([
                'message' => 'Gagal menyimpan pembayaran penjualan. Silakan coba lagi.',
            ], 500);
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
