<?php

namespace App\Http\Controllers\Api\Transaksi\PembayaranPiutang;

use App\Helpers\FormatingHelper;
use App\Http\Controllers\Controller;
use App\Models\Transaksi\Penjualan\HeaderCicilan;
use App\Models\Transaksi\Penjualan\HeaderPenjualan;
use App\Models\Transaksi\Penjualan\PembayaranCicilan;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PembayaranPiutangController extends Controller
{
    public function index()
    {
        $from = request('from').' 00:00:00';
        $to = request('to').' 23:59:59';
        $data = HeaderCicilan::with(
            [
                'pelanggan',
                'sales',
                'cicilan'
            ]
        )
        ->whereBetween('tgl_bayar', [
            $from,
            $to
        ])
        ->when(request('q'), function ($query) {
            $query->where(function($q) {
                $q->where('header_cicilans.nopembayaran', 'like', '%' . request('q') . '%');
            });
        })
        ->whereNotNull('nopembayaran')
        ->orderBy('id', 'desc')
        ->simplePaginate(request('per_page'));
        return new JsonResponse($data);
    }

    public function listpiutang()
    {

        $data = HeaderPenjualan::with([
            'pelanggan',
            'sales',
            'keterangan',
            'detail' => function ($q) {
                $q->with(['masterBarang']);
            },
            'cicilan' => function ($q) {
                    $q->select('no_penjualan', DB::raw('sum(jumlah) as jumlah'))->groupBy('no_penjualan');
            },
            'headerRetur' => function ($q) {
                $q->select('no_penjualan', DB::raw('sum(total) as total'))
                    ->where('status', '1')
                    ->groupBy('no_penjualan');
            },
        ])
        ->whereIn('flag', ['2', '3', '7', '8']);
        if(request('pelanggan_id') !== '0'){
            $data = $data->where('pelanggan_id', request('pelanggan_id'));
        }else{
            $data = $data->whereNull('pelanggan_id');
        }
        $data = $data->orderBy('id', 'desc');
        $data = $data->get();
        $data = self::onlyOutstanding($data);
        return new JsonResponse($data);
    }

    public function riwayatNota(string $noPenjualan): JsonResponse
    {
        $nota = DB::table('header_penjualans')
            ->leftJoin('pelanggans', 'pelanggans.id', '=', 'header_penjualans.pelanggan_id')
            ->where('header_penjualans.no_penjualan', $noPenjualan)
            ->select([
                'header_penjualans.id',
                'header_penjualans.no_penjualan',
                'header_penjualans.tgl as tgl_hutang',
                'header_penjualans.total as total_nota',
                'header_penjualans.bayar as pembayaran_awal',
                'header_penjualans.cara_bayar as cara_bayar_awal',
                'pelanggans.nama as nama_pelanggan',
            ])
            ->first();

        if (!$nota) {
            return new JsonResponse(['message' => 'Nota penjualan tidak ditemukan.'], 404);
        }

        $rincian = DB::table('pembayaran_cicilans')
            ->leftJoin('header_cicilans', 'header_cicilans.id', '=', 'pembayaran_cicilans.header_ciclan_id')
            ->where('pembayaran_cicilans.no_penjualan', $noPenjualan)
            ->orderBy('pembayaran_cicilans.tgl_bayar')
            ->orderBy('pembayaran_cicilans.id')
            ->select([
                'pembayaran_cicilans.id',
                'pembayaran_cicilans.tgl_bayar',
                'pembayaran_cicilans.jumlah',
                'header_cicilans.nopembayaran',
                'header_cicilans.cara_bayar',
                'header_cicilans.keterangan',
            ])
            ->get();

        if ((float) $nota->pembayaran_awal > 0) {
            $rincian->prepend((object) [
                'id' => 'pembayaran-awal-' . $nota->id,
                'tgl_bayar' => $nota->tgl_hutang,
                'jumlah' => (float) $nota->pembayaran_awal,
                'nopembayaran' => null,
                'cara_bayar' => $nota->cara_bayar_awal,
                'keterangan' => 'Pembayaran awal (DP)',
            ]);
        }

        $returList = DB::table('header_retur_penjualans')
            ->where('no_penjualan', $noPenjualan)
            ->where('status', '1')
            ->orderBy('tgl')
            ->get([
                'id',
                'no_retur',
                'tgl',
                'total',
                'subtotal',
                'diskon_kuitansi',
                'dpp',
                'ppn_nominal',
                'ppn_persen',
                'status',
            ]);

        $retur = (float) $returList->sum('total');
        $totalDibayar = (float) $rincian->sum('jumlah');
        $totalNota = (float) $nota->total_nota;
        $totalPengurang = $totalDibayar + $retur;

        $sisaPiutang = max(0.0, round($totalNota - $totalPengurang, 2));
        $hakPengembalian = max(0.0, round($totalPengurang - $totalNota, 2));
        $isLunas = ($sisaPiutang <= 0.001);

        $tglLunas = null;
        if ($isLunas) {
            $lastTglBayar = $rincian->last()?->tgl_bayar;
            $lastTglRetur = $returList->last()?->tgl;
            $candidates = array_filter([$lastTglBayar, $lastTglRetur]);
            $tglLunas = !empty($candidates) ? max($candidates) : $nota->tgl_hutang;
        }

        return new JsonResponse([
            'data' => [
                'no_penjualan' => $nota->no_penjualan,
                'nama_pelanggan' => $nota->nama_pelanggan,
                'tgl_hutang' => $nota->tgl_hutang,
                'total_nota' => $totalNota,
                'total_dibayar_nota' => $totalDibayar,
                'total_retur_nota' => $retur,
                'sisa_piutang_nota' => $sisaPiutang,
                'hak_pengembalian' => $hakPengembalian,
                'is_lunas' => $isLunas,
                'tgl_lunas' => $tglLunas,
                'status_nota' => $isLunas ? 'Lunas' : ($totalDibayar > 0 ? 'Proses Cicilan' : 'Belum Dibayar'),
                'rincian' => $rincian->values(),
                'daftar_retur' => $returList->values(),
            ],
        ]);
    }

    public function simpan(Request $request)
    {
        $request->validate([
            'nopenjualan' => 'required',
            'total' => 'required|numeric|min:0.01',
            'tgl' => 'required|date',
        ]);

        $nota = HeaderPenjualan::where('no_penjualan', $request->nopenjualan)->first();
        if (!$nota) {
            return new JsonResponse(['message' => 'Nota penjualan tidak ditemukan.'], 404);
        }

        $cicilanSebelumnya = (float) PembayaranCicilan::where('no_penjualan', $request->nopenjualan)->sum('jumlah');
        $returSelesai = (float) DB::table('header_retur_penjualans')->where('no_penjualan', $request->nopenjualan)->where('status', '1')->sum('total');
        $sisaPiutangAktual = max(0, round((float) $nota->total - (float) $nota->bayar - $cicilanSebelumnya - $returSelesai, 2));

        if ((float) $request->total > ($sisaPiutangAktual + 0.01)) {
            return new JsonResponse([
                'message' => 'Jumlah pembayaran melebihi sisa piutang aktual (Maksimal Rp ' . number_format($sisaPiutangAktual, 0, ',', '.') . ').'
            ], 422);
        }

        if($request->nopembayaran === '' || $request->nopembayaran === null)
        {
            try {
                DB::select('call noPembayaranPiutang(@nomor)');
                $x = DB::table('counter')->select('nopembayaranpiutang')->get();
                $no = $x[0]->nopembayaranpiutang;
            } catch (\Throwable $e) {
                DB::table('counter')->increment('nopembayaranpiutang');
                $no = DB::table('counter')->value('nopembayaranpiutang');
            }
            $nopembayaran = FormatingHelper::nopembayaranhutang($no, 'BP');
        }else{
            $nopembayaran = $request->nopembayaran;
        }

        $tglsekarang = Carbon::now();
        $tglbayar = Carbon::parse($request->tgl);

        $jumlahhari = $tglsekarang->diffInDays($tglbayar);
        if($jumlahhari > 7){
            return new JsonResponse(['message' => 'Maaf Tidak Dapat Menambah Data...,Tanggal Bayar Lebih Dari 7 Hari...!!!'], 500);
        }

        try {
            DB::beginTransaction();
            $data = HeaderCicilan::updateOrCreate(
                [
                    'nopembayaran' => $nopembayaran,
                ],
                [
                    'pelanggan_id' => $request->pelanggan_id,
                    'tgl_bayar' => $request->tgl,
                    'cara_bayar' => $request->carabayar,
                    'keterangan' => $request->keterangan,
                    'user' => Auth::id(),
            ]);

            $datax = PembayaranCicilan::create(
                [
                    'no_penjualan' => $request->nopenjualan,
                    'header_ciclan_id' => $data->id,
                    'jumlah' => $request->total,
                    'tgl_bayar' => $request->tgl,
                    'user' => Auth::id(),
                ]
            );

            // Sinkronkan total jumlah di HeaderCicilan
            $totalHeaderCicilan = (float) PembayaranCicilan::where('header_ciclan_id', $data->id)->sum('jumlah');
            $data->update(['jumlah' => $totalHeaderCicilan]);

            // Sinkronkan flag di HeaderPenjualan
            $totalCicilanBaru = $cicilanSebelumnya + (float) $request->total;
            $sisaBaru = round((float) $nota->total - (float) $nota->bayar - $totalCicilanBaru - $returSelesai, 2);
            if ($sisaBaru <= 0.001) {
                $nota->update(['flag' => '5']); // Lunas
            } else {
                $nota->update(['flag' => '3']); // Proses Cicilan
            }

            $hasil = self::getlistpembayaranbynotrans($nopembayaran);
            $newhutang = self::listhutangx($request->pelanggan_id);

            DB::commit();

            return new JsonResponse([
                'message' => 'Data Berhasil Disimpan',
                'data' => $hasil,
                'nopenjualan' => $request->nopenjualan,
                'newhutang' => $newhutang
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

    public static function getlistpembayaranbynotrans($notrans)
    {
       $data = HeaderCicilan::with(
            [
                'cicilan' ,
                'pelanggan'
            ]
        )->where('nopembayaran', $notrans)
        ->get();
        return $data;
    }

    public static function listhutangx($pelanggan)
    {
        $data = HeaderPenjualan::with([
            'pelanggan',
            'sales',
            'detail' => function ($q) {
                $q->with(['masterBarang']);
            },
            'cicilan' => function ($q) {
                    $q->select('no_penjualan', DB::raw('sum(jumlah) as jumlah'))->groupBy('no_penjualan');
                },
            'headerRetur' => function ($q) {
                $q->select('no_penjualan', DB::raw('sum(total) as total'))
                    ->where('status', '1')
                    ->groupBy('no_penjualan');
            },
            ])
        ->whereIn('flag', ['2', '3', '7', '8'])
        ->where('pelanggan_id', $pelanggan)
        ->orderBy('id', 'desc')
        ->get();
        return self::onlyOutstanding($data);
    }

    private static function onlyOutstanding($sales)
    {
        return $sales->filter(function ($sale) {
            $totalNota = (float) $sale->total;
            $totalTerbayar = (float) $sale->cicilan->sum('jumlah');
            $totalRetur = (float) ($sale->headerRetur->sum('total') ?: 0);
            $sisaPiutang = round($totalNota - $totalRetur - (float) $sale->bayar - $totalTerbayar, 2);

            return $sisaPiutang >= 0.01;
        })->values();
    }

    public function hapusrincian(Request $request)
    {
        $cek = PembayaranCicilan::leftjoin('header_cicilans', 'header_cicilans.id', '=', 'pembayaran_cicilans.header_ciclan_id')
        ->where('pembayaran_cicilans.id', $request->id)
        ->select('header_cicilans.tgl_bayar', 'header_cicilans.cara_bayar', 'header_cicilans.keterangan')
        ->first();
        if (!$cek) {
            return new JsonResponse(['message' => 'Data cicilan tidak ditemukan.'], 404);
        }
        $tglsekarang = Carbon::now();
        $tglbayar = Carbon::parse($cek->tgl_bayar);

        $jumlahhari = $tglsekarang->diffInDays($tglbayar);
        if($jumlahhari > 7){
            return new JsonResponse(['message' => 'Maaf Tidak Dapat Menghapus Data,Tanggal Bayar Lebih Dari 7 Hari...!!!'], 500);
        }
        if ($cek->cara_bayar === 'Penagihan Sales' || $cek->cara_bayar=== ''){
            return new JsonResponse(['message' => 'Maaf Tidak Dapat Menghapus Data...,Karena Transaksi ini melalui Penagihan Sales...!!!'], 500);
        }
        if($cek->keterangan === null){
            return new JsonResponse(['message' => 'Maaf Tidak Dapat Menghapus Data...,Karena Transaksi ini melalui Penagihan Sales...!!!'], 500);
        }

        $nopembayaran = $request->notrans;
        try {
            DB::beginTransaction();
                $targetCicil = PembayaranCicilan::find($request->id);
                if (!$targetCicil) {
                    DB::rollBack();
                    return new JsonResponse(['message' => 'Data cicilan tidak ditemukan.'], 404);
                }
                $noPenjualan = $targetCicil->no_penjualan;
                $headerCicilanId = $targetCicil->header_ciclan_id;

                $targetCicil->delete();

                // Re-sync HeaderCicilan jumlah
                $totalHeaderCicilan = (float) PembayaranCicilan::where('header_ciclan_id', $headerCicilanId)->sum('jumlah');
                HeaderCicilan::where('id', $headerCicilanId)->update(['jumlah' => $totalHeaderCicilan]);

                // Re-calculate flag on HeaderPenjualan
                $nota = HeaderPenjualan::where('no_penjualan', $noPenjualan)->first();
                if ($nota) {
                    $totalCicilanSisa = (float) PembayaranCicilan::where('no_penjualan', $noPenjualan)->sum('jumlah');
                    $returSelesai = (float) DB::table('header_retur_penjualans')->where('no_penjualan', $noPenjualan)->where('status', '1')->sum('total');
                    $sisa = round((float) $nota->total - (float) $nota->bayar - $totalCicilanSisa - $returSelesai, 2);

                    if ($sisa <= 0.001) {
                        $nota->update(['flag' => '5']);
                    } else if ($totalCicilanSisa > 0) {
                        $nota->update(['flag' => '3']);
                    } else {
                        $flagAwal = ((float) $nota->bayar > 0) ? '7' : '2';
                        $nota->update(['flag' => $flagAwal]);
                    }
                }

                $hasil = self::getlistpembayaranbynotrans($nopembayaran);
            DB::commit();
             return new JsonResponse([
                'message' => 'Data Berhasil Dihapus',
                'data' => $hasil
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
}
