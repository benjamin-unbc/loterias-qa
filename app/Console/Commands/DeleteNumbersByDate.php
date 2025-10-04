<?php

namespace App\Console\Commands;

use App\Models\Number;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class DeleteNumbersByDate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'lottery:delete-numbers {date : Fecha en formato Y-m-d (ej: 2025-10-03)} {--confirm : Confirmar eliminación sin preguntar}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Elimina todos los registros de números de una fecha específica';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $date = $this->argument('date');
        $confirm = $this->option('confirm');
        
        // Validar formato de fecha
        try {
            $parsedDate = Carbon::createFromFormat('Y-m-d', $date);
        } catch (\Exception $e) {
            $this->error("❌ Formato de fecha inválido. Use Y-m-d (ej: 2025-10-03)");
            return 1;
        }
        
        // Contar registros a eliminar
        $count = Number::where('date', $date)->count();
        
        if ($count === 0) {
            $this->info("ℹ️  No se encontraron registros para la fecha {$date}");
            return 0;
        }
        
        $this->info("🔍 Se encontraron {$count} registros para la fecha {$date}");
        
        // Mostrar algunos ejemplos de registros que se eliminarán
        $examples = Number::where('date', $date)
                         ->with(['city', 'extract'])
                         ->limit(5)
                         ->get();
        
        if ($examples->count() > 0) {
            $this->info("📋 Ejemplos de registros a eliminar:");
            foreach ($examples as $number) {
                $this->line("  - {$number->city->name} - {$number->extract->name} - Pos {$number->index}: {$number->value}");
            }
            if ($count > 5) {
                $this->line("  ... y " . ($count - 5) . " registros más");
            }
        }
        
        // Confirmar eliminación
        if (!$confirm) {
            if (!$this->confirm("¿Está seguro de que desea eliminar estos {$count} registros?")) {
                $this->info("❌ Operación cancelada");
                return 0;
            }
        }
        
        try {
            // Eliminar registros
            $deleted = Number::where('date', $date)->delete();
            
            $this->info("✅ Se eliminaron {$deleted} registros de la fecha {$date}");
            Log::info("DeleteNumbersByDate - Eliminados {$deleted} registros de la fecha {$date}");
            
            return 0;
            
        } catch (\Exception $e) {
            $this->error("❌ Error al eliminar registros: " . $e->getMessage());
            Log::error("DeleteNumbersByDate - Error: " . $e->getMessage());
            return 1;
        }
    }
}
