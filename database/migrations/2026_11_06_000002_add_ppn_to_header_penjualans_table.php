<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('header_penjualans', function (Blueprint $table) {
            $table->decimal('subtotal', 24, 2)->default(0)->after('total_diskon');
            $table->decimal('ppn_persen', 5, 2)->default(0)->after('subtotal');
            $table->decimal('ppn_nominal', 24, 2)->default(0)->after('ppn_persen');
        });

        // Nota lama sudah menyimpan total sebelum fitur PPN. Jadikan nilai itu
        // sebagai subtotal agar total historisnya tidak berubah.
        DB::table('header_penjualans')->update([
            'subtotal' => DB::raw('total'),
        ]);
    }

    public function down(): void
    {
        Schema::table('header_penjualans', function (Blueprint $table) {
            $table->dropColumn(['subtotal', 'ppn_persen', 'ppn_nominal']);
        });
    }
};
