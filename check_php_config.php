<?php
/**
 * Script para verificar configuración de PHP
 * Compara configuración entre local y servidor
 * 
 * USO: php check_php_config.php
 */

echo "========================================\n";
echo "VERIFICACIÓN DE CONFIGURACIÓN PHP\n";
echo "========================================\n\n";

// Información básica
echo "Versión de PHP: " . PHP_VERSION . "\n";
echo "SAPI: " . php_sapi_name() . "\n";
echo "Sistema Operativo: " . PHP_OS . "\n\n";

// Función helper para parsear memory_limit
function parseMemoryLimit($limit) {
    $limit = trim($limit);
    $last = strtolower($limit[strlen($limit)-1]);
    $value = (int)$limit;
    
    switch($last) {
        case 'g':
            $value *= 1024;
        case 'm':
            $value *= 1024;
        case 'k':
            $value *= 1024;
    }
    
    return $value;
}

// Configuración crítica
echo "----------------------------------------\n";
echo "CONFIGURACIÓN CRÍTICA\n";
echo "----------------------------------------\n";

$criticalSettings = [
    'memory_limit',
    'max_execution_time',
    'max_input_time',
    'post_max_size',
    'upload_max_filesize',
];

foreach ($criticalSettings as $setting) {
    $value = ini_get($setting);
    echo sprintf("%-25s: %s\n", $setting, $value);
}

// Opcache
echo "\n----------------------------------------\n";
echo "OPCACHE\n";
echo "----------------------------------------\n";

if (extension_loaded('opcache')) {
    echo "✅ Opcache está CARGADO\n\n";
    
    $opcacheSettings = [
        'opcache.enable',
        'opcache.enable_cli',
        'opcache.memory_consumption',
        'opcache.interned_strings_buffer',
        'opcache.max_accelerated_files',
        'opcache.revalidate_freq',
        'opcache.fast_shutdown',
    ];
    
    foreach ($opcacheSettings as $setting) {
        $value = ini_get($setting);
        echo sprintf("%-35s: %s\n", $setting, $value !== false ? $value : 'N/A');
    }
    
    // Verificar si está habilitado
    if (ini_get('opcache.enable') == '1' || ini_get('opcache.enable') == '1') {
        echo "\n✅ Opcache está HABILITADO\n";
    } else {
        echo "\n⚠️  Opcache está DESHABILITADO\n";
        echo "   Esto puede causar lentitud significativa\n";
    }
    
    // Estadísticas de Opcache
    if (function_exists('opcache_get_status')) {
        $status = opcache_get_status();
        if ($status && isset($status['opcache_statistics'])) {
            echo "\nEstadísticas de Opcache:\n";
            echo "  Hits: " . number_format($status['opcache_statistics']['opcache_statistics']['hits'] ?? 0) . "\n";
            echo "  Misses: " . number_format($status['opcache_statistics']['opcache_statistics']['misses'] ?? 0) . "\n";
            if (isset($status['memory_usage'])) {
                echo "  Memoria usada: " . number_format($status['memory_usage']['used_memory'] / 1024 / 1024, 2) . " MB\n";
                echo "  Memoria libre: " . number_format($status['memory_usage']['free_memory'] / 1024 / 1024, 2) . " MB\n";
            }
        }
    }
} else {
    echo "❌ Opcache NO está cargado\n";
    echo "   ⚠️  ESTO ES UN PROBLEMA CRÍTICO\n";
    echo "   Opcache mejora el rendimiento de PHP significativamente\n";
    echo "   Sin Opcache, cada request debe compilar el código PHP\n";
}

// Extensions importantes
echo "\n----------------------------------------\n";
echo "EXTENSIONS IMPORTANTES\n";
echo "----------------------------------------\n";

$importantExtensions = [
    'pdo',
    'pdo_mysql',
    'mbstring',
    'json',
    'curl',
    'openssl',
    'fileinfo',
    'zip',
];

foreach ($importantExtensions as $ext) {
    $loaded = extension_loaded($ext);
    echo sprintf("%-20s: %s\n", $ext, $loaded ? '✅ Cargado' : '❌ No cargado');
}

// Configuración de sesiones
echo "\n----------------------------------------\n";
echo "CONFIGURACIÓN DE SESIONES\n";
echo "----------------------------------------\n";

$sessionSettings = [
    'session.save_handler',
    'session.save_path',
    'session.gc_maxlifetime',
];

foreach ($sessionSettings as $setting) {
    $value = ini_get($setting);
    echo sprintf("%-25s: %s\n", $setting, $value);
}

// Resumen y recomendaciones
echo "\n========================================\n";
echo "RESUMEN Y RECOMENDACIONES\n";
echo "========================================\n";

$issues = [];

// Verificar Opcache
if (!extension_loaded('opcache')) {
    $issues[] = "❌ Opcache no está cargado - CRÍTICO para rendimiento";
} elseif (ini_get('opcache.enable') != '1' && ini_get('opcache.enable') != '1') {
    $issues[] = "⚠️  Opcache está deshabilitado - Habilítalo para mejor rendimiento";
}

// Verificar memory_limit
$memoryLimit = ini_get('memory_limit');
$memoryBytes = parseMemoryLimit($memoryLimit);
if ($memoryBytes < 128 * 1024 * 1024) { // Menos de 128MB
    $issues[] = "⚠️  memory_limit es bajo ($memoryLimit) - Recomendado: 256M o más";
}

// Verificar max_execution_time
$maxExecutionTime = ini_get('max_execution_time');
if ($maxExecutionTime > 0 && $maxExecutionTime < 60) {
    $issues[] = "⚠️  max_execution_time es bajo ($maxExecutionTime) - Recomendado: 60 o más";
}

if (empty($issues)) {
    echo "✅ Configuración parece correcta\n";
} else {
    echo "Problemas encontrados:\n";
    foreach ($issues as $issue) {
        echo "  $issue\n";
    }
}

echo "\n";

