<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class AnalyzeTicket63 extends Command
{
    protected $signature = 'analyze:ticket63';
    protected $description = 'Analiza el ticket 62-0003 con extractos CTE1800';

    public function handle()
    {
        $this->info("=== ANÁLISIS TICKET 62-0003 (CTE1800) ===\n");

        // Extractos CTE1800
        $extracts = [
            1 => '3333', 2 => '5436', 3 => '3333', 4 => '4363', 5 => '4333',
            6 => '3454', 7 => '3463', 8 => '3464', 9 => '6436', 10 => '4333',
            11 => '4333', 12 => '3464', 13 => '6346', 14 => '6344', 15 => '6346',
            16 => '3464', 17 => '3546', 18 => '3464', 19 => '4366', 20 => '4333'
        ];

        $this->info("Extractos CTE1800:");
        foreach ($extracts as $pos => $num) {
            $endsWith33 = substr($num, -2) === '33';
            $marker = $endsWith33 ? ' ✅ **33' : '';
            $this->line("  Posición {$pos}: {$num}{$marker}");
        }
        $this->newLine();

        // Analizar **33 a posición 5 (con redoblona 33 posición 10)
        $this->info("1. **33 a posición 5 con redoblona 33 posición 10:");
        $this->line("   Primero verificar **33 en posición 5 (busca 2-5):");
        $count5 = 0;
        $positions5 = [];
        foreach (range(2, 5) as $pos) {
            if (substr($extracts[$pos], -2) === '33') {
                $count5++;
                $positions5[] = $pos;
            }
        }
        $this->line("   Aparece en posiciones: " . implode(', ', $positions5));
        $this->line("   Veces esperado: {$count5}");
        $this->line("   Veces actual: 1");
        if ($count5 != 1) {
            $this->error("   ❌ Veces debería ser {$count5}, no 1");
        }
        $this->line("   Luego verificar redoblona 33 en posición 10 (busca 2-10):");
        $redoblonaCount = 0;
        $redoblonaPositions = [];
        foreach (range(2, 10) as $pos) {
            if (substr($extracts[$pos], -2) === '33') {
                $redoblonaCount++;
                $redoblonaPositions[] = $pos;
            }
        }
        $this->line("   Redoblona aparece en posiciones: " . implode(', ', $redoblonaPositions));
        $this->line("   Veces redoblona esperado: {$redoblonaCount}");
        $this->line("   Veces redoblona actual: 1");
        if ($redoblonaCount != 1) {
            $this->error("   ❌ Veces redoblona debería ser {$redoblonaCount}, no 1");
        }
        $this->line("   Premio actual: $57,600.00");
        $this->newLine();

        // Analizar **33 a posición 10
        $this->info("2. **33 a posición 10 (busca de 2-10):");
        $count10 = 0;
        $positions10 = [];
        foreach (range(2, 10) as $pos) {
            if (substr($extracts[$pos], -2) === '33') {
                $count10++;
                $positions10[] = $pos;
            }
        }
        $this->line("   Aparece en posiciones: " . implode(', ', $positions10));
        $this->line("   Veces esperado: {$count10}");
        $this->line("   Veces actual: 1");
        $expectedPrize10 = 21.00 * $count10 * 150;
        $this->line("   Premio esperado: $21.00 × {$count10} × $150 = $" . number_format($expectedPrize10, 2));
        $this->line("   Premio actual: $3,150.00");
        if ($count10 != 1) {
            $this->error("   ❌ Veces debería ser {$count10}, no 1");
            if ($expectedPrize10 != 3150) {
                $this->error("   ❌ Premio debería ser $" . number_format($expectedPrize10, 2) . ", no $3,150.00");
            }
        }
        $this->newLine();

        // Analizar **33 a posición 20
        $this->info("3. **33 a posición 20 (busca de 2-20):");
        $count20 = 0;
        $positions20 = [];
        foreach (range(2, 20) as $pos) {
            if (substr($extracts[$pos], -2) === '33') {
                $count20++;
                $positions20[] = $pos;
            }
        }
        $this->line("   Aparece en posiciones: " . implode(', ', $positions20));
        $this->line("   Veces esperado: {$count20}");
        $this->line("   Veces actual: 1");
        $expectedPrize20 = 3.50 * $count20 * 150;
        $this->line("   Premio esperado: $3.50 × {$count20} × $150 = $" . number_format($expectedPrize20, 2));
        $this->line("   Premio actual: $2,625.00");
        if ($count20 != 1) {
            $this->error("   ❌ Veces debería ser {$count20}, no 1");
            if ($expectedPrize20 != 2625) {
                $this->error("   ❌ Premio debería ser $" . number_format($expectedPrize20, 2) . ", no $2,625.00");
            }
        }
        $this->newLine();

        // Resumen
        $this->info("=== RESUMEN ===");
        $this->line("Redoblona **33 pos 5, redoblona 33 pos 10: Veces principal {$count5}, Veces redoblona {$redoblonaCount} (actual: 1, 1) " . ($count5 != 1 || $redoblonaCount != 1 ? "❌" : "✅"));
        $this->line("Posición 10: Veces esperado {$count10}, actual 1 " . ($count10 != 1 ? "❌" : "✅"));
        $this->line("Posición 20: Veces esperado {$count20}, actual 1 " . ($count20 != 1 ? "❌" : "✅"));

        return 0;
    }
}

