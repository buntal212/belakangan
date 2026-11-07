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
        // 1. Tambah kolom snapshot pada header_retur_penjualans
        Schema::table('header_retur_penjualans', function (Blueprint $table) {
            if (!Schema::hasColumn('header_retur_penjualans', 'subtotal')) {
                $table->decimal('subtotal', 24, 2)->nullable()->after('total');
            }
            if (!Schema::hasColumn('header_retur_penjualans', 'diskon_kuitansi')) {
                $table->decimal('diskon_kuitansi', 24, 2)->nullable()->after('subtotal');
            }
            if (!Schema::hasColumn('header_retur_penjualans', 'dpp')) {
                $table->decimal('dpp', 24, 2)->nullable()->after('diskon_kuitansi');
            }
            if (!Schema::hasColumn('header_retur_penjualans', 'ppn_persen')) {
                $table->decimal('ppn_persen', 5, 2)->nullable()->after('dpp');
            }
            if (!Schema::hasColumn('header_retur_penjualans', 'ppn_nominal')) {
                $table->decimal('ppn_nominal', 24, 2)->nullable()->after('ppn_persen');
            }
        });

        // 2. Tambah kolom snapshot pada detail_retur_penjualans
        Schema::table('detail_retur_penjualans', function (Blueprint $table) {
            if (!Schema::hasColumn('detail_retur_penjualans', 'diskon_nota')) {
                $table->decimal('diskon_nota', 24, 2)->nullable()->after('subtotal');
            }
            if (!Schema::hasColumn('detail_retur_penjualans', 'dpp')) {
                $table->decimal('dpp', 24, 2)->nullable()->after('diskon_nota');
            }
            if (!Schema::hasColumn('detail_retur_penjualans', 'ppn_nominal')) {
                $table->decimal('ppn_nominal', 24, 2)->nullable()->after('dpp');
            }
            if (!Schema::hasColumn('detail_retur_penjualans', 'total')) {
                $table->decimal('total', 24, 2)->nullable()->after('ppn_nominal');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 1. Rollback kolom pada detail_retur_penjualans
        Schema::table('detail_retur_penjualans', function (Blueprint $table) {
            $dropDetailCols = [];
            foreach (['diskon_nota', 'dpp', 'ppn_nominal', 'total'] as $col) {
                if (Schema::hasColumn('detail_retur_penjualans', $col)) {
                    $dropDetailCols[] = $col;
                }
            }
            if (!empty($dropDetailCols)) {
                $table->dropColumn($dropDetailCols);
            }
        });

        // 2. Rollback kolom pada header_retur_penjualans
        Schema::table('header_retur_penjualans', function (Blueprint $table) {
            $dropHeaderCols = [];
            foreach (['subtotal', 'diskon_kuitansi', 'dpp', 'ppn_persen', 'ppn_nominal'] as $col) {
                if (Schema::hasColumn('header_retur_penjualans', $col)) {
                    $dropHeaderCols[] = $col;
                }
            }
            if (!empty($dropHeaderCols)) {
                $table->dropColumn($dropHeaderCols);
            }
        });
    }
};
