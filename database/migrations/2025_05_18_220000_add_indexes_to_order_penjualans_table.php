<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_penjualans', function (Blueprint $table) {
            $table->index('pelanggan_id');
            $table->index('sales_id');
        });
    }

    public function down(): void
    {
        Schema::table('order_penjualans', function (Blueprint $table) {
            $table->dropIndex(['pelanggan_id']);
            $table->dropIndex(['sales_id']);
        });
    }
};