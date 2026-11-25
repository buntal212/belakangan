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

        if (!$menuLaporan) {
            return;
        }

        $laporan = [
            [
                'name' => 'laporan-hutang-per-distributor',
                'label' => 'Hutang per Distributor',
                'icon' => 'account_balance_wallet',
                'link' => '/admin/laporan/hutang-per-distributor',
            ],
            [
                'name' => 'laporan-piutang-per-pelanggan',
                'label' => 'Piutang per Pelanggan',
                'icon' => 'groups',
                'link' => '/admin/laporan/piutang-per-pelanggan',
            ],
        ];

        $urutan = (int) DB::table('admin_subs')->where('menu_id', $menuLaporan->id)->max('urut');

        foreach ($laporan as $item) {
            if (DB::table('admin_subs')->where('link', $item['link'])->exists()) {
                continue;
            }

            $data = [
                ...$item,
                'menu_id' => $menuLaporan->id,
                'urut' => ++$urutan,
            ];

            if (Schema::hasColumn('admin_subs', 'created_at')) {
                $data['created_at'] = now();
            }

            if (Schema::hasColumn('admin_subs', 'updated_at')) {
                $data['updated_at'] = now();
            }

            DB::table('admin_subs')->insert($data);
        }
    }

    public function down(): void
    {
        DB::table('admin_subs')
            ->whereIn('link', [
                '/admin/laporan/hutang-per-distributor',
                '/admin/laporan/piutang-per-pelanggan',
            ])
            ->delete();
    }
};
