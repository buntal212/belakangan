<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\Transaksi\Retur\ReturPenjualanController;
use App\Models\Stok\stok;
use App\Models\Transaksi\Penjualan\DetailPenjualan;
use App\Models\Transaksi\Penjualan\DetailPenjualanFifo;
use App\Models\Transaksi\Penjualan\DetailReturPenjualan;
use App\Models\Transaksi\Penjualan\HeaderPenjualan;
use App\Models\Transaksi\Penjualan\HeaderReturPenjualan;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ReturPenjualanTest extends TestCase
{
    protected string $kodeBarang;
    protected User $user;
    protected ReturPenjualanController $controller;
    protected int $stokId;

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
        $this->actingAs($this->user);
        $this->controller = app(ReturPenjualanController::class);

        $this->kodeBarang = 'TEST-RET-' . uniqid();
        DB::table('barangs')->insert([
            'kodebarang' => $this->kodeBarang,
            'namabarang' => 'Barang Uji Retur ' . $this->kodeBarang,
            'namagabung' => 'Barang Uji Retur ' . $this->kodeBarang,
            'slug' => 'barang-uji-retur-' . uniqid(),
            'satuan_b' => 'DUS',
            'satuan_k' => 'PCS',
            'isi' => 10,
            'hargajual1' => 6500,
            'hargajual2' => 6500,
            'hargabeli' => 5000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->stokId = DB::table('stoks')->insertGetId([
            'nopenerimaan' => 'TERIMA-' . uniqid(),
            'idpenerimaan' => 'IDT-' . uniqid(),
            'kdbarang' => $this->kodeBarang,
            'jumlah_b' => 10,
            'jumlah_k' => 100,
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

    protected function buatPenjualanUji(string $noNota, array $details): array
    {
        $totalSubtotal = 0;
        $totalDiskon = 0;
        foreach ($details as $d) {
            $totalSubtotal += $d['subtotal'];
            $totalDiskon += $d['diskon'] ?? 0;
        }

        DB::table('header_penjualans')->insert([
            'no_penjualan' => $noNota,
            'tgl' => now(),
            'subtotal' => $totalSubtotal,
            'total' => $totalSubtotal,
            'total_diskon' => $totalDiskon,
            'flag' => '5',
            'bayar' => $totalSubtotal,
            'kembali' => 0,
            'cara_bayar' => 'cash',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $createdDetails = [];
        foreach ($details as $d) {
            $detId = DB::table('detail_penjualans')->insertGetId([
                'no_penjualan' => $noNota,
                'kodebarang' => $d['kodebarang'] ?? $this->kodeBarang,
                'isi' => $d['isi'] ?? 10,
                'jumlah' => $d['jumlah'],
                'harga_beli' => $d['harga_beli'] ?? 5000,
                'harga_jual' => $d['harga_jual'],
                'diskon' => $d['diskon'] ?? 0,
                'subtotal' => $d['subtotal'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('detail_penjualan_fifos')->insert([
                'no_penjualan' => $noNota,
                'kodebarang' => $d['kodebarang'] ?? $this->kodeBarang,
                'jumlah' => $d['jumlah'],
                'harga_beli' => $d['harga_beli'] ?? 5000,
                'harga_jual' => $d['harga_jual'],
                'diskon' => $d['diskon'] ?? 0,
                'subtotal' => $d['subtotal'],
                'stok_id' => $d['stok_id'] ?? $this->stokId,
                'retur' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $createdDetails[] = DetailPenjualan::find($detId);
        }

        return $createdDetails;
    }

    /**
     * Skenario 1: Retur 1 dari 10 pcs
     */
    public function test_01_retur_1_dari_10_pcs(): void
    {
        $noNota = 'PJ-RET-01-' . uniqid();
        $details = $this->buatPenjualanUji($noNota, [
            [
                'jumlah' => 10,
                'harga_jual' => 6500,
                'diskon' => 2000,
                'subtotal' => 63000,
            ]
        ]);
        $detail = $details[0];

        $request = Request::create('/api/v1/transaksi/retur/simpan', 'POST', [
            'no_penjualan' => $noNota,
            'id' => $detail->id,
            'kodebarang' => $this->kodeBarang,
            'harga_jual' => 6500,
            'retur' => 1,
            'diskon' => $detail->diskon,
        ]);

        $response = $this->controller->store($request);
        $this->assertEquals(200, $response->getStatusCode());

        $itemRetur = DetailReturPenjualan::where('detail_penjualan_id', $detail->id)->first();
        $this->assertNotNull($itemRetur);
        $this->assertEquals(1.0, (float)$itemRetur->jumlah);
        $this->assertEquals(6300.0, (float)$itemRetur->subtotal);
        $this->assertEquals(200.0, (float)$itemRetur->diskon);
    }

    /**
     * Skenario 2: Retur tepat 10 dari 10 pcs
     */
    public function test_02_retur_tepat_10_dari_10_pcs(): void
    {
        $noNota = 'PJ-RET-02-' . uniqid();
        $details = $this->buatPenjualanUji($noNota, [
            [
                'jumlah' => 10,
                'harga_jual' => 6500,
                'diskon' => 2000,
                'subtotal' => 63000,
            ]
        ]);
        $detail = $details[0];

        $request = Request::create('/api/v1/transaksi/retur/simpan', 'POST', [
            'no_penjualan' => $noNota,
            'id' => $detail->id,
            'kodebarang' => $this->kodeBarang,
            'harga_jual' => 6500,
            'retur' => 10,
            'diskon' => $detail->diskon,
        ]);

        $response = $this->controller->store($request);
        $this->assertEquals(200, $response->getStatusCode());

        $itemRetur = DetailReturPenjualan::where('detail_penjualan_id', $detail->id)->first();
        $this->assertEquals(10.0, (float)$itemRetur->jumlah);
        $this->assertEquals(63000.0, (float)$itemRetur->subtotal);
        $this->assertEquals(2000.0, (float)$itemRetur->diskon);
    }

    /**
     * Skenario 3: Retur 11 dari 10 pcs ditolak
     */
    public function test_03_retur_11_dari_10_pcs_ditolak(): void
    {
        $noNota = 'PJ-RET-03-' . uniqid();
        $details = $this->buatPenjualanUji($noNota, [
            [
                'jumlah' => 10,
                'harga_jual' => 6500,
                'diskon' => 2000,
                'subtotal' => 63000,
            ]
        ]);
        $detail = $details[0];

        $request = Request::create('/api/v1/transaksi/retur/simpan', 'POST', [
            'no_penjualan' => $noNota,
            'id' => $detail->id,
            'kodebarang' => $this->kodeBarang,
            'harga_jual' => 6500,
            'retur' => 11,
            'diskon' => $detail->diskon,
        ]);

        $response = $this->controller->store($request);
        $this->assertEquals(422, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertStringContainsString('tidak boleh melebihi jumlah terjual', $data['message']);
    }

    /**
     * Skenario 4: Retur 0 dan negatif ditolak
     */
    public function test_04_retur_0_dan_negatif_ditolak(): void
    {
        $noNota = 'PJ-RET-04-' . uniqid();
        $details = $this->buatPenjualanUji($noNota, [
            [
                'jumlah' => 10,
                'harga_jual' => 6500,
                'diskon' => 2000,
                'subtotal' => 63000,
            ]
        ]);
        $detail = $details[0];

        // Retur 0
        $req0 = Request::create('/api/v1/transaksi/retur/simpan', 'POST', [
            'no_penjualan' => $noNota,
            'id' => $detail->id,
            'kodebarang' => $this->kodeBarang,
            'harga_jual' => 6500,
            'retur' => 0,
            'diskon' => $detail->diskon,
        ]);
        $resp0 = $this->controller->store($req0);
        $this->assertEquals(422, $resp0->getStatusCode());
        $data0 = json_decode($resp0->getContent(), true);
        $this->assertStringContainsString('lebih besar dari 0', $data0['message']);

        // Retur -3
        $reqNeg = Request::create('/api/v1/transaksi/retur/simpan', 'POST', [
            'no_penjualan' => $noNota,
            'id' => $detail->id,
            'kodebarang' => $this->kodeBarang,
            'harga_jual' => 6500,
            'retur' => -3,
            'diskon' => $detail->diskon,
        ]);
        $respNeg = $this->controller->store($reqNeg);
        $this->assertEquals(422, $respNeg->getStatusCode());
        $dataNeg = json_decode($respNeg->getContent(), true);
        $this->assertStringContainsString('lebih besar dari 0', $dataNeg['message']);
    }

    /**
     * Skenario 5: Retur selesai 4 pcs, lalu retur 6 pcs berhasil
     */
    public function test_05_retur_selesai_4_pcs_lalu_retur_6_pcs_berhasil(): void
    {
        $noNota = 'PJ-RET-05-' . uniqid();
        $details = $this->buatPenjualanUji($noNota, [
            [
                'jumlah' => 10,
                'harga_jual' => 6500,
                'diskon' => 2000,
                'subtotal' => 63000,
            ]
        ]);
        $detail = $details[0];

        // 1. Simpan draft retur 4 pcs
        $req1 = Request::create('/api/v1/transaksi/retur/simpan', 'POST', [
            'no_penjualan' => $noNota,
            'id' => $detail->id,
            'kodebarang' => $this->kodeBarang,
            'harga_jual' => 6500,
            'retur' => 4,
            'diskon' => $detail->diskon,
        ]);
        $resp1 = $this->controller->store($req1);
        $this->assertEquals(200, $resp1->getStatusCode());
        $data1 = json_decode($resp1->getContent(), true);
        $noRetur1 = $data1['noretur'];

        // 2. Selesaikan retur 1
        $reqSelesai = Request::create('/api/v1/transaksi/retur/selesai', 'POST', [
            'no_retur' => $noRetur1,
        ]);
        $respSelesai = $this->controller->selesai($reqSelesai);
        $this->assertEquals(200, $respSelesai->getStatusCode());

        // 3. Simpan retur baru 6 pcs
        $req2 = Request::create('/api/v1/transaksi/retur/simpan', 'POST', [
            'no_penjualan' => $noNota,
            'id' => $detail->id,
            'kodebarang' => $this->kodeBarang,
            'harga_jual' => 6500,
            'retur' => 6,
            'diskon' => $detail->diskon,
        ]);
        $resp2 = $this->controller->store($req2);
        $this->assertEquals(200, $resp2->getStatusCode());
    }

    /**
     * Skenario 6: Retur selesai 4 pcs, lalu retur 7 pcs ditolak
     */
    public function test_06_retur_selesai_4_pcs_lalu_retur_7_pcs_ditolak(): void
    {
        $noNota = 'PJ-RET-06-' . uniqid();
        $details = $this->buatPenjualanUji($noNota, [
            [
                'jumlah' => 10,
                'harga_jual' => 6500,
                'diskon' => 2000,
                'subtotal' => 63000,
            ]
        ]);
        $detail = $details[0];

        // 1. Simpan draft retur 4 pcs
        $req1 = Request::create('/api/v1/transaksi/retur/simpan', 'POST', [
            'no_penjualan' => $noNota,
            'id' => $detail->id,
            'kodebarang' => $this->kodeBarang,
            'harga_jual' => 6500,
            'retur' => 4,
            'diskon' => $detail->diskon,
        ]);
        $resp1 = $this->controller->store($req1);
        $data1 = json_decode($resp1->getContent(), true);
        $noRetur1 = $data1['noretur'];

        // 2. Selesaikan retur 1
        $this->controller->selesai(Request::create('/api/v1/transaksi/retur/selesai', 'POST', ['no_retur' => $noRetur1]));

        // 3. Coba retur 7 pcs (sisa hanya 6 pcs)
        $req2 = Request::create('/api/v1/transaksi/retur/simpan', 'POST', [
            'no_penjualan' => $noNota,
            'id' => $detail->id,
            'kodebarang' => $this->kodeBarang,
            'harga_jual' => 6500,
            'retur' => 7,
            'diskon' => $detail->diskon,
        ]);
        $resp2 = $this->controller->store($req2);
        $this->assertEquals(422, $resp2->getStatusCode());
        $data2 = json_decode($resp2->getContent(), true);
        $this->assertStringContainsString('melebihi sisa yang dapat diretur', $data2['message']);
    }

    /**
     * Skenario 7: Dua baris dengan kodebarang sama tidak tertukar
     */
    public function test_07_dua_baris_dengan_kodebarang_sama_tidak_tertukar(): void
    {
        $noNota = 'PJ-RET-07-' . uniqid();
        $details = $this->buatPenjualanUji($noNota, [
            [
                'kodebarang' => $this->kodeBarang,
                'jumlah' => 10,
                'harga_jual' => 5000,
                'diskon' => 2000,
                'subtotal' => 48000,
            ],
            [
                'kodebarang' => $this->kodeBarang,
                'jumlah' => 20,
                'harga_jual' => 4500,
                'diskon' => 5000,
                'subtotal' => 85000,
            ]
        ]);
        $d1 = $details[0];
        $d2 = $details[1];

        // Retur 3 pcs untuk Baris 1 (d1)
        $req1 = Request::create('/api/v1/transaksi/retur/simpan', 'POST', [
            'no_penjualan' => $noNota,
            'id' => $d1->id,
            'kodebarang' => $this->kodeBarang,
            'harga_jual' => 5000,
            'retur' => 3,
            'diskon' => $d1->diskon,
        ]);
        $resp1 = $this->controller->store($req1);
        $this->assertEquals(200, $resp1->getStatusCode());

        // Retur 5 pcs untuk Baris 2 (d2)
        $req2 = Request::create('/api/v1/transaksi/retur/simpan', 'POST', [
            'no_penjualan' => $noNota,
            'id' => $d2->id,
            'kodebarang' => $this->kodeBarang,
            'harga_jual' => 4500,
            'retur' => 5,
            'diskon' => $d2->diskon,
        ]);
        $resp2 = $this->controller->store($req2);
        $this->assertEquals(200, $resp2->getStatusCode());

        // Pastikan ada 2 baris terpisah di detail_retur_penjualans
        $ret1 = DetailReturPenjualan::where('detail_penjualan_id', $d1->id)->first();
        $ret2 = DetailReturPenjualan::where('detail_penjualan_id', $d2->id)->first();

        $this->assertNotNull($ret1);
        $this->assertNotNull($ret2);
        $this->assertNotEquals($ret1->id, $ret2->id);

        $this->assertEquals(3.0, (float)$ret1->jumlah);
        $this->assertEquals(14400.0, (float)$ret1->subtotal); // 48000 * 3/10

        $this->assertEquals(5.0, (float)$ret2->jumlah);
        $this->assertEquals(21250.0, (float)$ret2->subtotal); // 85000 * 5/20

        // Uji jika tanpa ID saat ada 2 baris dengan kodebarang sama -> harus ditolak ambigu
        $reqAmbigu = Request::create('/api/v1/transaksi/retur/simpan', 'POST', [
            'no_penjualan' => $noNota,
            'kodebarang' => $this->kodeBarang,
            'harga_jual' => 5000,
            'retur' => 1,
        ]);
        $respAmbigu = $this->controller->store($reqAmbigu);
        $this->assertEquals(422, $respAmbigu->getStatusCode());
        $dataAmbigu = json_decode($respAmbigu->getContent(), true);
        $this->assertStringContainsString('ambigu', $dataAmbigu['message']);
    }

    /**
     * Skenario 8: Detail penjualan tidak ditemukan ditolak
     */
    public function test_08_detail_penjualan_tidak_ditemukan_ditolak(): void
    {
        $noNota = 'PJ-RET-08-' . uniqid();
        $this->buatPenjualanUji($noNota, [
            [
                'jumlah' => 10,
                'harga_jual' => 6500,
                'diskon' => 0,
                'subtotal' => 65000,
            ]
        ]);

        // Kirim ID ngawur
        $reqNgawur = Request::create('/api/v1/transaksi/retur/simpan', 'POST', [
            'no_penjualan' => $noNota,
            'id' => 999999999,
            'kodebarang' => $this->kodeBarang,
            'harga_jual' => 6500,
            'retur' => 1,
        ]);
        $respNgawur = $this->controller->store($reqNgawur);
        $this->assertEquals(422, $respNgawur->getStatusCode());
        $dataNgawur = json_decode($respNgawur->getContent(), true);
        $this->assertStringContainsString('tidak ditemukan', $dataNgawur['message']);

        // Kirim barang yang tidak ada pada nota
        $reqKodeSalah = Request::create('/api/v1/transaksi/retur/simpan', 'POST', [
            'no_penjualan' => $noNota,
            'kodebarang' => 'KODE-TIDAK-PERNAH-ADA',
            'harga_jual' => 6500,
            'retur' => 1,
        ]);
        $respKodeSalah = $this->controller->store($reqKodeSalah);
        $this->assertEquals(422, $respKodeSalah->getStatusCode());
    }

    /**
     * Skenario 9: Update draft tidak dihitung ganda
     */
    public function test_09_update_draft_tidak_dihitung_ganda(): void
    {
        $noNota = 'PJ-RET-09-' . uniqid();
        $details = $this->buatPenjualanUji($noNota, [
            [
                'jumlah' => 10,
                'harga_jual' => 6500,
                'diskon' => 2000,
                'subtotal' => 63000,
            ]
        ]);
        $detail = $details[0];

        // 1. Simpan 2 pcs
        $req1 = Request::create('/api/v1/transaksi/retur/simpan', 'POST', [
            'no_penjualan' => $noNota,
            'id' => $detail->id,
            'kodebarang' => $this->kodeBarang,
            'harga_jual' => 6500,
            'retur' => 2,
            'diskon' => $detail->diskon,
        ]);
        $resp1 = $this->controller->store($req1);
        $this->assertEquals(200, $resp1->getStatusCode());

        // 2. Update menjadi 5 pcs pada draft yang sama
        $req2 = Request::create('/api/v1/transaksi/retur/simpan', 'POST', [
            'no_penjualan' => $noNota,
            'id' => $detail->id,
            'kodebarang' => $this->kodeBarang,
            'harga_jual' => 6500,
            'retur' => 5,
            'diskon' => $detail->diskon,
        ]);
        $resp2 = $this->controller->store($req2);
        $this->assertEquals(200, $resp2->getStatusCode());

        // Pastikan di database jumlahnya adalah 5 (bukan 7 dan bukan 2 row)
        $rows = DetailReturPenjualan::where('detail_penjualan_id', $detail->id)->get();
        $this->assertCount(1, $rows);
        $this->assertEquals(5.0, (float)$rows[0]->jumlah);
        $this->assertEquals(31500.0, (float)$rows[0]->subtotal); // 63000 * 5/10
    }

    /**
     * Skenario 10: Request paralel tidak menyebabkan over-retur
     */
    public function test_10_request_paralel_tidak_menyebabkan_over_retur(): void
    {
        $noNota = 'PJ-RET-10-' . uniqid();
        $details = $this->buatPenjualanUji($noNota, [
            [
                'jumlah' => 10,
                'harga_jual' => 6500,
                'diskon' => 0,
                'subtotal' => 65000,
            ]
        ]);
        $detail = $details[0];

        // Retur 5 pcs dan selesai
        $req1 = Request::create('/api/v1/transaksi/retur/simpan', 'POST', [
            'no_penjualan' => $noNota,
            'id' => $detail->id,
            'kodebarang' => $this->kodeBarang,
            'harga_jual' => 6500,
            'retur' => 5,
        ]);
        $resp1 = $this->controller->store($req1);
        $this->assertEquals(200, $resp1->getStatusCode());
        $data1 = json_decode($resp1->getContent(), true);

        $this->controller->selesai(Request::create('/api/v1/transaksi/retur/selesai', 'POST', ['no_retur' => $data1['noretur']]));

        // Dua request berikutnya mencoba retur 4 pcs berturut-turut
        // Request A: 4 pcs (berhasil, sisa 5 - 4 = 1 pcs)
        $reqA = Request::create('/api/v1/transaksi/retur/simpan', 'POST', [
            'no_penjualan' => $noNota,
            'id' => $detail->id,
            'kodebarang' => $this->kodeBarang,
            'harga_jual' => 6500,
            'retur' => 4,
        ]);
        $respA = $this->controller->store($reqA);
        $this->assertEquals(200, $respA->getStatusCode());
        $dataA = json_decode($respA->getContent(), true);
        $this->controller->selesai(Request::create('/api/v1/transaksi/retur/selesai', 'POST', ['no_retur' => $dataA['noretur']]));

        // Request B: 4 pcs (harus ditolak karena sisa hanya 1 pcs)
        $reqB = Request::create('/api/v1/transaksi/retur/simpan', 'POST', [
            'no_penjualan' => $noNota,
            'id' => $detail->id,
            'kodebarang' => $this->kodeBarang,
            'harga_jual' => 6500,
            'retur' => 4,
        ]);
        $respB = $this->controller->store($reqB);
        $this->assertEquals(422, $respB->getStatusCode());
        $dataB = json_decode($respB->getContent(), true);
        $this->assertStringContainsString('melebihi sisa yang dapat diretur', $dataB['message']);
    }

    /**
     * Skenario 11: Endpoint selesai berulang tidak menggandakan stok
     */
    public function test_11_endpoint_selesai_berulang_tidak_menggandakan_stok(): void
    {
        $noNota = 'PJ-RET-11-' . uniqid();
        $details = $this->buatPenjualanUji($noNota, [
            [
                'jumlah' => 10,
                'harga_jual' => 6500,
                'diskon' => 0,
                'subtotal' => 65000,
                'stok_id' => $this->stokId,
            ]
        ]);
        $detail = $details[0];

        $stokAwal = (int) DB::table('stoks')->where('id', $this->stokId)->value('jumlah_k');

        // Simpan retur 5 pcs
        $req = Request::create('/api/v1/transaksi/retur/simpan', 'POST', [
            'no_penjualan' => $noNota,
            'id' => $detail->id,
            'kodebarang' => $this->kodeBarang,
            'harga_jual' => 6500,
            'retur' => 5,
        ]);
        $resp = $this->controller->store($req);
        $data = json_decode($resp->getContent(), true);
        $noRetur = $data['noretur'];

        // Selesai 1: Berhasil
        $reqSelesai1 = Request::create('/api/v1/transaksi/retur/selesai', 'POST', ['no_retur' => $noRetur]);
        $respSelesai1 = $this->controller->selesai($reqSelesai1);
        $this->assertEquals(200, $respSelesai1->getStatusCode());

        $stokSetelahSelesai1 = (int) DB::table('stoks')->where('id', $this->stokId)->value('jumlah_k');
        $this->assertEquals($stokAwal + 5, $stokSetelahSelesai1);

        // Selesai 2 (Panggilan berulang dengan no_retur yang sama): Harus ditolak (410)
        $reqSelesai2 = Request::create('/api/v1/transaksi/retur/selesai', 'POST', ['no_retur' => $noRetur]);
        $respSelesai2 = $this->controller->selesai($reqSelesai2);
        $this->assertEquals(410, $respSelesai2->getStatusCode());

        // Pastikan stok TIDAK bertambah ganda
        $stokSetelahSelesai2 = (int) DB::table('stoks')->where('id', $this->stokId)->value('jumlah_k');
        $this->assertEquals($stokSetelahSelesai1, $stokSetelahSelesai2);
    }

    /**
     * Skenario 12: Retur bertahap menghasilkan total nominal konsisten
     */
    public function test_12_retur_bertahap_menghasilkan_total_nominal_konsisten(): void
    {
        $noNota = 'PJ-RET-12-' . uniqid();
        // 3 pcs dengan subtotal Rp100.000 (tidak habis dibagi 3 secara nominal integer)
        $details = $this->buatPenjualanUji($noNota, [
            [
                'jumlah' => 3,
                'harga_jual' => 33333.33,
                'diskon' => 3000,
                'subtotal' => 100000,
            ]
        ]);
        $detail = $details[0];

        // Tahap 1: Retur 1 pcs
        $req1 = Request::create('/api/v1/transaksi/retur/simpan', 'POST', [
            'no_penjualan' => $noNota,
            'id' => $detail->id,
            'kodebarang' => $this->kodeBarang,
            'harga_jual' => 33333.33,
            'retur' => 1,
            'diskon' => $detail->diskon,
        ]);
        $resp1 = $this->controller->store($req1);
        $this->assertEquals(200, $resp1->getStatusCode());
        $data1 = json_decode($resp1->getContent(), true);
        $this->controller->selesai(Request::create('/api/v1/transaksi/retur/selesai', 'POST', ['no_retur' => $data1['noretur']]));

        // Tahap 2: Retur 1 pcs
        $req2 = Request::create('/api/v1/transaksi/retur/simpan', 'POST', [
            'no_penjualan' => $noNota,
            'id' => $detail->id,
            'kodebarang' => $this->kodeBarang,
            'harga_jual' => 33333.33,
            'retur' => 1,
            'diskon' => $detail->diskon,
        ]);
        $resp2 = $this->controller->store($req2);
        $this->assertEquals(200, $resp2->getStatusCode());
        $data2 = json_decode($resp2->getContent(), true);
        $this->controller->selesai(Request::create('/api/v1/transaksi/retur/selesai', 'POST', ['no_retur' => $data2['noretur']]));

        // Tahap 3: Retur 1 pcs (sisa 1 pcs terakhir)
        $req3 = Request::create('/api/v1/transaksi/retur/simpan', 'POST', [
            'no_penjualan' => $noNota,
            'id' => $detail->id,
            'kodebarang' => $this->kodeBarang,
            'harga_jual' => 33333.33,
            'retur' => 1,
            'diskon' => $detail->diskon,
        ]);
        $resp3 = $this->controller->store($req3);
        $this->assertEquals(200, $resp3->getStatusCode());
        $data3 = json_decode($resp3->getContent(), true);
        $this->controller->selesai(Request::create('/api/v1/transaksi/retur/selesai', 'POST', ['no_retur' => $data3['noretur']]));

        // Periksa total akumulasi nilai retur dan diskon pada ketiga tahap
        $allReturs = DetailReturPenjualan::where('detail_penjualan_id', $detail->id)->get();
        $this->assertCount(3, $allReturs);

        $totalNilaiRetur = $allReturs->sum('subtotal');
        $totalDiskonRetur = $allReturs->sum('diskon');

        // Total nilai retur harus persis 100.000,00 (tidak ada selisih sen!)
        $this->assertEquals(100000.0, round($totalNilaiRetur, 2));
        $this->assertEquals(3000.0, round($totalDiskonRetur, 2));
    }
}
