<?php

namespace Tests\Feature;

use App\Helpers\FifoHelper;
use App\Helpers\StokHelper;
use App\Http\Controllers\Api\Transaksi\OrderPenjualan\OrderPenjualanController;
use App\Http\Controllers\Api\Transaksi\Penjualan\PenjualanController;
use App\Http\Controllers\Api\v4\Sales\PengorderanController;
use App\Models\Barang;
use App\Models\OrderPenjualan;
use App\Models\Pelanggan;
use App\Models\Stok\stok;
use App\Models\Transaksi\Penjualan\DetailPenjualan;
use App\Models\Transaksi\Penjualan\DetailPenjualanFifo;
use App\Models\Transaksi\Penjualan\HeaderPenjualan;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AlokasiStokTerpusatTest extends TestCase
{
    protected User $user;
    protected ?int $salesId;
    protected Pelanggan $pelanggan;
    protected PenjualanController $penjualanController;
    protected OrderPenjualanController $orderController;
    protected PengorderanController $pengorderanController;

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
        $this->penjualanController = app(PenjualanController::class);
        $this->orderController = app(OrderPenjualanController::class);
        $this->pengorderanController = app(PengorderanController::class);

        $this->pelanggan = Pelanggan::first() ?? Pelanggan::create([
            'nama' => 'Pelanggan Uji',
            'kodeplgn' => 'PLG-' . uniqid(),
            'alamat' => 'Jl. Uji No. 1',
            'kota' => 'Kota Uji',
            'kategori' => 'retail',
        ]);
    }

    protected function tearDown(): void
    {
        DB::rollBack();
        parent::tearDown();
    }

    protected function createBarangDenganStok(string $nama, int $isi, int $stokFisikSk, ?string $motif = null): array
    {
        $kode = 'TEST-ALOKASI-' . uniqid();
        $barangId = DB::table('barangs')->insertGetId([
            'kodebarang' => $kode,
            'namabarang' => $nama,
            'namagabung' => $nama,
            'slug' => 'test-alokasi-' . uniqid(),
            'satuan_b' => 'DUS',
            'satuan_k' => 'PCS',
            'isi' => $isi,
            'hargajual1' => 10000,
            'hargajual2' => 9000,
            'hargabeli' => 7000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $stokId = DB::table('stoks')->insertGetId([
            'nopenerimaan' => 'TRM-' . uniqid(),
            'idpenerimaan' => 'IDT-' . uniqid(),
            'kdbarang' => $kode,
            'motif' => $motif,
            'jumlah_b' => $isi > 0 ? (int) floor($stokFisikSk / $isi) : 0,
            'jumlah_k' => $stokFisikSk,
            'isi' => $isi,
            'satuan_b' => 'DUS',
            'satuan_k' => 'PCS',
            'harga_beli_b' => 7000 * $isi,
            'harga_beli_k' => 7000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            'barang_id' => $barangId,
            'kodebarang' => $kode,
            'stok_id' => $stokId,
            'isi' => $isi,
        ];
    }

    /**
     * Test 1 — Dua kasir membuat draft barang yang sama:
     * Stok fisik = 10 PCS.
     * Kasir 1 membuat draft 6 PCS -> sukses, sisa tersedia 4 PCS.
     * Kasir 2 meminta 6 PCS -> ditolak HTTP 422.
     * Kasir 2 meminta 4 PCS -> sukses, sisa tersedia 0 PCS.
     * Kasir 3 meminta 1 PCS -> ditolak HTTP 422.
     */
    public function test_01_dua_kasir_membuat_draft_barang_sama(): void
    {
        $b = $this->createBarangDenganStok('Barang Dua Kasir', 1, 10);
        $kode = $b['kodebarang'];

        // Kasir 1 input draft 6 PCS
        $res1 = $this->penjualanController->simpanDetail(new Request([
            'kodebarang' => $kode,
            'satuan' => 'SK',
            'jumlah_b' => 0,
            'jumlah_k' => 6,
            'isi' => 1,
            'harga_jual' => 10000,
            'diskon' => 0,
            'sales_id' => $this->salesId,
        ]));
        $this->assertEquals(200, $res1->getStatusCode());
        $nota1 = json_decode($res1->getContent(), true)['nota'];

        // Cek stok tersedia sekarang harus 4 PCS
        $tersedia = StokHelper::getKetersediaanStok($kode);
        $this->assertEquals(4, $tersedia['stok_tersedia']);

        // Kasir 2 input draft 6 PCS -> harus ditolak
        try {
            $this->penjualanController->simpanDetail(new Request([
                'kodebarang' => $kode,
                'satuan' => 'SK',
                'jumlah_b' => 0,
                'jumlah_k' => 6,
                'isi' => 1,
                'harga_jual' => 10000,
                'diskon' => 0,
                'sales_id' => $this->salesId,
            ]));
            $this->fail('Harus gagal karena stok tersedia hanya 4 PCS');
        } catch (ValidationException $e) {
            $this->assertEquals(422, $e->status);
            $this->assertArrayHasKey('jumlah', $e->errors());
            $this->assertStringContainsString('Stok tidak mencukupi', $e->errors()['jumlah'][0]);
        }

        // Kasir 2 input draft 4 PCS -> harus berhasil
        $res2 = $this->penjualanController->simpanDetail(new Request([
            'kodebarang' => $kode,
            'satuan' => 'SK',
            'jumlah_b' => 0,
            'jumlah_k' => 4,
            'isi' => 1,
            'harga_jual' => 10000,
            'diskon' => 0,
            'sales_id' => $this->salesId,
        ]));
        $this->assertEquals(200, $res2->getStatusCode());

        // Cek stok tersedia sekarang harus 0 PCS
        $tersedia2 = StokHelper::getKetersediaanStok($kode);
        $this->assertEquals(0, $tersedia2['stok_tersedia']);

        // Kasir 3 input 1 PCS -> harus ditolak
        try {
            $this->penjualanController->simpanDetail(new Request([
                'kodebarang' => $kode,
                'satuan' => 'SK',
                'jumlah_b' => 0,
                'jumlah_k' => 1,
                'isi' => 1,
                'harga_jual' => 10000,
                'diskon' => 0,
                'sales_id' => $this->salesId,
            ]));
            $this->fail('Harus gagal karena stok tersedia 0 PCS');
        } catch (ValidationException $e) {
            $this->assertEquals(422, $e->status);
        }
    }

    /**
     * Test 2 — Order Sales dan Kasir memesan barang yang sama:
     * Stok fisik = 10 PCS.
     * Order Sales membuat pesanan 6 PCS (status 1).
     * Dropdown kasir POS hanya menampilkan stok tersedia 4 PCS.
     * Kasir mencoba input 5 PCS -> ditolak 422.
     * Kasir input 4 PCS -> sukses.
     */
    public function test_02_order_sales_dan_kasir_memesan_barang_sama(): void
    {
        $b = $this->createBarangDenganStok('Barang Order vs Kasir', 1, 10);
        $kode = $b['kodebarang'];
        $barangId = $b['barang_id'];

        // Buat order sales aktif status 1 sebanyak 6 PCS
        $order = OrderPenjualan::create([
            'noorder' => 'ORD-' . uniqid(),
            'tglorder' => now()->toDateString(),
            'pelanggan_id' => $this->pelanggan->id,
            'sales_id' => $this->salesId,
            'status_order' => '1',
            'total_harga' => 60000,
        ]);
        $order->rincians()->create([
            'barang_id' => $barangId,
            'jumlah' => 6,
            'isi' => 1,
            'harga' => 10000,
            'subtotal' => 60000,
        ]);

        // Cek stok di dropdown kasir
        request()->merge(['q' => $kode]);
        $resDropdown = $this->penjualanController->getBarangByStok();
        $dropdownData = json_decode($resDropdown->getContent(), true);

        $this->assertCount(1, $dropdownData);
        $this->assertEquals(4, (float) $dropdownData[0]['jumlah_k']);
        $this->assertEquals('Stok: 4 DUS', $dropdownData[0]['stok_display']);

        // Kasir mencoba input 5 PCS -> ditolak 422
        try {
            $this->penjualanController->simpanDetail(new Request([
                'kodebarang' => $kode,
                'satuan' => 'SK',
                'jumlah_b' => 0,
                'jumlah_k' => 5,
                'isi' => 1,
                'harga_jual' => 10000,
                'diskon' => 0,
                'sales_id' => $this->salesId,
            ]));
            $this->fail('Harus gagal karena alokasi order sales mengambil 6 PCS');
        } catch (ValidationException $e) {
            $this->assertEquals(422, $e->status);
        }

        // Kasir input 4 PCS -> sukses
        $resKasir = $this->penjualanController->simpanDetail(new Request([
            'kodebarang' => $kode,
            'satuan' => 'SK',
            'jumlah_b' => 0,
            'jumlah_k' => 4,
            'isi' => 1,
            'harga_jual' => 10000,
            'diskon' => 0,
            'sales_id' => $this->salesId,
        ]));
        $this->assertEquals(200, $resKasir->getStatusCode());

        // Sekarang stok tersedia di dropdown menjadi 0 (sehingga tidak muncul lagi di dropdown)
        $resDropdownHabis = $this->penjualanController->getBarangByStok();
        $habisData = json_decode($resDropdownHabis->getContent(), true);
        $this->assertCount(0, $habisData);
    }

    /**
     * Test 3 — Draft dibayar atau dibatalkan melepaskan alokasi:
     * Stok fisik = 10 PCS.
     * Kasir 1 membuat draft 6 PCS -> sisa tersedia 4 PCS.
     * Hapus detail draft Kasir 1 -> alokasi lepas, sisa tersedia kembali 10 PCS.
     * Kasir 2 membuat draft 6 PCS -> sukses.
     * Kasir 2 membayar draft (simpanPembayaran) -> stok fisik berkurang menjadi 4 PCS, alokasi 0, tersedia 4 PCS.
     */
    public function test_03_draft_dibatalkan_dan_dibayar_melepaskan_alokasi(): void
    {
        $b = $this->createBarangDenganStok('Barang Batal dan Bayar', 1, 10);
        $kode = $b['kodebarang'];
        $stokId = $b['stok_id'];

        // 1. Kasir 1 input draft 6 PCS
        $res1 = $this->penjualanController->simpanDetail(new Request([
            'kodebarang' => $kode,
            'satuan' => 'SK',
            'jumlah_b' => 0,
            'jumlah_k' => 6,
            'isi' => 1,
            'harga_jual' => 10000,
            'diskon' => 0,
            'sales_id' => $this->salesId,
        ]));
        $data1 = json_decode($res1->getContent(), true);
        $nota1 = $data1['nota'];
        $detailId1 = $data1['detail']['id'];

        $this->assertEquals(4, StokHelper::getKetersediaanStok($kode)['stok_tersedia']);

        // 2. Kasir 1 menghapus detail draft
        $this->penjualanController->hapusDetail(new Request([
            'id' => $detailId1,
            'no_penjualan' => $nota1,
        ]));

        // Alokasi lepas tanpa mengurangi stok fisik!
        $this->assertEquals(10, StokHelper::getStokFisikGlobal($kode));
        $this->assertEquals(10, StokHelper::getKetersediaanStok($kode)['stok_tersedia']);

        // 3. Kasir 2 sekarang dapat mengambil 6 PCS
        $res2 = $this->penjualanController->simpanDetail(new Request([
            'kodebarang' => $kode,
            'satuan' => 'SK',
            'jumlah_b' => 0,
            'jumlah_k' => 6,
            'isi' => 1,
            'harga_jual' => 10000,
            'diskon' => 0,
            'sales_id' => $this->salesId,
        ]));
        $nota2 = json_decode($res2->getContent(), true)['nota'];

        // 4. Kasir 2 bayar lunas
        $resBayar = $this->penjualanController->simpanPembayaran(new Request([
            'no_penjualan' => $nota2,
            'flag' => '5',
            'cara_bayar' => 'cash',
            'bayar' => 60000,
            'diskon_kuitansi' => 0,
            'kena_ppn' => false,
        ]));
        $this->assertEquals(200, $resBayar->getStatusCode());

        // Stok fisik berkurang via FIFO menjadi 4 PCS
        $sisaFisik = DB::table('stoks')->where('id', $stokId)->value('jumlah_k');
        $this->assertEquals(4, (float) $sisaFisik);

        // Alokasi draft menjadi 0 karena flag sudah bukan null
        $this->assertEquals(0, StokHelper::getAlokasiDraftKasir($kode));
        $this->assertEquals(4, StokHelper::getKetersediaanStok($kode)['stok_tersedia']);
    }

    /**
     * Test 4 — Draft Order Sales tidak dihitung ganda:
     * Stok fisik = 15 PCS.
     * Order Sales status 1 dibuat dengan 5 PCS.
     * Order Sales diubah statusnya menjadi 3 (Disetujui) -> draf penjualan dibuat dengan noorder.
     * Total alokasi harus tetap 5 PCS, bukan 10 PCS. Stok tersedia harus 10 PCS (15 - 5).
     */
    public function test_04_draft_order_sales_tidak_dihitung_ganda(): void
    {
        $b = $this->createBarangDenganStok('Barang Anti Double Alokasi', 1, 15);
        $kode = $b['kodebarang'];
        $barangId = $b['barang_id'];

        $order = OrderPenjualan::create([
            'noorder' => 'ORD-ANTI-' . uniqid(),
            'tglorder' => now()->toDateString(),
            'pelanggan_id' => $this->pelanggan->id,
            'sales_id' => $this->salesId,
            'status_order' => '1',
            'total_harga' => 50000,
            'total_sebelum_diskon' => 50000,
        ]);
        $order->rincians()->create([
            'barang_id' => $barangId,
            'jumlah' => 5,
            'isi' => 1,
            'harga' => 10000,
            'subtotal' => 50000,
        ]);

        // Ubah status ke 3 (Disetujui)
        $this->orderController->updateStatus(new Request([
            'order_id' => $order->id,
            'status_order' => '3',
        ]));

        // Pastikan draf penjualan turunan terbentuk
        $penjualan = HeaderPenjualan::where('noorder', $order->noorder)->first();
        $this->assertNotNull($penjualan);
        $this->assertNull($penjualan->flag);

        // Alokasi order sales = 5
        $alokasiOrder = StokHelper::getAlokasiOrderSales($kode);
        $this->assertEquals(5, $alokasiOrder);

        // Alokasi draft kasir mandiri HARUS 0 (karena nota ini punya noorder)
        $alokasiDraft = StokHelper::getAlokasiDraftKasir($kode);
        $this->assertEquals(0, $alokasiDraft);

        // Total alokasi = 5, stok tersedia = 10 (bukan 5!)
        $ketersediaan = StokHelper::getKetersediaanStok($kode);
        $this->assertEquals(5, $ketersediaan['total_alokasi']);
        $this->assertEquals(10, $ketersediaan['stok_tersedia']);

        // Kasir lain bisa membuat draft hingga 10 PCS
        $resKasir = $this->penjualanController->simpanDetail(new Request([
            'kodebarang' => $kode,
            'satuan' => 'SK',
            'jumlah_b' => 0,
            'jumlah_k' => 10,
            'isi' => 1,
            'harga_jual' => 10000,
            'diskon' => 0,
            'sales_id' => $this->salesId,
        ]));
        $this->assertEquals(200, $resKasir->getStatusCode());
    }

    /**
     * Test 5 — Stok dengan motif berbeda dan order tanpa motif:
     * Barang memiliki Motif POLOS (8 PCS) dan Motif BATIK (8 PCS), total fisik 16 PCS.
     * Order Sales memesan 10 PCS (tanpa motif).
     * Stok global bebas = 16 - 10 = 6 PCS.
     * Motif POLOS tersedia = min(8, 6) = 6 PCS.
     * Motif BATIK tersedia = min(8, 6) = 6 PCS.
     * Kasir input 5 PCS Motif POLOS -> sukses (sisa global 1 PCS).
     * Kasir mencoba input 2 PCS Motif BATIK -> ditolak 422 (karena sisa global hanya 1 PCS).
     * Kasir input 1 PCS Motif BATIK -> sukses.
     */
    public function test_05_stok_dengan_motif_berbeda_dan_order_tanpa_motif(): void
    {
        $kode = 'TEST-MULTI-MOTIF-' . uniqid();
        $barangId = DB::table('barangs')->insertGetId([
            'kodebarang' => $kode,
            'namabarang' => 'Keramik Motif Campur',
            'namagabung' => 'Keramik Motif Campur',
            'slug' => 'keramik-motif-campur-' . uniqid(),
            'satuan_b' => 'DUS',
            'satuan_k' => 'PCS',
            'isi' => 1,
            'hargajual1' => 10000,
            'hargajual2' => 9000,
            'hargabeli' => 7000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Motif POLOS: 8 PCS
        DB::table('stoks')->insert([
            'nopenerimaan' => 'TRM-P-' . uniqid(),
            'idpenerimaan' => 'IDT-P-' . uniqid(),
            'kdbarang' => $kode,
            'motif' => 'POLOS',
            'jumlah_b' => 8,
            'jumlah_k' => 8,
            'isi' => 1,
            'satuan_b' => 'DUS',
            'satuan_k' => 'PCS',
            'harga_beli_b' => 7000,
            'harga_beli_k' => 7000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Motif BATIK: 8 PCS
        DB::table('stoks')->insert([
            'nopenerimaan' => 'TRM-B-' . uniqid(),
            'idpenerimaan' => 'IDT-B-' . uniqid(),
            'kdbarang' => $kode,
            'motif' => 'BATIK',
            'jumlah_b' => 8,
            'jumlah_k' => 8,
            'isi' => 1,
            'satuan_b' => 'DUS',
            'satuan_k' => 'PCS',
            'harga_beli_b' => 7000,
            'harga_beli_k' => 7000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Order Sales memesan 10 PCS tanpa motif
        $order = OrderPenjualan::create([
            'noorder' => 'ORD-MOTIF-' . uniqid(),
            'tglorder' => now()->toDateString(),
            'pelanggan_id' => $this->pelanggan->id,
            'sales_id' => $this->salesId,
            'status_order' => '2',
            'total_harga' => 100000,
        ]);
        $order->rincians()->create([
            'barang_id' => $barangId,
            'jumlah' => 10,
            'isi' => 1,
            'harga' => 10000,
            'subtotal' => 100000,
        ]);

        // Cek ketersediaan per motif
        $tersediaPolos = StokHelper::getKetersediaanStok($kode, 'POLOS');
        $this->assertEquals(6, $tersediaPolos['stok_tersedia']);

        $tersediaBatik = StokHelper::getKetersediaanStok($kode, 'BATIK');
        $this->assertEquals(6, $tersediaBatik['stok_tersedia']);

        // Kasir input 5 PCS Motif POLOS
        $resPolos = $this->penjualanController->simpanDetail(new Request([
            'kodebarang' => $kode,
            'motif' => 'POLOS',
            'satuan' => 'SK',
            'jumlah_b' => 0,
            'jumlah_k' => 5,
            'isi' => 1,
            'harga_jual' => 10000,
            'diskon' => 0,
            'sales_id' => $this->salesId,
        ]));
        $this->assertEquals(200, $resPolos->getStatusCode());
        $notaKasir = json_decode($resPolos->getContent(), true)['nota'];

        // Sekarang sisa bebas global = 16 - 10 (order) - 5 (kasir polos) = 1 PCS.
        // Kasir mencoba input 2 PCS Motif BATIK pada nota yang sama -> harus ditolak!
        try {
            $this->penjualanController->simpanDetail(new Request([
                'nota' => $notaKasir,
                'kodebarang' => $kode,
                'motif' => 'BATIK',
                'satuan' => 'SK',
                'jumlah_b' => 0,
                'jumlah_k' => 2,
                'isi' => 1,
                'harga_jual' => 10000,
                'diskon' => 0,
                'sales_id' => $this->salesId,
            ]));
            $this->fail('Harus gagal karena alokasi global hanya tersisa 1 PCS');
        } catch (ValidationException $e) {
            $this->assertEquals(422, $e->status);
        }

        // Kasir input 1 PCS Motif BATIK -> sukses!
        $resBatik = $this->penjualanController->simpanDetail(new Request([
            'nota' => $notaKasir,
            'kodebarang' => $kode,
            'motif' => 'BATIK',
            'satuan' => 'SK',
            'jumlah_b' => 0,
            'jumlah_k' => 1,
            'isi' => 1,
            'harga_jual' => 10000,
            'diskon' => 0,
            'sales_id' => $this->salesId,
        ]));
        $this->assertEquals(200, $resBatik->getStatusCode());
    }

    /**
     * Test 6 — Pembayaran Lunas, DP Rp0, DP positif, dan Tempo:
     * Memastikan seluruh jenis pembayaran memproses FIFO, memotong stok fisik secara benar,
     * dan melepaskan alokasi draft.
     */
    public function test_06_pembayaran_lunas_dp_dan_tempo(): void
    {
        // 1. Lunas
        $b1 = $this->createBarangDenganStok('Barang Lunas', 1, 10);
        $res1 = $this->penjualanController->simpanDetail(new Request([
            'kodebarang' => $b1['kodebarang'],
            'satuan' => 'SK',
            'jumlah_k' => 2,
            'isi' => 1,
            'harga_jual' => 10000,
            'diskon' => 0,
            'sales_id' => $this->salesId,
        ]));
        $nota1 = json_decode($res1->getContent(), true)['nota'];
        $this->penjualanController->simpanPembayaran(new Request([
            'no_penjualan' => $nota1,
            'flag' => '5',
            'cara_bayar' => 'cash',
            'bayar' => 20000,
            'diskon_kuitansi' => 0,
            'kena_ppn' => false,
        ]));
        $this->assertEquals(8, DB::table('stoks')->where('id', $b1['stok_id'])->value('jumlah_k'));
        $this->assertEquals('5', HeaderPenjualan::where('no_penjualan', $nota1)->value('flag'));

        // 2. DP Positif (flag 7, transfer)
        $b2 = $this->createBarangDenganStok('Barang DP Positif', 1, 10);
        $res2 = $this->penjualanController->simpanDetail(new Request([
            'kodebarang' => $b2['kodebarang'],
            'satuan' => 'SK',
            'jumlah_k' => 3,
            'isi' => 1,
            'harga_jual' => 10000,
            'diskon' => 0,
            'sales_id' => $this->salesId,
            'pelanggan_id' => $this->pelanggan->id,
        ]));
        $nota2 = json_decode($res2->getContent(), true)['nota'];
        $this->penjualanController->simpanPembayaran(new Request([
            'no_penjualan' => $nota2,
            'pelanggan_id' => $this->pelanggan->id,
            'flag' => '7',
            'cara_bayar' => 'transfer',
            'bayar' => 10000,
            'diskon_kuitansi' => 0,
            'kena_ppn' => false,
        ]));
        $this->assertEquals(7, DB::table('stoks')->where('id', $b2['stok_id'])->value('jumlah_k'));
        $this->assertEquals('7', HeaderPenjualan::where('no_penjualan', $nota2)->value('flag'));

        // 3. DP Rp0 (flag 7, cash)
        $b3 = $this->createBarangDenganStok('Barang DP Nol', 1, 10);
        $res3 = $this->penjualanController->simpanDetail(new Request([
            'kodebarang' => $b3['kodebarang'],
            'satuan' => 'SK',
            'jumlah_k' => 4,
            'isi' => 1,
            'harga_jual' => 10000,
            'diskon' => 0,
            'sales_id' => $this->salesId,
            'pelanggan_id' => $this->pelanggan->id,
        ]));
        $nota3 = json_decode($res3->getContent(), true)['nota'];
        $this->penjualanController->simpanPembayaran(new Request([
            'no_penjualan' => $nota3,
            'pelanggan_id' => $this->pelanggan->id,
            'flag' => '7',
            'cara_bayar' => 'cash',
            'bayar' => 0,
            'diskon_kuitansi' => 0,
            'kena_ppn' => false,
        ]));
        $this->assertEquals(6, DB::table('stoks')->where('id', $b3['stok_id'])->value('jumlah_k'));
        $this->assertEquals('7', HeaderPenjualan::where('no_penjualan', $nota3)->value('flag'));

        // 4. Tempo (flag 2, bayar 0)
        $b4 = $this->createBarangDenganStok('Barang Tempo', 1, 10);
        $res4 = $this->penjualanController->simpanDetail(new Request([
            'kodebarang' => $b4['kodebarang'],
            'satuan' => 'SK',
            'jumlah_k' => 5,
            'isi' => 1,
            'harga_jual' => 10000,
            'diskon' => 0,
            'sales_id' => $this->salesId,
            'pelanggan_id' => $this->pelanggan->id,
        ]));
        $nota4 = json_decode($res4->getContent(), true)['nota'];
        $this->penjualanController->simpanPembayaran(new Request([
            'no_penjualan' => $nota4,
            'pelanggan_id' => $this->pelanggan->id,
            'flag' => '2',
            'cara_bayar' => '',
            'bayar' => 0,
            'diskon_kuitansi' => 0,
            'kena_ppn' => false,
        ]));
        $this->assertEquals(5, DB::table('stoks')->where('id', $b4['stok_id'])->value('jumlah_k'));
        $this->assertEquals('2', HeaderPenjualan::where('no_penjualan', $nota4)->value('flag'));
    }

    /**
     * Test 7 — Stok kurang menghasilkan HTTP 422:
     * a) simpanDetail melebihi stok -> HTTP 422.
     * b) simpanPembayaran saat stok tersisa diambil transaksi lain -> HTTP 422.
     */
    public function test_07_stok_kurang_menghasilkan_http_422(): void
    {
        $b = $this->createBarangDenganStok('Barang Validasi 422', 1, 5);
        $kode = $b['kodebarang'];

        // a) simpanDetail meminta 6 PCS (stok hanya 5 PCS)
        try {
            $this->penjualanController->simpanDetail(new Request([
                'kodebarang' => $kode,
                'satuan' => 'SK',
                'jumlah_k' => 6,
                'isi' => 1,
                'harga_jual' => 10000,
                'diskon' => 0,
                'sales_id' => $this->salesId,
            ]));
            $this->fail('Harus gagal 422 di simpanDetail');
        } catch (ValidationException $e) {
            $this->assertEquals(422, $e->status);
        }

        // b) simpanPembayaran saat stok kurang
        $res = $this->penjualanController->simpanDetail(new Request([
            'kodebarang' => $kode,
            'satuan' => 'SK',
            'jumlah_k' => 5,
            'isi' => 1,
            'harga_jual' => 10000,
            'diskon' => 0,
            'sales_id' => $this->salesId,
        ]));
        $nota = json_decode($res->getContent(), true)['nota'];

        // Simulasikan stok terpotong menjadi 3 PCS sebelum pembayaran
        DB::table('stoks')->where('id', $b['stok_id'])->update(['jumlah_k' => 3]);

        try {
            $this->penjualanController->simpanPembayaran(new Request([
                'no_penjualan' => $nota,
                'flag' => '5',
                'cara_bayar' => 'cash',
                'bayar' => 50000,
                'diskon_kuitansi' => 0,
                'kena_ppn' => false,
            ]));
            $this->fail('Harus gagal 422 di simpanPembayaran');
        } catch (ValidationException $e) {
            $this->assertEquals(422, $e->status);
            $this->assertArrayHasKey('stok', $e->errors());
        }
    }

    /**
     * Test 8 — Idempotensi pembayaran & rollback penuh:
     * - Pembayaran tidak boleh memotong FIFO dua kali jika dipanggil berulang.
     * - Rollback saat error memastikan stok fisik tetap utuh.
     */
    public function test_08_pembayaran_tidak_memotong_fifo_dua_kali_dan_rollback_utuh(): void
    {
        $b = $this->createBarangDenganStok('Barang FIFO Idempotent', 1, 10);
        $kode = $b['kodebarang'];
        $stokId = $b['stok_id'];

        $res = $this->penjualanController->simpanDetail(new Request([
            'kodebarang' => $kode,
            'satuan' => 'SK',
            'jumlah_k' => 4,
            'isi' => 1,
            'harga_jual' => 10000,
            'diskon' => 0,
            'sales_id' => $this->salesId,
        ]));
        $nota = json_decode($res->getContent(), true)['nota'];

        // Pembayaran pertama berhasil
        $this->penjualanController->simpanPembayaran(new Request([
            'no_penjualan' => $nota,
            'flag' => '5',
            'cara_bayar' => 'cash',
            'bayar' => 40000,
            'diskon_kuitansi' => 0,
            'kena_ppn' => false,
        ]));

        $sisa1 = DB::table('stoks')->where('id', $stokId)->value('jumlah_k');
        $this->assertEquals(6, (float) $sisa1);
        $fifoCount1 = DetailPenjualanFifo::where('no_penjualan', $nota)->count();
        $this->assertGreaterThan(0, $fifoCount1);

        // Panggil simpanPembayaran kedua kali untuk nota yang sama (idempotensi)
        try {
            $this->penjualanController->simpanPembayaran(new Request([
                'no_penjualan' => $nota,
                'flag' => '5',
                'cara_bayar' => 'cash',
                'bayar' => 40000,
                'diskon_kuitansi' => 0,
                'kena_ppn' => false,
            ]));
            $this->fail('Harus ditolak karena nota sudah dibayar');
        } catch (ValidationException $e) {
            $this->assertEquals(422, $e->status);
            $this->assertStringContainsString('Pembayaran untuk nota ini sudah diproses', $e->getMessage());
        }

        // Stok TIDAK boleh terpotong lagi (tetap 6 PCS)
        $sisa2 = DB::table('stoks')->where('id', $stokId)->value('jumlah_k');
        $this->assertEquals(6, (float) $sisa2);
        $fifoCount2 = DetailPenjualanFifo::where('no_penjualan', $nota)->count();
        $this->assertEquals($fifoCount1, $fifoCount2);
    }
}
