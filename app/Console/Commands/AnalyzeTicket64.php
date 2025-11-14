<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class AnalyzeTicket64 extends Command
{
    protected $signature = 'analyze:ticket64';
    protected $description = 'Analiza el ticket 62-0004 con extractos SFE1800';

    public function handle()
    {
        $this->info("=== ANÁLISIS TICKET 62-0004 (SFE1800) ===\n");

        // Extractos SFE1800
        $extracts = [
            1 => '3232', 2 => '2323', 3 => '3232', 4 => '3463', 5 => '2323',
            6 => '3232', 7 => '4364', 8 => '3464', 9 => '4363', 10 => '6436',
            11 => '3232', 12 => '3466', 13 => '3232', 14 => '5654', 15 => '4755',
            16 => '7567', 17 => '4567', 18 => '3546', 19 => '3464', 20 => '3232'
        ];

        $this->info("Extractos SFE1800:");
        foreach ($extracts as $pos => $num) {
            $endsWith32 = substr($num, -2) === '32';
            $marker = $endsWith32 ? ' ✅ **32' : '';
            $this->line("  Posición {$pos}: {$num}{$marker}");
        }
        $this->newLine();

        // Analizar 3232 a posición 1
        $this->info("1. 3232 a posición 1 (busca solo posición 1):");
        $pos1 = $extracts[1];
        if ($pos1 === '3232') {
            $this->line("   ✅ Sale en posición 1: {$pos1}");
            $this->line("   Veces: 1 (correcto)");
            $this->line("   Premio: $3,500.00 × 1 × $150 = $525,000.00 ✅");
        }
        $this->newLine();

        // Analizar **32 a posición 5
        $this->info("2. **32 a posición 5 (busca de 2-5):");
        $count5 = 0;
        $positions5 = [];
        foreach (range(2, 5) as $pos) {
            if (substr($extracts[$pos], -2) === '32') {
                $count5++;
                $positions5[] = $pos;
            }
        }
        $this->line("   Aparece en posiciones: " . (empty($positions5) ? "ninguna" : implode(', ', $positions5)));
        $this->line("   Veces esperado: {$count5}");
        $this->line("   Veces actual: 1");
        $expectedPrize5 = 14.00 * $count5 * 150;
        $this->line("   Premio esperado: $14.00 × {$count5} × $150 = $" . number_format($expectedPrize5, 2));
        $this->line("   Premio actual: $2,100.00");
        if ($count5 != 1) {
            $this->error("   ❌ Veces debería ser {$count5}, no 1");
            if ($expectedPrize5 != 2100) {
                $this->error("   ❌ Premio debería ser $" . number_format($expectedPrize5, 2) . ", no $2,100.00");
            }
        } else {
            $this->info("   ⚠️ No aparece en el rango 2-5, pero el premio es $2,100.00 (debería ser $0.00)");
        }
        $this->newLine();

        // Analizar **32 a posición 10
        $this->info("3. **32 a posición 10 (busca de 2-10):");
        $count10 = 0;
        $positions10 = [];
        foreach (range(2, 10) as $pos) {
            if (substr($extracts[$pos], -2) === '32') {
                $count10++;
                $positions10[] = $pos;
            }
        }
        $this->line("   Aparece en posiciones: " . implode(', ', $positions10));
        $this->line("   Veces esperado: {$count10}");
        $this->line("   Veces actual: 1");
        $expectedPrize10 = 21.00 * $count10 * 150;
        $this->line("   Premio esperado: $21.00 × {$count10} × $150 = $" . number_format($expectedPrize10, 2));
        $this->line("   Premio actual: $2,100.00");
        if ($count10 != 1) {
            $this->error("   ❌ Veces debería ser {$count10}, no 1");
            if ($expectedPrize10 != 2100) {
                $this->error("   ❌ Premio debería ser $" . number_format($expectedPrize10, 2) . ", no $2,100.00");
            }
        }
        $this->newLine();

        // Analizar **32 a posición 20
        $this->info("4. **32 a posición 20 (busca de 2-20):");
        $count20 = 0;
        $positions20 = [];
        foreach (range(2, 20) as $pos) {
            if (substr($extracts[$pos], -2) === '32') {
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
        $this->line("3232 posición 1: Veces 1 ✅");
        $this->line("**32 posición 5: Veces esperado {$count5}, actual 1 " . ($count5 != 1 ? "❌" : "✅"));
        $this->line("**32 posición 10: Veces esperado {$count10}, actual 1 " . ($count10 != 1 ? "❌" : "✅"));
        $this->line("**32 posición 20: Veces esperado {$count20}, actual 1 " . ($count20 != 1 ? "❌" : "✅"));

        return 0;
    }
}

