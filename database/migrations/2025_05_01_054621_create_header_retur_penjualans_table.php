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
        Schema::create('header_retur_penjualans', function (Blueprint $table) {
            $table->id();
            $table->string('no_retur');
            $table->string('no_penjualan');
            $table->dateTime('tgl')->nullable();
            $table->double('total', 24, 2)->default(0);
            $table->string('status', 2)->default('');
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
        Schema::dropIfExists('header_retur_penjualans');
    }
};
