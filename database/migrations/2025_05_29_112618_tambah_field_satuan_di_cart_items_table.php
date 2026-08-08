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
        Schema::table('cart_items', function (Blueprint $table) {
            // Ubah status_order jadi lebih panjang jika perlu (misal dari 1 ke 2 karakter)
            $table->string('satuan')->nullable()->after('price'); // Ganti 'nama' dengan kolom yang sudah ada sebelumnya
            $table->text('satuans')->nullable()->after('satuan');
            $table->text('image')->nullable()->after('satuans');
        });
    }

    /**
     * Rollback perubahan.
     */
    public function down(): void
    {
        Schema::table('cart_items', function (Blueprint $table) {
            // Kembalikan status_order ke 1 karakter jika perlu
            $table->dropColumn('satuan');
            $table->dropColumn('satuans');
            $table->dropColumn('image');
        });
    }
};