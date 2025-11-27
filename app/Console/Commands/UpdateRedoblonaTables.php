<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class UpdateRedoblonaTables extends Command
{
    protected $signature = 'update:redoblona-tables';
    protected $description = 'Actualizar las tablas de redoblona con los valores correctos';

    public function handle()
    {
        $this->info('Actualizando valores de bet_collection_redoblona...');
        
        // ✅ VALORES CORRECTOS para bet_collection_redoblona (A los 1 todo a los 5/10/20)
        $redoblonaValues = [
            ['bet_amount' => 25.00, 'payout_1_to_5' => 1280.00, 'payout_1_to_10' => 640.00, 'payout_1_to_20' => 336.84],
            ['bet_amount' => 10.00, 'payout_1_to_5' => 12800.00, 'payout_1_to_10' => 6400.00, 'payout_1_to_20' => 3368.40],
            ['bet_amount' => 5.00, 'payout_1_to_5' => 6400.00, 'payout_1_to_10' => 3200.00, 'payout_1_to_20' => 1684.20],
            ['bet_amount' => 2.50, 'payout_1_to_5' => 3200.00, 'payout_1_to_10' => 1600.50, 'payout_1_to_20' => 842.10],
            ['bet_amount' => 2.00, 'payout_1_to_5' => 2560.00, 'payout_1_to_10' => 1280.00, 'payout_1_to_20' => 673.68],
            ['bet_amount' => 1.00, 'payout_1_to_5' => 1280.00, 'payout_1_to_10' => 640.00, 'payout_1_to_20' => 336.84],
        ];
        
        foreach ($redoblonaValues as $values) {
            DB::table('bet_collection_redoblona')
                ->updateOrInsert(
                    ['bet_amount' => $values['bet_amount']],
                    [
                        'payout_1_to_5' => $values['payout_1_to_5'],
                        'payout_1_to_10' => $values['payout_1_to_10'],
                        'payout_1_to_20' => $values['payout_1_to_20'],
                        'updated_at' => now(),
                    ]
                );
        }
        
        // Eliminar duplicados si existen (mantener solo el primero)
        $duplicates = DB::table('bet_collection_redoblona')
            ->select('bet_amount', DB::raw('COUNT(*) as count'))
            ->groupBy('bet_amount')
            ->having('count', '>', 1)
            ->get();
        
        foreach ($duplicates as $dup) {
            $ids = DB::table('bet_collection_redoblona')
                ->where('bet_amount', $dup->bet_amount)
                ->orderBy('id')
                ->pluck('id')
                ->toArray();
            
            // Mantener el primero, eliminar los demás
            if (count($ids) > 1) {
                DB::table('bet_collection_redoblona')
                    ->whereIn('id', array_slice($ids, 1))
                    ->delete();
                $this->info("Eliminados duplicados para bet_amount {$dup->bet_amount}");
            }
        }
        
        $this->info('Actualizando valores de bet_collection_5_20...');
        
        // ✅ VALORES CORRECTOS para bet_collection_5_20 (A los 5 todo a los 5/10/20)
        $bet520Values = [
            ['bet_amount' => 25.00, 'payout_5_to_5' => 256.00, 'payout_5_to_10' => 128.00, 'payout_5_to_20' => 64.00],
            ['bet_amount' => 10.00, 'payout_5_to_5' => 2560.00, 'payout_5_to_10' => 1280.00, 'payout_5_to_20' => 640.00],
            ['bet_amount' => 5.00, 'payout_5_to_5' => 1280.00, 'payout_5_to_10' => 640.00, 'payout_5_to_20' => 320.00],
            ['bet_amount' => 2.50, 'payout_5_to_5' => 640.00, 'payout_5_to_10' => 320.00, 'payout_5_to_20' => 160.00],
            ['bet_amount' => 2.00, 'payout_5_to_5' => 512.00, 'payout_5_to_10' => 256.00, 'payout_5_to_20' => 128.00],
            ['bet_amount' => 1.00, 'payout_5_to_5' => 256.00, 'payout_5_to_10' => 128.00, 'payout_5_to_20' => 64.00],
        ];
        
        foreach ($bet520Values as $values) {
            DB::table('bet_collection_5_20')
                ->updateOrInsert(
                    ['bet_amount' => $values['bet_amount']],
                    [
                        'payout_5_to_5' => $values['payout_5_to_5'],
                        'payout_5_to_10' => $values['payout_5_to_10'],
                        'payout_5_to_20' => $values['payout_5_to_20'],
                        'updated_at' => now(),
                    ]
                );
        }
        
        // Eliminar duplicados
        $duplicates = DB::table('bet_collection_5_20')
            ->select('bet_amount', DB::raw('COUNT(*) as count'))
            ->groupBy('bet_amount')
            ->having('count', '>', 1)
            ->get();
        
        foreach ($duplicates as $dup) {
            $ids = DB::table('bet_collection_5_20')
                ->where('bet_amount', $dup->bet_amount)
                ->orderBy('id')
                ->pluck('id')
                ->toArray();
            
            if (count($ids) > 1) {
                DB::table('bet_collection_5_20')
                    ->whereIn('id', array_slice($ids, 1))
                    ->delete();
                $this->info("Eliminados duplicados para bet_amount {$dup->bet_amount}");
            }
        }
        
        $this->info('Actualizando valores de bet_collection_10_20...');
        
        // ✅ VALORES CORRECTOS para bet_collection_10_20 (A los 10/20 todo a los 10/20)
        $bet1020Values = [
            ['bet_amount' => 25.00, 'payout_10_to_10' => 64.00, 'payout_10_to_20' => 32.00, 'payout_20_to_20' => 16.00],
            ['bet_amount' => 10.00, 'payout_10_to_10' => 640.00, 'payout_10_to_20' => 320.00, 'payout_20_to_20' => 160.00],
            ['bet_amount' => 5.00, 'payout_10_to_10' => 320.00, 'payout_10_to_20' => 160.00, 'payout_20_to_20' => 80.00],
            ['bet_amount' => 2.50, 'payout_10_to_10' => 160.00, 'payout_10_to_20' => 80.00, 'payout_20_to_20' => 40.00],
            ['bet_amount' => 2.00, 'payout_10_to_10' => 128.00, 'payout_10_to_20' => 64.00, 'payout_20_to_20' => 32.00],
            ['bet_amount' => 1.00, 'payout_10_to_10' => 64.00, 'payout_10_to_20' => 32.00, 'payout_20_to_20' => 16.00],
        ];
        
        foreach ($bet1020Values as $values) {
            DB::table('bet_collection_10_20')
                ->updateOrInsert(
                    ['bet_amount' => $values['bet_amount']],
                    [
                        'payout_10_to_10' => $values['payout_10_to_10'],
                        'payout_10_to_20' => $values['payout_10_to_20'],
                        'payout_20_to_20' => $values['payout_20_to_20'],
                        'updated_at' => now(),
                    ]
                );
        }
        
        // Eliminar duplicados
        $duplicates = DB::table('bet_collection_10_20')
            ->select('bet_amount', DB::raw('COUNT(*) as count'))
            ->groupBy('bet_amount')
            ->having('count', '>', 1)
            ->get();
        
        foreach ($duplicates as $dup) {
            $ids = DB::table('bet_collection_10_20')
                ->where('bet_amount', $dup->bet_amount)
                ->orderBy('id')
                ->pluck('id')
                ->toArray();
            
            if (count($ids) > 1) {
                DB::table('bet_collection_10_20')
                    ->whereIn('id', array_slice($ids, 1))
                    ->delete();
                $this->info("Eliminados duplicados para bet_amount {$dup->bet_amount}");
            }
        }
        
        $this->info('✅ Tablas de redoblona actualizadas correctamente');
        
        return 0;
    }
}

