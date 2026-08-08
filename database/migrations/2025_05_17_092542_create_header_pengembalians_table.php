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
        Schema::create('header_pengembalians', function (Blueprint $table) {
            $table->id();
            $table->string('no_pengembalian')->unique();
            $table->unsignedBigInteger('header_penjualan_id');
            $table->string('no_penjualan');
            $table->dateTime('tanggal');
            $table->text('keterangan')->nullable();
            $table->enum('status', ['pending', 'diganti', 'ditolak'])->default('pending');
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('header_pengembalians');
    }
};
