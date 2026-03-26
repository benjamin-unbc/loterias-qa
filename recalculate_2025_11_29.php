<?php
/**
 * Script para recalcular resultados del 29-11-2025
 * Usa los extractos que ya están cargados en el sistema
 * 
 * Uso: php recalculate_2025_11_29.php
 * 
 * Este script:
 * 1. Elimina los resultados existentes del 29-11-2025
 * 2. Recalcula usando la nueva lógica corregida (contando todas las veces que sale un número)
 * 3. Usa los extractos/números que ya están cargados en la base de datos
 */

// Cargar Laravel
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->bootstrap();

use App\Models\Result;
use App\Services\LotteryResultProcessor;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

// Configurar zona horaria
date_default_timezone_set('America/Argentina/Buenos_Aires');

// Fecha específica a recalcular
$date = '2025-11-29';

echo "=== RECALCULANDO RESULTADOS PARA LA FECHA: {$date} ===\n\n";

// Contar resultados existentes
$existingResults = Result::whereDate('date', $date)->count();

if ($existingResults > 0) {
    echo "⚠️  Se encontraron {$existingResults} resultados existentes para la fecha {$date}\n";
    echo "Eliminando resultados existentes...\n";
    
    $deleted = Result::whereDate('date', $date)->delete();
    echo "✅ {$deleted} resultados eliminados.\n\n";
} else {
    echo "No se encontraron resultados existentes para la fecha {$date}.\n\n";
}

// Recalcular resultados con la nueva lógica
echo "Recalculando resultados con la nueva lógica corregida...\n";
echo "(Usando extractos/números que ya están cargados en el sistema)\n\n";

try {
    $processor = new LotteryResultProcessor();
    $processor->process($date);
    
    echo "\n✅ Procesamiento completado.\n\n";
    
    // Contar resultados nuevos
    $newResults = Result::whereDate('date', $date)->count();
    echo "📊 Total de resultados insertados: {$newResults}\n";
    
    // Mostrar resumen de resultados con múltiples veces
    $multipleTimesResults = Result::whereDate('date', $date)
        ->where('times_won', '>', 1)
        ->count();
    
    if ($multipleTimesResults > 0) {
        echo "🎯 Resultados con múltiples apariciones (times_won > 1): {$multipleTimesResults}\n";
    }
    
    // Mostrar resumen de redoblonas
    $redoblonaResults = Result::whereDate('date', $date)
        ->whereNotNull('numR')
        ->whereNotNull('posR')
        ->count();
    
    if ($redoblonaResults > 0) {
        echo "🎯 Resultados con redoblona: {$redoblonaResults}\n";
    }
    
    echo "\n=== RECÁLCULO FINALIZADO ===\n";
    
} catch (\Exception $e) {
    echo "❌ Error al recalcular resultados: " . $e->getMessage() . "\n";
    Log::error("recalculate_2025_11_29 - Error: " . $e->getMessage(), [
        'date' => $date,
        'trace' => $e->getTraceAsString()
    ]);
    exit(1);
}

exit(0);

