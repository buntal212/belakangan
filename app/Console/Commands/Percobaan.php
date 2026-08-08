<?php

   namespace App\Console\Commands;

   use Illuminate\Console\Command;
   use Illuminate\Support\Facades\DB;
   use Carbon\Carbon;

   class Percobaan extends Command
   {
        protected $signature = 'stock:percobaan';
        protected $description = 'Success';

        public function handle(){
            $this->info('Stock Opname Laporan successfully processed.');
        }

   }
