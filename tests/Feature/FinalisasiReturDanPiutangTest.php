<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\Laporan\LaporanPembayaranPiutangController;
use App\Http\Controllers\Api\Transaksi\PembayaranPiutang\PembayaranPiutangController;
use App\Http\Controllers\Api\Transaksi\Retur\ReturPenjualanController;
use App\Models\Pelanggan;
use App\Models\Barang;
use App\Models\Transaksi\Penjualan\DetailPenjualan;
use App\Models\Transaksi\Penjualan\DetailReturPenjualan;
use App\Models\Transaksi\Penjualan\HeaderPenjualan;
use App\Models\Transaksi\Penjualan\HeaderReturPenjualan;
use App\Models\Transaksi\Penjualan\PembayaranCicilan;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FinalisasiReturDanPiutangTest extends TestCase
{
    protected int $pelangganId;
    protected int $salesId;
    protected int $userId;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'mysql',
            'database.connections.mysql.username' => 'root',
            'database.connections.mysql.password' => 'ServBay.dev',
            'database.connections.mysql.database' => 'tokobangunan',
        ]);
        DB::purge('mysql');

        DB::beginTransaction();

        $user = User::first() ?? User::create([
            'nama' => 'Final Test User ' . uniqid(),
            'email' => 'final_test_' . uniqid() . '@example.com',
            'password' => bcrypt('password'),
        ]);
        $this->userId = $user->id;
        Auth::loginUsingId($this->userId);

        $pelanggan = Pelanggan::create([
            'nama' => 'Pelanggan Final Test ' . uniqid(),
            'alamat' => 'Jl. Pengujian Final No. 88',
            'telepon' => '089999888877',
        ]);
        $this->pelangganId = $pelanggan->id;
        $this->salesId = $this->userId;
    }

    protected function tearDown(): void
    {
        DB::rollBack();
        parent::tearDown();
    }

    private function createPenjualan(array $attributes = []): HeaderPenjualan
    {
        $noPenjualan = 'TEST-FIN-' . uniqid() . '-PJL';
        $default = [
            'no_penjualan' => $noPenjualan,
            'pelanggan_id' => $this->pelangganId,
            'sales_id' => $this->salesId,
            'tgl' => now()->format('Y-m-d H:i:s'),
            'total' => 1000000,
            'total_diskon' => 0,
            'diskon_kuitansi' => 0,
            'subtotal' => 1000000,
            'ppn_persen' => 0,
            'ppn_nominal' => 0,
            'bayar' => 0,
            'kembali' => 0,
            'cara_bayar' => 'Tempo',
            'flag' => '2',
            'tempo' => now()->addDays(14)->format('Y-m-d'),
            'jml_tempo' => 14,
        ];

        return HeaderPenjualan::create(array_merge($default, $attributes));
    }

    private function createBarang(): Barang
    {
        $kode = 'BRG-' . strtoupper(substr(uniqid(), 0, 8));
        return Barang::create([
            'kodebarang' => $kode,
            'namabarang' => 'Barang Uji ' . $kode,
            'satuan_k' => 'PCS',
            'satuan_b' => 'DUS',
            'isi' => 1,
            'harga_beli' => 10000,
            'harga_jual' => 20000,
            'status' => '1',
        ]);
    }

    /**
     * Rule 1: Nota penjualan yang sudah LUNAS tetap dapat ditelusuri melalui riwayat piutang.
     */
    public function test_nota_lunas_tetap_dapat_ditelusuri_riwayat_piutang()
    {
        $penjualan = $this->createPenjualan([
            'total' => 500000,
            'bayar' => 500000,
            'cara_bayar' => 'Tunai',
            'flag' => '5',
        ]);

        $ctrl = new PembayaranPiutangController();
        $response = $ctrl->riwayatNota($penjualan->no_penjualan);
        $data = $response->getData(true)['data'];

        $this->assertEquals($penjualan->no_penjualan, $data['no_penjualan']);
        $this->assertTrue($data['is_lunas']);
        $this->assertEquals(0.0, $data['sisa_piutang_nota']);
        $this->assertEquals(500000.0, $data['total_dibayar_nota']);
        $this->assertEquals(0.0, $data['hak_pengembalian']);
        $this->assertNotNull($data['tgl_lunas']);
    }

    /**
     * Rule 1 & Tahap 1: Nota lunas TIDAK boleh muncul sebagai tagihan aktif di list-piutang.
     */
    public function test_nota_lunas_tidak_muncul_sebagai_tagihan_aktif_di_listpiutang()
    {
        $lunas = $this->createPenjualan([
            'total' => 400000,
            'bayar' => 400000,
            'flag' => '5',
        ]);

        $belumLunas = $this->createPenjualan([
            'total' => 600000,
            'bayar' => 100000,
            'flag' => '7', // DP
        ]);

        request()->merge(['pelanggan_id' => (string) $this->pelangganId]);
        $ctrl = new PembayaranPiutangController();
        $response = $ctrl->listpiutang();
        $data = json_decode($response->getContent(), true);

        $noPenjualans = array_column($data, 'no_penjualan');
        $this->assertNotContains($lunas->no_penjualan, $noPenjualans);
        $this->assertContains($belumLunas->no_penjualan, $noPenjualans);
    }

    /**
     * Rule 2 & 3: Retur setelah lunas menghasilkan hak pengembalian dana, BUKAN piutang negatif.
     */
    public function test_retur_setelah_lunas_menghasilkan_hak_pengembalian_dana_bukan_piutang_negatif()
    {
        // Nota sudah lunas dibayar 1.000.000
        $penjualan = $this->createPenjualan([
            'total' => 1000000,
            'bayar' => 1000000,
            'flag' => '5',
        ]);

        // Retur selesai sebesar 250.000
        HeaderReturPenjualan::create([
            'no_retur' => 'RET-LUNAS-' . uniqid(),
            'no_penjualan' => $penjualan->no_penjualan,
            'tgl' => now()->format('Y-m-d H:i:s'),
            'subtotal' => 250000,
            'diskon_kuitansi' => 0,
            'dpp' => 250000,
            'ppn_nominal' => 0,
            'total' => 250000,
            'status' => '1', // Selesai
        ]);

        $ctrl = new PembayaranPiutangController();
        $response = $ctrl->riwayatNota($penjualan->no_penjualan);
        $data = $response->getData(true)['data'];

        $this->assertTrue($data['is_lunas']);
        $this->assertEquals(0.0, $data['sisa_piutang_nota'], 'Sisa piutang tidak boleh negatif');
        $this->assertEquals(250000.0, $data['total_retur_nota']);
        $this->assertEquals(250000.0, $data['hak_pengembalian'], 'Kelebihan retur adalah hak pengembalian pelanggan');
    }

    /**
     * Rule 2: Retur selesai melebihi sisa piutang menutup piutang ke nol dan sisanya hak pengembalian.
     */
    public function test_retur_melebihi_sisa_piutang_menutup_piutang_ke_nol_dan_menghasilkan_hak_pengembalian()
    {
        // Total 1.000.000, DP 700.000 -> Sisa piutang 300.000
        $penjualan = $this->createPenjualan([
            'total' => 1000000,
            'bayar' => 700000,
            'flag' => '7',
        ]);

        // Retur selesai 500.000 (melebihi sisa piutang 300.000)
        HeaderReturPenjualan::create([
            'no_retur' => 'RET-OVER-' . uniqid(),
            'no_penjualan' => $penjualan->no_penjualan,
            'tgl' => now()->format('Y-m-d H:i:s'),
            'subtotal' => 500000,
            'diskon_kuitansi' => 0,
            'dpp' => 500000,
            'ppn_nominal' => 0,
            'total' => 500000,
            'status' => '1',
        ]);

        $ctrl = new PembayaranPiutangController();
        $response = $ctrl->riwayatNota($penjualan->no_penjualan);
        $data = $response->getData(true)['data'];

        $this->assertTrue($data['is_lunas'], 'Nota harus dianggap lunas karena retur melunasi sisa tagihan');
        $this->assertEquals(0.0, $data['sisa_piutang_nota']);
        $this->assertEquals(500000.0, $data['total_retur_nota']);
        $this->assertEquals(200000.0, $data['hak_pengembalian'], 'Selisih 500k - 300k = 200k adalah hak pengembalian');
    }

    /**
     * Rule 4 & 5: Retur sebagian dengan snapshot Diskon Nota dan PPN proporsional.
     */
    public function test_retur_sebagian_dengan_snapshot_diskon_nota_dan_ppn()
    {
        $barang = $this->createBarang();

        // Nota asli: 10 PCS @ 10.000 = 100.000 subtotal, Diskon Nota 10.000, DPP 90.000, PPN 11% = 9.900, Total 99.900
        $penjualan = $this->createPenjualan([
            'subtotal' => 100000,
            'diskon_kuitansi' => 10000,
            'ppn_persen' => 11,
            'ppn_nominal' => 9900,
            'total' => 99900,
            'flag' => '2',
        ]);

        $detailJual = DetailPenjualan::create([
            'no_penjualan' => $penjualan->no_penjualan,
            'kodebarang' => $barang->kodebarang,
            'jumlah' => 10,
            'harga_jual' => 10000,
            'diskon' => 0,
            'subtotal' => 100000,
        ]);

        // Retur 4 PCS (40%)
        $retur = HeaderReturPenjualan::create([
            'no_retur' => 'RET-SEB-' . uniqid(),
            'no_penjualan' => $penjualan->no_penjualan,
            'tgl' => now()->format('Y-m-d H:i:s'),
            'status' => '', // Draft
        ]);

        DetailReturPenjualan::create([
            'header_retur_penjualan_id' => $retur->id,
            'no_retur' => $retur->no_retur,
            'kodebarang' => $barang->kodebarang,
            'detail_penjualan_id' => $detailJual->id,
            'jumlah' => 4,
            'harga_jual' => 10000,
            'diskon' => 0,
            'subtotal' => 40000,
            'total' => 40000,
            'status' => '',
        ]);

        $returCtrl = new ReturPenjualanController();
        $returCtrl->selesai(new Request(['no_retur' => $retur->no_retur]));

        $returFresh = HeaderReturPenjualan::where('no_retur', $retur->no_retur)->first();

        $this->assertEquals('1', $returFresh->status);
        $this->assertEquals(40000.0, (float) $returFresh->subtotal);
        $this->assertEquals(4000.0, (float) $returFresh->diskon_kuitansi, 'Diskon nota retur 40% dari 10.000');
        $this->assertEquals(36000.0, (float) $returFresh->dpp, 'DPP retur 40.000 - 4.000');
        $this->assertEquals(3960.0, (float) $returFresh->ppn_nominal, 'PPN retur 11% dari 36.000');
        $this->assertEquals(39960.0, (float) $returFresh->total, 'Total retur DPP + PPN');
    }

    /**
     * Rule 5: Retur berulang dengan sisa alokasi terakhir (tidak melebihi total nota asli).
     */
    public function test_retur_berulang_akumulasi_total_tidak_melebihi_nilai_nota_asli()
    {
        $barang = $this->createBarang();

        // Nota: 10 PCS @ 10.000 = 100.000 subtotal, Diskon Nota 10.000, DPP 90.000, PPN 11% = 9.900, Total 99.900
        $penjualan = $this->createPenjualan([
            'subtotal' => 100000,
            'diskon_kuitansi' => 10000,
            'ppn_persen' => 11,
            'ppn_nominal' => 9900,
            'total' => 99900,
            'flag' => '2',
        ]);

        $detailJual = DetailPenjualan::create([
            'no_penjualan' => $penjualan->no_penjualan,
            'kodebarang' => $barang->kodebarang,
            'jumlah' => 10,
            'harga_jual' => 10000,
            'diskon' => 0,
            'subtotal' => 100000,
        ]);

        $returCtrl = new ReturPenjualanController();

        // Retur 1: 4 PCS (40%)
        $retur1 = HeaderReturPenjualan::create([
            'no_retur' => 'RET-REP-1-' . uniqid(),
            'no_penjualan' => $penjualan->no_penjualan,
            'tgl' => now()->format('Y-m-d H:i:s'),
            'status' => '',
        ]);
        DetailReturPenjualan::create([
            'header_retur_penjualan_id' => $retur1->id,
            'no_retur' => $retur1->no_retur,
            'kodebarang' => $barang->kodebarang,
            'detail_penjualan_id' => $detailJual->id,
            'jumlah' => 4,
            'harga_jual' => 10000,
            'diskon' => 0,
            'subtotal' => 40000,
            'total' => 40000,
            'status' => '',
        ]);
        $returCtrl->selesai(new Request(['no_retur' => $retur1->no_retur]));

        // Retur 2: sisa 6 PCS (60%) -> ini adalah sisa alokasi terakhir
        $retur2 = HeaderReturPenjualan::create([
            'no_retur' => 'RET-REP-2-' . uniqid(),
            'no_penjualan' => $penjualan->no_penjualan,
            'tgl' => now()->format('Y-m-d H:i:s'),
            'status' => '',
        ]);
        DetailReturPenjualan::create([
            'header_retur_penjualan_id' => $retur2->id,
            'no_retur' => $retur2->no_retur,
            'kodebarang' => $barang->kodebarang,
            'detail_penjualan_id' => $detailJual->id,
            'jumlah' => 6,
            'harga_jual' => 10000,
            'diskon' => 0,
            'subtotal' => 60000,
            'total' => 60000,
            'status' => '',
        ]);
        $returCtrl->selesai(new Request(['no_retur' => $retur2->no_retur]));

        $retur1Fresh = HeaderReturPenjualan::where('no_retur', $retur1->no_retur)->first();
        $retur2Fresh = HeaderReturPenjualan::where('no_retur', $retur2->no_retur)->first();

        // Retur 2 mengambil sisa alokasi
        $this->assertEquals(6000.0, (float) $retur2Fresh->diskon_kuitansi, 'Sisa diskon nota = 10.000 - 4.000');
        $this->assertEquals(54000.0, (float) $retur2Fresh->dpp, 'Sisa DPP = 90.000 - 36.000');
        $this->assertEquals(5940.0, (float) $retur2Fresh->ppn_nominal, 'Sisa PPN = 9.900 - 3.960');
        $this->assertEquals(59940.0, (float) $retur2Fresh->total, 'Sisa Total = 99.900 - 39.960');

        // Kumulatif retur = tepat sama dengan total transaksi asli
        $kumulatifTotal = (float) $retur1Fresh->total + (float) $retur2Fresh->total;
        $this->assertEquals(99900.0, $kumulatifTotal, 'Kumulatif total retur tepat sama dengan nilai nota asli');
    }

    /**
     * Rule 6: Transaksi historis tanpa snapshot tetap terbaca tanpa mengarang Diskon Nota atau PPN.
     */
    public function test_retur_pada_nota_historis_tanpa_snapshot_tetap_terbaca_tanpa_mengarang_pajak()
    {
        $barang = $this->createBarang();

        // Nota historis tanpa snapshot (pajak 0 dan diskon nota 0)
        $penjualan = $this->createPenjualan([
            'subtotal' => 150000,
            'diskon_kuitansi' => 0,
            'ppn_persen' => 0,
            'ppn_nominal' => 0,
            'total' => 150000,
            'flag' => '2',
        ]);

        $detailJual = DetailPenjualan::create([
            'no_penjualan' => $penjualan->no_penjualan,
            'kodebarang' => $barang->kodebarang,
            'jumlah' => 3,
            'harga_jual' => 50000,
            'diskon' => 0,
            'subtotal' => 150000,
        ]);

        $retur = HeaderReturPenjualan::create([
            'no_retur' => 'RET-HIST-' . uniqid(),
            'no_penjualan' => $penjualan->no_penjualan,
            'tgl' => now()->format('Y-m-d H:i:s'),
            'status' => '',
        ]);

        DetailReturPenjualan::create([
            'header_retur_penjualan_id' => $retur->id,
            'no_retur' => $retur->no_retur,
            'kodebarang' => $barang->kodebarang,
            'detail_penjualan_id' => $detailJual->id,
            'jumlah' => 1,
            'harga_jual' => 50000,
            'diskon' => 0,
            'subtotal' => 50000,
            'total' => 50000,
            'status' => '',
        ]);

        $returCtrl = new ReturPenjualanController();
        $returCtrl->selesai(new Request(['no_retur' => $retur->no_retur]));

        $returFresh = HeaderReturPenjualan::where('no_retur', $retur->no_retur)->first();

        $this->assertEquals(0.0, (float) $returFresh->diskon_kuitansi, 'Diskon kuitansi tidak dikarang');
        $this->assertEquals(0.0, (float) $returFresh->ppn_nominal, 'PPN nominal tidak dikarang');
        $this->assertEquals(50000.0, (float) $returFresh->total, 'Total retur murni subtotal barang');
    }

    /**
     * Rule 7: Sinkronisasi Laporan Pembayaran Piutang dengan hak pengembalian dan status lunas.
     */
    public function test_laporan_pembayaran_piutang_mencatat_hak_pengembalian_dan_status_lunas()
    {
        $penjualan = $this->createPenjualan([
            'total' => 1000000,
            'bayar' => 700000,
            'flag' => '7', // DP 700.000, sisa piutang 300.000
        ]);

        HeaderReturPenjualan::create([
            'no_retur' => 'RET-LAP-' . uniqid(),
            'no_penjualan' => $penjualan->no_penjualan,
            'tgl' => now()->format('Y-m-d H:i:s'),
            'subtotal' => 500000,
            'diskon_kuitansi' => 0,
            'dpp' => 500000,
            'ppn_nominal' => 0,
            'total' => 500000,
            'status' => '1',
        ]);

        $laporanCtrl = new LaporanPembayaranPiutangController();
        $req = new Request([
            'pelanggan_id' => $this->pelangganId,
            'tglawal' => now()->subDay()->format('Y-m-d'),
            'tglakhir' => now()->addDay()->format('Y-m-d'),
        ]);

        $response = $laporanCtrl->getData($req);
        $data = $response->getData(true);

        $row = collect($data['data'])->firstWhere('no_penjualan', $penjualan->no_penjualan);

        $this->assertNotNull($row, 'Nota harus ditemukan di laporan');
        $this->assertEquals(500000.0, (float) $row['total_retur_nota']);
        $this->assertEquals(200000.0, (float) $row['hak_pengembalian']);
        $this->assertEquals(0.0, (float) $row['sisa_piutang_nota']);
    }
}
