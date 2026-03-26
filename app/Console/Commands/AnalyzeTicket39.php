<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class AnalyzeTicket39 extends Command
{
    protected $signature = 'analyze:ticket39';
    protected $description = 'Analiza el ticket 53-0039 con extractos SFE2100';

    public function handle()
    {
        $this->info("=== ANÁLISIS TICKET 53-0039 (SFE2100) ===\n");

        // Extractos SFE2100
        $extracts = [
            1 => '4545', 2 => '4567', 3 => '4545', 4 => '5765', 5 => '4545',
            6 => '4574', 7 => '4674', 8 => '5674', 9 => '7467', 10 => '4545',
            11 => '7554', 12 => '7455', 13 => '4755', 14 => '4567', 15 => '4574',
            16 => '4574', 17 => '4575', 18 => '4545', 19 => '4545', 20 => '4537'
        ];

        $this->info("Extractos SFE2100:");
        foreach ($extracts as $pos => $num) {
            $endsWith45 = substr($num, -2) === '45';
            $marker = $endsWith45 ? ' ✅ **45' : '';
            $this->line("  Posición {$pos}: {$num}{$marker}");
        }
        $this->newLine();

        // Analizar **45 a posición 1
        $this->info("1. **45 a posición 1 (busca solo posición 1):");
        $pos1 = $extracts[1];
        if (substr($pos1, -2) === '45') {
            $this->line("   ✅ Sale en posición 1: {$pos1}");
            $this->line("   Veces: 1 (correcto)");
            $this->line("   Premio: $70.00 × 1 × $150 = $10,500.00 ✅");
        }
        $this->newLine();

        // Analizar **45 a posición 5
        $this->info("2. **45 a posición 5 (busca de 2-5):");
        $count5 = 0;
        $positions5 = [];
        foreach (range(2, 5) as $pos) {
            if (substr($extracts[$pos], -2) === '45') {
                $count5++;
                $positions5[] = $pos;
            }
        }
        $this->line("   Aparece en posiciones: " . implode(', ', $positions5));
        $this->line("   Veces: {$count5}");
        $expectedPrize5 = 14.00 * $count5 * 150;
        $this->line("   Premio esperado: $14.00 × {$count5} × $150 = $" . number_format($expectedPrize5, 2));
        $this->line("   Premio actual: $4,200.00");
        $this->line("   Veces actual: 1");
        if ($expectedPrize5 == 4200 && $count5 == 3) {
            $this->info("   ✅ Premio correcto, pero Veces debería ser {$count5}, no 1");
        } else {
            $this->error("   ❌ Premio incorrecto o Veces incorrecto");
        }
        $this->newLine();

        // Analizar **45 a posición 10
        $this->info("3. **45 a posición 10 (busca de 2-10):");
        $count10 = 0;
        $positions10 = [];
        foreach (range(2, 10) as $pos) {
            if (substr($extracts[$pos], -2) === '45') {
                $count10++;
                $positions10[] = $pos;
            }
        }
        $this->line("   Aparece en posiciones: " . implode(', ', $positions10));
        $this->line("   Veces: {$count10}");
        $expectedPrize10 = 21.00 * $count10 * 150;
        $this->line("   Premio esperado: $21.00 × {$count10} × $150 = $" . number_format($expectedPrize10, 2));
        $this->line("   Premio actual: $3,150.00");
        $this->line("   Veces actual: 1");
        if ($expectedPrize10 == 3150 && $count10 == 1) {
            $this->info("   ✅ Premio correcto para 1 vez");
        } else {
            $this->error("   ❌ Premio incorrecto (debería ser $" . number_format($expectedPrize10, 2) . " con {$count10} veces)");
        }
        $this->newLine();

        // Analizar **45 a posición 20
        $this->info("4. **45 a posición 20 (busca de 2-20):");
        $count20 = 0;
        $positions20 = [];
        foreach (range(2, 20) as $pos) {
            if (substr($extracts[$pos], -2) === '45') {
                $count20++;
                $positions20[] = $pos;
            }
        }
        $this->line("   Aparece en posiciones: " . implode(', ', $positions20));
        $this->line("   Veces: {$count20}");
        $expectedPrize20 = 3.50 * $count20 * 150;
        $this->line("   Premio esperado: $3.50 × {$count20} × $150 = $" . number_format($expectedPrize20, 2));
        $this->line("   Premio actual: $2,625.00");
        $this->line("   Veces actual: 1");
        if ($expectedPrize20 == 2625 && $count20 == 5) {
            $this->info("   ✅ Premio correcto para 5 veces");
        } else {
            $this->error("   ❌ Premio incorrecto (debería ser $" . number_format($expectedPrize20, 2) . " con {$count20} veces)");
        }
        $this->newLine();

        // Resumen
        $this->info("=== RESUMEN ===");
        $this->line("Posición 1: Veces 1 ✅");
        $this->line("Posición 5: Veces {$count5} (actual: 1) " . ($count5 == 3 ? "❌ Debería ser 3" : ""));
        $this->line("Posición 10: Veces {$count10} (actual: 1) " . ($count10 == 3 ? "❌ Debería ser 3" : ""));
        $this->line("Posición 20: Veces {$count20} (actual: 1) " . ($count20 == 6 ? "❌ Debería ser 6" : ""));

        return 0;
    }
}

