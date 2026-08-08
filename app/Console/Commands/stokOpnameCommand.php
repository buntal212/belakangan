<?php

namespace App\Console\Commands;

use App\Http\Controllers\Api\Transaksi\Stok\StokOpnameController;
use Illuminate\Console\Command;

class stokOpnameCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'stok:opname';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command to perform stock opname';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // //
        // info('mulai stok opname farmasi');
        //     $opname = new StokOpnameFarmasiController;
        //     $data = $opname->storeMonthly();
        //     info($data);
        // $depo = new Request([
        //     'kdruang' => 'Gd-05010100'
        // ]);
        // info('perbaikan data per depo ' . $depo);
        // $controller = new SetNewStokController;
        // $data = $controller->PerbaikanStokPerDepo($depo);
        // info($data);
        $data = StokOpnameController::storeMonthly();
        $this->info($data['message']);
        info($data['message']);
        // info($data['data']);

        return Command::SUCCESS;
    }
}
