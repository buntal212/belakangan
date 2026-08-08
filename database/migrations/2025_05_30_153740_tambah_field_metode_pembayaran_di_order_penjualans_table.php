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
        Schema::table('order_penjualans', function (Blueprint $table) {
            // Ubah status_order jadi lebih panjang jika perlu (misal dari 1 ke 2 karakter)
            $table->string('metode_bayar')->nullable()->after('status_pembayaran');
            $table->decimal('bayar', 10, 2)->default(0)->after('metode_bayar');
            $table->integer('tempo')->default(0)->after('bayar');
            $table->string('catatan')->nullable()->after('tempo');
        });
    }

    /**
     * Rollback perubahan.
     */
    public function down(): void
    {
        Schema::table('order_penjualans', function (Blueprint $table) {
            $table->dropColumn('metode_bayar');
            $table->dropColumn('bayar');
            $table->dropColumn('tempo');
            $table->dropColumn('catatan');
        });
    }
};
