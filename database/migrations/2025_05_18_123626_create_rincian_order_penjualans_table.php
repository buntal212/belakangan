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
        Schema::create('rincian_order_penjualans', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('order_penjualan_id');
            $table->unsignedBigInteger('barang_id');
            $table->string('satuan_k');
            $table->integer('jumlah');
            $table->decimal('harga', 10, 2);
            $table->timestamps();

            $table->foreign('order_penjualan_id')->references('id')->on('order_penjualans')->onDelete('cascade');
            $table->foreign('barang_id')->references('id')->on('barangs')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rincian_order_penjualans');
    }
};
