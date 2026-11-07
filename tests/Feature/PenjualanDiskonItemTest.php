<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\Transaksi\Penjualan\PenjualanController;
use App\Models\Stok\stok;
use App\Models\Transaksi\Penjualan\DetailPenjualan;
use App\Models\Transaksi\Penjualan\DetailPenjualanFifo;
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

        DB::table('stoks')->insert([
            'nopenerimaan' => 'TERIMA-' . uniqid(),
            'idpenerimaan' => 'IDT-' . uniqid(),
            'kdbarang' => $this->kodeBarang,
            'jumlah_b' => 100,
            'jumlah_k' => 1000,
            'isi' => 10,
            'satuan_b' => 'DUS',
            'satuan_k' => 'PCS',
            'harga_beli_b' => 50000,
            'harga_beli_k' => 5000,
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

    /**
     * Test J — Draft Penjualan Tidak Mengenakan PPN:
     * Sesuai contoh pada masalah:
     * Barang 1: 1 DUS, Harga Rp50.000, Diskon Rp0 -> Subtotal Rp50.000
     * Barang 2: 2 DUS, Harga Rp65.000, Diskon Rp10.000 (total) -> Subtotal Rp120.000
     * Header draft:
     * - Total diskon item = Rp10.000
     * - Subtotal = Rp170.000
     * - PPN = 0
     * - Total = Rp170.000 (bukan Rp188.700)
     */
    public function test_j_draft_penjualan_tidak_mengenakan_ppn(): void
    {
        $kodeBarang2 = 'TEST-BRG2-' . uniqid();
        DB::table('barangs')->insert([
            'kodebarang' => $kodeBarang2,
            'namabarang' => 'Semen Test 50kg',
            'namagabung' => 'Semen Test 50kg',
            'slug' => 'semen-test-' . uniqid(),
            'satuan_b' => 'DUS',
            'satuan_k' => 'PCS',
            'isi' => 1,
            'hargajual1' => 50000,
            'hargajual2' => 50000,
            'hargabeli' => 40000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('stoks')->insert([
            'nopenerimaan' => 'TRM-BRG2-' . uniqid(),
            'idpenerimaan' => 'IDT-BRG2-' . uniqid(),
            'kdbarang' => $kodeBarang2,
            'jumlah_b' => 10,
            'jumlah_k' => 10,
            'isi' => 1,
            'satuan_b' => 'DUS',
            'satuan_k' => 'PCS',
            'harga_beli_b' => 40000,
            'harga_beli_k' => 40000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Input Barang 1: 1 DUS @ 50.000, diskon 0
        $payload1 = [
            'kodebarang' => $kodeBarang2,
            'satuan' => 'SB',
            'jumlah_b' => 1,
            'jumlah_k' => 0,
            'isi' => 1,
            'harga_jual' => 50000,
            'harga_jual_b' => 50000,
            'diskon' => 0,
            'sales_id' => $this->salesId,
        ];
        $res1 = $this->controller->simpanDetail(new Request($payload1));
        $data1 = json_decode($res1->getContent(), true);
        $nota = $data1['nota'];

        // Input Barang 2: 2 DUS @ 65.000 (harga SB), diskon Rp5.000/dus (total diskon Rp10.000)
        $payload2 = [
            'nota' => $nota,
            'kodebarang' => $this->kodeBarang,
            'satuan' => 'SB',
            'jumlah_b' => 2,
            'jumlah_k' => 0,
            'isi' => 10,
            'harga_jual' => 6500,
            'harga_jual_b' => 65000,
            'diskon' => 5000,
            'sales_id' => $this->salesId,
        ];
        $res2 = $this->controller->simpanDetail(new Request($payload2));
        $data2 = json_decode($res2->getContent(), true);

        $header = HeaderPenjualan::where('no_penjualan', $nota)->first();
        $this->assertNotNull($header);
        $this->assertNull($header->flag, 'Transaksi draft harus memiliki flag null');
        $this->assertEquals(170000, (float) $header->subtotal, 'Subtotal draft harus Rp170.000');
        $this->assertEquals(10000, (float) $header->total_diskon, 'Total diskon draft harus Rp10.000');
        $this->assertEquals(0, (float) $header->ppn_persen, 'PPN persen draft harus 0');
        $this->assertEquals(0, (float) $header->ppn_nominal, 'PPN nominal draft harus 0');
        $this->assertEquals(170000, (float) $header->total, 'Total draft harus Rp170.000 (sama dengan subtotal bersih, TANPA PPN)');
    }

    /**
     * Test K — Hapus Detail Draft Tetap Tanpa PPN:
     */
    public function test_k_hapus_detail_draft_tetap_tanpa_ppn(): void
    {
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

        $detailSk = DetailPenjualan::where('no_penjualan', $nota)->where('jumlah', 5)->first();
        $this->assertNotNull($detailSk);

        // Hapus baris SK
        $resDelete = $this->controller->hapusDetail(new Request([
            'id' => $detailSk->id,
            'no_penjualan' => $nota,
        ]));

        $header = HeaderPenjualan::where('no_penjualan', $nota)->first();
        $this->assertNotNull($header);
        $this->assertEquals(128000, (float) $header->subtotal, 'Subtotal draft tersisa harus Rp128.000');
        $this->assertEquals(0, (float) $header->ppn_persen, 'PPN persen draft harus 0');
        $this->assertEquals(0, (float) $header->ppn_nominal, 'PPN nominal draft harus 0');
        $this->assertEquals(128000, (float) $header->total, 'Total draft tersisa harus Rp128.000 tanpa PPN');
    }

    /**
     * Test L — Simpan Pembayaran dengan PPN Aktif:
     */
    public function test_l_simpan_pembayaran_dengan_ppn_aktif(): void
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
        $res = $this->controller->simpanDetail(new Request($payload));
        $data = json_decode($res->getContent(), true);
        $nota = $data['nota'];

        // Subtotal = 130.000. DPP = 130.000. PPN 11% = 14.300. Total = 144.300.
        $bayarPayload = [
            'no_penjualan' => $nota,
            'flag' => '5',
            'cara_bayar' => 'cash',
            'bayar' => 144300,
            'diskon_kuitansi' => 0,
            'kena_ppn' => true,
        ];
        $resBayar = $this->controller->simpanPembayaran(new Request($bayarPayload));
        $this->assertEquals(200, $resBayar->getStatusCode());

        $header = HeaderPenjualan::where('no_penjualan', $nota)->first();
        $this->assertEquals('5', $header->flag);
        $this->assertEquals(130000, (float) $header->subtotal);
        $this->assertEquals(11.0, (float) $header->ppn_persen);
        $this->assertEquals(14300, (float) $header->ppn_nominal);
        $this->assertEquals(144300, (float) $header->total);
        $this->assertEquals(144300, (float) $header->bayar);
        $this->assertEquals(0, (float) $header->kembali);
    }

    /**
     * Test M — Simpan Pembayaran Tanpa PPN:
     */
    public function test_m_simpan_pembayaran_tanpa_ppn(): void
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
        $res = $this->controller->simpanDetail(new Request($payload));
        $data = json_decode($res->getContent(), true);
        $nota = $data['nota'];

        // Subtotal = 130.000. DPP = 130.000. PPN = 0. Total = 130.000.
        $bayarPayload = [
            'no_penjualan' => $nota,
            'flag' => '5',
            'cara_bayar' => 'cash',
            'bayar' => 130000,
            'diskon_kuitansi' => 0,
            'kena_ppn' => false,
        ];
        $resBayar = $this->controller->simpanPembayaran(new Request($bayarPayload));
        $this->assertEquals(200, $resBayar->getStatusCode());

        $header = HeaderPenjualan::where('no_penjualan', $nota)->first();
        $this->assertEquals('5', $header->flag);
        $this->assertEquals(130000, (float) $header->subtotal);
        $this->assertEquals(0.0, (float) $header->ppn_persen);
        $this->assertEquals(0.0, (float) $header->ppn_nominal);
        $this->assertEquals(130000, (float) $header->total);
        $this->assertEquals(130000, (float) $header->bayar);
        $this->assertEquals(0, (float) $header->kembali);
    }

    /**
     * Test N — Simpan Pembayaran Stok Cukup dengan Konversi Satuan SB ke SK:
     * Barang isi = 8 (1 DUS = 8 PCS).
     * Stok tersedia = 24 PCS (3 DUS).
     * Permintaan kasir = 2 DUS (konversi SB ke SK = 16 PCS).
     * Simpan pembayaran lunas: berhasil (HTTP 200), stok berkurang 16 PCS (sisa 8 PCS),
     * FIFO tercatat 16 PCS.
     */
    public function test_n_simpan_pembayaran_stok_cukup_konversi_sb_ke_sk(): void
    {
        $kode = 'TEST-SB-SK-' . uniqid();
        DB::table('barangs')->insert([
            'kodebarang' => $kode,
            'namabarang' => 'Keramik Uji SB SK',
            'namagabung' => 'Keramik Uji SB SK',
            'slug' => 'keramik-uji-sb-sk-' . uniqid(),
            'satuan_b' => 'DUS',
            'satuan_k' => 'PCS',
            'isi' => 8,
            'hargajual1' => 10000,
            'hargajual2' => 10000,
            'hargabeli' => 8000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $stokId = DB::table('stoks')->insertGetId([
            'nopenerimaan' => 'TRM-' . uniqid(),
            'idpenerimaan' => 'IDT-' . uniqid(),
            'kdbarang' => $kode,
            'jumlah_b' => 3,
            'jumlah_k' => 24, // 3 DUS = 24 PCS
            'isi' => 8,
            'satuan_b' => 'DUS',
            'satuan_k' => 'PCS',
            'harga_beli_b' => 64000,
            'harga_beli_k' => 8000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Input 2 DUS (= 16 PCS)
        $payload = [
            'kodebarang' => $kode,
            'satuan' => 'SB',
            'jumlah_b' => 2,
            'jumlah_k' => 0,
            'isi' => 8,
            'harga_jual' => 10000,
            'harga_jual_b' => 80000,
            'diskon' => 0,
            'sales_id' => $this->salesId,
        ];
        $res = $this->controller->simpanDetail(new Request($payload));
        $data = json_decode($res->getContent(), true);
        $nota = $data['nota'];

        // Detail harus tersimpan 16 PCS
        $detail = DetailPenjualan::where('no_penjualan', $nota)->where('kodebarang', $kode)->first();
        $this->assertEquals(16, (float) $detail->jumlah);
        $this->assertEquals(160000, (float) $detail->subtotal);

        // Bayar lunas
        $bayarPayload = [
            'no_penjualan' => $nota,
            'flag' => '5',
            'cara_bayar' => 'cash',
            'bayar' => 160000,
            'diskon_kuitansi' => 0,
            'kena_ppn' => false,
        ];
        $resBayar = $this->controller->simpanPembayaran(new Request($bayarPayload));
        $this->assertEquals(200, $resBayar->getStatusCode());

        // Verifikasi stok berkurang tepat 16 PCS (24 - 16 = 8 PCS)
        $sisaStok = DB::table('stoks')->where('id', $stokId)->value('jumlah_k');
        $this->assertEquals(8, (float) $sisaStok);

        // Verifikasi detail FIFO
        $fifo = DetailPenjualanFifo::where('no_penjualan', $nota)->where('kodebarang', $kode)->get();
        $this->assertEquals(16, (float) $fifo->sum('jumlah'));

        // Header status lunas
        $header = HeaderPenjualan::where('no_penjualan', $nota)->first();
        $this->assertEquals('5', $header->flag);
    }

    /**
     * Test O — Simpan Pembayaran Stok Kurang Ditolak dengan HTTP 422 (ValidationException) dan Rollback Penuh:
     * Barang isi = 8.
     * Stok tersedia = 8 PCS (1 DUS).
     * Permintaan kasir = 2 DUS (16 PCS).
     * Simpan pembayaran: harus melempar ValidationException (HTTP 422),
     * pesan kesalahan informatif menampilkan ketersediaan dan kebutuhan,
     * stok tidak berkurang sama sekali, status penjualan tetap draft (flag null),
     * dan tidak ada record FIFO yang dibuat.
     */
    public function test_o_simpan_pembayaran_stok_kurang_melempar_validation_exception_422_dan_rollback(): void
    {
        $kode = 'TEST-KURANG-' . uniqid();
        DB::table('barangs')->insert([
            'kodebarang' => $kode,
            'namabarang' => 'Keramik Stok Kurang',
            'namagabung' => 'Keramik Stok Kurang',
            'slug' => 'keramik-stok-kurang-' . uniqid(),
            'satuan_b' => 'DUS',
            'satuan_k' => 'PCS',
            'isi' => 8,
            'hargajual1' => 10000,
            'hargajual2' => 10000,
            'hargabeli' => 8000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $stokId = DB::table('stoks')->insertGetId([
            'nopenerimaan' => 'TRM-' . uniqid(),
            'idpenerimaan' => 'IDT-' . uniqid(),
            'kdbarang' => $kode,
            'jumlah_b' => 2,
            'jumlah_k' => 16, // Awalnya tersedia 16 PCS agar simpanDetail sukses
            'isi' => 8,
            'satuan_b' => 'DUS',
            'satuan_k' => 'PCS',
            'harga_beli_b' => 128000,
            'harga_beli_k' => 8000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Input 2 DUS (= 16 PCS)
        $payload = [
            'kodebarang' => $kode,
            'satuan' => 'SB',
            'jumlah_b' => 2,
            'jumlah_k' => 0,
            'isi' => 8,
            'harga_jual' => 10000,
            'harga_jual_b' => 80000,
            'diskon' => 0,
            'sales_id' => $this->salesId,
        ];
        $res = $this->controller->simpanDetail(new Request($payload));
        $data = json_decode($res->getContent(), true);
        $nota = $data['nota'];

        // Simulasikan stok berkurang menjadi 8 PCS sebelum pembayaran
        DB::table('stoks')->where('id', $stokId)->update(['jumlah_k' => 8, 'jumlah_b' => 1]);

        $bayarPayload = [
            'no_penjualan' => $nota,
            'flag' => '5',
            'cara_bayar' => 'cash',
            'bayar' => 160000,
            'diskon_kuitansi' => 0,
            'kena_ppn' => false,
        ];

        try {
            $this->controller->simpanPembayaran(new Request($bayarPayload));
            $this->fail('Harus melempar ValidationException karena stok tidak mencukupi');
        } catch (ValidationException $e) {
            // Verifikasi status HTTP 422
            $this->assertEquals(422, $e->status);
            $this->assertArrayHasKey('stok', $e->errors());
            $pesan = $e->errors()['stok'][0];
            $this->assertStringContainsString('Stok tidak mencukupi untuk barang', $pesan);
            $this->assertStringContainsString('Tersedia: 8 PCS (1 DUS)', $pesan);
            $this->assertStringContainsString('dibutuhkan: 16 PCS (2 DUS)', $pesan);
        }

        // Pastikan rollback penuh:
        // 1. Stok tetap utuh 8 PCS
        $sisaStok = DB::table('stoks')->where('id', $stokId)->value('jumlah_k');
        $this->assertEquals(8, (float) $sisaStok, 'Stok tidak boleh berkurang saat simpan pembayaran gagal');

        // 2. Status header tidak berubah menjadi lunas (flag tetap null)
        $header = HeaderPenjualan::where('no_penjualan', $nota)->first();
        $this->assertNull($header->flag, 'Flag penjualan harus tetap null (draft)');

        // 3. Tidak ada record FIFO yang tercatat
        $fifoCount = DetailPenjualanFifo::where('no_penjualan', $nota)->count();
        $this->assertEquals(0, $fifoCount, 'Record FIFO tidak boleh terbentuk');
    }

    /**
     * Test P — Simpan Pembayaran Multi Baris Stok Kurang Tidak Mengurangi Sebagian (Partial Decrement Protection):
     * Stok barang = 10 PCS.
     * Transaksi memiliki 2 baris detail untuk barang tersebut:
     * - Baris 1: 6 PCS
     * - Baris 2: 6 PCS
     * Total kebutuhan = 12 PCS (> 10 PCS tersedia).
     * Simpan pembayaran: harus ditolak secara atomik (ValidationException 422),
     * baris 1 TIDAK boleh mengurangi stok 6 pcs secara parsial, stok tetap utuh 10 PCS.
     */
    public function test_p_simpan_pembayaran_multi_baris_stok_kurang_tidak_mengurangi_sebagian(): void
    {
        $kode = 'TEST-PARTIAL-' . uniqid();
        DB::table('barangs')->insert([
            'kodebarang' => $kode,
            'namabarang' => 'Keramik Partial Test',
            'namagabung' => 'Keramik Partial Test',
            'slug' => 'keramik-partial-test-' . uniqid(),
            'satuan_b' => 'DUS',
            'satuan_k' => 'PCS',
            'isi' => 1,
            'hargajual1' => 10000,
            'hargajual2' => 10000,
            'hargabeli' => 8000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $stokId = DB::table('stoks')->insertGetId([
            'nopenerimaan' => 'TRM-' . uniqid(),
            'idpenerimaan' => 'IDT-' . uniqid(),
            'kdbarang' => $kode,
            'jumlah_b' => 13,
            'jumlah_k' => 13, // Awalnya ada 13 PCS agar simpanDetail sukses
            'isi' => 1,
            'satuan_b' => 'DUS',
            'satuan_k' => 'PCS',
            'harga_beli_b' => 8000,
            'harga_beli_k' => 8000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Baris 1: 6 PCS
        $payload1 = [
            'kodebarang' => $kode,
            'satuan' => 'SK',
            'jumlah_b' => 0,
            'jumlah_k' => 6,
            'isi' => 1,
            'harga_jual' => 10000,
            'harga_jual_b' => 10000,
            'diskon' => 0,
            'sales_id' => $this->salesId,
        ];
        $res1 = $this->controller->simpanDetail(new Request($payload1));
        $nota = json_decode($res1->getContent(), true)['nota'];

        // Baris 2: 7 PCS (nota yang sama, jumlah berbeda agar tersimpan 2 baris)
        $payload2 = [
            'nota' => $nota,
            'kodebarang' => $kode,
            'satuan' => 'SK',
            'jumlah_b' => 0,
            'jumlah_k' => 7,
            'isi' => 1,
            'harga_jual' => 10000,
            'harga_jual_b' => 10000,
            'diskon' => 0,
            'sales_id' => $this->salesId,
        ];
        $this->controller->simpanDetail(new Request($payload2));

        // Pastikan tersimpan 2 baris detail dengan total jumlah 13 PCS
        $details = DetailPenjualan::where('no_penjualan', $nota)->get();
        $this->assertCount(2, $details);
        $this->assertEquals(13, (float) $details->sum('jumlah'));

        // Simulasikan stok berkurang menjadi 10 PCS sebelum pembayaran
        DB::table('stoks')->where('id', $stokId)->update(['jumlah_k' => 10, 'jumlah_b' => 10]);

        // Total diminta = 13 PCS, stok hanya 10 PCS
        $bayarPayload = [
            'no_penjualan' => $nota,
            'flag' => '5',
            'cara_bayar' => 'cash',
            'bayar' => 130000,
            'diskon_kuitansi' => 0,
            'kena_ppn' => false,
        ];

        try {
            $this->controller->simpanPembayaran(new Request($bayarPayload));
            $this->fail('Harus melempar ValidationException');
        } catch (ValidationException $e) {
            $this->assertEquals(422, $e->status);
            $this->assertArrayHasKey('stok', $e->errors());
            $this->assertStringContainsString('Stok tidak mencukupi untuk barang', $e->errors()['stok'][0]);
            $this->assertStringContainsString('Tersedia: 10 PCS', $e->errors()['stok'][0]);
            $this->assertStringContainsString('dibutuhkan: 13 PCS', $e->errors()['stok'][0]);
        }

        // Stok HARUS tetap 10 PCS (tidak berkurang 6 pcs oleh baris 1)
        $sisaStok = DB::table('stoks')->where('id', $stokId)->value('jumlah_k');
        $this->assertEquals(10, (float) $sisaStok, 'Stok tidak boleh berkurang sebagian (partial reduction)');

        // Tidak ada FIFO
        $this->assertEquals(0, DetailPenjualanFifo::where('no_penjualan', $nota)->count());
    }

    /**
     * Test Q — Simpan Pembayaran Dua Barang Berbeda, Salah Satu Kurang, Rollback Penuh:
     * Barang 1: stok 10 PCS, kasir beli 5 PCS (stok cukup).
     * Barang 2: stok 2 PCS, kasir beli 5 PCS (stok kurang).
     * Simpan pembayaran: harus ditolak (HTTP 422).
     * Stok Barang 1 HARUS tetap 10 PCS (tidak berkurang akibat pemrosesan bertahap).
     * Stok Barang 2 HARUS tetap 2 PCS.
     */
    public function test_q_simpan_pembayaran_dua_barang_berbeda_salah_satu_kurang_rollback_penuh(): void
    {
        $kodeA = 'TEST-A-' . uniqid();
        DB::table('barangs')->insert([
            'kodebarang' => $kodeA,
            'namabarang' => 'Barang A Cukup',
            'namagabung' => 'Barang A Cukup',
            'slug' => 'barang-a-cukup-' . uniqid(),
            'satuan_b' => 'DUS',
            'satuan_k' => 'PCS',
            'isi' => 1,
            'hargajual1' => 10000,
            'hargajual2' => 10000,
            'hargabeli' => 8000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $stokIdA = DB::table('stoks')->insertGetId([
            'nopenerimaan' => 'TRM-A-' . uniqid(),
            'idpenerimaan' => 'IDT-A-' . uniqid(),
            'kdbarang' => $kodeA,
            'jumlah_b' => 10,
            'jumlah_k' => 10,
            'isi' => 1,
            'satuan_b' => 'DUS',
            'satuan_k' => 'PCS',
            'harga_beli_b' => 8000,
            'harga_beli_k' => 8000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $kodeB = 'TEST-B-' . uniqid();
        DB::table('barangs')->insert([
            'kodebarang' => $kodeB,
            'namabarang' => 'Barang B Kurang',
            'namagabung' => 'Barang B Kurang',
            'slug' => 'barang-b-kurang-' . uniqid(),
            'satuan_b' => 'DUS',
            'satuan_k' => 'PCS',
            'isi' => 1,
            'hargajual1' => 15000,
            'hargajual2' => 15000,
            'hargabeli' => 12000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $stokIdB = DB::table('stoks')->insertGetId([
            'nopenerimaan' => 'TRM-B-' . uniqid(),
            'idpenerimaan' => 'IDT-B-' . uniqid(),
            'kdbarang' => $kodeB,
            'jumlah_b' => 5,
            'jumlah_k' => 5, // Awalnya ada 5 PCS agar simpanDetail sukses
            'isi' => 1,
            'satuan_b' => 'DUS',
            'satuan_k' => 'PCS',
            'harga_beli_b' => 12000,
            'harga_beli_k' => 12000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Input Barang A: 5 PCS
        $resA = $this->controller->simpanDetail(new Request([
            'kodebarang' => $kodeA,
            'satuan' => 'SK',
            'jumlah_b' => 0,
            'jumlah_k' => 5,
            'isi' => 1,
            'harga_jual' => 10000,
            'harga_jual_b' => 10000,
            'diskon' => 0,
            'sales_id' => $this->salesId,
        ]));
        $nota = json_decode($resA->getContent(), true)['nota'];

        // Input Barang B: 5 PCS (nota sama)
        $this->controller->simpanDetail(new Request([
            'nota' => $nota,
            'kodebarang' => $kodeB,
            'satuan' => 'SK',
            'jumlah_b' => 0,
            'jumlah_k' => 5,
            'isi' => 1,
            'harga_jual' => 15000,
            'harga_jual_b' => 15000,
            'diskon' => 0,
            'sales_id' => $this->salesId,
        ]));

        // Simulasikan stok Barang B berkurang menjadi 2 PCS sebelum pembayaran
        DB::table('stoks')->where('id', $stokIdB)->update(['jumlah_k' => 2, 'jumlah_b' => 2]);

        $bayarPayload = [
            'no_penjualan' => $nota,
            'flag' => '5',
            'cara_bayar' => 'cash',
            'bayar' => 125000,
            'diskon_kuitansi' => 0,
            'kena_ppn' => false,
        ];

        try {
            $this->controller->simpanPembayaran(new Request($bayarPayload));
            $this->fail('Harus melempar ValidationException');
        } catch (ValidationException $e) {
            $this->assertEquals(422, $e->status);
            $this->assertArrayHasKey('stok', $e->errors());
            $this->assertStringContainsString('Barang B Kurang', $e->errors()['stok'][0]);
            $this->assertStringContainsString('Tersedia: 2 PCS', $e->errors()['stok'][0]);
            $this->assertStringContainsString('dibutuhkan: 5 PCS', $e->errors()['stok'][0]);
        }

        // Stok Barang A TETAP 10 PCS
        $sisaStokA = DB::table('stoks')->where('id', $stokIdA)->value('jumlah_k');
        $this->assertEquals(10, (float) $sisaStokA, 'Stok Barang A tidak boleh berkurang saat transaksi gagal');

        // Stok Barang B TETAP 2 PCS
        $sisaStokB = DB::table('stoks')->where('id', $stokIdB)->value('jumlah_k');
        $this->assertEquals(2, (float) $sisaStokB, 'Stok Barang B tidak boleh berkurang saat transaksi gagal');

        // Tidak ada FIFO terbentuk
        $this->assertEquals(0, DetailPenjualanFifo::where('no_penjualan', $nota)->count());
    }

    /**
     * Test R — Tambah Detail pada Nota Draft (flag = null) Berhasil:
     * Detail 1 disimpan (membuat draft baru).
     * Detail 2 ditambahkan pada nota draft tersebut.
     * Hasil: Keduanya tersimpan, subtotal terakumulasi, flag tetap null.
     */
    public function test_r_tambah_detail_pada_draft_berhasil(): void
    {
        $payload1 = [
            'kodebarang' => $this->kodeBarang,
            'satuan' => 'SK',
            'jumlah_b' => 0,
            'jumlah_k' => 2,
            'isi' => 10,
            'harga_jual' => 6500,
            'diskon' => 0,
            'sales_id' => $this->salesId,
        ];
        $res1 = $this->controller->simpanDetail(new Request($payload1));
        $this->assertEquals(200, $res1->getStatusCode());
        $nota = json_decode($res1->getContent(), true)['nota'];

        $payload2 = [
            'nota' => $nota,
            'kodebarang' => $this->kodeBarang,
            'satuan' => 'SK',
            'jumlah_b' => 0,
            'jumlah_k' => 3,
            'isi' => 10,
            'harga_jual' => 6500,
            'diskon' => 0,
            'sales_id' => $this->salesId,
        ];
        $res2 = $this->controller->simpanDetail(new Request($payload2));
        $this->assertEquals(200, $res2->getStatusCode());

        $header = HeaderPenjualan::where('no_penjualan', $nota)->first();
        $this->assertNull($header->flag, 'Flag harus tetap null (draft)');
        $this->assertEquals(32500, (float) $header->subtotal, 'Subtotal harus (2+3) * 6500 = 32500');

        $details = DetailPenjualan::where('no_penjualan', $nota)->get();
        $this->assertEquals(5, (float) $details->sum('jumlah'));
    }

    /**
     * Test S — Tambah Detail pada Nota Lunas Ditolak dengan HTTP 422:
     * Nota sudah diproses pembayaran (flag = '5').
     * Percobaan menambah detail baru harus ditolak dengan ValidationException (HTTP 422).
     * Jumlah detail dan subtotal header tidak boleh berubah.
     */
    public function test_s_tambah_detail_pada_nota_lunas_ditolak_422(): void
    {
        $payload = [
            'kodebarang' => $this->kodeBarang,
            'satuan' => 'SK',
            'jumlah_b' => 0,
            'jumlah_k' => 2,
            'isi' => 10,
            'harga_jual' => 6500,
            'diskon' => 0,
            'sales_id' => $this->salesId,
        ];
        $res = $this->controller->simpanDetail(new Request($payload));
        $nota = json_decode($res->getContent(), true)['nota'];

        // Bayar lunas (flag = '5')
        $this->controller->simpanPembayaran(new Request([
            'no_penjualan' => $nota,
            'flag' => '5',
            'cara_bayar' => 'cash',
            'bayar' => 13000,
            'diskon_kuitansi' => 0,
            'kena_ppn' => false,
        ]));

        $headerAwal = HeaderPenjualan::where('no_penjualan', $nota)->first();
        $this->assertEquals('5', $headerAwal->flag);
        $detailCountAwal = DetailPenjualan::where('no_penjualan', $nota)->count();

        // Coba tambah detail baru ke nota yang sudah lunas
        $payloadBaru = [
            'nota' => $nota,
            'kodebarang' => $this->kodeBarang,
            'satuan' => 'SK',
            'jumlah_b' => 0,
            'jumlah_k' => 5,
            'isi' => 10,
            'harga_jual' => 6500,
            'diskon' => 0,
            'sales_id' => $this->salesId,
        ];

        try {
            $this->controller->simpanDetail(new Request($payloadBaru));
            $this->fail('Harus melempar ValidationException');
        } catch (ValidationException $e) {
            $this->assertEquals(422, $e->status);
            $this->assertArrayHasKey('nota', $e->errors());
            $this->assertStringContainsString('Rincian hanya dapat diubah pada penjualan draf', $e->errors()['nota'][0]);
        }

        // Verifikasi tidak ada perubahan pada database
        $this->assertEquals($detailCountAwal, DetailPenjualan::where('no_penjualan', $nota)->count());
        $headerAkhir = HeaderPenjualan::where('no_penjualan', $nota)->first();
        $this->assertEquals((float) $headerAwal->subtotal, (float) $headerAkhir->subtotal);
        $this->assertEquals('5', $headerAkhir->flag);
    }

    /**
     * Test T — Tambah Detail pada Nota Batal Ditolak dengan HTTP 422:
     * Nota berstatus batal (flag = '6').
     * Percobaan menambah detail harus ditolak dengan ValidationException (HTTP 422).
     */
    public function test_t_tambah_detail_pada_nota_batal_ditolak_422(): void
    {
        $payload = [
            'kodebarang' => $this->kodeBarang,
            'satuan' => 'SK',
            'jumlah_b' => 0,
            'jumlah_k' => 2,
            'isi' => 10,
            'harga_jual' => 6500,
            'diskon' => 0,
            'sales_id' => $this->salesId,
        ];
        $res = $this->controller->simpanDetail(new Request($payload));
        $nota = json_decode($res->getContent(), true)['nota'];

        // Tandai nota sebagai batal
        HeaderPenjualan::where('no_penjualan', $nota)->update(['flag' => '6']);

        $payloadBaru = [
            'nota' => $nota,
            'kodebarang' => $this->kodeBarang,
            'satuan' => 'SK',
            'jumlah_b' => 0,
            'jumlah_k' => 5,
            'isi' => 10,
            'harga_jual' => 6500,
            'diskon' => 0,
            'sales_id' => $this->salesId,
        ];

        try {
            $this->controller->simpanDetail(new Request($payloadBaru));
            $this->fail('Harus melempar ValidationException');
        } catch (ValidationException $e) {
            $this->assertEquals(422, $e->status);
            $this->assertArrayHasKey('nota', $e->errors());
            $this->assertStringContainsString('Penjualan yang dibatalkan tidak dapat diubah', $e->errors()['nota'][0]);
        }

        $detailCount = DetailPenjualan::where('no_penjualan', $nota)->count();
        $this->assertEquals(1, $detailCount, 'Detail baru tidak boleh tersimpan pada nota batal');
    }

    /**
     * Test U — Tambah Detail pada Nota Order Sales Ditolak dengan HTTP 422:
     * Nota berasal dari order sales (noorder terisi).
     * Aturan existing: rincian tidak dapat diubah.
     */
    public function test_u_tambah_detail_pada_nota_order_sales_ditolak_422(): void
    {
        $payload = [
            'kodebarang' => $this->kodeBarang,
            'satuan' => 'SK',
            'jumlah_b' => 0,
            'jumlah_k' => 2,
            'isi' => 10,
            'harga_jual' => 6500,
            'diskon' => 0,
            'sales_id' => $this->salesId,
        ];
        $res = $this->controller->simpanDetail(new Request($payload));
        $nota = json_decode($res->getContent(), true)['nota'];

        // Set noorder pada header penjualan
        HeaderPenjualan::where('no_penjualan', $nota)->update(['noorder' => 'OPJ-TEST-SALES-01']);

        $payloadBaru = [
            'nota' => $nota,
            'kodebarang' => $this->kodeBarang,
            'satuan' => 'SK',
            'jumlah_b' => 0,
            'jumlah_k' => 5,
            'isi' => 10,
            'harga_jual' => 6500,
            'diskon' => 0,
            'sales_id' => $this->salesId,
        ];

        try {
            $this->controller->simpanDetail(new Request($payloadBaru));
            $this->fail('Harus melempar ValidationException');
        } catch (ValidationException $e) {
            $this->assertEquals(422, $e->status);
            $this->assertArrayHasKey('nota', $e->errors());
            $this->assertStringContainsString('Rincian penjualan dari order sales tidak dapat diubah', $e->errors()['nota'][0]);
        }
    }

    /**
     * Test V — Request Paralel: Simpan Detail Ditolak Saat Pembayaran Difinalisasi:
     * Memverifikasi locking dan proteksi transaksi:
     * Transaksi pembayaran memproses nota draf dan mengunci header, mengubah status menjadi lunas.
     * Request simpanDetail berikutnya langsung mendeteksi status terkini nota yang sudah lunas
     * dan ditolak dengan HTTP 422.
     */
    public function test_v_request_paralel_simpan_detail_terkunci_saat_finalisasi_pembayaran(): void
    {
        $payload = [
            'kodebarang' => $this->kodeBarang,
            'satuan' => 'SK',
            'jumlah_b' => 0,
            'jumlah_k' => 2,
            'isi' => 10,
            'harga_jual' => 6500,
            'diskon' => 0,
            'sales_id' => $this->salesId,
        ];
        $res = $this->controller->simpanDetail(new Request($payload));
        $nota = json_decode($res->getContent(), true)['nota'];

        // Simulasikan urutan transaksi konkurensi:
        // Transaksi 1 (Pembayaran) memproses pembayaran dan mengupdate status flag = '5'
        $this->controller->simpanPembayaran(new Request([
            'no_penjualan' => $nota,
            'flag' => '5',
            'cara_bayar' => 'cash',
            'bayar' => 13000,
            'diskon_kuitansi' => 0,
            'kena_ppn' => false,
        ]));

        // Transaksi 2 (Simpan Detail secara konkuren/bersamaan) mencoba menambah rincian
        $payload2 = [
            'nota' => $nota,
            'kodebarang' => $this->kodeBarang,
            'satuan' => 'SK',
            'jumlah_b' => 0,
            'jumlah_k' => 4,
            'isi' => 10,
            'harga_jual' => 6500,
            'diskon' => 0,
            'sales_id' => $this->salesId,
        ];

        try {
            $this->controller->simpanDetail(new Request($payload2));
            $this->fail('Harus melempar ValidationException');
        } catch (ValidationException $e) {
            $this->assertEquals(422, $e->status);
            $this->assertArrayHasKey('nota', $e->errors());
            $this->assertStringContainsString('Rincian hanya dapat diubah pada penjualan draf', $e->errors()['nota'][0]);
        }

        // Detail harus tetap 1 record (kuantitas 2)
        $details = DetailPenjualan::where('no_penjualan', $nota)->get();
        $this->assertCount(1, $details);
        $this->assertEquals(2, (float) $details[0]->jumlah);
    }

    /**
     * Test W — Dropdown Stok Konsisten dengan Halaman Stok Barang:
     * Menguji bahwa getBarangByStok mengambil satuan_k ('PCS'), satuan_b ('DUS'), dan isi (8)
     * dari master barangs, meskipun tabel stoks memiliki data lama satuan_k = 'DUS'.
     * Menghasilkan stok_display = 'Stok: 1 DUS (8 PCS)'.
     */
    public function test_w_dropdown_stok_konsisten_dengan_halaman_stok_barang(): void
    {
        $kode = 'TEST-STOK-KONSISTEN-' . uniqid();
        DB::table('barangs')->insert([
            'kodebarang' => $kode,
            'namabarang' => 'Keramik Konsistensi Stok',
            'namagabung' => 'Keramik Konsistensi Stok',
            'slug' => 'keramik-konsistensi-stok-' . uniqid(),
            'satuan_b' => 'DUS',
            'satuan_k' => 'PCS',
            'isi' => 8,
            'hargajual1' => 8125,
            'hargajual2' => 7000,
            'hargabeli' => 5625,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Simulasikan anomali data stoks (seperti barang 00122-BRG di mana satuan_k tersimpan 'DUS')
        DB::table('stoks')->insert([
            'nopenerimaan' => 'TRM-' . uniqid(),
            'idpenerimaan' => 'IDT-' . uniqid(),
            'kdbarang' => $kode,
            'motif' => 'XXX',
            'jumlah_b' => 1,
            'jumlah_k' => 8, // 8 PCS
            'isi' => 8,
            'satuan_b' => 'DUS',
            'satuan_k' => 'DUS', // data lama keliru
            'harga_beli_b' => 45000,
            'harga_beli_k' => 5625,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Request simulasi dropdown getBarangByStok
        $request = new Request(['q' => $kode]);
        $response = $this->controller->getBarangByStok();
        $data = json_decode($response->getContent(), true);

        $this->assertNotEmpty($data, 'Data barang harus ditemukan');
        $item = collect($data)->firstWhere('kdbarang', $kode);
        $this->assertNotNull($item);

        // Pastikan satuan_k diambil dari master barangs ('PCS'), bukan 'DUS'
        $this->assertEquals('PCS', $item['satuan_k'], 'Satuan kecil harus PCS dari master barangs');
        $this->assertEquals('DUS', $item['satuan_b'], 'Satuan besar harus DUS dari master barangs');
        $this->assertEquals(8, (int) $item['isi'], 'Isi harus 8 dari master barangs');
        $this->assertEquals(8, (float) $item['jumlah_k']);

        // Pastikan format display stok sama persis dengan halaman stok barang
        $this->assertEquals('Stok: 1 DUS (8 PCS)', $item['stok_display']);
    }

    /**
     * Test X — Dropdown Stok Pecahan dan Multi Motif Berbeda:
     * Motif AAA: stok 10 PCS (isi 8) -> 1 DUS lebih 2 PCS (10 PCS).
     * Motif BBB: stok 5 PCS (isi 8) -> 5 PCS (5 PCS).
     * Keduanya harus terpisah dengan benar.
     */
    public function test_x_dropdown_stok_pecahan_dan_motif_berbeda(): void
    {
        $kode = 'TEST-PECAHAN-' . uniqid();
        DB::table('barangs')->insert([
            'kodebarang' => $kode,
            'namabarang' => 'Keramik Pecahan & Multi Motif',
            'namagabung' => 'Keramik Pecahan & Multi Motif',
            'slug' => 'keramik-pecahan-motif-' . uniqid(),
            'satuan_b' => 'DUS',
            'satuan_k' => 'PCS',
            'isi' => 8,
            'hargajual1' => 8000,
            'hargajual2' => 7000,
            'hargabeli' => 5000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Motif AAA: 10 PCS
        DB::table('stoks')->insert([
            'nopenerimaan' => 'TRM-AAA-' . uniqid(),
            'idpenerimaan' => 'IDT-AAA-' . uniqid(),
            'kdbarang' => $kode,
            'motif' => 'AAA',
            'jumlah_b' => 1,
            'jumlah_k' => 10,
            'isi' => 8,
            'satuan_b' => 'DUS',
            'satuan_k' => 'PCS',
            'harga_beli_b' => 40000,
            'harga_beli_k' => 5000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Motif BBB: 5 PCS
        DB::table('stoks')->insert([
            'nopenerimaan' => 'TRM-BBB-' . uniqid(),
            'idpenerimaan' => 'IDT-BBB-' . uniqid(),
            'kdbarang' => $kode,
            'motif' => 'BBB',
            'jumlah_b' => 0,
            'jumlah_k' => 5,
            'isi' => 8,
            'satuan_b' => 'DUS',
            'satuan_k' => 'PCS',
            'harga_beli_b' => 40000,
            'harga_beli_k' => 5000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        request()->merge(['q' => $kode]);
        $response = $this->controller->getBarangByStok();
        $data = json_decode($response->getContent(), true);

        $items = collect($data)->where('kdbarang', $kode)->values();
        $this->assertCount(2, $items, 'Harus menghasilkan 2 motif terpisah');

        $itemAAA = $items->firstWhere('motif', 'AAA');
        $this->assertNotNull($itemAAA);
        $this->assertEquals('Stok: 1 DUS lebih 2 PCS (10 PCS)', $itemAAA['stok_display']);

        $itemBBB = $items->firstWhere('motif', 'BBB');
        $this->assertNotNull($itemBBB);
        $this->assertEquals('Stok: 5 PCS (5 PCS)', $itemBBB['stok_display']);
    }

    /**
     * Test Y — Dropdown Stok Barang Satuan Tunggal (isi = 1):
     * Barang isi = 1, stok 15 PCS.
     * Tidak boleh ada redundansi keterangan kurung (misal: 'Stok: 15 PCS').
     */
    public function test_y_dropdown_stok_isi_satu(): void
    {
        $kode = 'TEST-ISI-1-' . uniqid();
        DB::table('barangs')->insert([
            'kodebarang' => $kode,
            'namabarang' => 'Barang Satuan Tunggal',
            'namagabung' => 'Barang Satuan Tunggal',
            'slug' => 'barang-satuan-tunggal-' . uniqid(),
            'satuan_b' => 'PCS',
            'satuan_k' => 'PCS',
            'isi' => 1,
            'hargajual1' => 15000,
            'hargajual2' => 15000,
            'hargabeli' => 12000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('stoks')->insert([
            'nopenerimaan' => 'TRM-1-' . uniqid(),
            'idpenerimaan' => 'IDT-1-' . uniqid(),
            'kdbarang' => $kode,
            'motif' => 'POLOS',
            'jumlah_b' => 15,
            'jumlah_k' => 15,
            'isi' => 1,
            'satuan_b' => 'PCS',
            'satuan_k' => 'PCS',
            'harga_beli_b' => 12000,
            'harga_beli_k' => 12000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        request()->merge(['q' => $kode]);
        $response = $this->controller->getBarangByStok();
        $data = json_decode($response->getContent(), true);

        $item = collect($data)->firstWhere('kdbarang', $kode);
        $this->assertNotNull($item);
        $this->assertEquals('Stok: 15 PCS', $item['stok_display']);
    }
}
