<?php

namespace App\Console\Commands;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;
use App\Models\BarangView;

class SyncBarangViews extends Command
{
    protected $signature = 'sync:barang-views';
    protected $description = 'Sync Redis view counter to database';

    public function handle()
    {
        $keys = Redis::connection('views')->keys('views:barang:*');

        foreach ($keys as $key) {
            $id = str_replace('views:barang:', '', $key);
            $count = (int) Redis::connection('views')->get($key);

            // Simpan ke DB: update or insert
            BarangView::updateOrCreate(
                ['barang_id' => $id],
                ['views' => $count]
            );
        }

        $this->info('Barang views synced successfully.');
    }
}