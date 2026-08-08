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
        Schema::create('keterangan_pelanggans', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('header_penjualan_id')->nullable();
            $table->string('nama')->nullable();
            $table->string('tlp')->nullable();
            $table->text('alamat')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('keterangan_pelanggans');
    }
};
