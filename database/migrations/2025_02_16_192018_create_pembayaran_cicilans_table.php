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
        Schema::create('pembayaran_cicilans', function (Blueprint $table) {
            $table->id();
            $table->string('no_penjualan')->nullable();
            $table->string('header_ciclan_id')->nullable();
            $table->dateTime('tgl_bayar')->nullable();
            $table->double('jumlah', 24, 2)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pembayaran_cicilans');
    }
};
