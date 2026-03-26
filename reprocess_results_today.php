<?php
/**
 * Script para reprocesar resultados de hoy
 * Elimina todos los resultados de hoy y los vuelve a analizar con la lógica actualizada
 * 
 * Uso: Ejecutar desde cPanel o línea de comandos
 * php reprocess_results_today.php
 */

// Cargar Laravel
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->bootstrap();

use App\Models\Result;
use App\Services\LotteryResultProcessor;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

// Configurar zona horaria
date_default_timezone_set('America/Argentina/Buenos_Aires');

// Configurar output buffering para mostrar progreso en tiempo real
if (ob_get_level() == 0) {
    ob_start();
}
// Deshabilitar output buffering si está habilitado
ini_set('output_buffering', 'off');
ini_set('zlib.output_compression', false);

// Obtener fecha de hoy
$today = Carbon::today()->format('Y-m-d');

echo "<!DOCTYPE html>\n";
echo "<html lang='es'>\n";
echo "<head>\n";
echo "    <meta charset='UTF-8'>\n";
echo "    <meta name='viewport' content='width=device-width, initial-scale=1.0'>\n";
echo "    <title>Reprocesar Resultados de Hoy</title>\n";
echo "    <style>\n";
echo "        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }\n";
echo "        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }\n";
echo "        h1 { color: #333; border-bottom: 2px solid #007bff; padding-bottom: 10px; }\n";
echo "        .success { color: #28a745; background: #d4edda; padding: 10px; border-radius: 4px; margin: 10px 0; }\n";
echo "        .error { color: #dc3545; background: #f8d7da; padding: 10px; border-radius: 4px; margin: 10px 0; }\n";
echo "        .info { color: #0c5460; background: #d1ecf1; padding: 10px; border-radius: 4px; margin: 10px 0; }\n";
echo "        .warning { color: #856404; background: #fff3cd; padding: 10px; border-radius: 4px; margin: 10px 0; }\n";
echo "        pre { background: #f8f9fa; padding: 10px; border-radius: 4px; overflow-x: auto; }\n";
echo "        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin: 20px 0; }\n";
echo "        .stat-box { background: #f8f9fa; padding: 15px; border-radius: 4px; border-left: 4px solid #007bff; }\n";
echo "        .stat-label { font-size: 12px; color: #666; text-transform: uppercase; }\n";
echo "        .stat-value { font-size: 24px; font-weight: bold; color: #333; }\n";
echo "    </style>\n";
echo "</head>\n";
echo "<body>\n";
echo "<div class='container'>\n";
echo "    <h1>🔄 Reprocesar Resultados de Hoy</h1>\n";
echo "    <p><strong>Fecha:</strong> {$today}</p>\n";

