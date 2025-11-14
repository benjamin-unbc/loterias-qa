<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class AnalyzeTicket53 extends Command
{
    protected $signature = 'analyze:ticket53';
    protected $description = 'Analiza el ticket 53-0036';

    public function handle()
    {
        $this->info("=== ANÁLISIS TICKET 53-0036 ===\n");

        // Extractos RIO2100
        $extracts = [
            1 => '4433', 2 => '4433', 3 => null, 4 => '4433', 5 => '3464',
            6 => '4574', 7 => '4744', 8 => '4574', 9 => '4574', 10 => '6856',
            11 => '4657', 12 => '3463', 13 => null, 14 => '5685', 15 => '3547',
            16 => '4433', 17 => '6789', 18 => '7676', 19 => null, 20 => '6796'
        ];

        $this->info("Extractos RIO2100:");
        foreach ($extracts as $pos => $num) {
            if ($num) {
                $this->line("  Posición {$pos}: {$num}");
            }
        }
        $this->newLine();

        // Analizar **33 a posición 1
        $this->info("1. **33 a posición 1 (busca solo posición 1):");
        $pos1 = $extracts[1];
        if ($pos1 && substr($pos1, -2) === '33') {
            $this->line("   ✅ Sale en posición 1: {$pos1}");
            $this->line("   Veces: 1 (correcto)");
            $this->line("   Premio: $70.00 × 1 × $150 = $10,500.00 ✅");
        }
        $this->newLine();

        // Analizar **33 a posición 20
        $this->info("2. **33 a posición 20 (busca de 2-20):");
        $count20 = 0;
        $positions20 = [];
        foreach (range(2, 20) as $pos) {
            if (isset($extracts[$pos]) && $extracts[$pos] && substr($extracts[$pos], -2) === '33') {
                $count20++;
                $positions20[] = $pos;
            }
        }
        $this->line("   Aparece en posiciones: " . implode(', ', $positions20));
        $this->line("   Veces: {$count20}");
        if ($count20 > 1) {
            $this->error("   ❌ Debería mostrar Veces: {$count20}, pero muestra Veces: 1");
            $this->line("   Premio correcto: $3.50 × {$count20} × $150 = $" . number_format(3.50 * $count20 * 150, 2));
            $this->line("   Premio actual: $2,100.00");
        } else {
            $this->line("   ✅ Correcto");
        }
        $this->newLine();

        // Analizar **33 a posición 5
        $this->info("3. **33 a posición 5 (busca de 2-5):");
        $count5 = 0;
        $positions5 = [];
        foreach (range(2, 5) as $pos) {
            if (isset($extracts[$pos]) && $extracts[$pos] && substr($extracts[$pos], -2) === '33') {
                $count5++;
                $positions5[] = $pos;
            }
        }
        $this->line("   Aparece en posiciones: " . implode(', ', $positions5));
        $this->line("   Veces: {$count5}");
        if ($count5 > 1) {
            $this->error("   ❌ Debería mostrar Veces: {$count5}, pero muestra Veces: 1");
        } else {
            $this->line("   ✅ Correcto");
        }
        $this->newLine();

        // Analizar redoblona **33 posición 5, redoblona 33 posición 20
        $this->info("4. **33 posición 5, redoblona 33 posición 20:");
        $this->line("   Primero verificar **33 en posición 5 (busca 2-5):");
        $this->line("   - Posición 2: {$extracts[2]} (termina en " . substr($extracts[2], -2) . ")");
        $this->line("   - Posición 4: {$extracts[4]} (termina en " . substr($extracts[4], -2) . ")");
        $this->line("   ✅ Número principal ganador");
        $this->line("   Luego verificar redoblona 33 en posición 20 (busca 2-20):");
        $redoblonaCount = 0;
        $redoblonaPositions = [];
        foreach (range(2, 20) as $pos) {
            if (isset($extracts[$pos]) && $extracts[$pos] && substr($extracts[$pos], -2) === '33') {
                $redoblonaCount++;
                $redoblonaPositions[] = $pos;
            }
        }
        $this->line("   Redoblona aparece en posiciones: " . implode(', ', $redoblonaPositions));
        $this->line("   Veces redoblona: {$redoblonaCount}");
        if ($redoblonaCount > 1) {
            $this->error("   ❌ Debería mostrar Veces: {$redoblonaCount}, pero muestra Veces: 1");
            // Tabla redoblona 5→20: $64.00
            $this->line("   Premio correcto: $64.00 × {$redoblonaCount} × $150 = $" . number_format(64 * $redoblonaCount * 150, 2));
            $this->line("   Premio actual: $38,400.00");
        } else {
            $this->line("   ✅ Correcto");
        }

        return 0;
    }
}

