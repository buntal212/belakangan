<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\Transaksi\Penjualan\PenjualanController;
use App\Models\Barang\Barang;
use App\Models\Pelanggan\Pelanggan;
use App\Models\Transaksi\Penjualan\DetailPenjualan;
use App\Models\Transaksi\Penjualan\HeaderPenjualan;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RiwayatPenjualanSnapshotTest extends TestCase
{
    protected User $user;
    protected PenjualanController $controller;
    protected string $kodeBarang;

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
        $this->controller = app(PenjualanController::class);

        $this->kodeBarang = 'TEST-HIST-' . uniqid();
        DB::table('barangs')->insert([
            'kodebarang' => $this->kodeBarang,
            'namabarang' => 'Barang Test Riwayat',
            'namagabung' => 'Barang Test Riwayat',
            'slug' => 'barang-test-riwayat-' . uniqid(),
            'satuan_b' => 'DUS',
            'satuan_k' => 'PCS',
            'isi' => 10,
            'hargajual1' => 17500,
            'hargajual2' => 17500,
            'hargabeli' => 10000,
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
     * Helper to invoke getListPenjualan with request query parameters
     */
    protected function getRiwayatList(array $queryParams = [])
    {
        $request = Request::create('/v1/transaksi/penjualan/list', 'GET', array_merge([
            'from' => date('Y-m-01'),
            'to' => date('Y-m-d'),
            'per_page' => 50,
        ], $queryParams));

        app()->instance('request', $request);

        $response = $this->controller->getListPenjualan();
        return json_decode($response->getContent(), true);
    }

    /**
     * Test 1: Nota Lunas dengan PPN ON dan Diskon Nota Positif
     */
    public function test_nota_lunas_ppn_on_diskon_nota_positif()
    {
        $noPenjualan = 'TEST-LUNAS-' . uniqid();
        $header = HeaderPenjualan::create([
            'no_penjualan' => $noPenjualan,
            'tgl' => now(),
            'flag' => '5', // Lunas
            'total' => 69930,
            'total_diskon' => 5000,
            'diskon_kuitansi' => 2000,
            'subtotal' => 63000, // DPP tersimpan
            'ppn_persen' => 11.00,
            'ppn_nominal' => 6930,
            'bayar' => 70000,
            'kembali' => 70,
            'sistem_bayar' => '1',
            'cara_bayar' => 'cash',
        ]);

        DetailPenjualan::create([
            'no_penjualan' => $noPenjualan,
            'kodebarang' => $this->kodeBarang,
            'jumlah' => 4,
            'harga_jual' => 17500,
            'diskon' => 5000,
            'subtotal' => 65000,
        ]);

        $res = $this->getRiwayatList(['q' => $noPenjualan]);
        $this->assertNotEmpty($res['data']);
        $row = collect($res['data'])->firstWhere('no_penjualan', $noPenjualan);
        $this->assertNotNull($row);

        // Verifikasi field snapshot dikirim lengkap oleh backend
        $this->assertEquals(69930, $row['total']);
        $this->assertEquals(5000, $row['total_diskon']);
        $this->assertEquals(2000, (float)$row['diskon_kuitansi']);
        $this->assertEquals(63000, (float)$row['subtotal']);
        $this->assertEquals(11.00, (float)$row['ppn_persen']);
        $this->assertEquals(6930, (float)$row['ppn_nominal']);
        $this->assertEquals(70000, $row['bayar']);
        $this->assertEquals(70, $row['kembali']);
        $this->assertEquals('5', $row['flag']);

        // Verifikasi kalkulasi ringkasan:
        // 1. Subtotal barang = sum(detail.subtotal) = 65000
        $subtotalBarang = collect($row['detail'])->sum(fn($d) => (float)$d['subtotal']);
        $this->assertEquals(65000, $subtotalBarang);

        // 2. Diskon Nota = 2000
        $diskonNota = (float)$row['diskon_kuitansi'];
        $this->assertEquals(2000, $diskonNota);

        // 3. DPP = Subtotal Barang - Diskon Nota = 63000
        $dpp = $subtotalBarang - $diskonNota;
        $this->assertEquals(63000, $dpp);
        $this->assertEquals((float)$row['subtotal'], $dpp);

        // 4. PPN snapshot = 11% dan 6930
        $this->assertEquals(11.00, (float)$row['ppn_persen']);
        $this->assertEquals(6930, (float)$row['ppn_nominal']);

        // 5. Total Tagihan Final = DPP + PPN = 69930
        $this->assertEquals(69930, $row['total']);
    }

    /**
     * Test 2: Nota Down Payment (DP) dengan PPN ON dan Diskon Nota Rp0
     */
    public function test_nota_dp_ppn_on_diskon_nota_nol()
    {
        $noPenjualan = 'TEST-DP-' . uniqid();
        $header = HeaderPenjualan::create([
            'no_penjualan' => $noPenjualan,
            'tgl' => now(),
            'flag' => '7', // Down Payment
            'total' => 111000,
            'total_diskon' => 0,
            'diskon_kuitansi' => 0,
            'subtotal' => 100000,
            'ppn_persen' => 11.00,
            'ppn_nominal' => 11000,
            'bayar' => 30000,
            'kembali' => 0,
            'sistem_bayar' => '3',
            'cara_bayar' => 'cash',
        ]);

        DetailPenjualan::create([
            'no_penjualan' => $noPenjualan,
            'kodebarang' => $this->kodeBarang,
            'jumlah' => 10,
            'harga_jual' => 10000,
            'diskon' => 0,
            'subtotal' => 100000,
        ]);

        $res = $this->getRiwayatList(['q' => $noPenjualan]);
        $row = collect($res['data'])->firstWhere('no_penjualan', $noPenjualan);
        $this->assertNotNull($row);

        $subtotalBarang = collect($row['detail'])->sum(fn($d) => (float)$d['subtotal']);
        $this->assertEquals(100000, $subtotalBarang);
        $this->assertEquals(0, (float)$row['diskon_kuitansi']);
        $this->assertEquals(100000, (float)$row['subtotal']);
        $this->assertEquals(11.00, (float)$row['ppn_persen']);
        $this->assertEquals(11000, (float)$row['ppn_nominal']);
        $this->assertEquals(111000, $row['total']);
        $this->assertEquals(30000, $row['bayar']);
        $this->assertEquals('7', $row['flag']);
    }

    /**
     * Test 3: Nota Tempo dengan PPN OFF dan Diskon Nota Positif
     */
    public function test_nota_tempo_ppn_off_diskon_nota_positif()
    {
        $noPenjualan = 'TEST-TEMPO-' . uniqid();
        $header = HeaderPenjualan::create([
            'no_penjualan' => $noPenjualan,
            'tgl' => now(),
            'flag' => '2', // Tempo
            'total' => 90000,
            'total_diskon' => 0,
            'diskon_kuitansi' => 10000,
            'subtotal' => 90000, // DPP = 100000 - 10000 = 90000
            'ppn_persen' => 0.00,
            'ppn_nominal' => 0,
            'bayar' => 0,
            'kembali' => 0,
            'sistem_bayar' => '2',
            'cara_bayar' => 'cash',
            'tempo' => now()->addDays(30)->toDateString(),
            'jml_tempo' => 30,
        ]);

        DetailPenjualan::create([
            'no_penjualan' => $noPenjualan,
            'kodebarang' => $this->kodeBarang,
            'jumlah' => 10,
            'harga_jual' => 10000,
            'diskon' => 0,
            'subtotal' => 100000,
        ]);

        $res = $this->getRiwayatList(['q' => $noPenjualan]);
        $row = collect($res['data'])->firstWhere('no_penjualan', $noPenjualan);
        $this->assertNotNull($row);

        $subtotalBarang = collect($row['detail'])->sum(fn($d) => (float)$d['subtotal']);
        $this->assertEquals(100000, $subtotalBarang);
        $this->assertEquals(10000, (float)$row['diskon_kuitansi']);
        $this->assertEquals(90000, $subtotalBarang - (float)$row['diskon_kuitansi']);
        $this->assertEquals(0, (float)$row['ppn_persen']);
        $this->assertEquals(0, (float)$row['ppn_nominal']);
        $this->assertEquals(90000, $row['total']);
        $this->assertEquals(0, $row['bayar']);
        $this->assertEquals('2', $row['flag']);
    }

    /**
     * Test 4: Transaksi Lama tanpa PPN dan tanpa Diskon Nota (Data Historis)
     */
    public function test_transaksi_lama_historis()
    {
        $noPenjualan = 'TEST-LAMA-' . uniqid();
        $header = HeaderPenjualan::create([
            'no_penjualan' => $noPenjualan,
            'tgl' => now(),
            'flag' => '5',
            'total' => 50000,
            'total_diskon' => 0,
            'diskon_kuitansi' => 0,
            'subtotal' => 50000,
            'ppn_persen' => 0,
            'ppn_nominal' => 0,
            'bayar' => 50000,
            'kembali' => 0,
            'sistem_bayar' => '1',
            'cara_bayar' => 'cash',
        ]);

        DetailPenjualan::create([
            'no_penjualan' => $noPenjualan,
            'kodebarang' => $this->kodeBarang,
            'jumlah' => 5,
            'harga_jual' => 10000,
            'diskon' => 0,
            'subtotal' => 50000,
        ]);

        $res = $this->getRiwayatList(['q' => $noPenjualan]);
        $row = collect($res['data'])->firstWhere('no_penjualan', $noPenjualan);
        $this->assertNotNull($row);

        $subtotalBarang = collect($row['detail'])->sum(fn($d) => (float)$d['subtotal']);
        $this->assertEquals(50000, $subtotalBarang);

        // Fallback transaksi lama
        $diskonNota = (float)($row['diskon_kuitansi'] ?? 0);
        $this->assertEquals(0, $diskonNota);

        $dpp = $subtotalBarang - $diskonNota;
        $this->assertEquals(50000, $dpp);

        $ppnPersen = (float)($row['ppn_persen'] ?? 0);
        $ppnNominal = (float)($row['ppn_nominal'] ?? 0);
        $this->assertEquals(0, $ppnPersen);
        $this->assertEquals(0, $ppnNominal);

        $this->assertEquals(50000, $row['total']);
    }

    /**
     * Test 5: Snapshot tidak berubah jika konfigurasi sistem PPN berubah
     */
    public function test_snapshot_tidak_terpengaruh_perubahan_konfigurasi_ppn()
    {
        $noPenjualan = 'TEST-SNAPSHOT-' . uniqid();
        HeaderPenjualan::create([
            'no_penjualan' => $noPenjualan,
            'tgl' => now(),
            'flag' => '5',
            'total' => 11100,
            'total_diskon' => 0,
            'diskon_kuitansi' => 0,
            'subtotal' => 10000,
            'ppn_persen' => 11.00,
            'ppn_nominal' => 1100,
            'bayar' => 11100,
            'kembali' => 0,
            'sistem_bayar' => '1',
            'cara_bayar' => 'cash',
        ]);

        // Simulasikan tarif sistem berbeda di master / config
        config(['ppn.persen' => 12.00]);

        $res = $this->getRiwayatList(['q' => $noPenjualan]);
        $row = collect($res['data'])->firstWhere('no_penjualan', $noPenjualan);

        // Nilai snapshot pada nota tetap 11% dan 1.100, BUKAN 12%
        $this->assertEquals(11.00, (float)$row['ppn_persen']);
        $this->assertEquals(1100, (float)$row['ppn_nominal']);
        $this->assertEquals(11100, $row['total']);
    }

    /**
     * Test 6: Diskon item tidak terhitung dua kali
     */
    public function test_diskon_item_tidak_terhitung_dua_kali()
    {
        $noPenjualan = 'TEST-DISC-ITEM-' . uniqid();
        HeaderPenjualan::create([
            'no_penjualan' => $noPenjualan,
            'tgl' => now(),
            'flag' => '5',
            'total' => 45000,
            'total_diskon' => 5000, // diskon item
            'diskon_kuitansi' => 0,    // diskon nota
            'subtotal' => 45000,
            'ppn_persen' => 0,
            'ppn_nominal' => 0,
            'bayar' => 45000,
            'kembali' => 0,
            'sistem_bayar' => '1',
            'cara_bayar' => 'cash',
        ]);

        // 1 item: 5 pcs @ 10000 = 50000, diskon item = 5000, subtotal baris = 45000
        DetailPenjualan::create([
            'no_penjualan' => $noPenjualan,
            'kodebarang' => $this->kodeBarang,
            'jumlah' => 5,
            'harga_jual' => 10000,
            'diskon' => 5000,
            'subtotal' => 45000,
        ]);

        $res = $this->getRiwayatList(['q' => $noPenjualan]);
        $row = collect($res['data'])->firstWhere('no_penjualan', $noPenjualan);

        $subtotalBarang = collect($row['detail'])->sum(fn($d) => (float)$d['subtotal']);
        // Subtotal barang sudah 45000 (bersih setelah diskon item), tidak dikurangi lagi menjadi 40000
        $this->assertEquals(45000, $subtotalBarang);
        $this->assertEquals(45000, $row['total']);
    }

    /**
     * Test 7: Nota tanpa retur mengembalikan header_retur kosong
     */
    public function test_nota_tanpa_retur()
    {
        $noPenjualan = 'TEST-NO-RETUR-' . uniqid();
        HeaderPenjualan::create([
            'no_penjualan' => $noPenjualan,
            'tgl' => now(),
            'flag' => '5',
            'total' => 100000,
            'total_diskon' => 0,
            'diskon_kuitansi' => 0,
            'subtotal' => 100000,
            'ppn_persen' => 0,
            'ppn_nominal' => 0,
            'bayar' => 100000,
            'kembali' => 0,
            'sistem_bayar' => '1',
            'cara_bayar' => 'cash',
        ]);

        DetailPenjualan::create([
            'no_penjualan' => $noPenjualan,
            'kodebarang' => $this->kodeBarang,
            'jumlah' => 10,
            'harga_jual' => 10000,
            'diskon' => 0,
            'subtotal' => 100000,
        ]);

        $res = $this->getRiwayatList(['q' => $noPenjualan]);
        $row = collect($res['data'])->firstWhere('no_penjualan', $noPenjualan);

        $this->assertNotNull($row);
        $this->assertEmpty($row['header_retur']);
    }

    /**
     * Test 8: Retur Sebagian dengan kuantitas dan nilai tersimpan
     */
    public function test_retur_sebagian()
    {
        $noPenjualan = 'TEST-RET-SEBAGIAN-' . uniqid();
        HeaderPenjualan::create([
            'no_penjualan' => $noPenjualan,
            'tgl' => now(),
            'flag' => '5',
            'total' => 100000,
            'total_diskon' => 0,
            'diskon_kuitansi' => 0,
            'subtotal' => 100000,
            'ppn_persen' => 0,
            'ppn_nominal' => 0,
            'bayar' => 100000,
            'kembali' => 0,
            'sistem_bayar' => '1',
            'cara_bayar' => 'cash',
        ]);

        $detailPjl = DetailPenjualan::create([
            'no_penjualan' => $noPenjualan,
            'kodebarang' => $this->kodeBarang,
            'jumlah' => 10, // terjual 10 pcs
            'harga_jual' => 10000,
            'diskon' => 0,
            'subtotal' => 100000,
        ]);

        // Retur 4 dari 10 pcs (Sebagian)
        $headReturId = DB::table('header_retur_penjualans')->insertGetId([
            'no_retur' => 'RTR-' . uniqid(),
            'no_penjualan' => $noPenjualan,
            'tgl' => now(),
            'total' => 40000,
            'subtotal' => 40000,
            'diskon_kuitansi' => 0,
            'dpp' => 40000,
            'ppn_persen' => 0,
            'ppn_nominal' => 0,
            'status' => '1', // Selesai
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('detail_retur_penjualans')->insert([
            'header_retur_penjualan_id' => $headReturId,
            'no_penjualan' => $noPenjualan,
            'kodebarang' => $this->kodeBarang,
            'detail_penjualan_id' => $detailPjl->id,
            'jumlah' => 4,
            'harga_jual' => 10000,
            'subtotal' => 40000,
            'diskon' => 0,
            'total' => 40000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $res = $this->getRiwayatList(['q' => $noPenjualan]);
        $row = collect($res['data'])->firstWhere('no_penjualan', $noPenjualan);

        $this->assertNotEmpty($row['header_retur']);
        $retur = $row['header_retur'][0];

        $this->assertEquals('1', $retur['status']);
        $this->assertEquals(40000, (float)$retur['total']);
        $this->assertEquals(4, (float)$retur['detail'][0]['jumlah']);
        $this->assertNotNull($retur['detail'][0]['master_barang']);

        // Verifikasi perbandingan kuantitas: retur 4 < terjual 10 => Retur Sebagian
        $qtyTerjual = collect($row['detail'])->sum(fn($d) => (float)$d['jumlah']);
        $qtyRetur = collect($retur['detail'])->sum(fn($d) => (float)$d['jumlah']);
        $this->assertEquals(10, $qtyTerjual);
        $this->assertEquals(4, $qtyRetur);
        $this->assertTrue($qtyRetur < $qtyTerjual);

        // Total Tagihan penjualan tidak terpotong oleh retur
        $this->assertEquals(100000, $row['total']);
    }

    /**
     * Test 9: Retur Seluruhnya (seluruh kuantitas diretur)
     */
    public function test_retur_seluruhnya()
    {
        $noPenjualan = 'TEST-RET-PENUH-' . uniqid();
        HeaderPenjualan::create([
            'no_penjualan' => $noPenjualan,
            'tgl' => now(),
            'flag' => '5',
            'total' => 100000,
            'total_diskon' => 0,
            'diskon_kuitansi' => 0,
            'subtotal' => 100000,
            'ppn_persen' => 0,
            'ppn_nominal' => 0,
            'bayar' => 100000,
            'kembali' => 0,
            'sistem_bayar' => '1',
            'cara_bayar' => 'cash',
        ]);

        $detailPjl = DetailPenjualan::create([
            'no_penjualan' => $noPenjualan,
            'kodebarang' => $this->kodeBarang,
            'jumlah' => 10,
            'harga_jual' => 10000,
            'diskon' => 0,
            'subtotal' => 100000,
        ]);

        // Retur 10 dari 10 pcs (Seluruhnya)
        $headReturId = DB::table('header_retur_penjualans')->insertGetId([
            'no_retur' => 'RTR-' . uniqid(),
            'no_penjualan' => $noPenjualan,
            'tgl' => now(),
            'total' => 100000,
            'subtotal' => 100000,
            'diskon_kuitansi' => 0,
            'dpp' => 100000,
            'ppn_persen' => 0,
            'ppn_nominal' => 0,
            'status' => '1',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('detail_retur_penjualans')->insert([
            'header_retur_penjualan_id' => $headReturId,
            'no_penjualan' => $noPenjualan,
            'kodebarang' => $this->kodeBarang,
            'detail_penjualan_id' => $detailPjl->id,
            'jumlah' => 10,
            'harga_jual' => 10000,
            'subtotal' => 100000,
            'diskon' => 0,
            'total' => 100000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $res = $this->getRiwayatList(['q' => $noPenjualan]);
        $row = collect($res['data'])->firstWhere('no_penjualan', $noPenjualan);

        $qtyTerjual = collect($row['detail'])->sum(fn($d) => (float)$d['jumlah']);
        $qtyRetur = collect($row['header_retur'][0]['detail'])->sum(fn($d) => (float)$d['jumlah']);
        $this->assertEquals(10, $qtyTerjual);
        $this->assertEquals(10, $qtyRetur);
        $this->assertTrue($qtyRetur >= $qtyTerjual);
    }

    /**
     * Test 10: Retur berkali-kali pada satu nota & akumulasi kumulatif
     */
    public function test_retur_berkali_kali()
    {
        $noPenjualan = 'TEST-MULTI-RET-' . uniqid();
        HeaderPenjualan::create([
            'no_penjualan' => $noPenjualan,
            'tgl' => now(),
            'flag' => '5',
            'total' => 100000,
            'total_diskon' => 0,
            'diskon_kuitansi' => 0,
            'subtotal' => 100000,
            'ppn_persen' => 0,
            'ppn_nominal' => 0,
            'bayar' => 100000,
            'kembali' => 0,
            'sistem_bayar' => '1',
            'cara_bayar' => 'cash',
        ]);

        $detailPjl = DetailPenjualan::create([
            'no_penjualan' => $noPenjualan,
            'kodebarang' => $this->kodeBarang,
            'jumlah' => 10,
            'harga_jual' => 10000,
            'diskon' => 0,
            'subtotal' => 100000,
        ]);

        // Retur 1: 3 pcs (30000)
        $retur1Id = DB::table('header_retur_penjualans')->insertGetId([
            'no_retur' => 'RTR-1-' . uniqid(),
            'no_penjualan' => $noPenjualan,
            'tgl' => now()->subDay(),
            'total' => 30000,
            'status' => '1',
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ]);
        DB::table('detail_retur_penjualans')->insert([
            'header_retur_penjualan_id' => $retur1Id,
            'no_penjualan' => $noPenjualan,
            'kodebarang' => $this->kodeBarang,
            'detail_penjualan_id' => $detailPjl->id,
            'jumlah' => 3,
            'harga_jual' => 10000,
            'subtotal' => 30000,
            'total' => 30000,
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ]);

        // Retur 2: 7 pcs (70000)
        $retur2Id = DB::table('header_retur_penjualans')->insertGetId([
            'no_retur' => 'RTR-2-' . uniqid(),
            'no_penjualan' => $noPenjualan,
            'tgl' => now(),
            'total' => 70000,
            'status' => '1',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('detail_retur_penjualans')->insert([
            'header_retur_penjualan_id' => $retur2Id,
            'no_penjualan' => $noPenjualan,
            'kodebarang' => $this->kodeBarang,
            'detail_penjualan_id' => $detailPjl->id,
            'jumlah' => 7,
            'harga_jual' => 10000,
            'subtotal' => 70000,
            'total' => 70000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $res = $this->getRiwayatList(['q' => $noPenjualan]);
        $row = collect($res['data'])->firstWhere('no_penjualan', $noPenjualan);

        // Ada 2 transaksi retur
        $this->assertCount(2, $row['header_retur']);

        // Akumulasi kumulatif
        $totalNilaiRetur = collect($row['header_retur'])->where('status', '1')->sum(fn($r) => (float)$r['total']);
        $totalQtyRetur = collect($row['header_retur'])->where('status', '1')->flatMap(fn($r) => $r['detail'])->sum(fn($d) => (float)$d['jumlah']);

        $this->assertEquals(100000, $totalNilaiRetur);
        $this->assertEquals(10, $totalQtyRetur);
        $this->assertTrue($totalQtyRetur >= 10); // Menjadi Retur Seluruhnya secara kumulatif
    }

    /**
     * Test 11: Retur draft tidak dihitung dalam total kumulatif final
     */
    public function test_retur_draft_tidak_dihitung_kumulatif()
    {
        $noPenjualan = 'TEST-DRAFT-RET-' . uniqid();
        HeaderPenjualan::create([
            'no_penjualan' => $noPenjualan,
            'tgl' => now(),
            'flag' => '5',
            'total' => 100000,
            'total_diskon' => 0,
            'diskon_kuitansi' => 0,
            'subtotal' => 100000,
            'ppn_persen' => 0,
            'ppn_nominal' => 0,
            'bayar' => 100000,
            'kembali' => 0,
            'sistem_bayar' => '1',
            'cara_bayar' => 'cash',
        ]);

        $detailPjl = DetailPenjualan::create([
            'no_penjualan' => $noPenjualan,
            'kodebarang' => $this->kodeBarang,
            'jumlah' => 10,
            'harga_jual' => 10000,
            'diskon' => 0,
            'subtotal' => 100000,
        ]);

        // Retur 1 Selesai: 3 pcs (30000)
        $retur1Id = DB::table('header_retur_penjualans')->insertGetId([
            'no_retur' => 'RTR-FIN-' . uniqid(),
            'no_penjualan' => $noPenjualan,
            'tgl' => now()->subDay(),
            'total' => 30000,
            'status' => '1', // Selesai
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ]);
        DB::table('detail_retur_penjualans')->insert([
            'header_retur_penjualan_id' => $retur1Id,
            'no_penjualan' => $noPenjualan,
            'kodebarang' => $this->kodeBarang,
            'detail_penjualan_id' => $detailPjl->id,
            'jumlah' => 3,
            'harga_jual' => 10000,
            'subtotal' => 30000,
            'total' => 30000,
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ]);

        // Retur 2 Draft: 2 pcs (20000)
        $retur2Id = DB::table('header_retur_penjualans')->insertGetId([
            'no_retur' => 'RTR-DRF-' . uniqid(),
            'no_penjualan' => $noPenjualan,
            'tgl' => now(),
            'total' => 20000,
            'status' => '', // Draft
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('detail_retur_penjualans')->insert([
            'header_retur_penjualan_id' => $retur2Id,
            'no_penjualan' => $noPenjualan,
            'kodebarang' => $this->kodeBarang,
            'detail_penjualan_id' => $detailPjl->id,
            'jumlah' => 2,
            'harga_jual' => 10000,
            'subtotal' => 20000,
            'total' => 20000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $res = $this->getRiwayatList(['q' => $noPenjualan]);
        $row = collect($res['data'])->firstWhere('no_penjualan', $noPenjualan);

        // Hanya retur berstatus '1' yang dihitung kumulatif
        $returSelesai = collect($row['header_retur'])->where('status', '1');
        $returDraft = collect($row['header_retur'])->where('status', '!=', '1');

        $this->assertEquals(30000, $returSelesai->sum(fn($r) => (float)$r['total']));
        $this->assertEquals(20000, $returDraft->sum(fn($r) => (float)$r['total']));
    }

    /**
     * Test 12: Retur historis tanpa snapshot PPN dan Diskon Nota
     */
    public function test_retur_historis_tanpa_snapshot()
    {
        $noPenjualan = 'TEST-HIST-RET-' . uniqid();
        HeaderPenjualan::create([
            'no_penjualan' => $noPenjualan,
            'tgl' => now(),
            'flag' => '5',
            'total' => 50000,
            'total_diskon' => 0,
            'diskon_kuitansi' => 0,
            'subtotal' => 50000,
            'ppn_persen' => 0,
            'ppn_nominal' => 0,
            'bayar' => 50000,
            'kembali' => 0,
            'sistem_bayar' => '1',
            'cara_bayar' => 'cash',
        ]);

        $detailPjl = DetailPenjualan::create([
            'no_penjualan' => $noPenjualan,
            'kodebarang' => $this->kodeBarang,
            'jumlah' => 5,
            'harga_jual' => 10000,
            'diskon' => 0,
            'subtotal' => 50000,
        ]);

        // Retur lama: subtotal, diskon_kuitansi, ppn_persen, ppn_nominal semuanya null
        $returId = DB::table('header_retur_penjualans')->insertGetId([
            'no_retur' => 'RTR-HIST-' . uniqid(),
            'no_penjualan' => $noPenjualan,
            'tgl' => now(),
            'total' => 20000,
            'subtotal' => null,
            'diskon_kuitansi' => null,
            'dpp' => null,
            'ppn_persen' => null,
            'ppn_nominal' => null,
            'status' => '1',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('detail_retur_penjualans')->insert([
            'header_retur_penjualan_id' => $returId,
            'no_penjualan' => $noPenjualan,
            'kodebarang' => $this->kodeBarang,
            'detail_penjualan_id' => $detailPjl->id,
            'jumlah' => 2,
            'harga_jual' => 10000,
            'subtotal' => 20000,
            'diskon_nota' => null,
            'dpp' => null,
            'ppn_nominal' => null,
            'total' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $res = $this->getRiwayatList(['q' => $noPenjualan]);
        $row = collect($res['data'])->firstWhere('no_penjualan', $noPenjualan);

        $retur = $row['header_retur'][0];
        $this->assertEquals(20000, (float)$retur['total']);
        $this->assertNull($retur['ppn_nominal']);
        $this->assertNull($retur['diskon_kuitansi']);
    }

    /**
     * Test 13: Endpoint list-retur memuat relasi lengkap dan sesuai kasus nilai tersimpan Rp42.378
     */
    public function test_list_retur_penjualan_memuat_relasi_dan_nilai_tersimpan()
    {
        $noPenjualan = 'TEST-PJL-42378-' . uniqid();
        $headerPjl = HeaderPenjualan::create([
            'no_penjualan' => $noPenjualan,
            'tgl' => now(),
            'flag' => '5',
            'total' => 125438.88,
            'total_diskon' => 0,
            'diskon_kuitansi' => 0,
            'subtotal' => 113008,
            'ppn_persen' => 11.00,
            'ppn_nominal' => 12430.88,
            'bayar' => 125438.88,
            'kembali' => 0,
            'sistem_bayar' => '1',
            'cara_bayar' => 'cash',
        ]);

        $detailPjl = DetailPenjualan::create([
            'no_penjualan' => $noPenjualan,
            'kodebarang' => $this->kodeBarang,
            'motif' => 'MOTIF-TEST',
            'jumlah' => 16,
            'harga_jual' => 7063,
            'diskon' => 0,
            'subtotal' => 113008,
        ]);

        $noRetur = 'TEST-RTR-42378-' . uniqid();
        $returId = DB::table('header_retur_penjualans')->insertGetId([
            'no_retur' => $noRetur,
            'no_penjualan' => $noPenjualan,
            'tgl' => now(),
            'total' => 42378,
            'subtotal' => null,
            'diskon_kuitansi' => null,
            'dpp' => null,
            'ppn_persen' => null,
            'ppn_nominal' => null,
            'status' => '1',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('detail_retur_penjualans')->insert([
            'header_retur_penjualan_id' => $returId,
            'no_penjualan' => $noPenjualan,
            'kodebarang' => $this->kodeBarang,
            'detail_penjualan_id' => $detailPjl->id,
            'jumlah' => 6,
            'harga_jual' => 7063,
            'diskon' => 0,
            'subtotal' => 42378,
            'total' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Panggil endpoint /v1/transaksi/retur/list-retur
        $request = Request::create('/v1/transaksi/retur/list-retur', 'GET', [
            'from' => date('Y-m-01'),
            'to' => date('Y-m-d'),
            'q' => $noRetur,
        ]);
        app()->instance('request', $request);

        $returController = app(\App\Http\Controllers\Api\Transaksi\Retur\ReturPenjualanController::class);
        $res = json_decode($returController->retur()->getContent(), true);

        $this->assertNotEmpty($res['data']);
        $row = collect($res['data'])->firstWhere('no_retur', $noRetur);
        $this->assertNotNull($row);

        // Verifikasi nilai retur tersimpan murni Rp42.378 tanpa kalkulasi rekaan PPN
        $this->assertEquals(42378, (float)$row['total']);
        $this->assertNull($row['ppn_nominal']);
        $this->assertNull($row['diskon_kuitansi']);

        // Verifikasi detail retur memuat relasi master_barang dan detail_penjualan
        $this->assertNotEmpty($row['detail']);
        $det = $row['detail'][0];
        $this->assertEquals(6, (float)$det['jumlah']);
        $this->assertEquals(7063, (float)$det['harga_jual']);
        $this->assertEquals(42378, (float)$det['subtotal']);
        $this->assertNotNull($det['master_barang']);
        $this->assertNotNull($det['detail_penjualan']);
        $this->assertEquals('MOTIF-TEST', $det['detail_penjualan']['motif']);
        $this->assertEquals(16, (float)$det['detail_penjualan']['jumlah']);

        // Verifikasi relasi header_penjualan
        $this->assertNotNull($row['header_penjualan']);
        $this->assertEquals($noPenjualan, $row['header_penjualan']['no_penjualan']);
    }
}
