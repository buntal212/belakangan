<?php

namespace App\Http\Controllers\Api\Transaksi\OrderPenjualan;

use App\Events\SendNotificationEvent;
use App\Helpers\FormatingHelper;
use App\Helpers\PpnHelper;
use App\Http\Controllers\Controller;
use App\Models\OrderPenjualan;
use App\Models\Transaksi\Penjualan\DetailPenjualan;
use App\Models\Transaksi\Penjualan\HeaderPenjualan;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderPenjualanController extends Controller
{
	public function index(Request $request)
	{
		$validated = $request->validate([
			'tanggal_mulai' => ['nullable', 'date_format:Y-m-d'],
			'tanggal_selesai' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:tanggal_mulai'],
			'status_order' => ['nullable', 'in:1,2,3,4,5,6,9'],
		]);

		$query = OrderPenjualan::query();
		$query
			->when(
				filled($validated['tanggal_mulai'] ?? null),
				fn ($builder) => $builder->whereDate('tglorder', '>=', $validated['tanggal_mulai'])
			)
			->when(
				filled($validated['tanggal_selesai'] ?? null),
				fn ($builder) => $builder->whereDate('tglorder', '<=', $validated['tanggal_selesai'])
			)
			->when(
				filled($validated['status_order'] ?? null),
				fn ($builder) => $builder->where('status_order', $validated['status_order'])
			);

		$data = $this->eagerLoadOrder($query)
			->orderBy('tglorder', 'DESC')
			->simplePaginate(request('per_page'));

		return new JsonResponse($data);
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
			'total_sebelum_diskon',
			'diskon_kuitansi',
			'status_order',
			'alasan_batal',
			'status_pembayaran',
			'sistem_bayar',
			'cara_bayar',
			'down_payment',
			'tanggal_kirim',
			'tanggal_terima',
			'metode_bayar',
			'bayar',
			'tempo',
			'catatan'
		)->with([
			'rincians:id,order_penjualan_id,barang_id,jumlah,isi,harga,total_sebelum_diskon,satuan,satuans,subtotal',
			'rincians.barang:id,kodebarang,namabarang,namagabung,isi,satuan_k,satuan_b',
			'rincians.barang.images',
			'pelanggan:id,kodeplgn,nama,alamat,telepon,norek,namabank',
			'sales:id,nama'
		]);
	}


	public function updateRincian(Request $request)
	{
		throw ValidationException::withMessages([
			'order_id' => ['Rincian order tidak dapat diubah melalui endpoint ini. Buat order baru bila data barang harus diubah.'],
		]);
	}
	public function deleteRincian(Request $request)
	{
		throw ValidationException::withMessages([
			'order_id' => ['Rincian order tidak dapat dihapus melalui endpoint ini. Batalkan order lalu buat order baru bila diperlukan.'],
		]);
	}
	public function updateStatus(Request $request)
	{
		try {
			$validated = $request->validate([
				'order_id' => ['required', 'integer', 'exists:order_penjualans,id'],
				'status_order' => ['required', 'in:3,5,6,9'],
				'alasan_batal' => ['nullable', 'string', 'max:255'],
			]);

			$data = DB::transaction(function () use ($validated) {
				$statusTujuan = (string) $validated['status_order'];
				$order = OrderPenjualan::lockForUpdate()->findOrFail($validated['order_id']);
				$statusSaatIni = (string) $order->status_order;
				$penjualan = HeaderPenjualan::where('noorder', $order->noorder)
					->lockForUpdate()
					->first();

				if ($statusSaatIni === '9' && $statusTujuan !== '9') {
					throw ValidationException::withMessages([
						'status_order' => ['Order yang sudah dibatalkan tidak dapat dilanjutkan.'],
					]);
				}

				if ($statusTujuan === '3') {
					if ($statusSaatIni === '3') {
						return ['message' => 'Order sudah ditransaksikan.', 'order' => $order, 'penjualan' => $penjualan];
					}

					if (!in_array($statusSaatIni, ['1', '2'], true)) {
						throw ValidationException::withMessages([
							'status_order' => ['Hanya order draf yang dapat disetujui menjadi transaksi.'],
						]);
					}

					$order->update(['status_order' => '3']);
					$penjualan = $this->buatDraftPenjualanDariOrder($order);
				}

				if ($statusTujuan === '5') {
					if ($statusSaatIni === '5') {
						return ['message' => 'Order sudah dikirim.', 'order' => $order, 'penjualan' => $penjualan];
					}

					if ($statusSaatIni !== '4') {
						throw ValidationException::withMessages([
							'status_order' => ['Order harus diproses melalui pembayaran sebelum dapat dikirim.'],
						]);
					}

					if (!$penjualan || $penjualan->flag === null || $penjualan->flag === '6') {
						throw ValidationException::withMessages([
							'status_order' => ['Penjualan belum selesai diproses atau telah dibatalkan.'],
						]);
					}

					$order->update([
						'status_order' => '5',
						'tanggal_kirim' => Carbon::now(),
					]);
					$penjualan->update(['tgl_kirim' => Carbon::now()->toDateString()]);
				}

				if ($statusTujuan === '9') {
					if (in_array($statusSaatIni, ['5', '6'], true)) {
						throw ValidationException::withMessages([
							'status_order' => ['Order yang sudah dikirim atau selesai tidak dapat dibatalkan.'],
						]);
					}

					if ($penjualan && $penjualan->flag !== null && $penjualan->flag !== '6') {
						throw ValidationException::withMessages([
							'status_order' => ['Order yang sudah memiliki transaksi pembayaran tidak dapat dibatalkan.'],
						]);
					}

					$order->update([
						'status_order' => '9',
						'alasan_batal' => $validated['alasan_batal'] ?? $order->alasan_batal ?? 'Dibatalkan oleh admin',
					]);

					if ($penjualan && $penjualan->flag === null) {
						$penjualan->update(['flag' => '6']);
					}
				}

				if ($statusTujuan === '6') {
					if ($statusSaatIni !== '5') {
						throw ValidationException::withMessages([
							'status_order' => ['Hanya order yang sudah dikirim yang dapat diselesaikan.'],
						]);
					}

					if (!$penjualan || $penjualan->flag === null || $penjualan->flag === '6') {
						throw ValidationException::withMessages([
							'status_order' => ['Penjualan sumber tidak valid untuk diselesaikan.'],
						]);
					}

					$order->update([
						'status_order' => '6',
						'tanggal_terima' => Carbon::now(),
					]);
				}

				return ['message' => 'Status order berhasil diperbarui.', 'order' => $order->fresh(), 'penjualan' => $penjualan?->fresh()];
			});

			event(new SendNotificationEvent(null, 'order-penjualan', 'order-status', $data));

			return new JsonResponse($data);
		} catch (ValidationException $e) {
			throw $e;
		} catch (\Exception $e) {
			report($e);

			return response()->json([
				'message' => 'Gagal menyimpan data order. Silakan coba lagi.',
			], 500);
		}
	}

	/**
	 * Salin order yang telah disetujui sebagai draft penjualan. Proses pembayaran
	 * dan FIFO tetap dilakukan melalui alur Penjualan yang sudah berjalan.
	 */
	protected function buatDraftPenjualanDariOrder(OrderPenjualan $order): HeaderPenjualan
	{
		$order->loadMissing('rincians.barang');
		if ($order->rincians->isEmpty()) {
			throw ValidationException::withMessages([
				'order_id' => ['Order tanpa rincian barang tidak dapat dijadikan transaksi.'],
			]);
		}
		$subtotalSebelumDiskonKuitansi = (float) $order->rincians->sum('subtotal');
		$diskonKuitansi = min(
			(float) ($order->diskon_kuitansi ?? 0),
			$subtotalSebelumDiskonKuitansi,
		);
		$diskonKuitansiTersisa = $diskonKuitansi;
		$totalSebelumDiskon = (float) ($order->total_sebelum_diskon ?? 0);

		if ($totalSebelumDiskon <= 0) {
			$totalSebelumDiskon = (float) $order->rincians->sum(function ($rincian) {
				return (float) $rincian->jumlah * (float) $rincian->harga;
			});
		}

		$penjualan = HeaderPenjualan::where('noorder', $order->noorder)->first();
		if (!$penjualan) {
			// Kompatibilitas data lama yang pernah memakai nomor order sebagai nota penjualan.
			$penjualan = HeaderPenjualan::where('no_penjualan', $order->noorder)->first();
		}

		if (!$penjualan) {
			$penjualan = new HeaderPenjualan([
				'no_penjualan' => FormatingHelper::nomorNotaPenjualanBerikutnya(),
			]);
		}

		if ($penjualan->exists && $penjualan->flag !== null) {
			throw ValidationException::withMessages([
				'order_id' => ['Order ini sudah memiliki transaksi penjualan yang selesai diproses.'],
			]);
		}

		$nilaiPenjualan = PpnHelper::hitung((float) $order->total_harga, PpnHelper::tarifAktif());
		$penjualan->fill([
			'noorder' => $order->noorder,
			// Tanggal penjualan mengikuti saat draft disetujui menjadi transaksi,
			// sedangkan tanggal order tetap tersimpan pada order_penjualans.tglorder.
			'tgl' => Carbon::now(),
			'pelanggan_id' => $order->pelanggan_id,
			'sales_id' => $order->sales_id,
			...$nilaiPenjualan,
			'total_diskon' => max(0, $totalSebelumDiskon - (float) $order->total_harga),
			'bayar' => 0,
			'kembali' => 0,
			'flag' => null,
			'cara_bayar' => 'cash',
		]);
		$penjualan->save();

		$jumlahRincian = $order->rincians->count();
		foreach ($order->rincians->values() as $index => $rincian) {
			if (!$rincian->barang) {
				throw new Exception('Barang pada rincian order tidak ditemukan.');
			}

			$jumlah = (float) $rincian->jumlah;
			$harga = (float) $rincian->harga;
			$totalSebelumDiskonItem = (float) ($rincian->total_sebelum_diskon ?? ($jumlah * $harga));
			$subtotalOrder = (float) $rincian->subtotal;
			$alokasiDiskonKuitansi = $index === $jumlahRincian - 1
				? $diskonKuitansiTersisa
				: round(
					$subtotalSebelumDiskonKuitansi > 0
						? ($diskonKuitansi * $subtotalOrder / $subtotalSebelumDiskonKuitansi)
						: 0,
					2,
				);
			$alokasiDiskonKuitansi = min($alokasiDiskonKuitansi, $subtotalOrder, $diskonKuitansiTersisa);
			$diskonKuitansiTersisa -= $alokasiDiskonKuitansi;
			$subtotalPenjualan = max(0, $subtotalOrder - $alokasiDiskonKuitansi);

			DetailPenjualan::updateOrCreate(
				[
					'no_penjualan' => $penjualan->no_penjualan,
					'kodebarang' => $rincian->barang->kodebarang,
					'motif' => null,
				],
				[
					'isi' => max(1, (float) $rincian->isi),
					'jumlah' => $jumlah,
					'harga_beli' => 0,
					'harga_jual' => $harga,
					'diskon' => max(0, $totalSebelumDiskonItem - $subtotalPenjualan),
					'subtotal' => $subtotalPenjualan,
				],
			);
		}

		return $penjualan->fresh([
			'detail.masterBarang',
			'pelanggan',
			'sales',
		]);
	}
}
