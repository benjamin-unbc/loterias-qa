<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Jobs\CalculateLotteryResults;
use App\Models\Result;
use Carbon\Carbon;

echo "=== EJECUTANDO CÁLCULO DE RESULTADOS ===" . PHP_EOL;

$today = Carbon::today()->format('Y-m-d');
echo "Fecha: " . $today . PHP_EOL;

// Ejecutar el job de cálculo
$job = new CalculateLotteryResults($today);
$job->handle();

echo "✅ Job ejecutado" . PHP_EOL;

// Verificar resultados
$results = Result::where('date', $today)->get();

echo PHP_EOL . "=== RESULTADOS ENCONTRADOS ===" . PHP_EOL;
if ($results->count() > 0) {
    foreach ($results as $result) {
        echo "Ticket: " . $result->ticket . PHP_EOL;
        echo "Usuario ID: " . $result->user_id . PHP_EOL;
        echo "Lotería: " . $result->lottery . PHP_EOL;
        echo "Número: " . $result->number . PHP_EOL;
        echo "Posición: " . $result->position . PHP_EOL;
        echo "Importe: $" . $result->import . PHP_EOL;
        echo "Premio: $" . number_format($result->aciert) . PHP_EOL;
        echo "Fecha: " . $result->date . PHP_EOL;
        echo "Hora: " . $result->time . PHP_EOL;
        echo "---" . PHP_EOL;
    }
} else {
    echo "❌ No se encontraron resultados" . PHP_EOL;
}
