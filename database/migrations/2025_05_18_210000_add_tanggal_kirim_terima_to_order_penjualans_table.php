<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tambah kolom tanggal_kirim dan tanggal_terima ke order_penjualans.
     */
    public function up(): void
    {
        Schema::table('order_penjualans', function (Blueprint $table) {
            $table->timestamp('tanggal_kirim')->nullable()->after('status_order')->comment('Tanggal order dikirim ke pelanggan');
            $table->timestamp('tanggal_terima')->nullable()->after('tanggal_kirim')->comment('Tanggal order diterima pelanggan');
        });
    }

    /**
     * Rollback perubahan.
     */
    public function down(): void
    {
        Schema::table('order_penjualans', function (Blueprint $table) {
            $table->dropColumn(['tanggal_kirim', 'tanggal_terima']);
        });
    }
};