<?php
/**
 * Script para diagnosticar rendimiento de BD en QA
 * 
 * Como DB_HOST=localhost, la latencia de red NO es el problema.
 * Este script mide otras causas posibles:
 * - Tiempo de conexión
 * - Tiempo de consultas
 * - Carga del servidor (conexiones activas)
 * - Configuración de MySQL
 * - Tiempo de inserción real
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Models\Play;

echo "========================================\n";
echo "DIAGNÓSTICO DE RENDIMIENTO BD (QA)\n";
echo "========================================\n\n";

// Obtener configuración
$host = config('database.connections.mysql.host');
$port = config('database.connections.mysql.port');
$database = config('database.connections.mysql.database');

echo "Configuración:\n";
echo "  Host: {$host}\n";
echo "  Puerto: {$port}\n";
echo "  Base de datos: {$database}\n\n";

if ($host !== 'localhost' && $host !== '127.0.0.1') {
    echo "⚠️  ADVERTENCIA: DB_HOST no es localhost, puede haber latencia de red\n\n";
} else {
    echo "✅ BD está en localhost - La latencia de red NO es el problema\n\n";
}

// Test 1: Información del servidor MySQL
echo "----------------------------------------\n";
echo "TEST 1: Información del Servidor MySQL\n";
echo "----------------------------------------\n";

try {
    // Intentar obtener información del servidor (compatible con versiones antiguas)
    $serverInfo = DB::select("SELECT 
        VERSION() as version,
        @@max_connections as max_connections,
        @@threads_connected as threads_connected,
        @@threads_running as threads_running
    ")[0];
    
    // Intentar obtener variables adicionales si están disponibles
    try {
        $extraInfo = DB::select("SELECT 
            @@table_open_cache as table_open_cache,
            @@innodb_buffer_pool_size as innodb_buffer_pool_size
        ")[0];
        $serverInfo->table_open_cache = $extraInfo->table_open_cache ?? 'N/A';
        $serverInfo->innodb_buffer_pool_size = $extraInfo->innodb_buffer_pool_size ?? 'N/A';
    } catch (\Exception $e) {
        $serverInfo->table_open_cache = 'N/A';
        $serverInfo->innodb_buffer_pool_size = 'N/A';
    }
    
    // Intentar obtener max_used_connections si está disponible
    try {
        $maxUsed = DB::select("SHOW STATUS LIKE 'Max_used_connections'")[0];
        $serverInfo->max_used_connections = $maxUsed->Value ?? 'N/A';
    } catch (\Exception $e) {
        $serverInfo->max_used_connections = 'N/A';
    }
    
    echo "  Versión MySQL: " . $serverInfo->version . "\n";
    echo "  Conexiones máximas: " . number_format($serverInfo->max_connections) . "\n";
    if ($serverInfo->max_used_connections !== 'N/A') {
        echo "  Conexiones usadas (máx histórico): " . number_format($serverInfo->max_used_connections) . "\n";
    }
    echo "  Conexiones activas ahora: " . number_format($serverInfo->threads_connected) . "\n";
    echo "  Threads ejecutándose: " . number_format($serverInfo->threads_running) . "\n";
    
    if ($serverInfo->threads_connected > ($serverInfo->max_connections * 0.8)) {
        echo "\n  ⚠️  ADVERTENCIA: Muchas conexiones activas (" . $serverInfo->threads_connected . "/" . $serverInfo->max_connections . ")\n";
    }
    
    if ($serverInfo->threads_running > 10) {
        echo "\n  ⚠️  ADVERTENCIA: Muchos threads ejecutándose (" . $serverInfo->threads_running . ")\n";
        echo "      El servidor puede estar bajo carga\n";
    }
    
} catch (\Exception $e) {
    echo "  ERROR: " . $e->getMessage() . "\n";
}

// Test 2: Tiempo de conexión
echo "\n----------------------------------------\n";
echo "TEST 2: Tiempo de Conexión\n";
echo "----------------------------------------\n";

$connectionTimes = [];
for ($i = 1; $i <= 10; $i++) {
    $start = microtime(true);
    try {
        DB::connection()->getPdo();
        $end = microtime(true);
        $time = ($end - $start) * 1000;
        $connectionTimes[] = $time;
        echo "  Intento {$i}: " . number_format($time, 2) . " ms\n";
    } catch (\Exception $e) {
        echo "  Intento {$i}: ERROR\n";
    }
    usleep(50000); // 50ms entre intentos
}

if (!empty($connectionTimes)) {
    $avg = array_sum($connectionTimes) / count($connectionTimes);
    $min = min($connectionTimes);
    $max = max($connectionTimes);
    echo "\n  Promedio: " . number_format($avg, 2) . " ms\n";
    echo "  Mínimo: " . number_format($min, 2) . " ms\n";
    echo "  Máximo: " . number_format($max, 2) . " ms\n";
    
    if ($avg > 10) {
        echo "\n  ⚠️  ADVERTENCIA: Tiempo de conexión alto para localhost\n";
        echo "      En localhost debería ser < 5ms. Posibles causas:\n";
        echo "      - Servidor bajo carga (CPU/Memoria)\n";
        echo "      - Muchas conexiones activas\n";
        echo "      - Configuración de MySQL subóptima\n";
    }
}

// Test 3: Tiempo de consulta simple
echo "\n----------------------------------------\n";
echo "TEST 3: Tiempo de Consulta Simple\n";
echo "----------------------------------------\n";

$queryTimes = [];
for ($i = 1; $i <= 10; $i++) {
    $start = microtime(true);
    try {
        DB::select('SELECT 1 as test');
        $end = microtime(true);
        $time = ($end - $start) * 1000;
        $queryTimes[] = $time;
        echo "  Intento {$i}: " . number_format($time, 2) . " ms\n";
    } catch (\Exception $e) {
        echo "  Intento {$i}: ERROR\n";
    }
    usleep(50000);
}

if (!empty($queryTimes)) {
    $avg = array_sum($queryTimes) / count($queryTimes);
    $min = min($queryTimes);
    $max = max($queryTimes);
    echo "\n  Promedio: " . number_format($avg, 2) . " ms\n";
    echo "  Mínimo: " . number_format($min, 2) . " ms\n";
    echo "  Máximo: " . number_format($max, 2) . " ms\n";
}

// Test 4: Tiempo de validación FK (consulta a users)
echo "\n----------------------------------------\n";
echo "TEST 4: Tiempo de Validación FK (users)\n";
echo "----------------------------------------\n";

$userId = auth()->id() ?? 1;
$fkTimes = [];
for ($i = 1; $i <= 10; $i++) {
    $start = microtime(true);
    try {
        DB::table('users')->where('id', $userId)->first();
        $end = microtime(true);
        $time = ($end - $start) * 1000;
        $fkTimes[] = $time;
        echo "  Intento {$i}: " . number_format($time, 2) . " ms\n";
    } catch (\Exception $e) {
        echo "  Intento {$i}: ERROR\n";
    }
    usleep(50000);
}

if (!empty($fkTimes)) {
    $avg = array_sum($fkTimes) / count($fkTimes);
    $min = min($fkTimes);
    $max = max($fkTimes);
    echo "\n  Promedio: " . number_format($avg, 2) . " ms\n";
    echo "  Mínimo: " . number_format($min, 2) . " ms\n";
    echo "  Máximo: " . number_format($max, 2) . " ms\n";
}

// Test 5: Tiempo de inserción real (con análisis detallado)
echo "\n----------------------------------------\n";
echo "TEST 5: Tiempo de Inserción de Jugada\n";
echo "----------------------------------------\n";

$insertTimes = [];
$insertBreakdown = [];

for ($i = 1; $i <= 10; $i++) {
    $start = microtime(true);
    
    try {
        // Medir solo la creación
        $createStart = microtime(true);
        $play = Play::create([
            'user_id' => $userId,
            'type' => 'J',
            'number' => 'TEST' . time() . $i, // Usar timestamp para evitar duplicados
            'position' => '1',
            'import' => '100.00',
            'lottery' => 'TEST',
            'numberR' => null,
            'positionR' => null,
            'isChecked' => false,
        ]);
        $createEnd = microtime(true);
        
        // Medir el delete
        $deleteStart = microtime(true);
        $play->delete(); // Limpiar
        $deleteEnd = microtime(true);
        
        $end = microtime(true);
        $totalTime = ($end - $start) * 1000;
        $createTime = ($createEnd - $createStart) * 1000;
        $deleteTime = ($deleteEnd - $deleteStart) * 1000;
        
        $insertTimes[] = $totalTime;
        $insertBreakdown[] = [
            'total' => $totalTime,
            'create' => $createTime,
            'delete' => $deleteTime
        ];
        
        echo "  Intento {$i}: " . number_format($totalTime, 2) . " ms";
        echo " (crear: " . number_format($createTime, 2) . " ms, eliminar: " . number_format($deleteTime, 2) . " ms)\n";
    } catch (\Exception $e) {
        echo "  Intento {$i}: ERROR - " . $e->getMessage() . "\n";
    }
    usleep(50000); // 50ms entre inserciones
}

if (!empty($insertTimes)) {
    $avg = array_sum($insertTimes) / count($insertTimes);
    $min = min($insertTimes);
    $max = max($insertTimes);
    
    // Calcular desviación estándar para medir variabilidad
    $variance = 0;
    foreach ($insertTimes as $time) {
        $variance += pow($time - $avg, 2);
    }
    $stdDev = sqrt($variance / count($insertTimes));
    
    echo "\n  Promedio: " . number_format($avg, 2) . " ms\n";
    echo "  Mínimo: " . number_format($min, 2) . " ms\n";
    echo "  Máximo: " . number_format($max, 2) . " ms\n";
    echo "  Desviación estándar: " . number_format($stdDev, 2) . " ms\n";
    
    // Análisis de variabilidad
    if ($stdDev > ($avg * 0.5)) {
        echo "\n  ⚠️  ADVERTENCIA: Alta variabilidad en tiempos de inserción\n";
        echo "      La desviación estándar es > 50% del promedio\n";
        echo "      Esto indica que hay factores externos afectando:\n";
        echo "      - Bloqueos de tabla ocasionales\n";
        echo "      - Otras operaciones compitiendo\n";
        echo "      - Cache de MySQL calentándose\n";
    }
    
    // Análisis de tiempos de creación vs eliminación
    if (!empty($insertBreakdown)) {
        $avgCreate = array_sum(array_column($insertBreakdown, 'create')) / count($insertBreakdown);
        $avgDelete = array_sum(array_column($insertBreakdown, 'delete')) / count($insertBreakdown);
        echo "\n  Tiempo promedio de CREAR: " . number_format($avgCreate, 2) . " ms\n";
        echo "  Tiempo promedio de ELIMINAR: " . number_format($avgDelete, 2) . " ms\n";
    }
    
    if ($avg > 50) {
        echo "\n  ⚠️  ADVERTENCIA: Tiempo de inserción alto\n";
        echo "      En localhost debería ser < 20ms\n";
    } elseif ($max > ($avg * 3)) {
        echo "\n  ⚠️  ADVERTENCIA: Picos ocasionales muy altos\n";
        echo "      El máximo (" . number_format($max, 2) . " ms) es > 3x el promedio\n";
        echo "      Esto puede causar la sensación de lentitud\n";
    }
}

// Test 6: Verificar índices en tabla plays
echo "\n----------------------------------------\n";
echo "TEST 6: Índices en Tabla 'plays'\n";
echo "----------------------------------------\n";

try {
    $indexes = DB::select("SHOW INDEXES FROM plays");
    echo "  Índices encontrados: " . count($indexes) . "\n";
    foreach ($indexes as $index) {
        if ($index->Key_name !== 'PRIMARY') {
            echo "    - {$index->Key_name} en columna: {$index->Column_name}\n";
        }
    }
    
    if (count($indexes) <= 2) {
        echo "\n  ⚠️  ADVERTENCIA: Pocos índices en la tabla plays\n";
        echo "      Solo tiene PRIMARY KEY y posiblemente foreign key\n";
        echo "      Considera agregar índices en user_id y created_at\n";
    }
} catch (\Exception $e) {
    echo "  ERROR: " . $e->getMessage() . "\n";
}

// Test 7: Contar registros en plays (para ver tamaño de tabla)
echo "\n----------------------------------------\n";
echo "TEST 7: Tamaño de Tabla 'plays'\n";
echo "----------------------------------------\n";

try {
    $count = DB::table('plays')->count();
    echo "  Total de registros: " . number_format($count) . "\n";
    
    if ($count > 100000) {
        echo "\n  ⚠️  ADVERTENCIA: Tabla grande (" . number_format($count) . " registros)\n";
        echo "      Puede afectar el rendimiento si no hay índices adecuados\n";
    }
    
    // Tamaño de la tabla
    $tableSize = DB::select("SELECT 
        ROUND(((data_length + index_length) / 1024 / 1024), 2) AS size_mb
        FROM information_schema.TABLES 
        WHERE table_schema = ? AND table_name = 'plays'", [$database])[0];
    
    echo "  Tamaño de tabla: " . $tableSize->size_mb . " MB\n";
    
} catch (\Exception $e) {
    echo "  ERROR: " . $e->getMessage() . "\n";
}

// Resumen final
echo "\n========================================\n";
echo "RESUMEN Y RECOMENDACIONES\n";
echo "========================================\n";

if (!empty($connectionTimes)) {
    $connAvg = array_sum($connectionTimes) / count($connectionTimes);
    echo "Conexión promedio: " . number_format($connAvg, 2) . " ms\n";
    if ($connAvg > 10) {
        echo "  → Revisar carga del servidor y configuración MySQL\n";
    }
}

if (!empty($insertTimes)) {
    $insertAvg = array_sum($insertTimes) / count($insertTimes);
    echo "Inserción promedio: " . number_format($insertAvg, 2) . " ms\n";
    if ($insertAvg > 50) {
        echo "  → Posibles causas:\n";
        echo "     - Servidor bajo carga (CPU/Memoria)\n";
        echo "     - Falta de índices en tabla plays\n";
        echo "     - Muchas conexiones simultáneas\n";
        echo "     - Configuración de MySQL subóptima\n";
    }
}

echo "\n";
echo "PRÓXIMOS PASOS:\n";
echo "1. Comparar estos resultados con los de tu entorno LOCAL\n";
echo "2. Si la diferencia es significativa, revisar:\n";
echo "   - Carga del servidor (CPU, Memoria, I/O)\n";
echo "   - Configuración de MySQL (my.cnf)\n";
echo "   - Número de conexiones simultáneas\n";
echo "   - Índices en las tablas\n";
echo "   - Configuración de PHP (memory_limit, max_execution_time)\n";
echo "\n";

