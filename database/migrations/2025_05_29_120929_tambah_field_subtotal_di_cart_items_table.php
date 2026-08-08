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
        Schema::table('cart_items', function (Blueprint $table) {
            // Ubah status_order jadi lebih panjang jika perlu (misal dari 1 ke 2 karakter)
            $table->decimal('subtotal', 10, 2)->default(0)->after('price');
        });
    }

    /**
     * Rollback perubahan.
     */
    public function down(): void
    {
        Schema::table('cart_items', function (Blueprint $table) {
            $table->dropColumn('subtotal');
        });
    }
};
