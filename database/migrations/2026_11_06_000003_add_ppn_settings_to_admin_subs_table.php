<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $menuSettings = DB::table('admin_menus')
            ->where(function ($query) {
                $query->whereIn('name', ['settings', 'setting'])
                    ->orWhereIn('label', ['Settings', 'Setting', 'Pengaturan']);
            })
            ->first();

        if (!$menuSettings || DB::table('admin_subs')->where('link', '/admin/settings/ppn')->exists()) {
            return;
        }

        $data = [
            'menu_id' => $menuSettings->id,
            'name' => 'pengaturan-ppn',
            'label' => 'Pengaturan PPN',
            'icon' => 'percent',
            'link' => '/admin/settings/ppn',
            'urut' => (int) DB::table('admin_subs')->where('menu_id', $menuSettings->id)->max('urut') + 1,
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
        DB::table('admin_subs')->where('link', '/admin/settings/ppn')->delete();
    }
};
