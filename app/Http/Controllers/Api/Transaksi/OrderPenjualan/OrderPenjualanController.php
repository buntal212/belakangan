<?php

namespace App\Http\Controllers\Api\Transaksi\OrderPenjualan;

use App\Events\SendNotificationEvent;
use App\Helpers\FifoHelper;
use App\Helpers\FormatingHelper;
use App\Http\Controllers\Api\Transaksi\Penjualan\PenjualanController;
use App\Http\Controllers\Controller;
use App\Models\OrderPenjualan;
use App\Models\OrderPenjualanRincian;
use App\Models\Transaksi\Penjualan\DetailPenjualan;
use App\Models\Transaksi\Penjualan\DetailPenjualanFifo;
use App\Models\Transaksi\Penjualan\HeaderPenjualan;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderPenjualanController extends Controller
{
	public function index()
	{
		$query = OrderPenjualan::query();

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
			'pelanggan:id,nama,alamat,telepon,norek,namabank',
			'sales:id,nama'
		]);
	}


	public function updateRincian(Request $request)
	{
		$rincian_id = $request->id;
		$order_id = $request->order_id;


		$rincian = OrderPenjualanRincian::find($rincian_id);

		$rincian->jumlah = $request->jumlah;
		$rincian->satuan = $request->satuan;
		$rincian->subtotal = $request->subtotal;

		$rincian->save();

		$order = OrderPenjualan::find($order_id);
		$order->total_harga = $request->total_harga_order;
		$order->save();

		$data = ['message' => 'success', 'order' => $order, 'rincian' => $rincian];

		return new JsonResponse($data);
	}
	public function deleteRincian(Request $request)
	{
		$rincian_id = $request->id;
		$order_id = $request->order_id;


		$rincian = OrderPenjualanRincian::find($rincian_id);

		$rincian->delete();

		$order = OrderPenjualan::find($order_id);
		$order->total_harga = $request->total_harga_order;
		$order->save();


		$data = ['message' => 'success', 'order' => $order, 'rincian' => $rincian];

		return new JsonResponse($data);
	}
	public function updateStatus(Request $request)
	{
		try {
			DB::beginTransaction();
			$order_id = $request->order_id;

			$status = $request->status_order;

			$order = OrderPenjualan::find($order_id);
			if (!$order) throw new Exception('Order tidak ditemukan');
			$order->status_order = $request->status_order;
			$order->save();

			if ($status === '5') {
				// tgl kirim di isi di header order disini, default hari ini
				$order->tanggal_kirim = Carbon::now()->format('Y-m-d H:i:s');
				$order->save();

				$rinci = OrderPenjualanRincian::where('order_penjualan_id', $order->id)->with('barang')->get();
				// ngisi header rincian penjualan dengan nomor penjualan = nomor order
				foreach ($rinci as $key) {
					$subtotal = ($key['jumlah'] * $key['harga_jual']) - ($key['diskon'] ?? 0);
					$detail = DetailPenjualan::updateOrCreate(
						[
							'no_penjualan' => $order->noorder,
							'kodebarang' => $key['barang']['kodebarang'],
							'motif' => $key['motif'],
							'jumlah' => $key['jumlah'],
							'isi' => $key['isi'],
						],
						[
							'harga_jual' => $key['harga'],
							'harga_beli' => $key['harga_beli'],
							'diskon' => $key['diskon'],
							'subtotal' => $subtotal
						]
					);
				}
				$statusBayar = (int)$order->status_pembayaran;
				$tempo = Carbon::parse($order->tglorder)->addDays($order->tempo)->format('Y-m-d');
				$kembali = (float)$order->total_harga - (float)$order->bayar;
				$caraBayar = strtolower($order->metode_bayar);
				$totalDiskon = DetailPenjualan::where('no_penjualan', $order->noorder)->sum('diskon');
				$flag = $statusBayar == 1 ? ((int)$order->bayar > 0 ? '7' : '2') : '5';
				$data = HeaderPenjualan::updateOrCreate(
					[
						'no_penjualan' => $order->noorder,
					],
					[
						'tgl' => $order->tglorder,
						'tgl_kirim' => $order->tgl_kirim,
						'sales_id' => $order->sales_id,
						'pelanggan_id' => $order->pelanggan_id,
						'jml_tempo' => $order->tempo,
						'tempo' => $tempo,
						'bayar' => $order->bayar,
						'kembali' => $kembali,
						'cara_bayar' => $caraBayar,
						'flag' => $flag,
						'total' => $order->total_harga,
						'total_diskon' => $totalDiskon,
					]
				);
				// proses fifo disini seperti di funngsi simpanPembayaran() di penjualan controller
				try {
					if (!DetailPenjualanFifo::where('no_penjualan', $order->noorder)->exists()) $detaiPengurangan = FifoHelper::processFifo($order->noorder);
				} catch (\Exception $e) {
					throw new \Exception("Error memproses FIFO: " . $e->getMessage());
				}

				// flag tergantung cara bayar.. cash iku langsung 5, kalo ada dp, iku lali, lek utang, golek maneh nang kodingan
				$bonus = PenjualanController::setBonus($order->noorder, $data->flag);

				# code... INI HARUSNYA KE PENJUALAN AKTUAL
			}


			$data = ['message' => 'success', 'order' => $order];
			event(new SendNotificationEvent(null, 'order-penjualan', 'order-status', $data));

			DB::commit();
			return new JsonResponse($data);
		} catch (\Exception $e) {
			DB::rollBack();
			return response()->json([
				'message' => 'Gagal menyimpan data: ' . $e->getMessage(),
				'file' => $e->getFile(),
				'line' => $e->getLine(),
				'trace' => $e->getTrace(),

			], 410);
		}
	}
}
