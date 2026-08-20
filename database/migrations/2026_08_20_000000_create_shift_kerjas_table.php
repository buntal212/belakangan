<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shift_kerjas', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 20)->unique();
            $table->string('nama', 100);
            $table->time('jam_masuk');
            $table->time('jam_keluar');
            $table->unsignedSmallInteger('toleransi')->default(0);
            $table->boolean('aktif')->default(true);
            $table->timestamps();
            $table->index(['aktif', 'jam_masuk']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shift_kerjas');
    }
};
