<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // $schedule->command('inspire')->hourly();
        $schedule->command('stok:opname')->dailyAt('00:30');
        // $schedule->command('sync:barang-views')->everyFiveMinutes();
        // $schedule->command('sync:barang-views')->everyMinute();
        $schedule->command('sync:barang-likes')->everyFiveMinutes();

        // $schedule->command('stock:opnamelaporan')->monthly()->at('23:59');
        $schedule->command('stock:opnamelaporan')->monthlyOn(1, '00:10');
        // $schedule->command('stock:percobaan')->dailyAt( '00:10');
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
