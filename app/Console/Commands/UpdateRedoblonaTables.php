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
        $this->info('Limpiando y actualizando valores de bet_collection_redoblona...');
        
        // ✅ ELIMINAR TODOS los registros primero para evitar duplicados
        DB::table('bet_collection_redoblona')->truncate();
        
        // ✅ VALORES CORRECTOS para bet_collection_redoblona (A los 1 todo a los 5/10/20)
        $redoblonaValues = [
            ['bet_amount' => 25.00, 'payout_1_to_5' => 1280.00, 'payout_1_to_10' => 640.00, 'payout_1_to_20' => 336.84],
            ['bet_amount' => 10.00, 'payout_1_to_5' => 12800.00, 'payout_1_to_10' => 6400.00, 'payout_1_to_20' => 3368.40],
            ['bet_amount' => 5.00, 'payout_1_to_5' => 6400.00, 'payout_1_to_10' => 3200.00, 'payout_1_to_20' => 1684.20],
            ['bet_amount' => 2.50, 'payout_1_to_5' => 3200.00, 'payout_1_to_10' => 1600.50, 'payout_1_to_20' => 842.10],
            ['bet_amount' => 2.00, 'payout_1_to_5' => 2560.00, 'payout_1_to_10' => 1280.00, 'payout_1_to_20' => 673.68],
            ['bet_amount' => 1.00, 'payout_1_to_5' => 1280.00, 'payout_1_to_10' => 640.00, 'payout_1_to_20' => 336.84],
        ];
        
        // Insertar los valores correctos
        foreach ($redoblonaValues as $values) {
            DB::table('bet_collection_redoblona')->insert([
                'bet_amount' => $values['bet_amount'],
                'payout_1_to_5' => $values['payout_1_to_5'],
                'payout_1_to_10' => $values['payout_1_to_10'],
                'payout_1_to_20' => $values['payout_1_to_20'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        
        $this->info('✅ bet_collection_redoblona actualizada correctamente');
        
        $this->info('Limpiando y actualizando valores de bet_collection_5_20...');
        
        // ✅ ELIMINAR TODOS los registros primero para evitar duplicados
        DB::table('bet_collection_5_20')->truncate();
        
        // ✅ VALORES CORRECTOS para bet_collection_5_20 (A los 5 todo a los 5/10/20)
        $bet520Values = [
            ['bet_amount' => 25.00, 'payout_5_to_5' => 256.00, 'payout_5_to_10' => 128.00, 'payout_5_to_20' => 64.00],
            ['bet_amount' => 10.00, 'payout_5_to_5' => 2560.00, 'payout_5_to_10' => 1280.00, 'payout_5_to_20' => 640.00],
            ['bet_amount' => 5.00, 'payout_5_to_5' => 1280.00, 'payout_5_to_10' => 640.00, 'payout_5_to_20' => 320.00],
            ['bet_amount' => 2.50, 'payout_5_to_5' => 640.00, 'payout_5_to_10' => 320.00, 'payout_5_to_20' => 160.00],
            ['bet_amount' => 2.00, 'payout_5_to_5' => 512.00, 'payout_5_to_10' => 256.00, 'payout_5_to_20' => 128.00],
            ['bet_amount' => 1.00, 'payout_5_to_5' => 256.00, 'payout_5_to_10' => 128.00, 'payout_5_to_20' => 64.00],
        ];
        
        // Insertar los valores correctos
        foreach ($bet520Values as $values) {
            DB::table('bet_collection_5_20')->insert([
                'bet_amount' => $values['bet_amount'],
                'payout_5_to_5' => $values['payout_5_to_5'],
                'payout_5_to_10' => $values['payout_5_to_10'],
                'payout_5_to_20' => $values['payout_5_to_20'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        
        $this->info('✅ bet_collection_5_20 actualizada correctamente');
        
        $this->info('Limpiando y actualizando valores de bet_collection_10_20...');
        
        // ✅ ELIMINAR TODOS los registros primero para evitar duplicados
        DB::table('bet_collection_10_20')->truncate();
        
        // ✅ VALORES CORRECTOS para bet_collection_10_20 (A los 10/20 todo a los 10/20)
        $bet1020Values = [
            ['bet_amount' => 25.00, 'payout_10_to_10' => 64.00, 'payout_10_to_20' => 32.00, 'payout_20_to_20' => 16.00],
            ['bet_amount' => 10.00, 'payout_10_to_10' => 640.00, 'payout_10_to_20' => 320.00, 'payout_20_to_20' => 160.00],
            ['bet_amount' => 5.00, 'payout_10_to_10' => 320.00, 'payout_10_to_20' => 160.00, 'payout_20_to_20' => 80.00],
            ['bet_amount' => 2.50, 'payout_10_to_10' => 160.00, 'payout_10_to_20' => 80.00, 'payout_20_to_20' => 40.00],
            ['bet_amount' => 2.00, 'payout_10_to_10' => 128.00, 'payout_10_to_20' => 64.00, 'payout_20_to_20' => 32.00],
            ['bet_amount' => 1.00, 'payout_10_to_10' => 64.00, 'payout_10_to_20' => 32.00, 'payout_20_to_20' => 16.00],
        ];
        
        // Insertar los valores correctos
        foreach ($bet1020Values as $values) {
            DB::table('bet_collection_10_20')->insert([
                'bet_amount' => $values['bet_amount'],
                'payout_10_to_10' => $values['payout_10_to_10'],
                'payout_10_to_20' => $values['payout_10_to_20'],
                'payout_20_to_20' => $values['payout_20_to_20'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        
        $this->info('✅ bet_collection_10_20 actualizada correctamente');
        $this->info('✅ Todas las tablas de redoblona actualizadas correctamente sin duplicados');
        
        return 0;
    }
}

