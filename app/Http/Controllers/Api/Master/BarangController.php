<?php

namespace App\Http\Controllers\Api\Master;

use App\Helpers\FormatingHelper;
use App\Http\Controllers\Controller;
use App\Models\Barang;
use App\Models\Imagebarang;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BarangController extends Controller
{
    public function listbarang()
    {
        // Tentukan tahun dan bulan, default ke saat ini jika tidak ada
        $tahun = request('tahun') ?? Carbon::now()->format('Y');
        $bulan = request('bulan') ?? Carbon::now()->format('m');

        // Hitung awal dan akhir bulan
        $awal = Carbon::createFromFormat('Y-m-d', "$tahun-$bulan-01")->startOfMonth()->format('Y-m-d');
        $akhir = Carbon::createFromFormat('Y-m-d', $awal)->endOfMonth()->format('Y-m-d');

        // Hitung awal bulan sebelumnya untuk validasi saldo akhir
        $bulanSebelumnya = Carbon::createFromFormat('Y-m-d', "$tahun-$bulan-01")->subMonth()->startOfMonth()->format('Y-m-d');
        $akhirBulanSebelumnya = Carbon::createFromFormat('Y-m-d', $bulanSebelumnya)->endOfMonth()->format('Y-m-d');

        // Log rentang tanggal untuk debugging
        Log::info('Rentang tanggal', [
            'awal' => $awal,
            'akhir' => $akhir,
            'bulan_sebelumnya' => $bulanSebelumnya,
            'akhir_bulan_sebelumnya' => $akhirBulanSebelumnya,
            'tahun' => $tahun,
            'bulan' => $bulan
        ]);

        $data = Barang::whereNull('barangs.flaging')
            ->when(request('kodebarang'), function ($query) {
                $query->where('barangs.kodebarang', request('kodebarang'));
            })
            ->when(request('q'), function ($query) {
                $query->where(function ($q) {
                    $q->where('barangs.namabarang', 'like', '%' . request('q') . '%')
                        ->orWhere('barangs.kodebarang', 'like', '%' . request('q') . '%');
                });
            })
            ->leftJoin('imagebarangs', function ($join) {
                $join->on('barangs.kodebarang', '=', 'imagebarangs.kodebarang')
                    ->where('imagebarangs.flag_thumbnail', '=', 1);
            })
            ->leftJoin('stoks', function ($join) {
                $join->on('barangs.kodebarang', '=', 'stoks.kdbarang')
                    ->where('stoks.jumlah_k', '!=', 0);
            })
            ->with([
                'rincians',
                'penerimaan' => function ($query) use ($awal, $akhir) {
                    $query->join('penerimaan_h', 'penerimaan_h.nopenerimaan', '=', 'penerimaan_r.nopenerimaan')
                        ->whereBetween('penerimaan_h.tgl_faktur', [$awal, $akhir])
                        ->where('penerimaan_h.kunci', '=', '1')
                        ->when(request('x'), function ($q) {
                            $searchTrans = request('x');
                            $q->whereRaw('LOWER(penerimaan_r.motif) LIKE LOWER(?)', ["%$searchTrans%"]);
                        })
                        ->select(
                            'penerimaan_h.kunci',
                            'penerimaan_h.created_at',
                            'penerimaan_h.tgl_faktur as tanggal',
                            'penerimaan_r.kdbarang',
                            'penerimaan_r.nopenerimaan as notransaksi',
                            'penerimaan_r.jumlah_k as penerimaan',
                            'penerimaan_r.isi',
                            'penerimaan_r.satuan_k',
                            'penerimaan_r.satuan_b',
                            'penerimaan_r.motif'
                        );
                },
                'penjualan' => function ($query) use ($awal, $akhir) {
                    $query->join('header_penjualans', 'header_penjualans.no_penjualan', '=', 'detail_penjualan_fifos.no_penjualan')
                        ->join('barangs', 'barangs.kodebarang', '=', 'detail_penjualan_fifos.kodebarang')
                        ->join('stoks', 'stoks.id', '=', 'detail_penjualan_fifos.stok_id')
                        ->whereIn('header_penjualans.flag', ['2', '3', '4', '5', '7', '8'])
                        // ->whereBetween('header_penjualans.tgl', [$awal, $akhir])
                        ->whereBetween(DB::raw('DATE(header_penjualans.tgl)'), [$awal, $akhir])
                        ->when(request('x'), function ($q) {
                            $searchTrans = request('x');
                            $q->whereRaw('LOWER(stoks.motif) LIKE LOWER(?)', ["%$searchTrans%"]);
                        })
                        ->select(
                            'header_penjualans.tgl as tanggal',
                            'header_penjualans.created_at',
                            'detail_penjualan_fifos.kodebarang',
                            'detail_penjualan_fifos.no_penjualan as notransaksi',
                            'detail_penjualan_fifos.jumlah as pengeluaran',
                            'barangs.satuan_k',
                            'barangs.satuan_b',
                            'barangs.isi',
                            'stoks.motif as motif'
                        );
                },
                'returbarang' => function ($query) use ($awal, $akhir) {
                    $query->join('header_retur_penjualans', 'header_retur_penjualans.id', '=', 'detail_retur_penjualans.header_retur_penjualan_id')
                        ->join('barangs', 'barangs.kodebarang', '=', 'detail_retur_penjualans.kodebarang')
                        ->join('detail_penjualans', 'detail_penjualans.id', '=', 'detail_retur_penjualans.detail_penjualan_id')
                        ->whereBetween('header_retur_penjualans.tgl', [$awal, $akhir])
                        ->where('header_retur_penjualans.status', '=', '1')
                        ->when(request('x'), function ($q) {
                            $searchTrans = request('x');
                            $q->whereRaw('LOWER(detail_penjualans.motif) LIKE LOWER(?)', ["%$searchTrans%"]);
                        })
                        ->select(
                            'header_retur_penjualans.tgl as tanggal',
                            'header_retur_penjualans.created_at',
                            'detail_retur_penjualans.kodebarang',
                            'header_retur_penjualans.no_retur as notransaksi',
                            'detail_retur_penjualans.jumlah as penerimaan',
                            'barangs.satuan_k',
                            'barangs.satuan_b',
                            'barangs.isi',
                            'detail_penjualans.motif as motif'
                        );
                },
                'pengembalian' => function ($query) use ($awal, $akhir) {
                    $query->join('header_pengembalians', 'header_pengembalians.id', '=', 'detail_pengembalians.header_pengembalian_id')
                        ->join('barangs', 'barangs.kodebarang', '=', 'detail_pengembalians.kodebarang')
                        ->whereBetween('header_pengembalians.tanggal', [$awal, $akhir])
                        ->where('header_pengembalians.status', '=', 'diganti')
                        ->when(request('x'), function ($q) {
                            $searchTrans = request('x');
                            $q->whereRaw('LOWER(detail_pengembalians.motif) LIKE LOWER(?)', ["%$searchTrans%"]);
                        })
                        ->select(
                            'header_pengembalians.tanggal',
                            'header_pengembalians.created_at',
                            'header_pengembalians.no_pengembalian as notransaksi',
                            'header_pengembalians.keterangan',
                            'header_pengembalians.status',
                            'detail_pengembalians.kodebarang',
                            'detail_pengembalians.qty as pengeluaran',
                            'detail_pengembalians.motif'
                        );
                },
                'penyesuaian' => function ($query) use ($awal, $akhir) {
                    $query->whereBetween('penyesuaians.tgl', [$awal, $akhir])
                        ->when(request('x'), function ($q) {
                            $searchTrans = request('x');
                            $q->whereRaw('LOWER(motif) LIKE LOWER(?)', ["%$searchTrans%"]);
                        })
                        ->select('*');
                },
                'stoks'
            ])
            ->select('barangs.*')
            ->selectRaw('
                GROUP_CONCAT(imagebarangs.gambar) as image,
                GROUP_CONCAT(imagebarangs.flag_thumbnail) as flag_thumbnail,
                COALESCE(SUM(CASE WHEN stoks.jumlah_k != 0 THEN stoks.jumlah_k ELSE 0 END), 0) as stok_kecil,
                COALESCE(ROUND(SUM(CASE WHEN stoks.isi != 0 THEN stoks.jumlah_k / stoks.isi ELSE 0 END), 2), 0) as stok_besar
            ')
            ->when(request('minim_stok'), function ($query) {
                $query->havingRaw(
                    '
                    CASE
                        WHEN COALESCE(SUM(CASE WHEN stoks.jumlah_k != 0 THEN stoks.jumlah_k ELSE 0 END), 0) <= barangs.minim_stok THEN 1
                        WHEN COALESCE(SUM(CASE WHEN stoks.jumlah_k != 0 THEN stoks.jumlah_k ELSE 0 END), 0) > barangs.minim_stok THEN 2
                    END = ?',
                    [request('minim_stok')]
                );
            })
            ->groupBy('barangs.id')
            ->orderBy('barangs.id', 'desc')
            ->simplePaginate(request('per_page'));

        // Transformasi data untuk menambahkan kartustok dan data mentah
        $data->getCollection()->transform(function ($item) use ($awal, $bulanSebelumnya, $akhirBulanSebelumnya) {
            $searchTrans = request('x') ?? '';

            // Hitung saldo awal (sebelum rentang tanggal) dengan filter berdasarkan motif
            $saldoAwalQuery = Barang::where('barangs.kodebarang', $item->kodebarang)
                ->selectRaw('
                    (SELECT COALESCE(SUM(penerimaan_r.jumlah_k), 0) FROM penerimaan_r
                    JOIN penerimaan_h ON penerimaan_h.nopenerimaan = penerimaan_r.nopenerimaan
                    WHERE penerimaan_r.kdbarang = ?
                    AND penerimaan_h.tgl_faktur < ?
                    ' . (!empty($searchTrans) ? 'AND penerimaan_r.motif LIKE ?' : '') . ') as penerimaan,
                    (SELECT COALESCE(SUM(detail_penjualan_fifos.jumlah), 0) FROM detail_penjualan_fifos
                    JOIN header_penjualans ON header_penjualans.no_penjualan = detail_penjualan_fifos.no_penjualan
                    JOIN stoks ON stoks.id = detail_penjualan_fifos.stok_id
                    WHERE detail_penjualan_fifos.kodebarang = ?
                    AND header_penjualans.tgl < ?
                    AND header_penjualans.flag IN ("2", "3", "4", "5", "7", "8")
                    ' . (!empty($searchTrans) ? 'AND stoks.motif LIKE ?' : '') . ') as penjualan,
                    (SELECT COALESCE(SUM(detail_pengembalians.qty), 0) FROM detail_pengembalians
                    JOIN header_pengembalians ON header_pengembalians.id = detail_pengembalians.header_pengembalian_id
                    WHERE detail_pengembalians.kodebarang = ?
                    AND header_pengembalians.tanggal < ?
                    ' . (!empty($searchTrans) ? 'AND detail_pengembalians.motif LIKE ?' : '') . ') as pengembalian,
                    (SELECT COALESCE(SUM(detail_retur_penjualans.jumlah), 0) FROM detail_retur_penjualans
                    JOIN header_retur_penjualans ON header_retur_penjualans.id = detail_retur_penjualans.header_retur_penjualan_id
                    JOIN detail_penjualans ON detail_penjualans.id = detail_retur_penjualans.detail_penjualan_id
                    WHERE detail_retur_penjualans.kodebarang = ?
                    AND header_retur_penjualans.tgl < ?
                    AND header_retur_penjualans.status = "1"
                    ' . (!empty($searchTrans) ? 'AND detail_penjualans.motif LIKE ?' : '') . ') as retur,
                    (SELECT COALESCE(SUM(CASE WHEN penyesuaians.jumlah_k > 0 THEN penyesuaians.jumlah_k ELSE 0 END), 0) FROM penyesuaians
                    WHERE penyesuaians.kdbarang = ?
                    AND penyesuaians.tgl < ?
                    ' . (!empty($searchTrans) ? 'AND penyesuaians.motif LIKE ?' : '') . ') as penyesuaian_positif,
                    (SELECT COALESCE(SUM(CASE WHEN penyesuaians.jumlah_k < 0 THEN ABS(penyesuaians.jumlah_k) ELSE 0 END), 0) FROM penyesuaians
                    WHERE penyesuaians.kdbarang = ?
                    AND penyesuaians.tgl < ?
                    ' . (!empty($searchTrans) ? 'AND penyesuaians.motif LIKE ?' : '') . ') as penyesuaian_negatif
                ', [
                    $item->kodebarang,
                    $awal,
                    ...(!empty($searchTrans) ? ["%$searchTrans%"] : []),
                    $item->kodebarang,
                    $awal,
                    ...(!empty($searchTrans) ? ["%$searchTrans%"] : []),
                    $item->kodebarang,
                    $awal,
                    ...(!empty($searchTrans) ? ["%$searchTrans%"] : []),
                    $item->kodebarang,
                    $awal,
                    ...(!empty($searchTrans) ? ["%$searchTrans%"] : []),
                    $item->kodebarang,
                    $awal,
                    ...(!empty($searchTrans) ? ["%$searchTrans%"] : []),
                    $item->kodebarang,
                    $awal,
                    ...(!empty($searchTrans) ? ["%$searchTrans%"] : []),
                ])
                ->first();

            // Ambil nilai komponen dengan pengecekan aman
            $penerimaan = $saldoAwalQuery ? $saldoAwalQuery->penerimaan ?? 0 : 0;
            $penjualan = $saldoAwalQuery ? $saldoAwalQuery->penjualan ?? 0 : 0;
            $pengembalian = $saldoAwalQuery ? $saldoAwalQuery->pengembalian ?? 0 : 0;
            $retur = $saldoAwalQuery ? $saldoAwalQuery->retur ?? 0 : 0;
            $penyesuaian_positif = $saldoAwalQuery ? $saldoAwalQuery->penyesuaian_positif ?? 0 : 0;
            $penyesuaian_negatif = $saldoAwalQuery ? $saldoAwalQuery->penyesuaian_negatif ?? 0 : 0;

            // Hitung total debit dan kredit untuk saldo awal
            $saldoAwalDebit = $penerimaan + $penyesuaian_positif + $retur;
            $saldoAwalKredit = $penjualan + $pengembalian + $penyesuaian_negatif;

            // Gabungkan semua transaksi ke satu array
            $transaksi = [];

            // Tambahkan penerimaan (hanya yang kunci = '1' atau tidak null)
            foreach ($item->penerimaan as $penerimaan) {
                if (
                    !is_null($penerimaan->kunci) && $penerimaan->kunci === '1' &&
                    (empty($searchTrans) || stripos($penerimaan->motif, $searchTrans) !== false)
                ) {
                    $transaksi[] = [
                        'type' => 'penerimaan',
                        'tanggal' => $penerimaan->tanggal,
                        'notransaksi' => $penerimaan->notransaksi,
                        'debit' => floatval($penerimaan->penerimaan),
                        'kredit' => 0,
                        'satuan_k' => $penerimaan->satuan_k,
                        'satuan_b' => $penerimaan->satuan_b,
                        'seri' => $penerimaan->motif,
                        'isi' => floatval($penerimaan->isi),
                        'create' => $penerimaan->created_at
                    ];
                }
            }
            // Tambahkan penjualan
            foreach ($item->penjualan as $penjualan) {
                if (empty($searchTrans) || stripos($penjualan->motif, $searchTrans) !== false) {
                    $transaksi[] = [
                        'type' => 'penjualan',
                        'tanggal' => $penjualan->tanggal,
                        'notransaksi' => $penjualan->notransaksi,
                        'debit' => 0,
                        'kredit' => floatval($penjualan->pengeluaran),
                        'satuan_k' => $penjualan->satuan_k,
                        'satuan_b' => $penjualan->satuan_b,
                        'seri' => $penjualan->motif,
                        'isi' => floatval($penjualan->isi),
                        'create' => $penjualan->created_at
                    ];
                }
            }

            // Tambahkan retur
            foreach ($item->returbarang as $retur) {
                if (empty($searchTrans) || stripos($retur->motif, $searchTrans) !== false) {
                    $transaksi[] = [
                        'type' => 'retur',
                        'tanggal' => $retur->tanggal,
                        'notransaksi' => $retur->notransaksi,
                        'debit' => floatval($retur->penerimaan),
                        'kredit' => 0,
                        'satuan_k' => $retur->satuan_k,
                        'satuan_b' => $retur->satuan_b,
                        'seri' => $retur->motif,
                        'isi' => floatval($item->isi),
                        'create' => $retur->created_at
                    ];
                }
            }
            // Tambahkan Pengembalian
            foreach ($item->pengembalian as $pengembalian) {
                if (empty($searchTrans) || stripos($pengembalian->motif, $searchTrans) !== false) {
                    $transaksi[] = [
                        'type' => 'pengembalian',
                        'tanggal' => $pengembalian->tanggal,
                        'notransaksi' => $pengembalian->notransaksi,
                        'debit' => 0,
                        'kredit' => floatval($pengembalian->pengeluaran),
                        'satuan_k' => $item->satuan_k,
                        'satuan_b' => $item->satuan_b,
                        'seri' => $pengembalian->motif,
                        'isi' => floatval($item->isi),
                        'create' => $pengembalian->created_at
                    ];
                }
            }

            // Tambahkan penyesuaian
            foreach ($item->penyesuaian as $penyesuaian) {
                if (empty($searchTrans) || stripos($penyesuaian->motif ?? '', $searchTrans) !== false) {
                    $debit = floatval($penyesuaian->jumlah_k);
                    $kredit = 0;
                    if ($debit < 0) {
                        $kredit = abs($debit);
                        $debit = 0;
                    }
                    $transaksi[] = [
                        'type' => 'penyesuaian',
                        'tanggal' => $penyesuaian->tgl,
                        'notransaksi' => $penyesuaian->nopenyesuaian,
                        'debit' => $debit,
                        'kredit' => $kredit,
                        'seri' => $penyesuaian->motif,
                        'satuan_k' => $item->satuan_k,
                        'satuan_b' => $item->satuan_b,
                        'isi' => floatval($item->isi),
                        'create' => $penyesuaian->created_at
                    ];
                }
            }

            // Urutkan transaksi berdasarkan tanggal
            usort($transaksi, function ($a, $b) {
                return strtotime($a['create']) <=> strtotime($b['create']);
            });

            // Tambahkan saldo awal sebagai transaksi pertama
            $seri = !empty($searchTrans) ? $searchTrans : '';
            array_unshift($transaksi, [
                'type' => 'saldo_awal',
                'tanggal' => $awal,
                'notransaksi' => 'SALDO AWAL',
                'debit' => floatval($saldoAwalDebit),
                'kredit' => floatval($saldoAwalKredit),
                'satuan_k' => $item->satuan_k ?? '',
                'satuan_b' => $item->satuan_b ?? '',
                'seri' => $seri, // Tambahkan seri berdasarkan request('x')
                'isi' => floatval($item->isi ?? 1),
            ]);

            $item->transaksi = $transaksi;

            // Hapus relasi asli untuk mengurangi ukuran respons
            unset($item->penerimaan);
            unset($item->penjualan);
            unset($item->returbarang);
            unset($item->pengembalian);
            unset($item->penyesuaian);

            return $item;
        });

        return new JsonResponse($data);
    }

    public function simpanbarang(Request $request)
    {
        $messages = [
            'rincians.*.gambar.max' => 'Ukuran Foto Tidak Boleh Lebih dari 2MB.',
            'rincians.*.gambar.image' => 'File harus berupa gambar.',
            'namabarang.required' => 'Nama Barang Wajib diisi.',
            'hargajual1.numeric' => 'Harga Pengguna Harus Angka.',
            'hargajual2.numeric' => 'Harga Toko Harus Angka.',
            'hargabeli.numeric' => 'Harga Beli Harus Angka.',
            'bonus_cash.numeric' => 'Bonus jika Pembayaran Cash Harus Angka.',
            'bonus_hutang.numeric' => 'Bonus jika Pembayaran Hutang Harus Angka.',
        ];

        $request->validate([
            'rincians.*.gambar' => 'nullable|image|max:2048', // Maksimal 2MB
            'namabarang' => 'required',
            'hargajual1' => 'nullable|numeric',
            'hargajual2' => 'nullable|numeric',
            'hargabeli' => 'nullable|numeric',
            'bonus_cash' => 'nullable|numeric',
            'bonus_hutang' => 'nullable|numeric',

        ], $messages);

        if ($request->kodebarang === '' || $request->kodebarang === null) {
            DB::select('call kodebarang(@nomor)');
            $x = DB::table('counter')->select('kodebarang')->get();
            $no = $x[0]->kodebarang;

            // $cek = Barang::count();
            // $total = (int) $cek + (int) 1;
            $kodebarang = FormatingHelper::matkdbarang($no, 'BRG');
        } else {
            $kodebarang = $request->kodebarang;
        }

        $namagabung = $request->brand . ' ' . $request->ukuran . ' ' . $request->namagabung . ' ' . $request->kualitas;
        $simpan = Barang::updateOrCreate(
            [
                'kodebarang' => $kodebarang
            ],
            [
                'namagabung' => $request->namagabung,
                'namabarang' => $namagabung,
                'kualitas' => $request->kualitas,
                'brand' => $request->brand,
                'kodejenis' => $request->kodejenis,
                'seri' => $request->seri,
                'satuan_b' => $request->satuan_b,
                'satuan_k' => $request->satuan_k,
                'isi' => $request->isi,
                'kategori' => $request->kategori,
                'hargajual1' => $request->hargajual1,
                'hargajual2' => $request->hargajual2,
                'hargajual1besar' => $request->hargajual1besar,
                'hargajual2besar' => $request->hargajual2besar,
                'hargabeli' => $request->hargabeli,
                'minim_stok' => $request->minim_stok,
                'ukuran' => $request->ukuran,
                'bonus_cash' => $request->bonus_cash,
                'bonus_hutang' => $request->bonus_hutang,
            ]
        );
        if ($request->has('rincians')) {
            $hasThumbnail = false; // Flag untuk menandai apakah sudah ada thumbnail

            foreach ($request->rincians as $img) {
                if (isset($img['gambar']) && $img['gambar']->isValid()) {
                    $path = $img['gambar']->store('images', 'public');

                    // Jika flag_thumbnail = 1 dan belum ada thumbnail sebelumnya
                    if (isset($img['flag_thumbnail']) && $img['flag_thumbnail'] === '1' && !$hasThumbnail) {
                        $flagThumbnail = '1';
                        $hasThumbnail = true; // Set flag bahwa sudah ada thumbnail
                    } else {
                        $flagThumbnail = null; // Reset flag_thumbnail untuk gambar lain
                    }

                    // Simpan gambar dengan flag_thumbnail
                    $simpan->rincians()->create([
                        'kodebarang' => $simpan->kodebarang,
                        'gambar' => $path,
                        'flag_thumbnail' => $flagThumbnail,
                    ]);
                }
            }
        }

        return new JsonResponse(
            [
                'message' => 'Data Berhasil disimpan...!!!',
                'result' => $simpan->load('rincians')
            ],
            200
        );
    }
    public function setThumbnail(Request $request)
    {
        // Cari gambar yang dipilih berdasarkan ID
        $img = Imagebarang::find($request->id);

        if (!$img) {
            return new JsonResponse(['message' => 'Data Tidak Ditemukan'], 500);
        }

        // Mulai transaksi database
        DB::beginTransaction();

        try {
            // Ubah flag_thumbnail ke 0 untuk semua gambar terkait barang ini
            Imagebarang::where('kodebarang', $img->kodebarang)
                ->where('flag_thumbnail', '1')
                ->update(['flag_thumbnail' => NULL]);

            // Ubah flag_thumbnail ke 1 untuk gambar yang dipilih
            $img->flag_thumbnail = '1';
            $img->save();

            // Commit transaksi
            DB::commit();

            return new JsonResponse(['message' => 'Berhasil Memilih Thumbnail'], 200);
        } catch (\Exception $e) {
            // Rollback transaksi jika terjadi error
            DB::rollBack();
            return new JsonResponse(['message' => 'Gagal Memilih Thumbnail', 'error' => $e->getMessage()], 500);
        }
    }

    public function deleteItem(Request $request)
    {
        // nyoba biar bisa push

        $header = Barang::find($request->id);
        if (!$header) {
            return new JsonResponse(['message' => 'Data Tidak Ditemukan'], 500);
        }

        $header->delete();
        foreach ($header->rincians as $image) {
            $filePath = public_path('storage/' . $image->gambar);  // Ganti dengan path yang benar

            if (file_exists($filePath)) {
                unlink($filePath);
            }
        }
        $header->rincians()->delete();

        return new JsonResponse(['message' => 'Data Sudah Dihapus'], 200);
    }

    public function deletegambar(Request $request)
    {
        // Cari gambar berdasarkan ID
        $image = Imagebarang::find($request->id);

        if (!$image) {
            return response()->json([
                'message' => 'Gambar tidak ditemukan',
            ], 404);
        }

        // Hapus file gambar dari storage (opsional)
        $filePath = public_path('storage/' . $image->gambar);  // Ganti dengan path yang benar

        if (file_exists($filePath)) {
            unlink($filePath);
        }

        // Hapus data dari database
        $image->delete();

        return response()->json([
            'message' => 'Gambar berhasil dihapus',
        ], 200);
    }
}
