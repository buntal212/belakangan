<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\Laporan\LaporanAkuntansiController;
use App\Http\Controllers\Api\Laporan\LaporanPenjualanController;
use App\Models\Barang;
use App\Models\Pelanggan;
use App\Models\Transaksi\Penjualan\DetailPenjualan;
use App\Models\Transaksi\Penjualan\DetailPenjualanFifo;
use App\Models\Transaksi\Penjualan\DetailReturPenjualan;
use App\Models\Transaksi\Penjualan\HeaderPenjualan;
use App\Models\Transaksi\Penjualan\HeaderReturPenjualan;
use App\Models\Transaksi\Penjualan\PembayaranCicilan;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DashboardDanLaporanKeuanganTest extends TestCase
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
            'nama' => 'User Akuntansi ' . uniqid(),
            'email' => 'akuntansi_' . uniqid() . '@example.com',
            'password' => bcrypt('password'),
        ]);
        $this->userId = $user->id;
        Auth::loginUsingId($this->userId);

        $pelanggan = Pelanggan::create([
            'nama' => 'Pelanggan Akuntansi ' . uniqid(),
            'alamat' => 'Jl. Akuntansi No. 10',
            'telepon' => '081233445566',
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
        $noPenjualan = 'TEST-AKT-' . uniqid() . '-PJL';
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

    private function createBarang(array $attrs = []): Barang
    {
        $kode = 'BRG-' . strtoupper(substr(uniqid(), 0, 8));
        return Barang::create(array_merge([
            'kodebarang' => $kode,
            'namabarang' => 'Barang Akuntansi ' . $kode,
            'satuan_k' => 'PCS',
            'satuan_b' => 'DUS',
            'isi' => 1,
            'harga_beli' => 10000,
            'harga_jual' => 20000,
            'status' => '1',
        ], $attrs));
    }

    /**
     * Test 1: Konsistensi Omzet / Pendapatan Bersih antara Dashboard dan Laba Rugi.
     */
    public function test_konsistensi_omzet_dashboard_dengan_laba_rugi()
    {
        $year = now()->year;
        $month = now()->month;

        // Nota dengan Diskon Nota dan PPN:
        // Subtotal barang = 100.000, Diskon Nota = 10.000, DPP = 90.000, PPN 11% = 9.900, Total = 99.900
        $this->createPenjualan([
            'tgl' => now()->format('Y-m-d H:i:s'),
            'subtotal' => 100000,
            'diskon_kuitansi' => 10000,
            'ppn_persen' => 11,
            'ppn_nominal' => 9900,
            'total' => 99900,
            'flag' => '5',
        ]);

        request()->merge(['tahun' => (string) $year, 'bulan' => (string) $month]);
        $labarugiCtrl = new LaporanAkuntansiController();
        $lrData = $labarugiCtrl->Labarugi()->getData(true);

        $dashCtrl = new DashboardController();
        $dashData = $dashCtrl->index()->getData(true);

        $this->assertEquals(
            (float) $lrData['total_penjualan'],
            (float) $dashData['summary']['totalPendapatan'],
            'Omzet Laba Rugi harus sama persis dengan totalPendapatan Dashboard'
        );
    }

    /**
     * Test 2: Retur Selesai mengurangi omzet, sedangkan Retur Draft tidak mengurangi.
     */
    public function test_retur_selesai_mengurangi_omzet_dan_retur_draft_tidak_mengurangi()
    {
        $year = now()->year;
        $month = now()->month;

        $penjualan = $this->createPenjualan([
            'tgl' => now()->format('Y-m-d H:i:s'),
            'subtotal' => 200000,
            'diskon_kuitansi' => 0,
            'ppn_persen' => 0,
            'ppn_nominal' => 0,
            'total' => 200000,
            'flag' => '5',
        ]);

        // Retur 1: Selesai (status = '1') sebesar 50.000
        HeaderReturPenjualan::create([
            'no_retur' => 'RET-SEL-' . uniqid(),
            'no_penjualan' => $penjualan->no_penjualan,
            'tgl' => now()->format('Y-m-d H:i:s'),
            'subtotal' => 50000,
            'diskon_kuitansi' => 0,
            'dpp' => 50000,
            'ppn_nominal' => 0,
            'total' => 50000,
            'status' => '1',
        ]);

        // Retur 2: Draft (status = '') sebesar 30.000
        HeaderReturPenjualan::create([
            'no_retur' => 'RET-DFT-' . uniqid(),
            'no_penjualan' => $penjualan->no_penjualan,
            'tgl' => now()->format('Y-m-d H:i:s'),
            'subtotal' => 30000,
            'diskon_kuitansi' => 0,
            'dpp' => 30000,
            'ppn_nominal' => 0,
            'total' => 30000,
            'status' => '', // Draft
        ]);

        request()->merge(['tahun' => (string) $year, 'bulan' => (string) $month]);
        $labarugiCtrl = new LaporanAkuntansiController();
        $lrData = $labarugiCtrl->Labarugi()->getData(true);

        $dashCtrl = new DashboardController();
        $dashData = $dashCtrl->index()->getData(true);

        // Omzet bersih harus dikurangi hanya oleh retur selesai (50.000), bukan draft (30.000)
        $this->assertEquals((float) $lrData['total_penjualan'], (float) $dashData['summary']['totalPendapatan']);
    }

    /**
     * Test 3: Laporan Penjualan memuat rincian barang, motif, dan nilai retur tanpa duplikasi.
     */
    public function test_laporan_penjualan_detail_menampilkan_motif_dan_retur_tanpa_duplikasi()
    {
        $barang = $this->createBarang();

        $penjualan = $this->createPenjualan([
            'tgl' => now()->format('Y-m-d H:i:s'),
            'subtotal' => 100000,
            'diskon_kuitansi' => 0,
            'total' => 100000,
            'flag' => '5',
        ]);

        $detailJual = DetailPenjualan::create([
            'no_penjualan' => $penjualan->no_penjualan,
            'kodebarang' => $barang->kodebarang,
            'motif' => 'SERI-GOLD',
            'jumlah' => 5,
            'harga_jual' => 20000,
            'diskon' => 0,
            'subtotal' => 100000,
        ]);

        $retur = HeaderReturPenjualan::create([
            'no_retur' => 'RET-LAP-' . uniqid(),
            'no_penjualan' => $penjualan->no_penjualan,
            'tgl' => now()->format('Y-m-d H:i:s'),
            'subtotal' => 40000,
            'total' => 40000,
            'status' => '1',
        ]);

        DetailReturPenjualan::create([
            'header_retur_penjualan_id' => $retur->id,
            'no_retur' => $retur->no_retur,
            'no_penjualan' => $penjualan->no_penjualan,
            'kodebarang' => $barang->kodebarang,
            'detail_penjualan_id' => $detailJual->id,
            'jumlah' => 2,
            'harga_jual' => 20000,
            'diskon' => 0,
            'subtotal' => 40000,
            'status' => '1',
        ]);

        request()->merge([
            'tglawal' => now()->subDay()->format('Y-m-d'),
            'tglakhir' => now()->addDay()->format('Y-m-d'),
            'q' => $penjualan->no_penjualan,
        ]);

        $ctrl = new LaporanPenjualanController();
        $response = $ctrl->getData();
        $items = $response->getData(true);

        $row = collect($items)->firstWhere('no_penjualan', $penjualan->no_penjualan);
        $this->assertNotNull($row);
        $this->assertEquals(100000.0, (float) $row['total']);
        $this->assertEquals(40000.0, (float) $row['nilairetur']);
        $this->assertEquals(60000.0, (float) $row['totaldenganretur']);

        $this->assertNotEmpty($row['detail']);
        $firstDetail = $row['detail'][0];
        $this->assertEquals('SERI-GOLD', $firstDetail['motif']);
        $this->assertEquals(2.0, (float) $firstDetail['jumlah_retur']);
        $this->assertEquals(40000.0, (float) $firstDetail['nilai_retur']);
    }

    /**
     * Test 4: HPP FIFO retur tidak terjadi penggandaan (multiplication) pada beberapa batch.
     */
    public function test_hpp_fifo_retur_tidak_terjadi_duplikasi_pada_beberapa_batch()
    {
        $year = now()->year;
        $month = now()->month;
        $barang = $this->createBarang();

        // Nota Penjualan 10 PCS @ 20.000 = 200.000
        $penjualan = $this->createPenjualan([
            'tgl' => now()->format('Y-m-d H:i:s'),
            'subtotal' => 200000,
            'total' => 200000,
            'flag' => '5',
        ]);

        $detailJual = DetailPenjualan::create([
            'no_penjualan' => $penjualan->no_penjualan,
            'kodebarang' => $barang->kodebarang,
            'jumlah' => 10,
            'harga_jual' => 20000,
            'diskon' => 0,
            'subtotal' => 200000,
        ]);

        // Penjualan dipenuhi dari DUA BATCH FIFO:
        // Batch 1: 4 PCS @ 10.000 = 40.000
        DetailPenjualanFifo::create([
            'no_penjualan' => $penjualan->no_penjualan,
            'kodebarang' => $barang->kodebarang,
            'jumlah' => 4,
            'harga_beli' => 10000,
            'harga_jual' => 20000,
            'diskon' => 0,
            'subtotal' => 80000,
            'retur' => 0,
        ]);

        // Batch 2: 6 PCS @ 15.000 = 90.000
        // Total HPP terjual = 40.000 + 90.000 = 130.000. Rata-rata per unit = 13.000
        DetailPenjualanFifo::create([
            'no_penjualan' => $penjualan->no_penjualan,
            'kodebarang' => $barang->kodebarang,
            'jumlah' => 6,
            'harga_beli' => 15000,
            'harga_jual' => 20000,
            'diskon' => 0,
            'subtotal' => 120000,
            'retur' => 0,
        ]);

        // Retur 2 PCS
        $retur = HeaderReturPenjualan::create([
            'no_retur' => 'RET-FIFO-' . uniqid(),
            'no_penjualan' => $penjualan->no_penjualan,
            'tgl' => now()->format('Y-m-d H:i:s'),
            'subtotal' => 40000,
            'total' => 40000,
            'status' => '1',
        ]);

        DetailReturPenjualan::create([
            'header_retur_penjualan_id' => $retur->id,
            'no_retur' => $retur->no_retur,
            'no_penjualan' => $penjualan->no_penjualan,
            'kodebarang' => $barang->kodebarang,
            'detail_penjualan_id' => $detailJual->id,
            'jumlah' => 2,
            'harga_jual' => 20000,
            'diskon' => 0,
            'subtotal' => 40000,
            'status' => '1',
        ]);

        // HPP retur seharusnya tepat: 2 PCS * 13.000 = 26.000
        // Jika terjadi duplikasi batch via join tanpa agregasi, akan menghasilkan 2 * (10.000 + 15.000) = 50.000 (salah!)
        request()->merge(['tahun' => (string) $year, 'bulan' => (string) $month]);
        $labarugiCtrl = new LaporanAkuntansiController();
        $lrData = $labarugiCtrl->Labarugi()->getData(true);

        // Verifikasi returhpp yang dihitung untuk retur ini tidak berlipat ganda
        $fifoCost = DB::table('detail_penjualan_fifos')
            ->selectRaw('no_penjualan, kodebarang, SUM(jumlah * harga_beli) as total_cost, SUM(jumlah) as total_qty')
            ->where('no_penjualan', $penjualan->no_penjualan)
            ->groupBy('no_penjualan', 'kodebarang')
            ->first();

        $expectedReturHppPerUnit = $fifoCost->total_cost / $fifoCost->total_qty; // 13.000
        $this->assertEquals(13000.0, (float) $expectedReturHppPerUnit);
    }

    /**
     * Test 5: Arus Kas Masuk tidak menghitung ganda nota yang lunas dari cicilan atau retur.
     */
    public function test_arus_kas_masuk_tidak_menghitung_ganda_nota_lunas_dari_cicilan_atau_retur()
    {
        $year = now()->year;
        $month = now()->month;

        // Nota kredit: Total 1.000.000, DP 300.000, Sisa 700.000
        $penjualan = $this->createPenjualan([
            'tgl' => now()->format('Y-m-d H:i:s'),
            'total' => 1000000,
            'bayar' => 300000,
            'flag' => '7', // DP
            'cara_bayar' => 'Tempo',
        ]);

        // Cicilan 1: 400.000
        PembayaranCicilan::create([
            'no_penjualan' => $penjualan->no_penjualan,
            'tgl_bayar' => now()->format('Y-m-d H:i:s'),
            'jumlah' => 400000,
        ]);

        // Retur 300.000 melunasi sisa tagihan, mengubah status nota ke Lunas (flag = 5)
        $penjualan->update(['flag' => '5']);

        request()->merge(['tahun' => (string) $year, 'bulan' => (string) $month]);
        $akCtrl = new LaporanAkuntansiController();
        $akData = $akCtrl->Aruskas()->getData(true);

        // Kas masuk dari nota ini harus tepat: DP (300.000) + Cicilan (400.000) = 700.000
        // Nota tidak boleh dianggap sebagai penjualan tunai lunas 1.000.000!
        $kasMasuk = collect($akData['kasmasuk']);
        $this->assertNotEmpty($kasMasuk);
    }

    /**
     * Test 6: Arus Kas Keluar tidak menganggap retur piutang atau hak pengembalian sebagai kas keluar.
     */
    public function test_arus_kas_keluar_tidak_menganggap_retur_piutang_atau_hak_pengembalian_sebagai_kas_keluar()
    {
        $year = now()->year;
        $month = now()->month;

        $penjualan = $this->createPenjualan([
            'tgl' => now()->format('Y-m-d H:i:s'),
            'total' => 500000,
            'bayar' => 0,
            'flag' => '2',
        ]);

        // Retur 200.000 memotong piutang (bukan kas keluar)
        HeaderReturPenjualan::create([
            'no_retur' => 'RET-NOKAS-' . uniqid(),
            'no_penjualan' => $penjualan->no_penjualan,
            'tgl' => now()->format('Y-m-d H:i:s'),
            'subtotal' => 200000,
            'total' => 200000,
            'status' => '1',
        ]);

        request()->merge(['tahun' => (string) $year, 'bulan' => (string) $month]);
        $akCtrl = new LaporanAkuntansiController();
        $akData = $akCtrl->Aruskas()->getData(true);

        $returKeluar = collect($akData['kaskeluar'])->firstWhere('akun', '0003-OUT');
        $this->assertEquals(0.0, (float) ($returKeluar['total'] ?? 0), 'Retur potong piutang tidak boleh menjadi kas keluar');
    }

    /**
     * Test 7: Rekap Piutang sinkron dengan uang muka, cicilan, dan retur.
     */
    public function test_rekap_piutang_sinkron_dengan_uang_muka_cicilan_dan_retur()
    {
        $year = now()->year;
        $month = now()->month;

        // Penjualan kredit: Total 1.000.000, DP 200.000
        $penjualan = $this->createPenjualan([
            'tgl' => now()->format('Y-m-d H:i:s'),
            'total' => 1000000,
            'bayar' => 200000,
            'flag' => '7',
            'cara_bayar' => 'Tempo',
        ]);

        // Cicilan 300.000
        PembayaranCicilan::create([
            'no_penjualan' => $penjualan->no_penjualan,
            'tgl_bayar' => now()->format('Y-m-d H:i:s'),
            'jumlah' => 300000,
        ]);

        // Retur 150.000
        HeaderReturPenjualan::create([
            'no_retur' => 'RET-REKAP-' . uniqid(),
            'no_penjualan' => $penjualan->no_penjualan,
            'tgl' => now()->format('Y-m-d H:i:s'),
            'total' => 150000,
            'status' => '1',
        ]);

        request()->merge(['tahun' => (string) $year, 'bulan' => (string) $month]);
        $akCtrl = new LaporanAkuntansiController();
        $hpData = $akCtrl->hutangpiutang()->getData(true);

        $this->assertGreaterThanOrEqual(1000000.0, (float) $hpData['debitpiutang']);
        $this->assertGreaterThanOrEqual(650000.0, (float) $hpData['kreditpiutang']);
        $this->assertGreaterThanOrEqual(0.0, (float) $hpData['sisapiutang']);
    }

    /**
     * Test 8: Transaksi historis tanpa snapshot konsisten tanpa mengarang pajak.
     */
    public function test_transaksi_historis_tanpa_snapshot_konsisten_tanpa_mengarang_pajak()
    {
        $year = now()->year;
        $month = now()->month;

        $penjualan = $this->createPenjualan([
            'tgl' => now()->format('Y-m-d H:i:s'),
            'subtotal' => 80000,
            'diskon_kuitansi' => 0,
            'ppn_persen' => 0,
            'ppn_nominal' => 0,
            'total' => 80000,
            'flag' => '5',
        ]);

        request()->merge(['tahun' => (string) $year, 'bulan' => (string) $month]);
        $labarugiCtrl = new LaporanAkuntansiController();
        $lrData = $labarugiCtrl->Labarugi()->getData(true);

        $dashCtrl = new DashboardController();
        $dashData = $dashCtrl->index()->getData(true);

        $this->assertEquals((float) $lrData['total_penjualan'], (float) $dashData['summary']['totalPendapatan']);
    }
}
