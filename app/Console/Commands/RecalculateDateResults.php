<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Result;
use App\Services\LotteryResultProcessor;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class RecalculateDateResults extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'recalculate:date-results {date} {--force : Forzar recálculo sin confirmación}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Recalcula todos los resultados de una fecha específica con la nueva lógica de redoblonas corregida';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $dateInput = $this->argument('date');
        $force = $this->option('force');

        // Validar formato de fecha
        try {
            $date = Carbon::parse($dateInput)->format('Y-m-d');
        } catch (\Exception $e) {
            $this->error("Fecha inválida: {$dateInput}. Use el formato YYYY-MM-DD (ej: 2025-11-25)");
            return 1;
        }

        $this->info("=== RECALCULANDO RESULTADOS PARA LA FECHA: {$date} ===");
        $this->newLine();

        // Contar resultados existentes
        $existingResults = Result::whereDate('date', $date)->count();
        
        if ($existingResults > 0) {
            $this->warn("⚠️  Se encontraron {$existingResults} resultados existentes para la fecha {$date}");
            
            if (!$force) {
                if (!$this->confirm("¿Desea eliminar estos resultados y recalcularlos con la nueva lógica?", false)) {
                    $this->info("Operación cancelada.");
                    return 0;
                }
            }

            $this->info("Eliminando resultados existentes...");
            $deleted = Result::whereDate('date', $date)->delete();
            $this->info("✅ {$deleted} resultados eliminados.");
            $this->newLine();
        } else {
            $this->info("No se encontraron resultados existentes para la fecha {$date}.");
            $this->newLine();
        }

        // Recalcular resultados con la nueva lógica
        $this->info("Recalculando resultados con la nueva lógica de redoblonas...");
        $this->newLine();

        try {
            $processor = new LotteryResultProcessor();
            $processor->process($date);
            
            $this->newLine();
            $this->info("✅ Procesamiento completado.");
            
            // Contar resultados nuevos
            $newResults = Result::whereDate('date', $date)->count();
            $this->info("📊 Total de resultados insertados: {$newResults}");
            
            // Mostrar resumen de redoblonas
            $redoblonaResults = Result::whereDate('date', $date)
                ->whereNotNull('numR')
                ->whereNotNull('posR')
                ->count();
            
            if ($redoblonaResults > 0) {
                $this->info("🎯 Resultados con redoblona: {$redoblonaResults}");
            }
            
            $this->newLine();
            $this->info("=== RECÁLCULO FINALIZADO ===");
            
            return 0;
        } catch (\Exception $e) {
            $this->error("❌ Error al recalcular resultados: " . $e->getMessage());
            Log::error("RecalculateDateResults - Error: " . $e->getMessage(), [
                'date' => $date,
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }
    }
}

