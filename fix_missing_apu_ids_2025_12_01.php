<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

$date = '2025-12-01';

echo "=== VERIFICANDO RESULTADOS SIN APU_ID (Fecha: {$date}) ===\n\n";

// Buscar resultados sin apu_id
$resultsWithoutApuId = DB::select("
    SELECT 
        id, 
        ticket, 
        lottery, 
        number, 
        position, 
        numR, 
        posR,
        date,
        user_id,
        aciert
    FROM results 
    WHERE date = ?
      AND (apu_id IS NULL OR apu_id = 0)
    ORDER BY ticket, lottery, number, position
", [$date]);

if (empty($resultsWithoutApuId)) {
    echo "✅ No hay resultados sin apu_id\n";
    exit(0);
}

echo "⚠️  Se encontraron " . count($resultsWithoutApuId) . " resultados sin apu_id:\n\n";

$updatedCount = 0;
$notFoundCount = 0;

foreach ($resultsWithoutApuId as $result) {
    echo "Resultado ID: {$result->id} - Ticket: {$result->ticket} - Lotería: {$result->lottery} - Número: {$result->number} - Posición: {$result->position}\n";
    
    // Buscar el APU correspondiente
    // Necesitamos buscar en la tabla apus basándonos en ticket, lottery, number, position, numR, posR, user_id
    $apuQuery = DB::table('apus')
        ->where('ticket', $result->ticket)
        ->where('lottery', $result->lottery)
        ->where('number', $result->number)
        ->where('position', $result->position)
        ->where('user_id', $result->user_id);
    
    // Incluir numR y posR si existen
    if ($result->numR !== null) {
        $apuQuery->where('numberR', $result->numR);
    } else {
        $apuQuery->whereNull('numberR');
    }
    
    if ($result->posR !== null) {
        $apuQuery->where('positionR', $result->posR);
    } else {
        $apuQuery->whereNull('positionR');
    }
    
    // Buscar también por fecha del plays_sent
    $apuQuery->join('plays_sent', 'apus.ticket', '=', 'plays_sent.ticket')
        ->whereDate('plays_sent.date', $date);
    
    $apus = $apuQuery->select('apus.*')->get();
    
    if ($apus->isEmpty()) {
        echo "  ❌ No se encontró APU correspondiente\n";
        $notFoundCount++;
    } elseif ($apus->count() == 1) {
        // Solo hay un APU que coincide, actualizar
        $apu = $apus->first();
        DB::table('results')
            ->where('id', $result->id)
            ->update(['apu_id' => $apu->id]);
        echo "  ✅ Actualizado con APU ID: {$apu->id}\n";
        $updatedCount++;
    } else {
        // Múltiples APUs que coinciden, usar el primero (o el que tenga el mismo import)
        $apu = $apus->first();
        DB::table('results')
            ->where('id', $result->id)
            ->update(['apu_id' => $apu->id]);
        echo "  ⚠️  Múltiples APUs encontrados, usando el primero (APU ID: {$apu->id})\n";
        $updatedCount++;
    }
    echo "\n";
}

echo "\n=== RESUMEN ===\n";
echo "Total de resultados sin apu_id: " . count($resultsWithoutApuId) . "\n";
echo "Resultados actualizados: {$updatedCount}\n";
echo "Resultados no encontrados: {$notFoundCount}\n";

if ($notFoundCount > 0) {
    echo "\n⚠️  Algunos resultados no pudieron ser actualizados porque no se encontró el APU correspondiente.\n";
    echo "Estos resultados pueden necesitar ser recalculados o eliminados.\n";
}

echo "\n=== VERIFICANDO DUPLICADOS DESPUÉS DE LA ACTUALIZACIÓN ===\n\n";

// Verificar si ahora hay duplicados
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
    echo "✅ No hay duplicados después de la actualización\n";
} else {
    echo "❌ Se encontraron " . count($duplicates) . " grupos de duplicados:\n\n";
    foreach ($duplicates as $dup) {
        echo "Ticket: {$dup->ticket} - Lotería: {$dup->lottery} - Número: {$dup->number} - Posición: {$dup->position} - APU ID: {$dup->apu_id} - Cantidad: {$dup->cantidad}\n";
        echo "  IDs de resultados: {$dup->result_ids}\n\n";
    }
}

echo "\n=== FIN ===\n";

