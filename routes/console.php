<?php

use App\Jobs\StockOpnameJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

/*
|--------------------------------------------------------------------------
| Console Routes
|--------------------------------------------------------------------------
|
| This file is where you may define all of your Closure based console
| commands. Each Closure is bound to a command instance allowing a
| simple approach to interacting with each command's IO methods.
|
*/

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('stock:opname-test', function () {
    $this->info('Running Stock Opname Test...');
    StockOpnameJob::dispatch();
    $this->info('Stock Opname Pendapatan, Hutang, Piutang, Pengeluarans Berhasil.');
})->purpose('Run Stock Opname Job manually for testing');
