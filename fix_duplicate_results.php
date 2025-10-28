<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Services\ResultManager;
use Carbon\Carbon;

echo "=== CORRECCIÓN DE DUPLICADOS EN RESULTADOS ===" . PHP_EOL;
echo "Fecha: " . Carbon::now()->format('Y-m-d H:i:s') . PHP_EOL;
echo PHP_EOL;

// Paso 1: Ejecutar migración para agregar restricción de unicidad
echo "📋 Paso 1: Ejecutando migración para agregar restricción de unicidad..." . PHP_EOL;
try {
    \Artisan::call('migrate', ['--path' => 'database/migrations/2025_01_15_000000_add_unique_constraint_to_results_table.php']);
    echo "✅ Migración ejecutada exitosamente" . PHP_EOL;
} catch (\Exception $e) {
    echo "⚠️  Error en migración: " . $e->getMessage() . PHP_EOL;
    echo "   Esto puede ser normal si la restricción ya existe" . PHP_EOL;
}
echo PHP_EOL;

// Paso 2: Limpiar duplicados existentes
echo "🧹 Paso 2: Limpiando duplicados existentes..." . PHP_EOL;
$today = Carbon::today()->format('Y-m-d');
$removedCount = ResultManager::cleanDuplicateResults($today);

if ($removedCount > 0) {
    echo "✅ Se eliminaron {$removedCount} resultados duplicados para hoy" . PHP_EOL;
} else {
    echo "✨ No se encontraron duplicados para hoy" . PHP_EOL;
}
echo PHP_EOL;

// Paso 3: Verificar resultados
echo "📊 Paso 3: Verificando resultados..." . PHP_EOL;
$results = \App\Models\Result::where('date', $today)->get();

if ($results->count() > 0) {
    echo "📈 Total de resultados para hoy: " . $results->count() . PHP_EOL;
    
    // Agrupar por ticket para verificar duplicados
    $groupedResults = $results->groupBy(function($result) {
        return $result->ticket . '_' . $result->lottery . '_' . $result->number . '_' . $result->position;
    });
    
    $duplicateGroups = $groupedResults->filter(function($group) {
        return $group->count() > 1;
    });
    
    if ($duplicateGroups->count() > 0) {
        echo "⚠️  Aún existen " . $duplicateGroups->count() . " grupos con duplicados:" . PHP_EOL;
        foreach ($duplicateGroups as $key => $group) {
            echo "   - {$key}: " . $group->count() . " resultados" . PHP_EOL;
            foreach ($group as $result) {
                echo "     * ID {$result->id}: Premio \${$result->aciert}" . PHP_EOL;
            }
        }
    } else {
        echo "✅ No se encontraron duplicados restantes" . PHP_EOL;
    }
} else {
    echo "❌ No se encontraron resultados para hoy" . PHP_EOL;
}

echo PHP_EOL;
echo "🎉 Corrección completada" . PHP_EOL;
echo "💡 Recomendación: Ejecutar 'php artisan lottery:clean-duplicates --all' para limpiar todas las fechas" . PHP_EOL;
