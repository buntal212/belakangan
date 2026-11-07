<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\Laporan\LaporanAkuntansiController;
use App\Http\Controllers\Api\Laporan\LaporanPembayaranPiutangController;
use App\Http\Controllers\Api\Transaksi\PembayaranPiutang\PembayaranPiutangController;
use App\Http\Controllers\Api\Transaksi\Penjualan\CicilanController;
use App\Models\Pelanggan;
use App\Models\Transaksi\Penjualan\HeaderCicilan;
use App\Models\Transaksi\Penjualan\HeaderPenjualan;
use App\Models\Transaksi\Penjualan\HeaderReturPenjualan;
use App\Models\Transaksi\Penjualan\PembayaranCicilan;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PiutangPembayaranPelangganTest extends TestCase
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
            'nama' => 'Test User ' . uniqid(),
            'email' => 'test_' . uniqid() . '@example.com',
            'password' => bcrypt('password'),
        ]);
        $this->userId = $user->id;
        Auth::loginUsingId($this->userId);

        $pelanggan = Pelanggan::create([
            'nama' => 'Pelanggan Test ' . uniqid(),
            'alamat' => 'Jl. Test No. 123',
            'telepon' => '081234567890',
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
        $noPenjualan = 'TEST-' . uniqid() . '-PJL';
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

    /**
     * Skenario 1: DP Rp0 (Tempo Murni, flag = 2)
     */
    public function test_dp_rp0_saldo_piutang_penuh()
    {
        $penjualan = $this->createPenjualan([
            'total' => 500000,
            'bayar' => 0,
            'flag' => '2',
        ]);

        $ctrl = new PembayaranPiutangController();
        $response = $ctrl->riwayatNota($penjualan->no_penjualan);
        $data = json_decode($response->getContent(), true)['data'];

        $this->assertEquals(500000, $data['total_nota']);
        $this->assertEquals(0, $data['total_dibayar_nota']);
        $this->assertEquals(500000, $data['sisa_piutang_nota']);
    }

    /**
     * Skenario 2: DP sebagian (flag = 7)
     */
    public function test_dp_sebagian_mengurangi_piutang_awal()
    {
        $penjualan = $this->createPenjualan([
            'total' => 1000000,
            'bayar' => 300000,
            'flag' => '7',
            'cara_bayar' => 'DP',
        ]);

        $ctrl = new PembayaranPiutangController();
        $response = $ctrl->riwayatNota($penjualan->no_penjualan);
        $data = json_decode($response->getContent(), true)['data'];

        $this->assertEquals(1000000, $data['total_nota']);
        $this->assertEquals(300000, $data['total_dibayar_nota']);
        $this->assertEquals(700000, $data['sisa_piutang_nota']);
    }

    /**
     * Skenario 3: Transaksi Tempo khusus (flag = 8) muncul di listpiutang
     */
    public function test_transaksi_tempo_khusus_flag_8_muncul_di_listpiutang()
    {
        $penjualan = $this->createPenjualan([
            'total' => 800000,
            'bayar' => 0,
            'flag' => '8',
        ]);

        request()->merge(['pelanggan_id' => (string) $this->pelangganId]);
        $ctrl = new PembayaranPiutangController();
        $response = $ctrl->listpiutang();
        $items = json_decode($response->getContent(), true);

        $found = collect($items)->firstWhere('no_penjualan', $penjualan->no_penjualan);
        $this->assertNotNull($found, 'Transaksi flag 8 harus muncul di listpiutang');
        $this->assertEquals(800000, $found['total']);
    }

    /**
     * Skenario 4: Cicilan berulang mengurangi piutang bertahap dan mengubah flag ke 3
     */
    public function test_cicilan_berulang_mengurangi_piutang_bertahap()
    {
        $penjualan = $this->createPenjualan([
            'total' => 1000000,
            'bayar' => 0,
            'flag' => '2',
        ]);

        $ctrl = new PembayaranPiutangController();

        // Cicilan ke-1: Rp 300.000
        $req1 = new Request([
            'nopenjualan' => $penjualan->no_penjualan,
            'total' => 300000,
            'pelanggan_id' => $this->pelangganId,
            'tgl' => now()->format('Y-m-d'),
            'carabayar' => 'Cash',
            'keterangan' => 'Cicilan 1',
        ]);
        $res1 = $ctrl->simpan($req1);
        $this->assertEquals(200, $res1->getStatusCode());

        $penjualan->refresh();
        $this->assertEquals('3', $penjualan->flag, 'Flag harus menjadi 3 (Proses Cicilan)');

        // Cicilan ke-2: Rp 400.000
        $req2 = new Request([
            'nopenjualan' => $penjualan->no_penjualan,
            'total' => 400000,
            'pelanggan_id' => $this->pelangganId,
            'tgl' => now()->format('Y-m-d'),
            'carabayar' => 'Transfer',
            'keterangan' => 'Cicilan 2',
        ]);
        $res2 = $ctrl->simpan($req2);
        $this->assertEquals(200, $res2->getStatusCode());

        $riwayat = json_decode($ctrl->riwayatNota($penjualan->no_penjualan)->getContent(), true)['data'];
        $this->assertEquals(700000, $riwayat['total_dibayar_nota']);
        $this->assertEquals(300000, $riwayat['sisa_piutang_nota']);
    }

    /**
     * Skenario 5: Pelunasan penuh mengubah flag menjadi 5 (Lunas)
     */
    public function test_pelunasan_penuh_mengubah_flag_menjadi_lunas()
    {
        $penjualan = $this->createPenjualan([
            'total' => 500000,
            'bayar' => 200000,
            'flag' => '7',
        ]);

        $ctrl = new PembayaranPiutangController();
        $reqPelunasan = new Request([
            'nopenjualan' => $penjualan->no_penjualan,
            'total' => 300000,
            'pelanggan_id' => $this->pelangganId,
            'tgl' => now()->format('Y-m-d'),
            'carabayar' => 'Cash',
            'keterangan' => 'Pelunasan sisa',
        ]);
        $res = $ctrl->simpan($reqPelunasan);
        $this->assertEquals(200, $res->getStatusCode());

        $penjualan->refresh();
        $this->assertEquals('5', $penjualan->flag, 'Flag harus menjadi 5 (Lunas)');

        $riwayat = json_decode($ctrl->riwayatNota($penjualan->no_penjualan)->getContent(), true)['data'];
        $this->assertEquals(0, $riwayat['sisa_piutang_nota']);
    }

    /**
     * Skenario 6: Retur sebagian mengurangi sisa piutang aktual
     */
    public function test_retur_sebagian_mengurangi_sisa_piutang()
    {
        $penjualan = $this->createPenjualan([
            'total' => 1000000,
            'bayar' => 0,
            'flag' => '2',
        ]);

        // Retur selesai Rp 250.000
        HeaderReturPenjualan::create([
            'no_retur' => 'RTR-' . uniqid(),
            'no_penjualan' => $penjualan->no_penjualan,
            'tgl' => now()->format('Y-m-d H:i:s'),
            'status' => '1',
            'total' => 250000,
        ]);

        $ctrl = new PembayaranPiutangController();
        $riwayat = json_decode($ctrl->riwayatNota($penjualan->no_penjualan)->getContent(), true)['data'];

        $this->assertEquals(1000000, $riwayat['total_nota']);
        $this->assertEquals(250000, $riwayat['total_retur_nota']);
        $this->assertEquals(750000, $riwayat['sisa_piutang_nota']);
    }

    /**
     * Skenario 7: Retur penuh melunasi nota piutang
     */
    public function test_retur_penuh_melunasi_nota_piutang()
    {
        $penjualan = $this->createPenjualan([
            'total' => 400000,
            'bayar' => 100000,
            'flag' => '7',
        ]);

        // Retur selesai senilai sisa hutang Rp 300.000
        $retur = HeaderReturPenjualan::create([
            'no_retur' => 'RTR-' . uniqid(),
            'no_penjualan' => $penjualan->no_penjualan,
            'tgl' => now()->format('Y-m-d H:i:s'),
            'status' => '1',
            'total' => 300000,
        ]);

        $ctrl = new PembayaranPiutangController();
        $riwayat = json_decode($ctrl->riwayatNota($penjualan->no_penjualan)->getContent(), true)['data'];

        $this->assertEquals(0, $riwayat['sisa_piutang_nota']);
    }

    /**
     * Skenario 8: Validasi pembayaran berlebih (overpayment protection)
     */
    public function test_validasi_pembayaran_berlebih_overpayment()
    {
        $penjualan = $this->createPenjualan([
            'total' => 500000,
            'bayar' => 200000,
            'flag' => '7',
        ]);
        // Sisa piutang aktual = Rp 300.000

        $ctrl = new PembayaranPiutangController();
        $reqOver = new Request([
            'nopenjualan' => $penjualan->no_penjualan,
            'total' => 350000, // Melebihi 300.000
            'pelanggan_id' => $this->pelangganId,
            'tgl' => now()->format('Y-m-d'),
            'carabayar' => 'Cash',
        ]);

        $res = $ctrl->simpan($reqOver);
        $this->assertEquals(422, $res->getStatusCode(), 'Harus menolak overpayment dengan HTTP 422');
        $body = json_decode($res->getContent(), true);
        $this->assertStringContainsString('melebihi sisa piutang', $body['message']);
    }

    /**
     * Skenario 9: Laporan Pembayaran Piutang mengecualikan transaksi tunai kasir biasa
     */
    public function test_laporan_pembayaran_piutang_mengecualikan_tunai_kasir_biasa()
    {
        // Transaksi tunai kasir biasa (flag 5, bayar = total)
        $kasirTunai = $this->createPenjualan([
            'total' => 150000,
            'bayar' => 150000,
            'flag' => '5',
            'cara_bayar' => 'cash',
        ]);

        // Transaksi DP (flag 7, bayar 200rb dari 500rb)
        $transDP = $this->createPenjualan([
            'total' => 500000,
            'bayar' => 200000,
            'flag' => '7',
            'cara_bayar' => 'cash',
        ]);

        $ctrl = new LaporanPembayaranPiutangController();
        $req = new Request([
            'pelanggan_id' => $this->pelangganId,
        ]);

        $res = $ctrl->getData($req);
        $data = json_decode($res->getContent(), true)['data'];

        $notaTunaiFound = collect($data)->firstWhere('no_penjualan', $kasirTunai->no_penjualan);
        $this->assertNull($notaTunaiFound, 'Transaksi kasir tunai lunas tidak boleh masuk ke Laporan Pembayaran Piutang');

        $notaDPFound = collect($data)->firstWhere('no_penjualan', $transDP->no_penjualan);
        $this->assertNotNull($notaDPFound, 'Transaksi DP harus masuk ke Laporan Pembayaran Piutang');
        $this->assertEquals(200000, $notaDPFound['jumlah']);
    }

    /**
     * Skenario 10: Konsistensi saldo piutang antar modul
     */
    public function test_konsistensi_saldo_piutang_antar_modul()
    {
        $penjualan = $this->createPenjualan([
            'total' => 1000000,
            'bayar' => 200000,
            'flag' => '7',
            'tempo' => '2000-01-01',
        ]);

        // Retur selesai Rp 100.000
        HeaderReturPenjualan::create([
            'no_retur' => 'RTR-' . uniqid(),
            'no_penjualan' => $penjualan->no_penjualan,
            'tgl' => now()->format('Y-m-d H:i:s'),
            'status' => '1',
            'total' => 100000,
        ]);

        // Cicilan Rp 300.000
        $headCicilan = HeaderCicilan::create([
            'nopembayaran' => 'TEST-BP-' . uniqid(),
            'pelanggan_id' => $this->pelangganId,
            'tgl_bayar' => now()->format('Y-m-d H:i:s'),
            'cara_bayar' => 'Transfer',
            'jumlah' => 300000,
        ]);
        PembayaranCicilan::create([
            'no_penjualan' => $penjualan->no_penjualan,
            'header_ciclan_id' => $headCicilan->id,
            'jumlah' => 300000,
            'tgl_bayar' => now()->format('Y-m-d H:i:s'),
        ]);

        // Expected remaining debt: 1.000.000 - 200.000 (DP) - 100.000 (retur) - 300.000 (cicilan) = 400.000
        $expectedSisa = 400000.0;

        // 1. Modul Riwayat Nota
        $ctrlPiutang = new PembayaranPiutangController();
        $riwayat = json_decode($ctrlPiutang->riwayatNota($penjualan->no_penjualan)->getContent(), true)['data'];
        $this->assertEquals($expectedSisa, (float) $riwayat['sisa_piutang_nota'], 'Sisa piutang di riwayatNota harus 400.000');

        // 2. Modul Dashboard Piutang Jatuh Tempo
        $ctrlDashboard = new DashboardController();
        $dashboardRes = json_decode($ctrlDashboard->index()->getContent(), true);
        $piutangJatuhTempo = $dashboardRes['jatuhTempo']['piutang'];
        $foundDashboard = collect($piutangJatuhTempo)->firstWhere('no_penjualan', $penjualan->no_penjualan);
        $this->assertNotNull($foundDashboard, 'Nota harus ditemukan di Dashboard jatuh tempo');
        $this->assertEquals($expectedSisa, (float) $foundDashboard['sisa'], 'Sisa piutang di Dashboard harus 400.000');

        // 3. Modul Laporan Pembayaran Piutang
        $ctrlLapPiutang = new LaporanPembayaranPiutangController();
        $lapRes = json_decode($ctrlLapPiutang->getData(new Request(['no_penjualan' => $penjualan->no_penjualan]))->getContent(), true)['data'];
        $this->assertNotEmpty($lapRes);
        $this->assertEquals($expectedSisa, (float) $lapRes[0]['sisa_piutang_nota'], 'Sisa piutang di Laporan Pembayaran Piutang harus 400.000');
    }
}
