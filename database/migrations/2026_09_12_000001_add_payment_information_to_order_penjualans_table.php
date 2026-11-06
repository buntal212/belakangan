<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_penjualans', function (Blueprint $table) {
            $table->string('sistem_bayar', 20)->nullable()->after('status_pembayaran');
            $table->string('cara_bayar', 20)->nullable()->after('sistem_bayar');
            $table->decimal('down_payment', 15, 2)->default(0)->after('bayar');
        });
    }

    public function down(): void
    {
        Schema::table('order_penjualans', function (Blueprint $table) {
            $table->dropColumn(['sistem_bayar', 'cara_bayar', 'down_payment']);
        });
    }
};
