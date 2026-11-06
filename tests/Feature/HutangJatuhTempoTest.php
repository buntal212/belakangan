<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\DashboardController;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class HutangJatuhTempoTest extends TestCase
{
    protected string $suplierKode;

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

        $this->suplierKode = 'TEST-SUPL-' . uniqid();
        DB::table('suppliers')->insert([
            'kodesupl' => $this->suplierKode,
            'nama' => 'Supplier Test Hutang',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function tearDown(): void
    {
        DB::rollBack();
        parent::tearDown();
    }

    private function getHutangJatuhTempoQuery()
    {
        $batasJatuhTempo = now()->startOfDay()->addDays(7)->endOfDay();

        $totalPenerimaan = DB::table('penerimaan_r')
            ->selectRaw('nopenerimaan, SUM(subtotal) as total_hutang')
            ->groupBy('nopenerimaan');

        $pembayaranPerPenerimaan = DB::table('pembayaran_hutang_r')
            ->selectRaw('nopenerimaan, SUM(total) as total_dibayar')
            ->groupBy('nopenerimaan');

        return DB::table('penerimaan_h as penerimaan')
            ->leftJoin('suppliers as supplier', 'supplier.kodesupl', '=', 'penerimaan.kdsupllier')
            ->leftJoinSub($totalPenerimaan, 'nilai_hutang', fn ($join) => $join->on('nilai_hutang.nopenerimaan', '=', 'penerimaan.nopenerimaan'))
            ->leftJoinSub($pembayaranPerPenerimaan, 'pembayaran_hutang', fn ($join) => $join->on('pembayaran_hutang.nopenerimaan', '=', 'penerimaan.nopenerimaan'))
            ->where('penerimaan.jenis_pembayaran', 'Hutang')
            ->whereNotNull('penerimaan.tgljatuhtempo')
            ->whereDate('penerimaan.tgljatuhtempo', '<=', $batasJatuhTempo)
            ->selectRaw('penerimaan.nopenerimaan as nomor, penerimaan.tgljatuhtempo as jatuh_tempo, COALESCE(supplier.nama, penerimaan.kdsupllier) as pihak, GREATEST(COALESCE(nilai_hutang.total_hutang, 0) - COALESCE(pembayaran_hutang.total_dibayar, 0), 0) as sisa')
            ->whereRaw('GREATEST(COALESCE(nilai_hutang.total_hutang, 0) - COALESCE(pembayaran_hutang.total_dibayar, 0), 0) > 0')
            ->orderBy('penerimaan.tgljatuhtempo')
            ->limit(8);
    }

    public function test_hutang_belum_dibayar_muncul_dengan_sisa_penuh()
    {
        $noPenerimaan = 'TEST-P-UNPAID-' . uniqid();
        $jatuhTempo = now()->addDays(3)->format('Y-m-d');

        DB::table('penerimaan_h')->insert([
            'nopenerimaan' => $noPenerimaan,
            'jenis_pembayaran' => 'Hutang',
            'tgl_faktur' => now()->format('Y-m-d'),
            'kdsupllier' => $this->suplierKode,
            'tgljatuhtempo' => $jatuhTempo,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('penerimaan_r')->insert([
            'nopenerimaan' => $noPenerimaan,
            'kdbarang' => 'TEST-BRG',
            'subtotal' => 150000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $result = $this->getHutangJatuhTempoQuery()
            ->where('penerimaan.nopenerimaan', $noPenerimaan)
            ->first();

        $this->assertNotNull($result, 'Hutang belum dibayar harus muncul di hasil query');
        $this->assertEquals($noPenerimaan, $result->nomor);
        $this->assertEquals('Supplier Test Hutang', $result->pihak);
        $this->assertEquals($jatuhTempo, $result->jatuh_tempo);
        $this->assertEquals(150000, (float) $result->sisa);
    }

    public function test_hutang_dibayar_sebagian_muncul_dengan_sisa_yang_benar()
    {
        $noPenerimaan = 'TEST-P-PARTIAL-' . uniqid();
        $jatuhTempo = now()->addDays(4)->format('Y-m-d');

        DB::table('penerimaan_h')->insert([
            'nopenerimaan' => $noPenerimaan,
            'jenis_pembayaran' => 'Hutang',
            'tgl_faktur' => now()->format('Y-m-d'),
            'kdsupllier' => $this->suplierKode,
            'tgljatuhtempo' => $jatuhTempo,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('penerimaan_r')->insert([
            'nopenerimaan' => $noPenerimaan,
            'kdbarang' => 'TEST-BRG',
            'subtotal' => 200000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('pembayaran_hutang_r')->insert([
            'notrans' => 'TEST-BAYAR-' . uniqid(),
            'nopenerimaan' => $noPenerimaan,
            'total' => 75000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $result = $this->getHutangJatuhTempoQuery()
            ->where('penerimaan.nopenerimaan', $noPenerimaan)
            ->first();

        $this->assertNotNull($result, 'Hutang dibayar sebagian harus muncul di hasil query');
        $this->assertEquals($noPenerimaan, $result->nomor);
        $this->assertEquals(125000, (float) $result->sisa, 'Sisa hutang harus 200000 - 75000 = 125000');
    }

    public function test_hutang_sudah_lunas_tidak_muncul()
    {
        $noPenerimaan = 'TEST-P-PAID-' . uniqid();
        $jatuhTempo = now()->addDays(2)->format('Y-m-d');

        DB::table('penerimaan_h')->insert([
            'nopenerimaan' => $noPenerimaan,
            'jenis_pembayaran' => 'Hutang',
            'tgl_faktur' => now()->format('Y-m-d'),
            'kdsupllier' => $this->suplierKode,
            'tgljatuhtempo' => $jatuhTempo,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('penerimaan_r')->insert([
            'nopenerimaan' => $noPenerimaan,
            'kdbarang' => 'TEST-BRG',
            'subtotal' => 300000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('pembayaran_hutang_r')->insert([
            'notrans' => 'TEST-BAYAR-' . uniqid(),
            'nopenerimaan' => $noPenerimaan,
            'total' => 300000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $result = $this->getHutangJatuhTempoQuery()
            ->where('penerimaan.nopenerimaan', $noPenerimaan)
            ->first();

        $this->assertNull($result, 'Hutang yang sudah lunas tidak boleh muncul');
    }

    public function test_hutang_melebihi_pembayaran_atau_overpaid_tidak_muncul()
    {
        $noPenerimaan = 'TEST-P-OVERPAID-' . uniqid();
        $jatuhTempo = now()->addDays(2)->format('Y-m-d');

        DB::table('penerimaan_h')->insert([
            'nopenerimaan' => $noPenerimaan,
            'jenis_pembayaran' => 'Hutang',
            'tgl_faktur' => now()->format('Y-m-d'),
            'kdsupllier' => $this->suplierKode,
            'tgljatuhtempo' => $jatuhTempo,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('penerimaan_r')->insert([
            'nopenerimaan' => $noPenerimaan,
            'kdbarang' => 'TEST-BRG',
            'subtotal' => 100000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('pembayaran_hutang_r')->insert([
            'notrans' => 'TEST-BAYAR-' . uniqid(),
            'nopenerimaan' => $noPenerimaan,
            'total' => 120000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $result = $this->getHutangJatuhTempoQuery()
            ->where('penerimaan.nopenerimaan', $noPenerimaan)
            ->first();

        $this->assertNull($result, 'Hutang dengan kelebihan bayar tidak boleh muncul');
    }

    public function test_filter_jatuh_tempo_melewati_batas_tidak_muncul()
    {
        $noPenerimaan = 'TEST-P-FUTURE-' . uniqid();
        // Jatuh tempo 15 hari ke depan (melebihi batas 7 hari)
        $jatuhTempo = now()->addDays(15)->format('Y-m-d');

        DB::table('penerimaan_h')->insert([
            'nopenerimaan' => $noPenerimaan,
            'jenis_pembayaran' => 'Hutang',
            'tgl_faktur' => now()->format('Y-m-d'),
            'kdsupllier' => $this->suplierKode,
            'tgljatuhtempo' => $jatuhTempo,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('penerimaan_r')->insert([
            'nopenerimaan' => $noPenerimaan,
            'kdbarang' => 'TEST-BRG',
            'subtotal' => 100000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $result = $this->getHutangJatuhTempoQuery()
            ->where('penerimaan.nopenerimaan', $noPenerimaan)
            ->first();

        $this->assertNull($result, 'Hutang dengan jatuh tempo melebihi batas 7 hari tidak boleh muncul');
    }

    public function test_jenis_pembayaran_bukan_hutang_tidak_muncul()
    {
        $noPenerimaan = 'TEST-P-CASH-' . uniqid();
        $jatuhTempo = now()->addDays(3)->format('Y-m-d');

        DB::table('penerimaan_h')->insert([
            'nopenerimaan' => $noPenerimaan,
            'jenis_pembayaran' => 'Tunai',
            'tgl_faktur' => now()->format('Y-m-d'),
            'kdsupllier' => $this->suplierKode,
            'tgljatuhtempo' => $jatuhTempo,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('penerimaan_r')->insert([
            'nopenerimaan' => $noPenerimaan,
            'kdbarang' => 'TEST-BRG',
            'subtotal' => 100000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $result = $this->getHutangJatuhTempoQuery()
            ->where('penerimaan.nopenerimaan', $noPenerimaan)
            ->first();

        $this->assertNull($result, 'Penerimaan selain jenis Hutang tidak boleh muncul');
    }

    public function test_supplier_fallback_ke_kodesupllier_jika_tidak_ditemukan()
    {
        $noPenerimaan = 'TEST-P-NOSUPL-' . uniqid();
        $jatuhTempo = now()->addDays(2)->format('Y-m-d');
        $kodeSuplTidakAda = 'NON-EXISTING-SUPL';

        DB::table('penerimaan_h')->insert([
            'nopenerimaan' => $noPenerimaan,
            'jenis_pembayaran' => 'Hutang',
            'tgl_faktur' => now()->format('Y-m-d'),
            'kdsupllier' => $kodeSuplTidakAda,
            'tgljatuhtempo' => $jatuhTempo,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('penerimaan_r')->insert([
            'nopenerimaan' => $noPenerimaan,
            'kdbarang' => 'TEST-BRG',
            'subtotal' => 100000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $result = $this->getHutangJatuhTempoQuery()
            ->where('penerimaan.nopenerimaan', $noPenerimaan)
            ->first();

        $this->assertNotNull($result);
        $this->assertEquals($kodeSuplTidakAda, $result->pihak, 'Pihak harus fallback ke kodesupllier jika supplier tidak ditemukan di tabel suppliers');
    }

    public function test_dashboard_controller_index_berjalan_tanpa_error_sql()
    {
        $controller = new DashboardController();
        $response = $controller->index();

        $this->assertEquals(200, $response->getStatusCode());
        $data = $response->getData(true);

        $this->assertArrayHasKey('jatuhTempo', $data);
        $this->assertArrayHasKey('hutang', $data['jatuhTempo']);

        $hutangList = $data['jatuhTempo']['hutang'];
        $this->assertLessThanOrEqual(8, count($hutangList), 'Jumlah hutang jatuh tempo maksimal 8 data');

        $prevDate = null;
        foreach ($hutangList as $hutang) {
            $this->assertGreaterThan(0, $hutang['sisa'], 'Semua hutang yang tampil harus memiliki sisa > 0');
            $this->assertArrayHasKey('nomor', $hutang);
            $this->assertArrayHasKey('jatuh_tempo', $hutang);
            $this->assertArrayHasKey('pihak', $hutang);

            // Verifikasi urutan tanggal asc
            if ($prevDate !== null) {
                $this->assertGreaterThanOrEqual(
                    strtotime($prevDate),
                    strtotime($hutang['jatuh_tempo']),
                    'Data hutang harus diurutkan berdasarkan jatuh_tempo ascending'
                );
            }
            $prevDate = $hutang['jatuh_tempo'];
        }
    }
}
