<?php

namespace App\Http\Controllers\Api\Transaksi\Penjualan;

use App\Helpers\FormatingHelper;
use App\Http\Controllers\Controller;
use App\Models\Pelanggan;
use App\Models\Transaksi\Penjualan\HeaderCicilan;
use App\Models\Transaksi\Penjualan\HeaderPenjualan;
use App\Models\Transaksi\Penjualan\HeaderReturPenjualan;
use App\Models\Transaksi\Penjualan\PembayaranCicilan;
use App\Models\User;
use GuzzleHttp\Psr7\Header;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CicilanController extends Controller
{
    //
    public function getPenjualan()
    {
        $raw = HeaderPenjualan::with([
            'pelanggan' => function ($q) {
                $q->with([
                    'headerPenjualan' => function ($q) {
                        $q->whereIn('flag', ['2', '3', '4', '7', '8'])
                            ->with([
                                'cicilan',
                                'headerRetur' => function ($q) {
                                    $q->where('status', '!=', '');
                                }
                            ]);
                    },
                ]);
            },
            'sales',
            'cicilan',
            'keterangan',
            'detail.masterBarang',
            'headerRetur' => function ($q) {
                $q->where('status', '!=', '')
                    ->with([
                        'detail'
                    ]);
            },
        ])
            ->where('no_penjualan', 'like', '%' . request('q') . '%')
            ->when(
                request('flag') == 'semua',
                function ($q) {
                    $q->whereIn('flag', ['2', '3', '4', '7', '8']);
                },
                function ($q) {
                    $q->where('flag', request('flag'));
                }
            )
            ->when(request()->has('sales'), function ($q) {
                $sales = User::select('id')->where('nama', 'LIKE', '%' . request('sales') . '%')->pluck('id');
                $q->whereIn('sales_id', $sales);
            })
            ->when(request()->has('pelanggan'), function ($q) {
                $req = Pelanggan::select('id')->where('nama', 'LIKE', '%' . request('pelanggan') . '%')->pluck('id');
                $q->whereIn('pelanggan_id', $req);
            })
            ->when(request()->has('q'), function ($q) {
                $q->where('no_penjualan', 'like', '%' . request('q') . '%');
            })
            ->orderBy('id', 'asc')
            ->simplePaginate(request('per_page'));
        $data['data'] = collect($raw)['data'];
        $data['meta'] = collect($raw)->except('data');
        return new JsonResponse($data);
    }

    public function bawaNota(Request $request)
    {
        $data = HeaderPenjualan::find($request->id);
        if (!$data) {
            return new JsonResponse(['message' => 'Data Tidak Ditemukan'], 410);
        }
        $data->update([
            'flag' => '4'
        ]);
        $data->load([
            'pelanggan',
            'sales',
            'cicilan',
            'detail.masterBarang',
            'headerRetur' => function ($q) {
                $q->where('status', '!=', '')
                    ->with([
                        'detail'
                    ]);
            },
        ]);
        return new JsonResponse([
            'message' => 'Berhasil Membawa Nota',
            'data' => $data

        ], 200);
    }
    public function tidakNyicil(Request $request)
    {
        $data = HeaderPenjualan::find($request->id);
        if (!$data) {
            return new JsonResponse(['message' => 'Data Tidak Ditemukan'], 410);
        }

        $flag = '2';
        $message = 'Nota kembali tanpa cicilan';
        $count = PembayaranCicilan::where('no_penjualan', $data->no_penjualan)->count();
        if ($count > 0) {
            $flag = '3';
        }
        $data->update([
            'flag' => $flag
        ]);

        $data->load([
            'pelanggan',
            'sales',
            'cicilan',
            'detail.masterBarang',
            'headerRetur' => function ($q) {
                $q->where('status', '!=', '')
                    ->with([
                        'detail'
                    ]);
            },
        ]);
        return new JsonResponse([
            'message' => $message,
            'data' => $data

        ], 200);
    }

    public function newSimpanCicilan(Request $request)
    {
        try {
            DB::beginTransaction();
            $data = $this->ackSimpanCicilan($request);
            DB::commit();
            return new JsonResponse($data, 200);
        } catch (\Throwable $th) {
            DB::rollBack();
            return new JsonResponse([
                'message' => $th->getMessage(),
            ], 410);
        }
    }
    public static function ackSimpanCicilan($request)
    {
        $request->validate([
            'jumlah' => 'required',
            'pelanggan_id' => 'required',
            'sales_id' => 'required',
        ], [
            'jumlah.required' => 'Jumlah dibayarkan wajib diisi',
            'pelanggan_id.required' => 'Pelanggan belum dipilih, tutup dialog terlebih dahulu, Pelanggan terpilih secara otomatis',
            'sales_id.required' => 'Sales belum dipilih, tutup dialog terlebih dahulu, Sales terpilih secara otomatis',
        ]);

        if ($request->jumlah <= 0) {
            return new JsonResponse(['message' => 'Jumlah Cicilan Tidak Boleh 0'], 410);
        }


        // kemmbalikan flag menjadi 2
        HeaderPenjualan::find($request->id)->update(['flag' => '2']);
        $hutang = HeaderPenjualan::where('pelanggan_id', $request->pelanggan_id)->whereIn('flag', ['2', '3', '4'])
            ->orderBy('no_penjualan', 'asc')
            ->get();

        $jumlahCicilan = $request->jumlah;
        $headerCicilan = HeaderCicilan::create([
            'pelanggan_id' => $request->pelanggan_id,
            'sales_id' => $request->sales_id,
            'cara_bayar' => $request->cara_bayar,
            'jumlah' => $jumlahCicilan,
            'tgl_bayar' => date('Y-m-d H:i:s'),
        ]);
        $dibayar = [];
        foreach ($hutang as $key) {
            if ($jumlahCicilan <= 0) break;
            $retur = HeaderReturPenjualan::where('no_penjualan', $key->no_penjualan)->where('status', '=', '1')->sum('total');
            $cicilan = PembayaranCicilan::where('no_penjualan', $key->no_penjualan)->sum('jumlah');
            $sisa = (float)$key->total - (float)$key->bayar - (float)$cicilan - (float)$retur;
            $pengurang = min($sisa, $jumlahCicilan);

            PembayaranCicilan::create([
                'no_penjualan' => $key->no_penjualan,
                'tgl_bayar' => $headerCicilan->tgl_bayar,
                'header_ciclan_id' => $headerCicilan->id,
                'jumlah' => $pengurang,
            ]);
            if ($sisa <= $jumlahCicilan) {
                $dibayar[] = [
                    'key' => $key,
                    'pengurang' => $pengurang
                ];
                $key->update(['flag' => '5']);
            } else {
                $dibayar[] = [
                    'key' => $key,
                    'pengurang' => $pengurang
                ];
                $key->update(['flag' => '3']);
            }
            $jumlahCicilan -= $pengurang;
        }
        DB::commit();
        return
            [
                'message' => 'Berhasil Menyimpan Cicilan',
                'hutang' => $hutang,
                'dibayar' => $dibayar,
                'req' => $request->all(),
            ];
    }

    public function simpanPelunasan(Request $request)
    {

        try {
            DB::beginTransaction();
            $headerPenjualan = HeaderPenjualan::find($request->id);
            if (!$headerPenjualan) {
                return new JsonResponse([
                    'message' => 'Terjadi Kesalahan, Data penjualan tidak ditemukan '
                ], 410);
            }
            $jumlahBayar = $request->jumlah;
            $jumlah = (float)$headerPenjualan->total - (float)$headerPenjualan->bayar; // ini jumlah hutang
            if ((float) $jumlah != (float) $jumlahBayar) {
                if ($headerPenjualan->pelanggan_id == null) {
                    throw new \Exception('Karena bukan pelanggan, maka harus lunas dan jumlah pembayaran harus pas');
                } else if ($headerPenjualan->sales_id == null) {
                    throw new \Exception('Karena Sales tidak ditemukan, maka harus lunas dan jumlah pembayaran harus pas');
                } else {
                    $result = $this->ackSimpanCicilan($request);
                }
            } else {
                // bisa jadi ada pelanggan, bisa jadi tidak ada

                $headerCicilan = HeaderCicilan::create([
                    'pelanggan_id' => $request->pelanggan_id,
                    'sales_id' => $request->sales_id,
                    'cara_bayar' => $request->cara_bayar,
                    'jumlah' => $jumlahBayar,
                    'tgl_bayar' => date('Y-m-d H:i:s'),
                ]);
                if (!$headerCicilan) {
                    return new JsonResponse([
                        'message' => 'Terjadi Kesalahan, Data tidak tersimpan '
                    ], 410);
                }
                PembayaranCicilan::create([
                    'no_penjualan' => $request->no_penjualan,
                    'tgl_bayar' => $headerCicilan->tgl_bayar,
                    'header_ciclan_id' => $headerCicilan->id,
                    'jumlah' => $jumlahBayar,
                ]);

                $headerPenjualan->update([
                    'flag' => '5',
                ]);
            }
            DB::commit();
            return new JsonResponse([
                'message' => 'Berhasil Menyimpan Pelunasan',
                'req' => $request->all(),
                'result' => $result ?? null
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return new JsonResponse([
                'message' => 'Terjadi Kesalahan ' . $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),

            ], 410);
        }
    }
    public function simpanCicilan(Request $request)
    {
        $request->validate([
            'id' => 'required',
            'jumlah' => 'required',
        ]);

        if ($request->jumlah <= 0) {
            return new JsonResponse(['message' => 'Jumlah Cicilan Tidak Boleh 0'], 410);
        }

        $data = HeaderPenjualan::find($request->id);
        if (!$data) {
            return new JsonResponse(['message' => 'Data Tidak Ditemukan'], 410);
        }

        $count = PembayaranCicilan::where('no_penjualan', $data->no_penjualan)->count();
        $awal = explode('-', $data->no_penjualan);
        $nomor = FormatingHelper::notaPenjualan($count + 1, 'CCL/' . $awal[0]);

        PembayaranCicilan::create([
            'no_penjualan' => $data->no_penjualan,
            'sales_id' => $data->sales_id,
            'pelanggan_id' => $data->pelanggan_id,
            'no_pembayaran' => $nomor,
            'jumlah' => $request->jumlah,
            'tgl_bayar' => date('Y-m-d H:i:s'),
        ]);
        $flag = '3';
        $message = 'Cicilan Sudah Dibayarkan';
        $sum = PembayaranCicilan::where('no_penjualan', $data->no_penjualan)->sum('jumlah');
        if ($sum >= ($data->total - $data->total_diskon)) {
            $flag = '5';
            $message = 'Nota Sudah Lunas';
        }
        $data->update([
            'flag' => $flag
        ]);

        $data->load([
            'pelanggan',
            'sales',
            'cicilan',
            'detail.masterBarang',
            'headerRetur' => function ($q) {
                $q->where('status', '!=', '')
                    ->with([
                        'detail'
                    ]);
            },
        ]);
        return new JsonResponse([
            'message' => $message,
            'data' => $data,
            'sum' => $sum,

        ], 200);
    }
    public function hapusCicilan(Request $request)
    {
        $request->validate([
            'id' => 'required',
        ]);

        $cicil = PembayaranCicilan::find($request->id);
        if (!$cicil) {
            return new JsonResponse(['message' => 'Data Tidak Ditemukan'], 410);
        }

        $data = HeaderPenjualan::where('no_penjualan', $cicil->no_penjualan)->first();
        $cicil->delete();

        if ($data) {
            $flag = '3';
            $sum = PembayaranCicilan::where('no_penjualan', $data->no_penjualan)->sum('jumlah');
            if ($sum >= ($data->total - $data->total_diskon)) {
                $flag = '5';
            }
            $data->update([
                'flag' => $flag
            ]);
            $data->load([
                'pelanggan',
                'sales',
                'cicilan',
                'detail.masterBarang',
            ]);
        }
        return new JsonResponse([
            'message' => 'Cicilan Sudah Dihapus',
            'data' => $data

        ], 200);
    }
}
