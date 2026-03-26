<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RevertRedoblonaSeeders extends Command
{
    protected $signature = 'revert:redoblona-seeders';
    protected $description = 'Revertir los valores de las tablas de redoblona a los valores anteriores';

    public function handle()
    {
        $this->info('Revirtiendo valores de bet_collection_redoblona...');
        
        // Valores anteriores para bet_collection_redoblona
        $redoblonaValues = [
            ['bet_amount' => 25.00, 'payout_1_to_5' => 1600.00, 'payout_1_to_10' => 800.00, 'payout_1_to_20' => 400.00],
            ['bet_amount' => 10.00, 'payout_1_to_5' => 640.00, 'payout_1_to_10' => 320.00, 'payout_1_to_20' => 160.00],
            ['bet_amount' => 5.00, 'payout_1_to_5' => 320.00, 'payout_1_to_10' => 160.00, 'payout_1_to_20' => 80.00],
            ['bet_amount' => 2.50, 'payout_1_to_5' => 160.00, 'payout_1_to_10' => 80.00, 'payout_1_to_20' => 40.00],
            ['bet_amount' => 2.00, 'payout_1_to_5' => 128.00, 'payout_1_to_10' => 64.00, 'payout_1_to_20' => 32.00],
            ['bet_amount' => 1.00, 'payout_1_to_5' => 64.00, 'payout_1_to_10' => 32.00, 'payout_1_to_20' => 16.00],
        ];
        
        foreach ($redoblonaValues as $values) {
            DB::table('bet_collection_redoblona')
                ->where('bet_amount', $values['bet_amount'])
                ->update([
                    'payout_1_to_5' => $values['payout_1_to_5'],
                    'payout_1_to_10' => $values['payout_1_to_10'],
                    'payout_1_to_20' => $values['payout_1_to_20'],
                    'updated_at' => now(),
                ]);
        }
        
        $this->info('Revirtiendo valores de bet_collection_5_20...');
        
        // Valores anteriores para bet_collection_5_20
        $bet520Values = [
            ['bet_amount' => 25.00, 'payout_5_to_5' => 6400.00, 'payout_5_to_10' => 3200.00, 'payout_5_to_20' => 1600.00],
            ['bet_amount' => 10.00, 'payout_5_to_5' => 2560.00, 'payout_5_to_10' => 1280.00, 'payout_5_to_20' => 640.00],
            ['bet_amount' => 5.00, 'payout_5_to_5' => 1280.00, 'payout_5_to_10' => 640.00, 'payout_5_to_20' => 320.00],
            ['bet_amount' => 2.50, 'payout_5_to_5' => 640.00, 'payout_5_to_10' => 320.00, 'payout_5_to_20' => 160.00],
            ['bet_amount' => 2.00, 'payout_5_to_5' => 512.00, 'payout_5_to_10' => 256.00, 'payout_5_to_20' => 128.00],
            ['bet_amount' => 1.00, 'payout_5_to_5' => 256.00, 'payout_5_to_10' => 128.00, 'payout_5_to_20' => 64.00],
        ];
        
        foreach ($bet520Values as $values) {
            DB::table('bet_collection_5_20')
                ->where('bet_amount', $values['bet_amount'])
                ->update([
                    'payout_5_to_5' => $values['payout_5_to_5'],
                    'payout_5_to_10' => $values['payout_5_to_10'],
                    'payout_5_to_20' => $values['payout_5_to_20'],
                    'updated_at' => now(),
                ]);
        }
        
        $this->info('Revirtiendo valores de bet_collection_10_20...');
        
        // Valores anteriores para bet_collection_10_20
        $bet1020Values = [
            ['bet_amount' => 25.00, 'payout_10_to_10' => 32000.00, 'payout_10_to_20' => 16000.00, 'payout_20_to_20' => 8421.00],
            ['bet_amount' => 10.00, 'payout_10_to_10' => 12800.00, 'payout_10_to_20' => 6400.00, 'payout_20_to_20' => 3368.40],
            ['bet_amount' => 5.00, 'payout_10_to_10' => 6400.00, 'payout_10_to_20' => 3200.00, 'payout_20_to_20' => 1684.20],
            ['bet_amount' => 2.50, 'payout_10_to_10' => 3200.00, 'payout_10_to_20' => 1600.50, 'payout_20_to_20' => 842.10],
            ['bet_amount' => 2.00, 'payout_10_to_10' => 2560.00, 'payout_10_to_20' => 1280.00, 'payout_20_to_20' => 673.68],
            ['bet_amount' => 1.00, 'payout_10_to_10' => 1280.00, 'payout_10_to_20' => 640.00, 'payout_20_to_20' => 336.84],
        ];
        
        foreach ($bet1020Values as $values) {
            DB::table('bet_collection_10_20')
                ->where('bet_amount', $values['bet_amount'])
                ->update([
                    'payout_10_to_10' => $values['payout_10_to_10'],
                    'payout_10_to_20' => $values['payout_10_to_20'],
                    'payout_20_to_20' => $values['payout_20_to_20'],
                    'updated_at' => now(),
                ]);
        }
        
        $this->info('✅ Valores revertidos exitosamente');
        
        return 0;
    }
}