try {
    // Paso 1: Contar resultados actuales de hoy
    $resultsBefore = Result::whereDate('date', $today)->count();
    echo "    <div class='info'>📊 Resultados encontrados antes de eliminar: <strong>{$resultsBefore}</strong></div>\n";
    
    if ($resultsBefore > 0) {
        // Paso 2: Eliminar todos los resultados de hoy
        echo "    <div class='warning'>⚠️ Eliminando resultados de hoy...</div>\n";
        
        $deleted = Result::whereDate('date', $today)->delete();
        
        if ($deleted > 0) {
            echo "    <div class='success'>✅ Se eliminaron <strong>{$deleted}</strong> resultados de la fecha {$today}</div>\n";
            Log::info("reprocess_results_today - Eliminados {$deleted} resultados de la fecha {$today}");
        } else {
            echo "    <div class='warning'>⚠️ No se eliminaron resultados (puede que ya estuvieran eliminados)</div>\n";
        }
    } else {
        echo "    <div class='info'>ℹ️ No había resultados para eliminar</div>\n";
    }
    
    // Paso 3: Reprocesar con la nueva lógica
    echo "    <div class='info'>🔄 Iniciando reprocesamiento con la nueva lógica...</div>\n";
    
    // Forzar output para que se vea el mensaje
    if (ob_get_level() == 0) {
        ob_start();
    }
    ob_flush();
    flush();
    
    // Registrar tiempo de inicio
    $startTime = microtime(true);
    
    // Contar jugadas a procesar para estimar tiempo
    $playsCount = \App\Models\PlaysSentModel::whereDate('date', $today)->count();
    echo "    <div class='info'>📊 Jugadas a procesar: <strong>{$playsCount}</strong></div>\n";
    
    // Estimación de tiempo aproximado
    $estimatedMinutes = max(1, ceil($playsCount / 100)); // Aproximadamente 100 jugadas por minuto
    echo "    <div class='warning'>⏳ Tiempo estimado: <strong>{$estimatedMinutes} minutos</strong> aproximadamente</div>\n";
    echo "    <div class='info' style='border: 2px solid #007bff; padding: 15px; text-align: center;'>\n";
    echo "        <strong style='font-size: 18px;'>🔄 Procesando... Por favor espere</strong>\n";
    echo "        <br><small>No cierre esta ventana. El proceso continuará automáticamente.</small>\n";
    echo "    </div>\n";
    ob_flush();
    flush();
    
    try {
        $processor = new LotteryResultProcessor();
        $processor->process($today);
        
        // Calcular tiempo transcurrido
        $endTime = microtime(true);
        $elapsedTime = round($endTime - $startTime, 2);
        $minutes = floor($elapsedTime / 60);
        $seconds = $elapsedTime % 60;
        
        echo "    <div class='success'>✅ Procesamiento completado en <strong>{$minutes}m {$seconds}s</strong></div>\n";
    } catch (\Exception $e) {
        $endTime = microtime(true);
        $elapsedTime = round($endTime - $startTime, 2);
        throw $e; // Re-lanzar para que se maneje en el catch general
    }
    
    // Paso 4: Contar resultados nuevos
    $resultsAfter = Result::whereDate('date', $today)->count();
    
    echo "    <div class='stats'>\n";
    echo "        <div class='stat-box'>\n";
    echo "            <div class='stat-label'>Resultados Antes</div>\n";
    echo "            <div class='stat-value'>{$resultsBefore}</div>\n";
    echo "        </div>\n";
    echo "        <div class='stat-box'>\n";
    echo "            <div class='stat-label'>Resultados Después</div>\n";
    echo "            <div class='stat-value'>{$resultsAfter}</div>\n";
    echo "        </div>\n";
    echo "        <div class='stat-box'>\n";
    echo "            <div class='stat-label'>Diferencia</div>\n";
    $difference = $resultsAfter - $resultsBefore;
    $diffColor = $difference >= 0 ? '#28a745' : '#dc3545';
    echo "            <div class='stat-value' style='color: {$diffColor};'>{$difference}</div>\n";
    echo "        </div>\n";
    echo "    </div>\n";
    
    // Paso 5: Mostrar algunos ejemplos de resultados nuevos
    if ($resultsAfter > 0) {
        $sampleResults = Result::whereDate('date', $today)
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();
        
        echo "    <h2>📋 Ejemplos de Resultados Procesados (últimos 10)</h2>\n";
        echo "    <table border='1' cellpadding='8' cellspacing='0' style='width: 100%; border-collapse: collapse;'>\n";
        echo "        <thead>\n";
        echo "            <tr style='background: #007bff; color: white;'>\n";
        echo "                <th>Ticket</th>\n";
        echo "                <th>Lotería</th>\n";
        echo "                <th>Número</th>\n";
        echo "                <th>Posición</th>\n";
        echo "                <th>NumR</th>\n";
        echo "                <th>PosR</th>\n";
        echo "                <th>Num_g</th>\n";
        echo "                <th>Pos_g</th>\n";
        echo "                <th>Num_g_r</th>\n";
        echo "                <th>Pos_g_r</th>\n";
        echo "                <th>Premio</th>\n";
        echo "            </tr>\n";
        echo "        </thead>\n";
        echo "        <tbody>\n";
        
        foreach ($sampleResults as $result) {
            echo "            <tr>\n";
            echo "                <td>{$result->ticket}</td>\n";
            echo "                <td>{$result->lottery}</td>\n";
            echo "                <td>{$result->number}</td>\n";
            echo "                <td>{$result->position}</td>\n";
            echo "                <td>" . ($result->numR ?? '-') . "</td>\n";
            echo "                <td>" . ($result->posR ?? '-') . "</td>\n";
            echo "                <td>" . ($result->numero_g ?? '-') . "</td>\n";
            echo "                <td>" . ($result->posicion_g ?? '-') . "</td>\n";
            echo "                <td>" . ($result->num_g_r ?? '-') . "</td>\n";
            echo "                <td>" . ($result->pos_g_r ?? '-') . "</td>\n";
            echo "                <td>\${$result->aciert}</td>\n";
            echo "            </tr>\n";
        }
        
        echo "        </tbody>\n";
        echo "    </table>\n";
    }
    
    // Resumen final
    echo "    <div class='success' style='margin-top: 20px;'>\n";
    echo "        <h3>✅ Proceso Completado Exitosamente</h3>\n";
    echo "        <ul>\n";
    echo "            <li>Resultados eliminados: <strong>{$deleted}</strong></li>\n";
    echo "            <li>Resultados reprocesados: <strong>{$resultsAfter}</strong></li>\n";
    echo "            <li>Fecha procesada: <strong>{$today}</strong></li>\n";
    echo "        </ul>\n";
    echo "    </div>\n";
    
    Log::info("reprocess_results_today - Proceso completado exitosamente. Resultados antes: {$resultsBefore}, Resultados después: {$resultsAfter}");
    
} catch (\Exception $e) {
    $errorMessage = $e->getMessage();
    $errorTrace = $e->getTraceAsString();
    
    echo "    <div class='error'>\n";
    echo "        <h3>❌ Error durante el procesamiento</h3>\n";
    echo "        <p><strong>Mensaje:</strong> {$errorMessage}</p>\n";
    echo "        <details>\n";
    echo "            <summary>Ver detalles del error</summary>\n";
    echo "            <pre>{$errorTrace}</pre>\n";
    echo "        </details>\n";
    echo "    </div>\n";
    
    Log::error("reprocess_results_today - Error: " . $errorMessage);
    Log::error("reprocess_results_today - Trace: " . $errorTrace);
}

echo "    <p style='margin-top: 20px; color: #666; font-size: 12px;'>\n";
echo "        Script ejecutado el: " . date('Y-m-d H:i:s') . "\n";
echo "    </p>\n";
echo "</div>\n";
echo "</body>\n";
echo "</html>\n";

