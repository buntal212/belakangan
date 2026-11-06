<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('counter_order_penjualans', function (Blueprint $table) {
            $table->tinyIncrements('id');
            $table->unsignedBigInteger('nomor_terakhir')->default(0);
            $table->timestamps();
        });

        DB::table('counter_order_penjualans')->insert([
            'id' => 1,
            'nomor_terakhir' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('counter_order_penjualans');
    }
};
