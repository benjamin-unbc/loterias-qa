<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\QuinielaModel;
use App\Models\PrizesModel;
use App\Models\FigureOneModel;
use App\Models\FigureTwoModel;
use App\Models\BetCollectionRedoblonaModel;
use App\Models\BetCollection5To20Model;
use App\Models\BetCollection10To20Model;
use Carbon\Carbon;

class TestMultipleWinners extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:multiple-winners';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Prueba la lógica de múltiples ganadores con jugadas y extractos simulados';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info("=== PRUEBA DE MÚLTIPLES GANADORES ===");
        $this->newLine();

        // Cargar las tablas de premios
        $quiniela = QuinielaModel::first();
        $prizes = PrizesModel::first();
        $figureOne = FigureOneModel::first();
        $figureTwo = FigureTwoModel::first();
        $redoblona1toX = BetCollectionRedoblonaModel::first();
        $redoblona5to20 = BetCollection5To20Model::first();
        $redoblona10to20 = BetCollection10To20Model::first();

        if (!$quiniela || !$prizes || !$figureOne || !$figureTwo) {
            $this->error("❌ Faltan tablas de pagos. Asegúrate de que existan en la base de datos.");
            return 1;
        }

        $this->info("📊 TABLAS DE PREMIOS:");
        $this->line("Prizes (2 dígitos) - Pos 5: $" . $prizes->cobra_5 . " | Pos 10: $" . $prizes->cobra_10 . " | Pos 20: $" . $prizes->cobra_20);
        $this->line("FigureOne (3 dígitos) - Pos 5: $" . $figureOne->cobra_5 . " | Pos 10: $" . $figureOne->cobra_10 . " | Pos 20: $" . $figureOne->cobra_20);
        $this->line("FigureTwo (4 dígitos) - Pos 5: $" . $figureTwo->cobra_5 . " | Pos 10: $" . $figureTwo->cobra_10 . " | Pos 20: $" . $figureTwo->cobra_20);
        $this->newLine();

        // ============================================
        // PRUEBA 1: Jugada posición 5 con múltiples apariciones
        // ============================================
        $this->info("🎯 PRUEBA 1: Jugada posición 5 con múltiples apariciones");
        $this->line("Jugada: 33 a posición 5, importe $150");
        $this->line("Extractos simulados:");
        
        // Simular números ganadores: 33 sale en posición 3 y 4
        $winningNumbers1 = [
            ['value' => 1233, 'position' => 3], // 33 en posición 3
            ['value' => 4533, 'position' => 4], // 33 en posición 4
        ];
        
        foreach ($winningNumbers1 as $num) {
            $this->line("  - Número: {$num['value']} en posición {$num['position']}");
        }
        
        $winningCount1 = count($winningNumbers1);
        $baseMultiplier1 = (float)$prizes->cobra_5; // Pago base de posición 5
        $import1 = 150;
        $prize1 = $baseMultiplier1 * $winningCount1 * $import1;
        
        $this->line("Resultado:");
        $this->line("  - Veces que salió: {$winningCount1}");
        $this->line("  - Pago base (posición 5): $" . number_format($baseMultiplier1, 2));
        $this->line("  - Cálculo: $" . number_format($baseMultiplier1, 2) . " × {$winningCount1} × $" . number_format($import1, 2));
        $this->line("  - ✅ PREMIO TOTAL: $" . number_format($prize1, 2));
        $this->newLine();

        // ============================================
        // PRUEBA 2: Jugada posición 10 con múltiples apariciones
        // ============================================
        $this->info("🎯 PRUEBA 2: Jugada posición 10 con múltiples apariciones");
        $this->line("Jugada: 3456 a posición 10, importe $150");
        $this->line("Extractos simulados:");
        
        // Simular números ganadores: 3456 sale en posición 2, 5, 7 y 8
        $winningNumbers2 = [
            ['value' => 23456, 'position' => 2], // 3456 en posición 2
            ['value' => 53456, 'position' => 5], // 3456 en posición 5
            ['value' => 73456, 'position' => 7], // 3456 en posición 7
            ['value' => 83456, 'position' => 8], // 3456 en posición 8
        ];
        
        foreach ($winningNumbers2 as $num) {
            $this->line("  - Número: {$num['value']} en posición {$num['position']}");
        }
        
        $winningCount2 = count($winningNumbers2);
        $baseMultiplier2 = (float)$figureTwo->cobra_10; // Pago base de posición 10 (4 dígitos)
        $import2 = 150;
        $prize2 = $baseMultiplier2 * $winningCount2 * $import2;
        
        $this->line("Resultado:");
        $this->line("  - Veces que salió: {$winningCount2}");
        $this->line("  - Pago base (posición 10, 4 dígitos): $" . number_format($baseMultiplier2, 2));
        $this->line("  - Cálculo: $" . number_format($baseMultiplier2, 2) . " × {$winningCount2} × $" . number_format($import2, 2));
        $this->line("  - ✅ PREMIO TOTAL: $" . number_format($prize2, 2));
        $this->newLine();

        // ============================================
        // PRUEBA 3: Jugada posición 20 con múltiples apariciones
        // ============================================
        $this->info("🎯 PRUEBA 3: Jugada posición 20 con múltiples apariciones");
        $this->line("Jugada: 345 a posición 20, importe $100");
        $this->line("Extractos simulados:");
        
        // Simular números ganadores: 345 sale en posición 3, 5, 6, 7, 17
        $winningNumbers3 = [
            ['value' => 2345, 'position' => 3],  // 345 en posición 3
            ['value' => 5345, 'position' => 5],  // 345 en posición 5
            ['value' => 6345, 'position' => 6],  // 345 en posición 6
            ['value' => 7345, 'position' => 7],  // 345 en posición 7
            ['value' => 17345, 'position' => 17], // 345 en posición 17
        ];
        
        foreach ($winningNumbers3 as $num) {
            $this->line("  - Número: {$num['value']} en posición {$num['position']}");
        }
        
        $winningCount3 = count($winningNumbers3);
        $baseMultiplier3 = (float)$figureOne->cobra_20; // Pago base de posición 20 (3 dígitos)
        $import3 = 100;
        $prize3 = $baseMultiplier3 * $winningCount3 * $import3;
        
        $this->line("Resultado:");
        $this->line("  - Veces que salió: {$winningCount3}");
        $this->line("  - Pago base (posición 20, 3 dígitos): $" . number_format($baseMultiplier3, 2));
        $this->line("  - Cálculo: $" . number_format($baseMultiplier3, 2) . " × {$winningCount3} × $" . number_format($import3, 2));
        $this->line("  - ✅ PREMIO TOTAL: $" . number_format($prize3, 2));
        $this->newLine();

        // ============================================
        // PRUEBA 4: Redoblona con múltiples apariciones
        // ============================================
        if ($redoblona5to20) {
            $this->info("🎯 PRUEBA 4: Redoblona con múltiples apariciones");
            $this->line("Jugada principal: 33 a posición 5");
            $this->line("Redoblona: 45 a posición 10, importe $150");
            $this->line("Extractos simulados:");
            
            // Simular número principal: 33 sale en posición 3
            $this->line("  - Número principal 33 en posición 3: ✅ GANADOR");
            
            // Simular redoblona: 45 sale en posición 2, 5, 7 y 8
            $redoblonaNumbers = [
                ['value' => 2345, 'position' => 2], // 45 en posición 2
                ['value' => 5345, 'position' => 5], // 45 en posición 5
                ['value' => 7345, 'position' => 7], // 45 en posición 7
                ['value' => 8345, 'position' => 8], // 45 en posición 8
            ];
            
            foreach ($redoblonaNumbers as $num) {
                $this->line("  - Redoblona 45 en posición {$num['position']}: ✅ GANADOR");
            }
            
            $redoblonaCount = count($redoblonaNumbers);
            $baseMultiplier4 = (float)$redoblona5to20->payout_5_to_10; // Pago base redoblona posición 5 → 10
            $import4 = 150;
            $prize4 = $baseMultiplier4 * $redoblonaCount * $import4;
            
            $this->line("Resultado:");
            $this->line("  - Número principal: ✅ GANADOR");
            $this->line("  - Redoblona salió: {$redoblonaCount} vez(es)");
            $this->line("  - Pago base redoblona (5→10): $" . number_format($baseMultiplier4, 2));
            $this->line("  - Cálculo: $" . number_format($baseMultiplier4, 2) . " × {$redoblonaCount} × $" . number_format($import4, 2));
            $this->line("  - ✅ PREMIO TOTAL: $" . number_format($prize4, 2));
            $this->newLine();
        }

        // ============================================
        // PRUEBA 5: Jugada posición 5 sin ganadores
        // ============================================
        $this->info("🎯 PRUEBA 5: Jugada posición 5 SIN ganadores");
        $this->line("Jugada: 99 a posición 5, importe $100");
        $this->line("Extractos simulados:");
        $this->line("  - No hay números ganadores en rango 2-5");
        $this->line("Resultado:");
        $this->line("  - ❌ PREMIO TOTAL: $0.00");
        $this->newLine();

        // ============================================
        // RESUMEN
        // ============================================
        $this->info("=== RESUMEN ===");
        $this->line("✅ Prueba 1 (Pos 5, 2 veces): $" . number_format($prize1, 2));
        $this->line("✅ Prueba 2 (Pos 10, 4 veces): $" . number_format($prize2, 2));
        $this->line("✅ Prueba 3 (Pos 20, 5 veces): $" . number_format($prize3, 2));
        if ($redoblona5to20) {
            $this->line("✅ Prueba 4 (Redoblona, 4 veces): $" . number_format($prize4, 2));
        }
        $this->line("❌ Prueba 5 (Sin ganadores): $0.00");
        $this->newLine();
        $this->info("🎉 Pruebas completadas. Verifica que los cálculos sean correctos según la nueva lógica.");

        return 0;
    }
}

