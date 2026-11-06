<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('header_penjualans', 'noorder')) {
            return;
        }

        Schema::table('header_penjualans', function (Blueprint $table) {
            $table->string('noorder')->nullable()->unique()->after('no_penjualan');
        });
    }

    public function down(): void
    {
        if (!Schema::hasColumn('header_penjualans', 'noorder')) {
            return;
        }

        Schema::table('header_penjualans', function (Blueprint $table) {
            $table->dropUnique(['noorder']);
            $table->dropColumn('noorder');
        });
    }
};
