<?php
/**
 * Script para medir la latencia de conexión a MySQL y tiempo de inserción
 * 
 * USO: php test_db_latency.php
 * 
 * Este script mide:
 * 1. Tiempo de conexión a MySQL
 * 2. Tiempo de inserción de una jugada (simulada)
 * 3. Tiempo de validación de foreign key
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Models\Play;

echo "========================================\n";
echo "TEST DE LATENCIA DE BASE DE DATOS\n";
echo "========================================\n\n";

// Obtener configuración de BD
$host = config('database.connections.mysql.host');
$port = config('database.connections.mysql.port');
$database = config('database.connections.mysql.database');
$username = config('database.connections.mysql.username');

echo "Configuración de BD:\n";
echo "  Host: {$host}\n";
echo "  Puerto: {$port}\n";
echo "  Base de datos: {$database}\n";
echo "  Usuario: {$username}\n\n";

// Test 1: Medir tiempo de conexión
echo "----------------------------------------\n";
echo "TEST 1: Tiempo de conexión a MySQL\n";
echo "----------------------------------------\n";

$connectionTimes = [];
for ($i = 1; $i <= 5; $i++) {
    $start = microtime(true);
    try {
        DB::connection()->getPdo();
        $end = microtime(true);
        $time = ($end - $start) * 1000; // Convertir a milisegundos
        $connectionTimes[] = $time;
        echo "  Intento {$i}: " . number_format($time, 2) . " ms\n";
    } catch (\Exception $e) {
        echo "  Intento {$i}: ERROR - " . $e->getMessage() . "\n";
    }
}

if (!empty($connectionTimes)) {
    $avg = array_sum($connectionTimes) / count($connectionTimes);
    $min = min($connectionTimes);
    $max = max($connectionTimes);
    echo "\n  Promedio: " . number_format($avg, 2) . " ms\n";
    echo "  Mínimo: " . number_format($min, 2) . " ms\n";
    echo "  Máximo: " . number_format($max, 2) . " ms\n";
}

// Test 2: Medir tiempo de consulta simple
echo "\n----------------------------------------\n";
echo "TEST 2: Tiempo de consulta simple (SELECT)\n";
echo "----------------------------------------\n";

$queryTimes = [];
for ($i = 1; $i <= 5; $i++) {
    $start = microtime(true);
    try {
        DB::select('SELECT 1 as test');
        $end = microtime(true);
        $time = ($end - $start) * 1000;
        $queryTimes[] = $time;
        echo "  Intento {$i}: " . number_format($time, 2) . " ms\n";
    } catch (\Exception $e) {
        echo "  Intento {$i}: ERROR - " . $e->getMessage() . "\n";
    }
}

if (!empty($queryTimes)) {
    $avg = array_sum($queryTimes) / count($queryTimes);
    $min = min($queryTimes);
    $max = max($queryTimes);
    echo "\n  Promedio: " . number_format($avg, 2) . " ms\n";
    echo "  Mínimo: " . number_format($min, 2) . " ms\n";
    echo "  Máximo: " . number_format($max, 2) . " ms\n";
}

// Test 3: Medir tiempo de validación de foreign key (consulta a users)
echo "\n----------------------------------------\n";
echo "TEST 3: Tiempo de validación de Foreign Key (users)\n";
echo "----------------------------------------\n";

$userId = auth()->id() ?? 1; // Usar el usuario autenticado o ID 1 por defecto
$fkTimes = [];
for ($i = 1; $i <= 5; $i++) {
    $start = microtime(true);
    try {
        DB::table('users')->where('id', $userId)->first();
        $end = microtime(true);
        $time = ($end - $start) * 1000;
        $fkTimes[] = $time;
        echo "  Intento {$i}: " . number_format($time, 2) . " ms\n";
    } catch (\Exception $e) {
        echo "  Intento {$i}: ERROR - " . $e->getMessage() . "\n";
    }
}

if (!empty($fkTimes)) {
    $avg = array_sum($fkTimes) / count($fkTimes);
    $min = min($fkTimes);
    $max = max($fkTimes);
    echo "\n  Promedio: " . number_format($avg, 2) . " ms\n";
    echo "  Mínimo: " . number_format($min, 2) . " ms\n";
    echo "  Máximo: " . number_format($max, 2) . " ms\n";
}

// Test 4: Medir tiempo de inserción de jugada (simulada)
echo "\n----------------------------------------\n";
echo "TEST 4: Tiempo de inserción de jugada (Play::create)\n";
echo "----------------------------------------\n";

$insertTimes = [];
for ($i = 1; $i <= 5; $i++) {
    $start = microtime(true);
    try {
        $play = Play::create([
            'user_id' => $userId,
            'type' => 'J',
            'number' => '1234',
            'position' => '1',
            'import' => '100.00',
            'lottery' => 'TEST',
            'numberR' => null,
            'positionR' => null,
            'isChecked' => false,
        ]);
        
        // Eliminar inmediatamente para no llenar la BD
        $play->delete();
        
        $end = microtime(true);
        $time = ($end - $start) * 1000;
        $insertTimes[] = $time;
        echo "  Intento {$i}: " . number_format($time, 2) . " ms\n";
    } catch (\Exception $e) {
        echo "  Intento {$i}: ERROR - " . $e->getMessage() . "\n";
    }
}

if (!empty($insertTimes)) {
    $avg = array_sum($insertTimes) / count($insertTimes);
    $min = min($insertTimes);
    $max = max($insertTimes);
    echo "\n  Promedio: " . number_format($avg, 2) . " ms\n";
    echo "  Mínimo: " . number_format($min, 2) . " ms\n";
    echo "  Máximo: " . number_format($max, 2) . " ms\n";
}

// Resumen
echo "\n========================================\n";
echo "RESUMEN\n";
echo "========================================\n";
if (!empty($connectionTimes)) {
    echo "Conexión promedio: " . number_format(array_sum($connectionTimes) / count($connectionTimes), 2) . " ms\n";
}
if (!empty($queryTimes)) {
    echo "Consulta promedio: " . number_format(array_sum($queryTimes) / count($queryTimes), 2) . " ms\n";
}
if (!empty($fkTimes)) {
    echo "FK validation promedio: " . number_format(array_sum($fkTimes) / count($fkTimes), 2) . " ms\n";
}
if (!empty($insertTimes)) {
    echo "Inserción promedio: " . number_format(array_sum($insertTimes) / count($insertTimes), 2) . " ms\n";
}

echo "\n";
echo "NOTA: Si los tiempos son > 50ms, hay latencia de red significativa.\n";
echo "      En local debería ser < 5ms, en servidor remoto puede ser 20-100ms.\n";
echo "\n";

