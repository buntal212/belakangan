<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_penjualans', function (Blueprint $table) {
            $table->decimal('total_sebelum_diskon', 15, 2)->default(0)->after('total_harga');
        });

        DB::table('order_penjualans')->update([
            'total_sebelum_diskon' => DB::raw('(SELECT COALESCE(SUM(rincian.total_sebelum_diskon), 0) FROM rincian_order_penjualans AS rincian WHERE rincian.order_penjualan_id = order_penjualans.id)'),
        ]);
    }

    public function down(): void
    {
        Schema::table('order_penjualans', function (Blueprint $table) {
            $table->dropColumn('total_sebelum_diskon');
        });
    }
};
