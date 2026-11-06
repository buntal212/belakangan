<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pelanggans', function (Blueprint $table) {
            $table->string('nama_toko')->nullable()->after('nama');
            $table->string('nama_owner')->nullable()->after('nama_toko');
            $table->string('no_tlp', 50)->nullable()->after('telepon');
            $table->string('no_ktp', 50)->nullable()->after('no_tlp');
            $table->string('no_npwp', 50)->nullable()->after('no_ktp');
        });
    }

    public function down(): void
    {
        Schema::table('pelanggans', function (Blueprint $table) {
            $table->dropColumn(['nama_toko', 'nama_owner', 'no_tlp', 'no_ktp', 'no_npwp']);
        });
    }
};
