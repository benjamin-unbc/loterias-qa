<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class AnalyzeTicket38 extends Command
{
    protected $signature = 'analyze:ticket38';
    protected $description = 'Analiza el ticket 53-0038 con extractos NAC2100';

    public function handle()
    {
        $this->info("=== ANÁLISIS TICKET 53-0038 (NAC2100) ===\n");

        // Extractos NAC2100
        $extracts = [
            1 => '3333', 2 => '4533', 3 => '3463', 4 => '3546', 5 => '4333',
            6 => '3546', 7 => '5435', 8 => '5646', 9 => '3233', 10 => '3464',
            11 => '2345', 12 => '4633', 13 => '3333', 14 => '3463', 15 => '3333',
            16 => '7547', 17 => '3464', 18 => '6346', 19 => '3463', 20 => '3333'
        ];

        $this->info("Extractos NAC2100:");
        foreach ($extracts as $pos => $num) {
            $endsWith33 = substr($num, -2) === '33';
            $marker = $endsWith33 ? ' ✅ **33' : '';
            $this->line("  Posición {$pos}: {$num}{$marker}");
        }
        $this->newLine();

        // Analizar **33 a posición 1
        $this->info("1. **33 a posición 1 (busca solo posición 1):");
        $pos1 = $extracts[1];
        if (substr($pos1, -2) === '33') {
            $this->line("   ✅ Sale en posición 1: {$pos1}");
            $this->line("   Veces: 1 (correcto)");
            $this->line("   Premio: $70.00 × 1 × $150 = $10,500.00 ✅");
        }
        $this->newLine();

        // Analizar **33 a posición 5
        $this->info("2. **33 a posición 5 (busca de 2-5):");
        $count5 = 0;
        $positions5 = [];
        foreach (range(2, 5) as $pos) {
            if (substr($extracts[$pos], -2) === '33') {
                $count5++;
                $positions5[] = $pos;
            }
        }
        $this->line("   Aparece en posiciones: " . implode(', ', $positions5));
        $this->line("   Veces: {$count5}");
        $expectedPrize5 = 14.00 * $count5 * 150;
        $this->line("   Premio esperado: $14.00 × {$count5} × $150 = $" . number_format($expectedPrize5, 2));
        $this->line("   Premio actual: $4,200.00");
        if ($expectedPrize5 == 4200) {
            $this->info("   ✅ Premio correcto");
        } else {
            $this->error("   ❌ Premio incorrecto");
        }
        $this->newLine();

        // Analizar **33 a posición 10
        $this->info("3. **33 a posición 10 (busca de 2-10):");
        $count10 = 0;
        $positions10 = [];
        foreach (range(2, 10) as $pos) {
            if (substr($extracts[$pos], -2) === '33') {
                $count10++;
                $positions10[] = $pos;
            }
        }
        $this->line("   Aparece en posiciones: " . implode(', ', $positions10));
        $this->line("   Veces: {$count10}");
        $expectedPrize10 = 21.00 * $count10 * 150;
        $this->line("   Premio esperado: $21.00 × {$count10} × $150 = $" . number_format($expectedPrize10, 2));
        $this->line("   Premio actual: $3,150.00");
        if ($expectedPrize10 == 3150) {
            $this->info("   ✅ Premio correcto");
        } else {
            $this->error("   ❌ Premio incorrecto (debería ser $" . number_format($expectedPrize10, 2) . ")");
        }
        $this->newLine();

        // Analizar **33 a posición 20
        $this->info("4. **33 a posición 20 (busca de 2-20):");
        $count20 = 0;
        $positions20 = [];
        foreach (range(2, 20) as $pos) {
            if (substr($extracts[$pos], -2) === '33') {
                $count20++;
                $positions20[] = $pos;
            }
        }
        $this->line("   Aparece en posiciones: " . implode(', ', $positions20));
        $this->line("   Veces: {$count20}");
        $expectedPrize20 = 3.50 * $count20 * 150;
        $this->line("   Premio esperado: $3.50 × {$count20} × $150 = $" . number_format($expectedPrize20, 2));
        $this->line("   Premio actual: $3,675.00");
        if ($expectedPrize20 == 3675) {
            $this->info("   ✅ Premio correcto");
        } else {
            $this->error("   ❌ Premio incorrecto (debería ser $" . number_format($expectedPrize20, 2) . ")");
        }
        $this->newLine();

        // Resumen
        $this->info("=== RESUMEN ===");
        $this->line("Posición 1: Veces 1 ✅");
        $this->line("Posición 5: Veces {$count5} " . ($count5 == 2 ? "✅" : "❌"));
        $this->line("Posición 10: Veces {$count10} " . ($count10 == 3 ? "✅" : "❌"));
        $this->line("Posición 20: Veces {$count20} " . ($count20 == 6 ? "✅" : "❌"));

        return 0;
    }
}

