<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('absensis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->date('tanggal');
            $table->enum('tipe', ['masuk', 'pulang']);
            $table->timestamp('waktu')->useCurrent();
            $table->string('foto_path')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->decimal('akurasi_lokasi', 10, 2)->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'tanggal', 'tipe']);
            $table->index(['tanggal', 'tipe']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('absensis');
    }
};
