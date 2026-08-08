<?php

namespace Database\Factories\Stok;

use App\Models\Stok\stok;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\stok>
 */
class stokFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    protected $model = stok::class;
    public function definition(): array
    {
        return [
            //
            'kdbarang' => 'ABC123',
            'motif' => 'MotifA',
            'jumlah_k' => $this->faker->numberBetween(1, 100),
            'harga_beli_k' => $this->faker->randomFloat(2, 1000, 5000),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
