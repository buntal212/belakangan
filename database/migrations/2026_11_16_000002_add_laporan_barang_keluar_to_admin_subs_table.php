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

        if (!$menuLaporan || DB::table('admin_subs')->where('link', '/admin/laporan/barang-keluar')->exists()) {
            return;
        }

        $data = [
            'menu_id' => $menuLaporan->id,
            'name' => 'laporan-barang-keluar',
            'label' => 'Laporan Barang Keluar',
            'icon' => 'inventory_2',
            'link' => '/admin/laporan/barang-keluar',
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
        DB::table('admin_subs')->where('link', '/admin/laporan/barang-keluar')->delete();
    }
};
