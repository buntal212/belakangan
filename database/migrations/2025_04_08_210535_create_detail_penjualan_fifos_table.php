<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('detail_penjualan_fifos', function (Blueprint $table) {
            $table->id();
            $table->string('no_penjualan');
            $table->string('kodebarang')->nullable();
            $table->double('jumlah', 24, 2)->default(0);
            $table->double('harga_beli', 24, 2)->default(0);
            $table->double('harga_jual', 24, 2)->default(0);
            $table->double('diskon', 24, 2)->default(0);
            $table->double('subtotal', 24, 2)->default(0);
            $table->unsignedBigInteger('stok_id')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('detail_penjualan_fifos');
    }
};
