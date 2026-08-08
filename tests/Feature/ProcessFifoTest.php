<?php

namespace Tests\Feature;

use App\Helpers\FifoHelper;
use App\Models\Barang;
use App\Models\Stok\stok;
use App\Models\Transaksi\Penjualan\DetailPenjualan;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class ProcessFifoTest extends TestCase
{

    use RefreshDatabase;
    /**
     * A basic feature test example.
     */
    public function test_example(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }
    public function test_fifo_berhasil_kalau_stok_cukup()
    {
        // Arrange
        $noTransaksi = 'T123';

        $barang = Barang::factory()->create(['kodebarang' => 'ABC']);
        $stok1 = stok::factory()->create([
            'kdbarang' => 'ABC',
            'motif' => 'Motif1',
            'jumlah_k' => 5,
            'harga_beli_k' => 100,
            'created_at' => now()->subDays(2),
        ]);
        $stok2 = stok::factory()->create([
            'kdbarang' => 'ABC',
            'motif' => 'Motif1',
            'jumlah_k' => 10,
            'harga_beli_k' => 110,
            'created_at' => now()->subDay(),
        ]);

        DetailPenjualan::factory()->create([
            'no_penjualan' => $noTransaksi,
            'kodebarang' => 'ABC',
            'motif' => 'Motif1',
            'jumlah' => 8,
            'harga_jual' => 200,
            'diskon' => 0,
        ]);

        // Act
        $result = FifoHelper::processFifo($noTransaksi);

        // Assert
        $this->assertIsArray($result);
        $this->assertEquals(2, count($result)); // karena ambil dari 2 stok
        $this->assertEquals(0, stok::find($stok1->id)->jumlah_k); // stok1 habis
        $this->assertEquals(7, stok::find($stok2->id)->jumlah_k); // stok2 berkurang
    }

    public function test_fifo_gagal_kalau_stok_kurang()
    {
        $this->expectException(Exception::class);

        // Arrange
        $noTransaksi = 'T124';

        Barang::factory()->create(['kodebarang' => 'ABC']);
        stok::factory()->create([
            'kdbarang' => 'ABC',
            'motif' => 'Motif1',
            'jumlah_k' => 2,
            'harga_beli_k' => 100,
        ]);

        DetailPenjualan::factory()->create([
            'no_penjualan' => $noTransaksi,
            'kodebarang' => 'ABC',
            'motif' => 'Motif1',
            'jumlah' => 5,
            'harga_jual' => 200,
            'diskon' => 0,
        ]);

        // Act
        FifoHelper::processFifo($noTransaksi);
    }
}
