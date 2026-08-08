<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;
use App\Models\BarangLike;

class SyncBarangLikes extends Command
{
    protected $signature = 'sync:barang-likes';
    protected $description = 'Sync Redis view counter to database';

    public function handle()
    {
        $keys = Redis::connection('likes')->keys('likes:barang:*');

        foreach ($keys as $key) {
            $id = str_replace('likes:barang:', '', $key);
            $count = (int) Redis::connection('likes')->get($key);

            // Simpan ke DB: update or insert
            BarangLike::updateOrCreate(
                ['barang_id' => $id],
                ['likes' => $count]
            );
        }

        $this->info('Barang likes synced successfully.');
    }
}