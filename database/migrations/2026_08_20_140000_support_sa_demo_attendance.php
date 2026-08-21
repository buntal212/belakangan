<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Migration sebelumnya mungkin sudah sempat membuat kolom ini
        // sebelum gagal saat drop unique index.
        if (! Schema::hasColumn('absensis', 'demo_sequence')) {
            Schema::table('absensis', function (Blueprint $table) {
                $table->unsignedInteger('demo_sequence')
                    ->default(0)
                    ->after('tipe');
            });
        }

        /*
         * Pastikan foreign key user_id punya index sendiri.
         * Jangan biarkan FK bergantung pada composite unique index
         * yang akan kita hapus.
         */
        $userIndexExists = collect(
            DB::select("SHOW INDEX FROM absensis WHERE Key_name = 'absensis_user_id_index'")
        )->isNotEmpty();

        if (! $userIndexExists) {
            Schema::table('absensis', function (Blueprint $table) {
                $table->index('user_id', 'absensis_user_id_index');
            });
        }

        // Hapus unique lama jika masih ada
        $oldUniqueExists = collect(
            DB::select(
                "SHOW INDEX FROM absensis
                 WHERE Key_name = 'absensis_user_id_tanggal_tipe_unique'"
            )
        )->isNotEmpty();

        if ($oldUniqueExists) {
            Schema::table('absensis', function (Blueprint $table) {
                $table->dropUnique(
                    'absensis_user_id_tanggal_tipe_unique'
                );
            });
        }

        // Buat unique baru jika belum ada
        $newUniqueExists = collect(
            DB::select(
                "SHOW INDEX FROM absensis
                 WHERE Key_name = 'absensis_user_id_tanggal_tipe_demo_sequence_unique'"
            )
        )->isNotEmpty();

        if (! $newUniqueExists) {
            Schema::table('absensis', function (Blueprint $table) {
                $table->unique(
                    ['user_id', 'tanggal', 'tipe', 'demo_sequence'],
                    'absensis_user_id_tanggal_tipe_demo_sequence_unique'
                );
            });
        }
    }

    public function down(): void
    {
        $newUniqueExists = collect(
            DB::select(
                "SHOW INDEX FROM absensis
                 WHERE Key_name = 'absensis_user_id_tanggal_tipe_demo_sequence_unique'"
            )
        )->isNotEmpty();

        if ($newUniqueExists) {
            Schema::table('absensis', function (Blueprint $table) {
                $table->dropUnique(
                    'absensis_user_id_tanggal_tipe_demo_sequence_unique'
                );
            });
        }

        if (Schema::hasColumn('absensis', 'demo_sequence')) {
            Schema::table('absensis', function (Blueprint $table) {
                $table->dropColumn('demo_sequence');
            });
        }

        $oldUniqueExists = collect(
            DB::select(
                "SHOW INDEX FROM absensis
                 WHERE Key_name = 'absensis_user_id_tanggal_tipe_unique'"
            )
        )->isNotEmpty();

        if (! $oldUniqueExists) {
            Schema::table('absensis', function (Blueprint $table) {
                $table->unique(
                    ['user_id', 'tanggal', 'tipe'],
                    'absensis_user_id_tanggal_tipe_unique'
                );
            });
        }
    }
};
