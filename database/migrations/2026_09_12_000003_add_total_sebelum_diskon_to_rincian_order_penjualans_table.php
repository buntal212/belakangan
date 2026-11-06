<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rincian_order_penjualans', function (Blueprint $table) {
            $table->decimal('total_sebelum_diskon', 15, 2)->default(0)->after('harga');
        });

        DB::table('rincian_order_penjualans')->update([
            'total_sebelum_diskon' => DB::raw('ROUND(jumlah * harga, 0)'),
        ]);
    }

    public function down(): void
    {
        Schema::table('rincian_order_penjualans', function (Blueprint $table) {
            $table->dropColumn('total_sebelum_diskon');
        });
    }
};
