<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$date = '2025-12-01';

echo "=== LIMPIANDO RESULTADOS DUPLICADOS (Fecha: {$date}) ===\n\n";
echo "Estrategia: Mantener los resultados más recientes (creados después de las 13:50)\n";
echo "y eliminar los más antiguos (creados antes de las 13:00)\n\n";

// Buscar duplicados con el mismo apu_id
$duplicates = DB::select("
    SELECT 
        ticket, 
        lottery, 
        number, 
        position, 
        date, 
        apu_id,
        COUNT(*) as cantidad,
        GROUP_CONCAT(id ORDER BY id) as result_ids
    FROM results 
    WHERE date = ?
      AND apu_id IS NOT NULL
    GROUP BY ticket, lottery, number, position, date, apu_id
    HAVING COUNT(*) > 1
", [$date]);

if (empty($duplicates)) {
    echo "✅ No hay duplicados para limpiar\n";
    exit(0);
}

echo "Se encontraron " . count($duplicates) . " grupos de duplicados:\n\n";

$deletedCount = 0;
$keptCount = 0;
$deletedIds = [];
$keptIds = [];

foreach ($duplicates as $dup) {
    $resultIds = explode(',', $dup->result_ids);
    
    echo "=== GRUPO DUPLICADO ===\n";
    echo "Ticket: {$dup->ticket} - Lotería: {$dup->lottery} - Número: {$dup->number} - Posición: {$dup->position}\n";
    echo "APU ID: {$dup->apu_id}\n";
    
    // Obtener todos los resultados duplicados ordenados por fecha de creación (más reciente primero)
    $results = DB::select("
        SELECT id, aciert, created_at
        FROM results
        WHERE id IN (" . implode(',', $resultIds) . ")
        ORDER BY created_at DESC, id DESC
    ");
    
    // Mantener el primero (más reciente)
    $keepResult = $results[0];
    $deleteResults = array_slice($results, 1);
    
    echo "  ✅ Manteniendo resultado ID: {$keepResult->id} (Creado: {$keepResult->created_at}, Premio: \${$keepResult->aciert})\n";
    $keptCount++;
    $keptIds[] = $keepResult->id;
    
    // Eliminar los duplicados más antiguos
    foreach ($deleteResults as $deleteResult) {
        DB::table('results')->where('id', $deleteResult->id)->delete();
        echo "  ❌ Eliminado resultado ID: {$deleteResult->id} (Creado: {$deleteResult->created_at}, Premio: \${$deleteResult->aciert})\n";
        $deletedCount++;
        $deletedIds[] = $deleteResult->id;
    }
    echo "\n";
}

echo "\n=== RESUMEN ===\n";
echo "Grupos de duplicados encontrados: " . count($duplicates) . "\n";
echo "Resultados mantenidos: {$keptCount}\n";
echo "Resultados eliminados: {$deletedCount}\n";
echo "\n";
echo "IDs mantenidos: " . implode(', ', $keptIds) . "\n";
echo "IDs eliminados: " . implode(', ', $deletedIds) . "\n";

echo "\n=== VERIFICANDO DUPLICADOS DESPUÉS DE LA LIMPIEZA ===\n\n";

// Verificar si aún hay duplicados
$remainingDuplicates = DB::select("
    SELECT 
        ticket, 
        lottery, 
        number, 
        position, 
        date, 
        apu_id,
        COUNT(*) as cantidad,
        GROUP_CONCAT(id) as result_ids
    FROM results 
    WHERE date = ?
      AND apu_id IS NOT NULL
    GROUP BY ticket, lottery, number, position, date, apu_id
    HAVING COUNT(*) > 1
", [$date]);

if (empty($remainingDuplicates)) {
    echo "✅ No hay duplicados después de la limpieza\n";
} else {
    echo "❌ Aún se encontraron " . count($remainingDuplicates) . " grupos de duplicados:\n\n";
    foreach ($remainingDuplicates as $dup) {
        echo "Ticket: {$dup->ticket} - Lotería: {$dup->lottery} - Número: {$dup->number} - Posición: {$dup->position} - APU ID: {$dup->apu_id} - Cantidad: {$dup->cantidad}\n";
        echo "  IDs de resultados: {$dup->result_ids}\n\n";
    }
}

// Verificar resumen final
$summary = DB::select("
    SELECT 
        COUNT(*) as total_resultados,
        COUNT(DISTINCT apu_id) as total_apus_unicos,
        COUNT(CASE WHEN apu_id IS NULL THEN 1 END) as resultados_sin_apu_id
    FROM results 
    WHERE date = ?
", [$date]);

if (!empty($summary)) {
    $s = $summary[0];
    echo "\n=== RESUMEN FINAL ===\n";
    echo "Total de resultados: {$s->total_resultados}\n";
    echo "Total de APUs únicos: {$s->total_apus_unicos}\n";
    echo "Resultados sin apu_id: {$s->resultados_sin_apu_id}\n";
}

echo "\n=== FIN ===\n";
