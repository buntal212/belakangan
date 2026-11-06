<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_penjualans', function (Blueprint $table) {
            $table->decimal('diskon_kuitansi', 15, 2)->default(0)->after('total_harga');
        });
    }

    public function down(): void
    {
        Schema::table('order_penjualans', function (Blueprint $table) {
            $table->dropColumn('diskon_kuitansi');
        });
    }
};
