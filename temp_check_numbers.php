<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Number;
use App\Models\City;
use Carbon\Carbon;

// Buscar la ciudad Ciudad NAC1015
$city = City::where('code', 'NAC1015')->first();
if ($city) {
    echo "Ciudad encontrada: " . $city->name . " - Código: " . $city->code . PHP_EOL;
    
    // Buscar números de hoy
    $today = Carbon::today()->format('Y-m-d');
    echo "Buscando números para la fecha: " . $today . PHP_EOL;
    
    $numbers = Number::where('city_id', $city->id)
        ->where('date', $today)
        ->orderBy('index')
        ->get();
    
    if ($numbers->count() > 0) {
        echo "Números encontrados: " . $numbers->count() . PHP_EOL;
        foreach ($numbers as $number) {
            echo "Posición " . $number->index . ": " . $number->value . PHP_EOL;
        }
    } else {
        echo "No se encontraron números para hoy. Buscando en fechas recientes..." . PHP_EOL;
        
        // Buscar en los últimos 7 días
        for ($i = 1; $i <= 7; $i++) {
            $date = Carbon::today()->subDays($i)->format('Y-m-d');
            $numbers = Number::where('city_id', $city->id)
                ->where('date', $date)
                ->orderBy('index')
                ->get();
            
            if ($numbers->count() > 0) {
                echo "Números encontrados para " . $date . ": " . $numbers->count() . PHP_EOL;
                foreach ($numbers as $number) {
                    echo "Posición " . $number->index . ": " . $number->value . PHP_EOL;
                }
                break;
            }
        }
    }
} else {
    echo "No se encontró la ciudad NAC1015" . PHP_EOL;
}
