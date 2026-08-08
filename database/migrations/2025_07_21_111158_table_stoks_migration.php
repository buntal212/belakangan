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
        //
        Schema::create('stoks', function (Blueprint $table) {
            $table->id();
            $table->string('nopenerimaan')->nullable();
            $table->unsignedBigInteger('idpenerimaan')->nullable();
            $table->string('kdbarang')->nullable();
            $table->string('motif')->nullable();
            $table->double('jumlah_b', 24, 2)->default(0);
            $table->double('jumlah_k', 24, 2)->default(0);
            $table->double('isi', 24, 2)->default(0);
            $table->string('satuan_b')->nullable();
            $table->string('satuan_k')->nullable();
            $table->double('harga_beli_b', 24, 2)->default(0);
            $table->double('harga_beli_k', 24, 2)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
        Schema::dropIfExists('stoks');
    }
};
