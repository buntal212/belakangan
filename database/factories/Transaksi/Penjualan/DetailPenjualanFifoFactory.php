<?php

namespace Database\Factories\Transaksi\Penjualan;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Model>
 */
class DetailPenjualanFifoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            //
            'no_penjualan' => 'PJ-' . $this->faker->unique()->numerify('#####'),
            'kodebarang' => 'ABC123',
            'jumlah' => $this->faker->numberBetween(1, 10),
            'retur' => 0,
            'harga_beli' => $this->faker->randomFloat(2, 1000, 10000),
            'harga_jual' => $this->faker->randomFloat(2, 10000, 50000),
            'diskon' => $this->faker->randomFloat(2, 0, 5000),
            'subtotal' => $this->faker->randomFloat(2, 10000, 50000),
            'stok_id' => null, // atau generate stok_id jika butuh relasi
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
