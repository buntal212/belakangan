<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $menuLaporan = DB::table('admin_menus')
            ->where('name', 'laporan')
            ->orWhere('label', 'Laporan')
            ->first();

        if (!$menuLaporan || DB::table('admin_subs')->where('link', '/admin/laporan/pembayaran-piutang')->exists()) {
            return;
        }

        $data = [
            'menu_id' => $menuLaporan->id,
            'name' => 'laporan-pembayaran-piutang',
            'label' => 'Histori Pembayaran Piutang',
            'icon' => 'receipt_long',
            'link' => '/admin/laporan/pembayaran-piutang',
            'urut' => (int) DB::table('admin_subs')->where('menu_id', $menuLaporan->id)->max('urut') + 1,
        ];

        if (Schema::hasColumn('admin_subs', 'created_at')) {
            $data['created_at'] = now();
        }

        if (Schema::hasColumn('admin_subs', 'updated_at')) {
            $data['updated_at'] = now();
        }

        DB::table('admin_subs')->insert($data);
    }

    public function down(): void
    {
        DB::table('admin_subs')
            ->where('link', '/admin/laporan/pembayaran-piutang')
            ->delete();
    }
};
