<?php

namespace Database\Factories\Transaksi\Penjualan;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Model>
 */
class DetailPenjualanFactory extends Factory
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
            'motif' => 'MotifX',
            'jumlah' => $this->faker->numberBetween(1, 10),
            'harga_jual' => $this->faker->randomFloat(2, 10000, 50000),
            'diskon' => $this->faker->randomFloat(2, 0, 5000),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
