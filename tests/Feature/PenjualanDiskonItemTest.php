<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\Transaksi\Penjualan\PenjualanController;
use App\Models\Transaksi\Penjualan\DetailPenjualan;
use App\Models\Transaksi\Penjualan\HeaderPenjualan;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PenjualanDiskonItemTest extends TestCase
{
    protected string $kodeBarang;
    protected ?int $salesId;
    protected User $user;
    protected PenjualanController $controller;

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

        $this->user = User::first() ?? User::factory()->create();
        $this->salesId = $this->user->id;
        $this->controller = app(PenjualanController::class);

        $this->kodeBarang = 'TEST-BRG-' . uniqid();
        DB::table('barangs')->insert([
            'kodebarang' => $this->kodeBarang,
            'namabarang' => 'Keramik Test Diskon Item',
            'namagabung' => 'Keramik Test Diskon Item',
            'slug' => 'keramik-test-diskon-item-' . uniqid(),
            'satuan_b' => 'DUS',
            'satuan_k' => 'PCS',
            'isi' => 10,
            'hargajual1' => 6500,
            'hargajual2' => 6500,
            'hargabeli' => 5000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function tearDown(): void
    {
        DB::rollBack();
        parent::tearDown();
    }

    /**
     * Test A — Satuan Besar:
     * SB = 2, Harga = Rp65.000, Diskon = Rp1.000.
     * Total diskon = Rp2.000.
     * Subtotal = Rp128.000.
     */
    public function test_a_satuan_besar(): void
    {
        $payload = [
            'kodebarang' => $this->kodeBarang,
            'satuan' => 'SB',
            'jumlah_b' => 2,
            'jumlah_k' => 0,
            'isi' => 10,
            'harga_jual' => 6500,
            'harga_jual_b' => 65000,
            'diskon' => 1000,
            'sales_id' => $this->salesId,
        ];

        $response = $this->controller->simpanDetail(new Request($payload));
        $data = json_decode($response->getContent(), true);

        $this->assertNotNull($data['nota']);
        $nota = $data['nota'];

        $detail = DetailPenjualan::where('no_penjualan', $nota)
            ->where('kodebarang', $this->kodeBarang)
            ->first();

        $this->assertNotNull($detail);
        $this->assertEquals(20, (float) $detail->jumlah, 'Jumlah allSK harus 2 dus x 10 = 20 pcs');
        $this->assertEquals(2000, (float) $detail->diskon, 'Total diskon baris harus 2 x 1.000 = Rp2.000');
        $this->assertEquals(128000, (float) $detail->subtotal, 'Subtotal harus (2 x 65.000) - 2.000 = Rp128.000');

        $header = HeaderPenjualan::where('no_penjualan', $nota)->first();
        $this->assertNotNull($header);
        $this->assertEquals(2000, (float) $header->total_diskon);
        $this->assertEquals(128000, (float) $header->subtotal);
    }

    /**
     * Test B — Satuan Kecil:
     * SK = 5, Harga = Rp6.500, Diskon = Rp200.
     * Total diskon = Rp1.000.
     * Subtotal = Rp31.500.
     */
    public function test_b_satuan_kecil(): void
    {
        $payload = [
            'kodebarang' => $this->kodeBarang,
            'satuan' => 'SK',
            'jumlah_b' => 0,
            'jumlah_k' => 5,
            'isi' => 10,
            'harga_jual' => 6500,
            'diskon' => 200,
            'sales_id' => $this->salesId,
        ];

        $response = $this->controller->simpanDetail(new Request($payload));
        $data = json_decode($response->getContent(), true);

        $this->assertNotNull($data['nota']);
        $nota = $data['nota'];

        $detail = DetailPenjualan::where('no_penjualan', $nota)
            ->where('kodebarang', $this->kodeBarang)
            ->first();

        $this->assertNotNull($detail);
        $this->assertEquals(5, (float) $detail->jumlah, 'Jumlah allSK harus 5 pcs');
        $this->assertEquals(1000, (float) $detail->diskon, 'Total diskon baris harus 5 x 200 = Rp1.000');
        $this->assertEquals(31500, (float) $detail->subtotal, 'Subtotal harus (5 x 6.500) - 1.000 = Rp31.500');

        $header = HeaderPenjualan::where('no_penjualan', $nota)->first();
        $this->assertNotNull($header);
        $this->assertEquals(1000, (float) $header->total_diskon);
        $this->assertEquals(31500, (float) $header->subtotal);
    }

    /**
     * Test C — Diskon Nol:
     * Diskon = 0.
     * Subtotal tetap jumlah x harga.
     */
    public function test_c_diskon_nol(): void
    {
        $payload = [
            'kodebarang' => $this->kodeBarang,
            'satuan' => 'SB',
            'jumlah_b' => 2,
            'jumlah_k' => 0,
            'isi' => 10,
            'harga_jual' => 6500,
            'harga_jual_b' => 65000,
            'diskon' => 0,
            'sales_id' => $this->salesId,
        ];

        $response = $this->controller->simpanDetail(new Request($payload));
        $data = json_decode($response->getContent(), true);

        $nota = $data['nota'];
        $detail = DetailPenjualan::where('no_penjualan', $nota)->first();

        $this->assertEquals(0, (float) $detail->diskon, 'Diskon tersimpan harus 0');
        $this->assertEquals(130000, (float) $detail->subtotal, 'Subtotal harus 2 x 65.000 = Rp130.000');
    }

    /**
     * Test D — Diskon Melebihi Harga:
     * Diskon per satuan lebih besar daripada harga satuan.
     * Transaksi harus ditolak tanpa menyimpan data.
     */
    public function test_d_diskon_melebihi_harga(): void
    {
        $payload = [
            'kodebarang' => $this->kodeBarang,
            'satuan' => 'SB',
            'jumlah_b' => 2,
            'jumlah_k' => 0,
            'isi' => 10,
            'harga_jual' => 6500,
            'harga_jual_b' => 65000,
            'diskon' => 70000, // Melebihi harga SB 65.000
            'sales_id' => $this->salesId,
        ];

        $this->expectException(ValidationException::class);

        try {
            $this->controller->simpanDetail(new Request($payload));
        } finally {
            // Pastikan tidak ada data tersimpan
            $detailCount = DetailPenjualan::where('kodebarang', $this->kodeBarang)->count();
            $this->assertEquals(0, $detailCount, 'Data detail tidak boleh tersimpan saat validasi gagal');
        }
    }

    /**
     * Test E — Transaksi Lama:
     * Nilai diskon dan subtotal transaksi historis tidak berubah.
     */
    public function test_e_transaksi_lama(): void
    {
        $notaHistoris = 'HISTORIS-' . uniqid();

        // Data transaksi lama yang sudah tersimpan di database
        DB::table('header_penjualans')->insert([
            'no_penjualan' => $notaHistoris,
            'tgl' => '2025-01-10 10:00:00',
            'subtotal' => 250000,
            'total_diskon' => 10000,
            'total' => 250000,
            'flag' => '5',
            'created_at' => '2025-01-10 10:00:00',
            'updated_at' => '2025-01-10 10:00:00',
        ]);

        DB::table('detail_penjualans')->insert([
            'no_penjualan' => $notaHistoris,
            'kodebarang' => $this->kodeBarang,
            'jumlah' => 20,
            'isi' => 10,
            'harga_jual' => 13000,
            'diskon' => 10000,
            'subtotal' => 250000,
            'created_at' => '2025-01-10 10:00:00',
            'updated_at' => '2025-01-10 10:00:00',
        ]);

        // Verifikasi pembacaan data historis tetap konsisten
        $header = HeaderPenjualan::where('no_penjualan', $notaHistoris)->first();
        $detail = DetailPenjualan::where('no_penjualan', $notaHistoris)->first();

        $this->assertNotNull($header);
        $this->assertNotNull($detail);
        $this->assertEquals(10000, (float) $detail->diskon, 'Diskon historis detail tidak boleh berubah');
        $this->assertEquals(250000, (float) $detail->subtotal, 'Subtotal historis detail tidak boleh berubah');
        $this->assertEquals(10000, (float) $header->total_diskon, 'Diskon historis header tidak boleh berubah');
        $this->assertEquals(250000, (float) $header->total, 'Total historis header tidak boleh berubah');
    }

    /**
     * Test F — Dua Baris:
     * Produk sama dijual sebagai SB pada baris pertama dan SK pada baris kedua.
     * Diskon masing-masing dihitung berdasarkan satuan barisnya.
     */
    public function test_f_dua_baris(): void
    {
        // Baris 1: SB = 2 dus, harga = 65.000, diskon = 1.000
        $payload1 = [
            'kodebarang' => $this->kodeBarang,
            'satuan' => 'SB',
            'jumlah_b' => 2,
            'jumlah_k' => 0,
            'isi' => 10,
            'harga_jual' => 6500,
            'harga_jual_b' => 65000,
            'diskon' => 1000,
            'sales_id' => $this->salesId,
        ];

        $res1 = $this->controller->simpanDetail(new Request($payload1));
        $data1 = json_decode($res1->getContent(), true);
        $nota = $data1['nota'];

        // Baris 2: SK = 5 pcs, harga = 6.500, diskon = 200 pada nota yang sama
        $payload2 = [
            'nota' => $nota,
            'kodebarang' => $this->kodeBarang,
            'satuan' => 'SK',
            'jumlah_b' => 0,
            'jumlah_k' => 5,
            'isi' => 10,
            'harga_jual' => 6500,
            'diskon' => 200,
            'sales_id' => $this->salesId,
        ];

        $res2 = $this->controller->simpanDetail(new Request($payload2));
        $data2 = json_decode($res2->getContent(), true);

        $details = DetailPenjualan::where('no_penjualan', $nota)
            ->where('kodebarang', $this->kodeBarang)
            ->orderBy('id', 'asc')
            ->get();

        $this->assertCount(2, $details, 'Harus ada 2 baris detail terpisah');

        // Baris 1 (SB)
        $this->assertEquals(20, (float) $details[0]->jumlah);
        $this->assertEquals(2000, (float) $details[0]->diskon, 'Baris 1 diskon harus 2 x 1.000 = Rp2.000');
        $this->assertEquals(128000, (float) $details[0]->subtotal, 'Baris 1 subtotal harus Rp128.000');

        // Baris 2 (SK)
        $this->assertEquals(5, (float) $details[1]->jumlah);
        $this->assertEquals(1000, (float) $details[1]->diskon, 'Baris 2 diskon harus 5 x 200 = Rp1.000');
        $this->assertEquals(31500, (float) $details[1]->subtotal, 'Baris 2 subtotal harus Rp31.500');

        // Header akumulasi
        $header = HeaderPenjualan::where('no_penjualan', $nota)->first();
        $this->assertEquals(3000, (float) $header->total_diskon, 'Total diskon header harus 2.000 + 1.000 = Rp3.000');
        $this->assertEquals(159500, (float) $header->subtotal, 'Subtotal header harus 128.000 + 31.500 = Rp159.500');
    }

    /**
     * Test G — Regression Test: 10 pcs dengan isi 10 harus tetap SK (bukan 1 dus SB).
     * Input kasir: Jumlah (SK) = 10, Diskon = Rp200/pcs.
     * Total diskon = 10 x 200 = Rp2.000.
     * Subtotal = (10 x 6.500) - 2.000 = Rp63.000.
     */
    public function test_g_sepuluh_pcs_dengan_isi_sepuluh_tetap_sk(): void
    {
        $payload = [
            'kodebarang' => $this->kodeBarang,
            'satuan' => 'SK',
            'jumlah_b' => 0,
            'jumlah_k' => 10,
            'isi' => 10,
            'harga_jual' => 6500,
            'diskon' => 200,
            'sales_id' => $this->salesId,
        ];

        $response = $this->controller->simpanDetail(new Request($payload));
        $data = json_decode($response->getContent(), true);

        $this->assertNotNull($data['nota']);
        $nota = $data['nota'];

        $detail = DetailPenjualan::where('no_penjualan', $nota)
            ->where('kodebarang', $this->kodeBarang)
            ->first();

        $this->assertNotNull($detail);
        $this->assertEquals(10, (float) $detail->jumlah, 'Jumlah allSK harus 10 pcs');
        $this->assertEquals(2000, (float) $detail->diskon, 'Total diskon harus 10 x Rp200 = Rp2.000, bukan Rp200');
        $this->assertEquals(63000, (float) $detail->subtotal, 'Subtotal harus (10 x 6.500) - 2.000 = Rp63.000');

        $header = HeaderPenjualan::where('no_penjualan', $nota)->first();
        $this->assertEquals(2000, (float) $header->total_diskon);
        $this->assertEquals(63000, (float) $header->subtotal);
    }

    /**
     * Test H — Regression Test: Payload legacy tanpa satuan dengan jumlah = 10 dan isi = 10 tetap SK.
     * Tidak boleh menebak SB menggunakan fmod kelipatan kemasan!
     */
    public function test_h_fallback_tanpa_satuan_sepuluh_pcs_dengan_isi_sepuluh_tetap_sk(): void
    {
        $payload = [
            'kodebarang' => $this->kodeBarang,
            'jumlah' => 10,
            'isi' => 10,
            'harga_jual' => 6500,
            'diskon' => 200,
            'sales_id' => $this->salesId,
            // Perhatikan: tidak mengirim satuan, jumlah_b, atau jumlah_k
        ];

        $response = $this->controller->simpanDetail(new Request($payload));
        $data = json_decode($response->getContent(), true);

        $this->assertNotNull($data['nota']);
        $nota = $data['nota'];

        $detail = DetailPenjualan::where('no_penjualan', $nota)
            ->where('kodebarang', $this->kodeBarang)
            ->first();

        $this->assertNotNull($detail);
        $this->assertEquals(10, (float) $detail->jumlah, 'Jumlah allSK harus 10 pcs');
        $this->assertEquals(2000, (float) $detail->diskon, 'Total diskon fallback harus 10 x Rp200 = Rp2.000 (SK)');
        $this->assertEquals(63000, (float) $detail->subtotal, 'Subtotal harus Rp63.000 (SK), bukan Rp64.800 (SB)');
    }

    /**
     * Test I — 1 dus tetap SB:
     * Input kasir: Jumlah (SB) = 1, isi = 10, Harga SB = Rp65.000, Diskon = Rp1.000/dus.
     * Total diskon = 1 x 1.000 = Rp1.000.
     * Subtotal = (1 x 65.000) - 1.000 = Rp64.000.
     */
    public function test_i_satu_dus_tetap_sb(): void
    {
        $payload = [
            'kodebarang' => $this->kodeBarang,
            'satuan' => 'SB',
            'jumlah_b' => 1,
            'jumlah_k' => 0,
            'isi' => 10,
            'harga_jual' => 6500,
            'harga_jual_b' => 65000,
            'diskon' => 1000,
            'sales_id' => $this->salesId,
        ];

        $response = $this->controller->simpanDetail(new Request($payload));
        $data = json_decode($response->getContent(), true);

        $nota = $data['nota'];
        $detail = DetailPenjualan::where('no_penjualan', $nota)->first();

        $this->assertEquals(10, (float) $detail->jumlah, 'Jumlah allSK 1 dus x 10 = 10 pcs');
        $this->assertEquals(1000, (float) $detail->diskon, 'Total diskon harus 1 x Rp1.000 = Rp1.000');
        $this->assertEquals(64000, (float) $detail->subtotal, 'Subtotal harus (1 x 65.000) - 1.000 = Rp64.000');
    }
}
