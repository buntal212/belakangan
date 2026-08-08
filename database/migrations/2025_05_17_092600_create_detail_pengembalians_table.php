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
        Schema::create('detail_pengembalians', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('header_pengembalian_id');
            $table->string('no_pengembalian');
            $table->unsignedBigInteger('barang_id');
            $table->string('kodebarang');
            $table->string('motif');
            $table->integer('qty');
            $table->text('keterangan_rusak')->nullable();
            $table->enum('status', ['pending', 'diganti', 'ditolak'])->default('pending');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('detail_pengembalians');
    }
};
