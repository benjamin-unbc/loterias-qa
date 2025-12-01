<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$date = '2025-12-01';

echo "=== VERIFICANDO ÍNDICE ÚNICO ===\n\n";

// Verificar si el índice único existe
$indexes = DB::select("SHOW INDEX FROM results WHERE Key_name = 'unique_result_per_apu'");

if (empty($indexes)) {
    echo "❌ El índice único 'unique_result_per_apu' NO existe\n\n";
} else {
    echo "✅ El índice único 'unique_result_per_apu' existe\n";
    echo "Columnas del índice:\n";
    foreach ($indexes as $index) {
        echo "  - {$index->Column_name}\n";
    }
    echo "\n";
}

echo "=== VERIFICANDO RESULTADOS DUPLICADOS CON MISMO APU_ID (Fecha: {$date}) ===\n\n";

// Buscar resultados duplicados con el mismo apu_id
$duplicates = DB::select("
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

if (empty($duplicates)) {
    echo "✅ No hay resultados duplicados con el mismo apu_id\n\n";
} else {
    echo "❌ Se encontraron " . count($duplicates) . " grupos de duplicados con el mismo apu_id:\n\n";
    foreach ($duplicates as $dup) {
        echo "Ticket: {$dup->ticket} - Lotería: {$dup->lottery} - Número: {$dup->number} - Posición: {$dup->position} - APU ID: {$dup->apu_id} - Cantidad: {$dup->cantidad}\n";
        echo "  IDs de resultados: {$dup->result_ids}\n\n";
    }
}

echo "=== VERIFICANDO RESULTADOS DUPLICADOS SIN APU_ID (Fecha: {$date}) ===\n\n";

// Buscar resultados duplicados sin apu_id (deberían tener apu_id)
$duplicatesWithoutApuId = DB::select("
    SELECT 
        ticket, 
        lottery, 
        number, 
        position, 
        date,
        COUNT(*) as cantidad,
        GROUP_CONCAT(id) as result_ids,
        GROUP_CONCAT(apu_id) as apu_ids
    FROM results 
    WHERE date = ?
      AND (apu_id IS NULL OR apu_id = 0)
    GROUP BY ticket, lottery, number, position, date
    HAVING COUNT(*) > 1
", [$date]);

if (empty($duplicatesWithoutApuId)) {
    echo "✅ No hay resultados duplicados sin apu_id\n\n";
} else {
    echo "⚠️  Se encontraron " . count($duplicatesWithoutApuId) . " grupos de duplicados sin apu_id:\n\n";
    foreach ($duplicatesWithoutApuId as $dup) {
        echo "Ticket: {$dup->ticket} - Lotería: {$dup->lottery} - Número: {$dup->number} - Posición: {$dup->position} - Cantidad: {$dup->cantidad}\n";
        echo "  IDs de resultados: {$dup->result_ids}\n";
        echo "  APU IDs: {$dup->apu_ids}\n\n";
    }
}

echo "=== VERIFICANDO RESULTADOS CON MISMO TICKET/LOTERÍA/NÚMERO/POSICIÓN PERO DIFERENTE APU_ID (Fecha: {$date}) ===\n\n";

// Buscar resultados que tienen el mismo ticket/lottery/number/position pero diferente apu_id
// Esto es normal y esperado - cada APU debería tener su propio resultado
$sameTicketDifferentApu = DB::select("
    SELECT 
        ticket, 
        lottery, 
        number, 
        position, 
        date,
        COUNT(DISTINCT apu_id) as cantidad_apus,
        COUNT(*) as cantidad_resultados,
        GROUP_CONCAT(DISTINCT apu_id) as apu_ids,
        GROUP_CONCAT(id) as result_ids
    FROM results 
    WHERE date = ?
      AND apu_id IS NOT NULL
    GROUP BY ticket, lottery, number, position, date
    HAVING COUNT(DISTINCT apu_id) > 1
    ORDER BY cantidad_resultados DESC
    LIMIT 20
", [$date]);

if (empty($sameTicketDifferentApu)) {
    echo "✅ No hay resultados con mismo ticket/lottery/number/position pero diferente apu_id\n\n";
} else {
    echo "ℹ️  Se encontraron " . count($sameTicketDifferentApu) . " casos donde el mismo número en el mismo ticket tiene múltiples APUs (esto es CORRECTO):\n\n";
    foreach ($sameTicketDifferentApu as $dup) {
        echo "Ticket: {$dup->ticket} - Lotería: {$dup->lottery} - Número: {$dup->number} - Posición: {$dup->position}\n";
        echo "  Cantidad de APUs diferentes: {$dup->cantidad_apus} - Cantidad de resultados: {$dup->cantidad_resultados}\n";
        echo "  APU IDs: {$dup->apu_ids}\n";
        echo "  Result IDs: {$dup->result_ids}\n\n";
    }
}

echo "=== RESUMEN DE RESULTADOS PARA LA FECHA {$date} ===\n\n";

$summary = DB::select("
    SELECT 
        COUNT(*) as total_resultados,
        COUNT(DISTINCT apu_id) as total_apus_unicos,
        COUNT(CASE WHEN apu_id IS NULL THEN 1 END) as resultados_sin_apu_id,
        SUM(aciert) as total_premios
    FROM results 
    WHERE date = ?
", [$date]);

if (!empty($summary)) {
    $s = $summary[0];
    echo "Total de resultados: {$s->total_resultados}\n";
    echo "Total de APUs únicos: {$s->total_apus_unicos}\n";
    echo "Resultados sin apu_id: {$s->resultados_sin_apu_id}\n";
    echo "Total de premios: \${$s->total_premios}\n\n";
}

echo "=== FIN DE VERIFICACIÓN ===\n";

