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
        Schema::table('rincian_order_penjualans', function (Blueprint $table) {
            // Ubah status_order jadi lebih panjang jika perlu (misal dari 1 ke 2 karakter)
            $table->decimal('subtotal', 10, 2)->default(0)->after('harga');
            $table->string('satuan')->nullable()->after('subtotal');
            $table->text('satuans')->nullable()->after('satuan');

            // hapus kolom lama
            $table->dropColumn('satuan_k');
        });
    }

    /**
     * Rollback perubahan.
     */
    public function down(): void
    {
        Schema::table('rincian_order_penjualans', function (Blueprint $table) {

            // Tambahkan kembali kolom lama
            $table->string('satuan_k')->nullable();

            // Hapus kolom baru
            $table->dropColumn('subtotal');
            $table->dropColumn('satuan');
            $table->dropColumn('satuans');
        });
    }
};
