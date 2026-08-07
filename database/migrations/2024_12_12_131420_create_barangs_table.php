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
        Schema::create('barangs', function (Blueprint $table) {
            $table->id();
            $table->string('kodebarang')->unique();
            $table->string('namabarang')->nullable();
            $table->string('merk')->nullable();
            $table->string('satuan_b')->nullable();
            $table->string('satuan_k')->nullable();
            $table->integer('isi')->default(1);
            $table->string('kategori')->nullable();
            $table->decimal('hargajual1', 12, 2)->default(0);
            $table->decimal('hargajual2', 12, 2)->default(0);
            $table->string('ukuran')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('barangs');
    }
};
