<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('header_penjualans', 'diskon_kuitansi')) {
            Schema::table('header_penjualans', function (Blueprint $table) {
                $table->decimal('diskon_kuitansi', 24, 2)->default(0)->after('total_diskon');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('header_penjualans', 'diskon_kuitansi')) {
            Schema::table('header_penjualans', function (Blueprint $table) {
                $table->dropColumn('diskon_kuitansi');
            });
        }
    }
};
