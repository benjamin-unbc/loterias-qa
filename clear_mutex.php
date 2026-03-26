<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

echo "=== LIMPIEZA DE MUTEX LOCKS ===\n";
echo "Fecha/Hora actual: " . now()->format('Y-m-d H:i:s') . "\n\n";

// 1. Limpiar mutex locks del scheduler
echo "1. LIMPIANDO MUTEX LOCKS DEL SCHEDULER:\n";

$mutexKeys = [
    'framework/schedule-' . md5('php artisan playssent:update-status'),
    'framework/schedule-' . md5('php artisan fetch:plays-sent'),
    'framework/schedule-' . md5('php artisan lottery:auto-update'),
    'framework/schedule-' . md5('php artisan lottery:auto-payment'),
    'framework/schedule-' . md5('php artisan lottery:auto-extract'),
];

$clearedCount = 0;
foreach ($mutexKeys as $key) {
    if (Cache::has($key)) {
        Cache::forget($key);
        echo "   ✅ Limpiado: {$key}\n";
        $clearedCount++;
    } else {
        echo "   ℹ️  No existe: {$key}\n";
    }
}

echo "\n   Total limpiados: {$clearedCount}\n\n";

// 2. Limpiar archivos de lock del filesystem (si existen)
echo "2. LIMPIANDO ARCHIVOS DE LOCK:\n";
$storagePath = storage_path('framework/schedule-*');

$lockFiles = glob($storagePath);
$clearedFiles = 0;
foreach ($lockFiles as $file) {
    if (unlink($file)) {
        echo "   ✅ Eliminado: {$file}\n";
        $clearedFiles++;
    }
}

echo "\n   Total archivos eliminados: {$clearedFiles}\n\n";

// 3. Verificar procesos colgados
echo "3. VERIFICANDO PROCESOS COLGADOS:\n";
echo "   Ejecuta manualmente: ps aux | grep 'artisan'\n";
echo "   Si encuentras procesos colgados, mátalos con: kill -9 <PID>\n\n";

echo "=== RECOMENDACIONES ===\n";
echo "1. Espera unos minutos y ejecuta: php artisan schedule:list\n";
echo "2. Los mutex locks se regenerarán automáticamente cuando los comandos se ejecuten correctamente\n";
echo "3. Verifica los logs: tail -f storage/logs/laravel.log\n\n";

echo "=== FIN DE LA LIMPIEZA ===\n";

