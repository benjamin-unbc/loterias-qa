<?php
/**
 * Script para verificar Opcache en el servidor web
 * 
 * ACCEDER DESDE EL NAVEGADOR:
 * https://qa.tusuerte22.store/check_opcache_web.php
 * 
 * IMPORTANTE: Eliminar este archivo después de verificar
 */

header('Content-Type: text/plain; charset=utf-8');

echo "========================================\n";
echo "VERIFICACIÓN DE OPCACHE (SERVIDOR WEB)\n";
echo "========================================\n\n";

// Información básica
echo "Versión de PHP: " . PHP_VERSION . "\n";
echo "SAPI: " . php_sapi_name() . "\n";
echo "Sistema Operativo: " . PHP_OS . "\n\n";

// Verificar Opcache
echo "----------------------------------------\n";
echo "ESTADO DE OPCACHE\n";
echo "----------------------------------------\n";

if (extension_loaded('opcache')) {
    echo "✅ Opcache está CARGADO\n\n";
    
    $opcacheEnabled = ini_get('opcache.enable');
    $opcacheEnableCli = ini_get('opcache.enable_cli');
    
    echo "opcache.enable: " . ($opcacheEnabled ?: 'No configurado') . "\n";
    echo "opcache.enable_cli: " . ($opcacheEnableCli ?: 'No configurado') . "\n\n";
    
    if ($opcacheEnabled == '1' || $opcacheEnabled === '1' || $opcacheEnabled === true) {
        echo "✅✅✅ OPCACHE ESTÁ HABILITADO PARA EL SERVIDOR WEB ✅✅✅\n\n";
        echo "Esto debería mejorar significativamente el rendimiento.\n";
    } else {
        echo "⚠️  Opcache está cargado pero DESHABILITADO\n";
        echo "   Verifica la configuración en .user.ini\n";
    }
    
    // Configuración de Opcache
    echo "\n----------------------------------------\n";
    echo "CONFIGURACIÓN DE OPCACHE\n";
    echo "----------------------------------------\n";
    
    $opcacheSettings = [
        'opcache.memory_consumption',
        'opcache.interned_strings_buffer',
        'opcache.max_accelerated_files',
        'opcache.revalidate_freq',
        'opcache.fast_shutdown',
        'opcache.validate_timestamps',
    ];
    
    foreach ($opcacheSettings as $setting) {
        $value = ini_get($setting);
        echo sprintf("%-35s: %s\n", $setting, $value !== false ? $value : 'N/A');
    }
    
    // Estadísticas de Opcache
    if (function_exists('opcache_get_status')) {
        $status = opcache_get_status(false);
        if ($status) {
            echo "\n----------------------------------------\n";
            echo "ESTADÍSTICAS DE OPCACHE\n";
            echo "----------------------------------------\n";
            
            if (isset($status['opcache_statistics'])) {
                $stats = $status['opcache_statistics'];
                echo "Hits (aciertos): " . number_format($stats['hits'] ?? 0) . "\n";
                echo "Misses (fallos): " . number_format($stats['misses'] ?? 0) . "\n";
                $total = ($stats['hits'] ?? 0) + ($stats['misses'] ?? 0);
                if ($total > 0) {
                    echo "Hit rate (tasa de aciertos): " . 
                         number_format((($stats['hits'] ?? 0) / $total) * 100, 2) . "%\n";
                }
            }
            
            if (isset($status['memory_usage'])) {
                $mem = $status['memory_usage'];
                echo "\nMemoria:\n";
                echo "  Usada: " . number_format($mem['used_memory'] / 1024 / 1024, 2) . " MB\n";
                echo "  Libre: " . number_format($mem['free_memory'] / 1024 / 1024, 2) . " MB\n";
                echo "  Total: " . number_format(($mem['used_memory'] + $mem['free_memory']) / 1024 / 1024, 2) . " MB\n";
            }
            
            if (isset($status['opcache_statistics']['num_cached_scripts'])) {
                echo "\nScripts en cache: " . number_format($status['opcache_statistics']['num_cached_scripts']) . "\n";
            }
        }
    }
    
} else {
    echo "❌ Opcache NO está cargado\n";
    echo "\n⚠️  PROBLEMA CRÍTICO\n";
    echo "Opcache no está disponible. Esto causa lentitud significativa.\n";
    echo "\nPosibles causas:\n";
    echo "1. La extensión no está instalada en el servidor\n";
    echo "2. El archivo .user.ini no se ha aplicado aún (espera 2-5 minutos)\n";
    echo "3. Necesitas contactar al proveedor de hosting\n";
}

echo "\n========================================\n";
echo "INSTRUCCIONES\n";
echo "========================================\n";
echo "1. Si Opcache está habilitado: ✅ Problema resuelto\n";
echo "2. Si NO está habilitado:\n";
echo "   - Espera 2-5 minutos y recarga esta página\n";
echo "   - Verifica que .user.ini esté en la raíz del proyecto\n";
echo "   - Contacta al proveedor de hosting si persiste\n";
echo "\n3. ⚠️  IMPORTANTE: Elimina este archivo después de verificar\n";
echo "   rm public/check_opcache_web.php\n";
echo "\n";

