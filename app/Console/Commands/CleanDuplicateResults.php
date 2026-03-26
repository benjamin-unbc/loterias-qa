<?php

namespace App\Console\Commands;

use App\Services\ResultManager;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CleanDuplicateResults extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'lottery:clean-duplicates {--date= : Fecha específica para limpiar (YYYY-MM-DD)} {--all : Limpiar todas las fechas}';

    /**
     * The console command description.
     */
    protected $description = 'Limpia resultados duplicados manteniendo el de mayor premio';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info("🧹 Iniciando limpieza de resultados duplicados...");
        
        if ($this->option('all')) {
            $this->cleanAllDates();
        } elseif ($this->option('date')) {
            $date = $this->option('date');
            $this->cleanSpecificDate($date);
        } else {
            // Por defecto, limpiar solo la fecha de hoy
            $today = Carbon::today()->format('Y-m-d');
            $this->cleanSpecificDate($today);
        }
        
        $this->info("✅ Limpieza completada");
    }

    /**
     * Limpia duplicados para una fecha específica
     */
    private function cleanSpecificDate(string $date)
    {
        $this->info("📅 Limpiando duplicados para la fecha: {$date}");
        
        $removedCount = ResultManager::cleanDuplicateResults($date);
        
        if ($removedCount > 0) {
            $this->info("🗑️  Se eliminaron {$removedCount} resultados duplicados");
        } else {
            $this->info("✨ No se encontraron duplicados para esta fecha");
        }
    }

    /**
     * Limpia duplicados para todas las fechas
     */
    private function cleanAllDates()
    {
        $this->info("📅 Limpiando duplicados para todas las fechas...");
        
        // Obtener todas las fechas únicas con resultados
        $dates = \App\Models\Result::select('date')
            ->distinct()
            ->orderBy('date', 'desc')
            ->pluck('date');
        
        $totalRemoved = 0;
        
        foreach ($dates as $date) {
            $this->line("Procesando fecha: {$date}");
            $removedCount = ResultManager::cleanDuplicateResults($date);
            $totalRemoved += $removedCount;
            
            if ($removedCount > 0) {
                $this->line("  - Eliminados: {$removedCount}");
            }
        }
        
        $this->info("🗑️  Total de duplicados eliminados: {$totalRemoved}");
    }
}
