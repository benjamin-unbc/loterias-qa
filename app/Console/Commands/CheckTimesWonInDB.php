<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Result;

class CheckTimesWonInDB extends Command
{
    protected $signature = 'check:times-won-db';
    protected $description = 'Verifica times_won en la base de datos';

    public function handle()
    {
        $this->info("=== VERIFICANDO times_won EN BASE DE DATOS ===\n");

        // Verificar **74 en CTE1500
        $result = Result::where('ticket', '63-0001')
            ->where('number', '**74')
            ->where('lottery', 'CTE1500')
            ->first();

        if ($result) {
            $this->line("**74 en CTE1500:");
            $this->line("  - times_won: " . ($result->times_won ?? 'NULL'));
            $this->line("  - aciert: $" . number_format($result->aciert ?? 0, 2));
            $this->line("  - position: " . $result->position);
        } else {
            $this->error("No se encontró el resultado");
        }

        // Verificar todos los resultados del ticket
        $allResults = Result::where('ticket', '63-0001')
            ->where('times_won', '>', 1)
            ->get();

        if ($allResults->count() > 0) {
            $this->info("\nResultados con times_won > 1:");
            foreach ($allResults as $r) {
                $this->line("  - {$r->number} posición {$r->position} en {$r->lottery}: Veces {$r->times_won}");
            }
        } else {
            $this->warn("\nNo se encontraron resultados con times_won > 1");
        }

        return 0;
    }
}

