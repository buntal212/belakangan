<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pengaturan_ppns', function (Blueprint $table) {
            $table->id();
            $table->boolean('aktif')->default(false);
            $table->decimal('persen', 5, 2)->default(0);
            $table->timestamps();
        });

        DB::table('pengaturan_ppns')->insert([
            'id' => 1,
            'aktif' => false,
            'persen' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('pengaturan_ppns');
    }
};
