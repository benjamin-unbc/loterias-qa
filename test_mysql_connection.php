<?php
/**
 * Script simple para medir tiempo de conexión a MySQL usando PDO directo
 * 
 * USO: php test_mysql_connection.php
 * 
 * Este script NO requiere Laravel, solo PDO de PHP
 */

// Leer configuración del .env manualmente
function getEnvValue($key, $default = '') {
    $envFile = __DIR__ . '/.env';
    if (!file_exists($envFile)) {
        return $default;
    }
    
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);
        if ($name === $key) {
            return $value;
        }
    }
    return $default;
}

$host = getEnvValue('DB_HOST', '127.0.0.1');
$port = getEnvValue('DB_PORT', '3306');
$database = getEnvValue('DB_DATABASE', '');
$username = getEnvValue('DB_USERNAME', '');
$password = getEnvValue('DB_PASSWORD', '');

echo "========================================\n";
echo "TEST DE CONEXIÓN DIRECTA A MYSQL\n";
echo "========================================\n\n";

echo "Configuración:\n";
echo "  Host: {$host}\n";
echo "  Puerto: {$port}\n";
echo "  Base de datos: {$database}\n";
echo "  Usuario: {$username}\n\n";

if (empty($database) || empty($username)) {
    echo "ERROR: Faltan datos de configuración en .env\n";
    echo "Asegúrate de tener DB_DATABASE y DB_USERNAME configurados\n";
    exit(1);
}

// Test de conexión
echo "----------------------------------------\n";
echo "Test de conexión (5 intentos)\n";
echo "----------------------------------------\n";

$connectionTimes = [];

for ($i = 1; $i <= 5; $i++) {
    $start = microtime(true);
    
    try {
        $dsn = "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        
        $pdo = new PDO($dsn, $username, $password, $options);
        
        // Hacer una consulta simple para asegurar que la conexión funciona
        $pdo->query('SELECT 1');
        
        $end = microtime(true);
        $time = ($end - $start) * 1000; // Convertir a milisegundos
        $connectionTimes[] = $time;
        
        echo "  Intento {$i}: " . number_format($time, 2) . " ms\n";
        
        $pdo = null; // Cerrar conexión
        
    } catch (PDOException $e) {
        echo "  Intento {$i}: ERROR - " . $e->getMessage() . "\n";
    }
    
    // Pequeña pausa entre intentos
    usleep(100000); // 100ms
}

if (!empty($connectionTimes)) {
    $avg = array_sum($connectionTimes) / count($connectionTimes);
    $min = min($connectionTimes);
    $max = max($connectionTimes);
    
    echo "\n  Promedio: " . number_format($avg, 2) . " ms\n";
    echo "  Mínimo: " . number_format($min, 2) . " ms\n";
    echo "  Máximo: " . number_format($max, 2) . " ms\n";
    
    echo "\n";
    echo "========================================\n";
    echo "ANÁLISIS\n";
    echo "========================================\n";
    
    if ($avg < 5) {
        echo "Estado: EXCELENTE - Conexión local o misma máquina\n";
        echo "        La latencia es mínima, no debería afectar el rendimiento.\n";
    } elseif ($avg < 20) {
        echo "Estado: BUENO - Servidor en misma red local\n";
        echo "        La latencia es aceptable, impacto mínimo en rendimiento.\n";
    } elseif ($avg < 50) {
        echo "Estado: REGULAR - Servidor remoto en misma región\n";
        echo "        La latencia puede notarse en operaciones rápidas.\n";
    } elseif ($avg < 100) {
        echo "Estado: MODERADO - Servidor remoto con latencia notable\n";
        echo "        Puede causar retrasos de 50-200ms en operaciones de BD.\n";
    } else {
        echo "Estado: ALTA LATENCIA - Servidor muy remoto o problemas de red\n";
        echo "        Esto definitivamente afectará el rendimiento.\n";
        echo "        Cada operación de BD añadirá 100ms+ de retraso.\n";
    }
    
    echo "\n";
    echo "NOTA: El tiempo de inserción de una jugada incluye:\n";
    echo "  - Conexión a BD: ~{$avg} ms\n";
    echo "  - Validación de FK (consulta a users): ~" . number_format($avg * 1.5, 2) . " ms\n";
    echo "  - Inserción en plays: ~" . number_format($avg * 1.2, 2) . " ms\n";
    echo "  - Total estimado: ~" . number_format($avg * 3.7, 2) . " ms\n";
    echo "\n";
} else {
    echo "\nERROR: No se pudo establecer conexión con la base de datos\n";
    echo "Verifica la configuración en .env\n";
}

