<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$date = '2025-12-01';

echo "=== ANÁLISIS DE DUPLICADOS (Fecha: {$date}) ===\n\n";

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
    echo "✅ No hay duplicados\n";
    exit(0);
}

echo "Se encontraron " . count($duplicates) . " grupos de duplicados:\n\n";

foreach ($duplicates as $dup) {
    $resultIds = explode(',', $dup->result_ids);
    
    echo "=== GRUPO DUPLICADO ===\n";
    echo "Ticket: {$dup->ticket} - Lotería: {$dup->lottery} - Número: {$dup->number} - Posición: {$dup->position}\n";
    echo "APU ID: {$dup->apu_id}\n";
    echo "Cantidad de duplicados: {$dup->cantidad}\n\n";
    
    // Obtener detalles de cada resultado duplicado
    $results = DB::select("
        SELECT 
            id, 
            aciert, 
            created_at, 
            updated_at,
            import,
            times_won,
            numero_g,
            posicion_g,
            numR,
            posR
        FROM results
        WHERE id IN (" . implode(',', $resultIds) . ")
        ORDER BY id
    ");
    
    echo "Detalles de cada resultado:\n";
    foreach ($results as $result) {
        echo "  ID: {$result->id}\n";
        echo "    Premio: \${$result->aciert}\n";
        echo "    Importe: \${$result->import}\n";
        echo "    Veces ganado: {$result->times_won}\n";
        echo "    Creado: {$result->created_at}\n";
        echo "    Actualizado: {$result->updated_at}\n";
        echo "    Numero_g: {$result->numero_g}\n";
        echo "    Posicion_g: {$result->posicion_g}\n";
        if ($result->numR) {
            echo "    NumR: {$result->numR} - PosR: {$result->posR}\n";
        }
        echo "\n";
    }
    
    // Verificar el APU correspondiente
    $apu = DB::select("
        SELECT 
            id,
            ticket,
            lottery,
            number,
            position,
            numberR,
            positionR,
            import,
            user_id,
            created_at
        FROM apus
        WHERE id = ?
    ", [$dup->apu_id]);
    
    if (!empty($apu)) {
        $apu = $apu[0];
        echo "APU correspondiente:\n";
        echo "  ID: {$apu->id}\n";
        echo "  Ticket: {$apu->ticket}\n";
        echo "  Lotería: {$apu->lottery}\n";
        echo "  Número: {$apu->number}\n";
        echo "  Posición: {$apu->position}\n";
        if ($apu->numberR) {
            echo "  NumR: {$apu->numberR} - PosR: {$apu->positionR}\n";
        }
        echo "  Importe: \${$apu->import}\n";
        echo "  Creado: {$apu->created_at}\n";
        echo "\n";
    }
    
    // Verificar si hay múltiples PlaysSent con el mismo ticket
    $playsSents = DB::select("
        SELECT 
            id,
            ticket,
            date,
            status,
            created_at
        FROM plays_sent
        WHERE ticket = ?
          AND DATE(date) = ?
    ", [$dup->ticket, $date]);
    
    if (count($playsSents) > 1) {
        echo "⚠️  ADVERTENCIA: Hay " . count($playsSents) . " PlaysSent con el mismo ticket:\n";
        foreach ($playsSents as $ps) {
            echo "  ID: {$ps->id} - Status: {$ps->status} - Creado: {$ps->created_at}\n";
        }
        echo "\n";
    }
    
    // Analizar diferencias entre los resultados
    if (count($results) > 1) {
        $first = $results[0];
        $second = $results[1];
        
        echo "Análisis de diferencias:\n";
        if ($first->aciert != $second->aciert) {
            echo "  ⚠️  Los premios son diferentes: \${$first->aciert} vs \${$second->aciert}\n";
        }
        if ($first->created_at != $second->created_at) {
            echo "  ⚠️  Fechas de creación diferentes: {$first->created_at} vs {$second->created_at}\n";
            $diff = strtotime($second->created_at) - strtotime($first->created_at);
            echo "     Diferencia: " . abs($diff) . " segundos\n";
        }
        if ($first->numero_g != $second->numero_g || $first->posicion_g != $second->posicion_g) {
            echo "  ⚠️  Números ganadores diferentes:\n";
            echo "     Resultado 1: numero_g={$first->numero_g}, posicion_g={$first->posicion_g}\n";
            echo "     Resultado 2: numero_g={$second->numero_g}, posicion_g={$second->posicion_g}\n";
        }
    }
    
    echo "\n" . str_repeat("=", 80) . "\n\n";
}

// Análisis general
echo "=== ANÁLISIS GENERAL ===\n\n";

// Verificar si hay resultados creados en diferentes momentos
$timeAnalysis = DB::select("
    SELECT 
        DATE_FORMAT(created_at, '%Y-%m-%d %H:%i:%s') as creation_time,
        COUNT(*) as cantidad
    FROM results
    WHERE date = ?
      AND apu_id IS NOT NULL
    GROUP BY DATE_FORMAT(created_at, '%Y-%m-%d %H:%i:%s')
    ORDER BY creation_time
", [$date]);

echo "Distribución de resultados por hora de creación:\n";
foreach ($timeAnalysis as $ta) {
    echo "  {$ta->creation_time}: {$ta->cantidad} resultados\n";
}
echo "\n";

// Verificar si el índice único está funcionando
echo "=== VERIFICACIÓN DEL ÍNDICE ÚNICO ===\n";
$indexCheck = DB::select("SHOW INDEX FROM results WHERE Key_name = 'unique_result_per_apu'");
if (empty($indexCheck)) {
    echo "❌ El índice único NO existe\n";
} else {
    echo "✅ El índice único existe\n";
    echo "Columnas: ";
    $columns = [];
    foreach ($indexCheck as $idx) {
        $columns[] = $idx->Column_name;
    }
    echo implode(', ', $columns) . "\n";
    echo "\n";
    
    // Intentar insertar un duplicado para ver si el índice lo previene
    echo "Nota: El índice único debería prevenir la inserción de duplicados.\n";
    echo "Si hay duplicados, probablemente se crearon:\n";
    echo "  1. Antes de que existiera el índice único\n";
    echo "  2. O se insertaron en una transacción que falló parcialmente\n";
    echo "  3. O hay un problema con la lógica de verificación en ResultManager\n";
}

echo "\n=== FIN DEL ANÁLISIS ===\n";

