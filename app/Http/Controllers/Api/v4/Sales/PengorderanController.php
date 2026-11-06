<?php

namespace App\Http\Controllers\Api\v4\Sales;

use App\Http\Controllers\Controller;
use App\Models\Barang;
use App\Models\OrderPenjualan;
use App\Models\Pelanggan;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PengorderanController extends Controller
{
    public function riwayat(Request $request): JsonResponse
    {
        $this->pastikanBolehMembuatOrder($request);

        $filter = $request->validate([
            'bulan' => ['nullable', 'date_format:Y-m'],
            'pelanggan_id' => [
                'nullable',
                Rule::exists('pelanggans', 'id')->where(fn ($query) => $query->whereNull('flaging')),
            ],
        ]);
        $bulan = $filter['bulan'] ?? now()->format('Y-m');
        $tanggalAwal = Carbon::createFromFormat('Y-m', $bulan)->startOfMonth();
        $tanggalAkhir = $tanggalAwal->copy()->endOfMonth();

        $jabatan = strtolower(trim((string) $request->user()->jabatan));
        $admin = in_array($jabatan, ['admin', 'super admin', 'superadmin', 'owner'], true);

        $orders = OrderPenjualan::query()
            ->with([
                'pelanggan:id,kodeplgn,nama,telepon',
                'sales:id,nama',
                'rincians:id,order_penjualan_id,barang_id,jumlah,isi,harga,total_sebelum_diskon,satuan,satuans,subtotal',
                'rincians.barang:id,kodebarang,namabarang,namagabung,satuan_k,satuan_b,isi',
            ])
            ->when(!$admin, fn ($query) => $query->where('sales_id', $request->user()->id))
            ->whereBetween('tglorder', [$tanggalAwal, $tanggalAkhir])
            ->when($filter['pelanggan_id'] ?? null, fn ($query, $pelangganId) => $query->where('pelanggan_id', $pelangganId))
            ->when($request->filled('q'), function ($query) use ($request) {
                $kataKunci = '%' . $request->string('q')->trim() . '%';

                $query->where(function ($pencarian) use ($kataKunci) {
                    $pencarian->where('noorder', 'like', $kataKunci)
                        ->orWhereHas('pelanggan', function ($pelanggan) use ($kataKunci) {
                            $pelanggan->where('nama', 'like', $kataKunci)
                                ->orWhere('kodeplgn', 'like', $kataKunci);
                        });
                });
            })
            ->orderByDesc('tglorder')
            ->simplePaginate(min(max($request->integer('per_page', 20), 1), 50));

        return response()->json($orders);
    }

    public function pelanggan(Request $request): JsonResponse
    {
        $this->pastikanBolehMembuatOrder($request);

        $pelanggan = Pelanggan::query()
            ->whereNull('flaging')
            ->when($request->filled('q'), function ($query) use ($request) {
                $kataKunci = '%' . $request->string('q')->trim() . '%';

                $query->where(function ($pencarian) use ($kataKunci) {
                    $pencarian->where('nama', 'like', $kataKunci)
                        ->orWhere('kodeplgn', 'like', $kataKunci)
                        ->orWhere('telepon', 'like', $kataKunci);
                });
            })
            ->select('id', 'kodeplgn', 'nama', 'telepon')
            ->orderBy('nama')
            ->simplePaginate(min(max($request->integer('per_page', 20), 1), 50));

        return response()->json($pelanggan);
    }

    public function simpan(Request $request): JsonResponse
    {
        $this->pastikanBolehMembuatOrder($request);

        // Kompatibilitas untuk bundle PWA lama yang belum mengirim informasi pembayaran.
        $request->merge([
            'sistem_bayar' => $request->input('sistem_bayar', 'tempo'),
        ]);

        $data = $request->validate([
            'pelanggan_id' => [
                'required',
                Rule::exists('pelanggans', 'id')->where(fn ($query) => $query->whereNull('flaging')),
            ],
            'sistem_bayar' => ['required', Rule::in(['lunas', 'dp', 'tempo'])],
            'cara_bayar' => [
                Rule::requiredIf(fn () => in_array($request->input('sistem_bayar'), ['lunas', 'dp'], true)),
                'nullable',
                Rule::in(['cash', 'transfer']),
            ],
            'down_payment' => ['exclude_unless:sistem_bayar,dp', 'required', 'numeric', 'min:0.01'],
            'diskon_kuitansi' => ['nullable', 'numeric', 'min:0'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.kodebarang' => ['required', 'string', 'distinct', 'exists:barangs,kodebarang'],
            'items.*.jumlah' => ['required', 'integer', 'min:1'],
            'items.*.diskon' => ['nullable', 'numeric', 'min:0'],
        ]);

        try {
            $order = DB::transaction(function () use ($data, $request) {
                $rincian = [];
                $totalHarga = 0;
                $totalSebelumDiskon = 0;

                foreach ($data['items'] as $item) {
                    $barang = Barang::query()
                        ->select('id', 'kodebarang', 'hargajual1', 'isi', 'satuan_k', 'satuan_b')
                        ->where('kodebarang', $item['kodebarang'])
                        ->firstOrFail();

                    $jumlah = (int) $item['jumlah'];
                    $stokFisik = (int) DB::table('stoks')
                        ->where('kdbarang', $barang->kodebarang)
                        ->lockForUpdate()
                        ->get(['jumlah_k'])
                        ->sum('jumlah_k');
                    $stokAlokasi = (int) DB::table('rincian_order_penjualans as rincian')
                        ->join('order_penjualans as order_penjualan', 'order_penjualan.id', '=', 'rincian.order_penjualan_id')
                        ->where('rincian.barang_id', $barang->id)
                        ->whereIn('order_penjualan.status_order', ['1', '2', '3', '4'])
						->whereNotExists(function ($query) {
							$query->select(DB::raw(1))
								->from('header_penjualans as penjualan')
								->whereColumn('penjualan.noorder', 'order_penjualan.noorder')
								->whereNotNull('penjualan.flag')
								->where('penjualan.flag', '<>', '6');
						})
                        ->sum('rincian.jumlah');
                    $stokBebas = max(0, $stokFisik - $stokAlokasi);

                    if ($jumlah > $stokBebas) {
                        throw ValidationException::withMessages([
                            'items' => ["Stok bebas {$barang->kodebarang} tidak mencukupi."],
                        ]);
                    }

                    $harga = (float) $barang->hargajual1;
                    $diskon = min((float) ($item['diskon'] ?? 0), $harga);
                    $totalItemSebelumDiskon = round($jumlah * $harga, 0, PHP_ROUND_HALF_UP);
                    $subtotal = round($jumlah * ($harga - $diskon), 0, PHP_ROUND_HALF_UP);
                    $totalSebelumDiskon += $totalItemSebelumDiskon;
                    $totalHarga += $subtotal;

                    $rincian[] = [
                        'barang_id' => $barang->id,
                        'jumlah' => $jumlah,
                        'isi' => max(1, (float) $barang->isi),
                        'harga' => $harga,
                        'total_sebelum_diskon' => $totalItemSebelumDiskon,
                        'subtotal' => $subtotal,
                        'satuan' => $barang->satuan_k,
                        'satuans' => [
                            'satuan_k' => $barang->satuan_k,
                            'satuan_b' => $barang->satuan_b,
                        ],
                    ];
                }

                $diskonKuitansi = round((float) ($data['diskon_kuitansi'] ?? 0), 0, PHP_ROUND_HALF_UP);

                if ($diskonKuitansi > $totalHarga) {
                    throw ValidationException::withMessages([
                        'diskon_kuitansi' => ['Diskon kuitansi tidak boleh lebih besar dari total barang.'],
                    ]);
                }

                $totalHarga -= $diskonKuitansi;
                $sistemBayar = $data['sistem_bayar'];
                $downPayment = $sistemBayar === 'dp'
                    ? round((float) $data['down_payment'], 0, PHP_ROUND_HALF_UP)
                    : 0;

                if ($downPayment > $totalHarga) {
                    throw ValidationException::withMessages([
                        'down_payment' => ['Down payment tidak boleh lebih besar dari total order.'],
                    ]);
                }

                $order = OrderPenjualan::create([
                    'noorder' => $this->nomorOrder(),
                    'tglorder' => now(),
                    'pelanggan_id' => $data['pelanggan_id'],
                    'sales_id' => $request->user()->id,
                    'total_harga' => $totalHarga,
                    'total_sebelum_diskon' => $totalSebelumDiskon,
                    'diskon_kuitansi' => $diskonKuitansi,
                    'status_order' => '1',
                    'status_pembayaran' => $sistemBayar === 'lunas' ? '2' : '1',
                    'sistem_bayar' => $sistemBayar,
                    'cara_bayar' => $sistemBayar === 'tempo' ? null : $data['cara_bayar'],
                    'down_payment' => $downPayment,
                    'metode_bayar' => match ($sistemBayar) {
                        'lunas' => 'Lunas',
                        'dp' => 'Down Payment (DP)',
                        default => 'Tempo',
                    },
                    'bayar' => $sistemBayar === 'lunas' ? $totalHarga : $downPayment,
                ]);

                $order->rincians()->createMany($rincian);

                return $order->load('rincians');
            });
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            Log::error('Gagal menyimpan order dari aplikasi Jangur Keramik.', [
                'user_id' => $request->user()->id,
                'exception' => $exception,
            ]);

            return response()->json(['message' => 'Order gagal disimpan. Silakan coba kembali.'], 500);
        }

        return response()->json([
            'message' => 'Order berhasil dikirim.',
            'data' => $order,
        ], 201);
    }

    private function nomorOrder(): string
    {
        $counter = DB::table('counter_order_penjualans')
            ->where('id', 1)
            ->lockForUpdate()
            ->first();

        if (!$counter) {
            throw new \RuntimeException('Counter nomor order belum tersedia.');
        }

        $nomorBerikutnya = (int) $counter->nomor_terakhir + 1;

        DB::table('counter_order_penjualans')
            ->where('id', 1)
            ->update([
                'nomor_terakhir' => $nomorBerikutnya,
                'updated_at' => now(),
            ]);

        return 'OPJ-' . str_pad((string) $nomorBerikutnya, 8, '0', STR_PAD_LEFT);
    }

    private function pastikanBolehMembuatOrder(Request $request): void
    {
        $jabatan = strtolower(trim((string) $request->user()->jabatan));
        $boleh = str_contains($jabatan, 'sales') || in_array($jabatan, ['admin', 'super admin', 'superadmin', 'owner'], true);

        abort_unless($boleh, 403, 'Anda tidak memiliki akses untuk membuat order.');
    }
}
