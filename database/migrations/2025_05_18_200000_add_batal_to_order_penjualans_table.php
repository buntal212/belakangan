<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tambah status batal dan alasan_batal ke order_penjualans.
     */
    public function up(): void
    {
        Schema::table('order_penjualans', function (Blueprint $table) {
            // Ubah status_order jadi lebih panjang jika perlu (misal dari 1 ke 2 karakter)
            $table->string('status_order', 2)
                ->default('1')
                ->comment('1: draft, 2: menunggu persetujuan, 3: disetujui, 4: diproses, 5: dikirim, 6: diterima, 9: dibatalkan')
                ->change();

            // Tambah alasan_batal
            $table->string('alasan_batal')->nullable()->after('status_order')->comment('Alasan pembatalan order');
        });
    }

    /**
     * Rollback perubahan.
     */
    public function down(): void
    {
        Schema::table('order_penjualans', function (Blueprint $table) {
            // Kembalikan status_order ke 1 karakter jika perlu
            $table->string('status_order', 1)
                ->default('1')
                ->comment('1: draft, 2: disetujui admin, 3: sudah jadi penjualan')
                ->change();

            $table->dropColumn('alasan_batal');
        });
    }
};