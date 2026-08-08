<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Jalankan migrasi.
     */
    public function up(): void
    {
        Schema::create('order_penjualans', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('noorder')->unique();
            $table->timestamp('tglorder');
            $table->unsignedBigInteger('pelanggan_id');
            $table->unsignedBigInteger('sales_id');
            $table->decimal('total_harga', 10, 2);

            // 1: draft, 2: disetujui admin, 3: sudah jadi penjualan
            $table->string('status_order', 1)->default('1')->comment('1: draft, 2: disetujui admin, 3: sudah jadi penjualan');

            // 1: hutang, 2: lunas
            $table->string('status_pembayaran', 1)->default('1')->comment('1: hutang, 2: lunas,');

            $table->timestamps();

            $table->foreign('pelanggan_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('sales_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse migrasi.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_penjualans');
    }
};
