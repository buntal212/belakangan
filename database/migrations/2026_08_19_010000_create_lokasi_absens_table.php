<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration { public function up(): void { Schema::create('lokasi_absens', function (Blueprint $table) { $table->id(); $table->string('nama'); $table->decimal('latitude', 10, 7); $table->decimal('longitude', 10, 7); $table->unsignedInteger('radius_meter')->default(100); $table->boolean('aktif')->default(true); $table->timestamps(); }); } public function down(): void { Schema::dropIfExists('lokasi_absens'); } };
