<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Result;
use App\Models\ApusModel;
use App\Models\Number;
use App\Models\QuinielaModel;
use App\Models\PrizesModel;
use App\Models\FigureOneModel;
use App\Models\FigureTwoModel;

echo "=== ANÁLISIS DE PAGOS VESPERTINO (18:00) ===\n\n";

$date = '2025-10-21';
$userId = 24;

// Obtener tablas de pagos
$quinielaPayouts = QuinielaModel::first();
$prizesPayouts = PrizesModel::first();
$figureOnePayouts = FigureOneModel::first();
$figureTwoPayouts = FigureTwoModel::first();

echo "📊 TABLAS DE PAGOS:\n";
echo "   Quiniela (1 cifra): $" . $quinielaPayouts->cobra_1_cifra . "\n";
echo "   Quiniela (2 cifras): $" . $quinielaPayouts->cobra_2_cifra . "\n";
echo "   Quiniela (3 cifras): $" . $quinielaPayouts->cobra_3_cifra . "\n";
echo "   Quiniela (4 cifras): $" . $quinielaPayouts->cobra_4_cifra . "\n";
echo "   Prizes (5 pos): $" . $prizesPayouts->cobra_5 . "\n";
echo "   Prizes (10 pos): $" . $prizesPayouts->cobra_10 . "\n";
echo "   Prizes (20 pos): $" . $prizesPayouts->cobra_20 . "\n";
echo "   FigureOne (5 pos): $" . $figureOnePayouts->cobra_5 . "\n";
echo "   FigureOne (10 pos): $" . $figureOnePayouts->cobra_10 . "\n";
echo "   FigureOne (20 pos): $" . $figureOnePayouts->cobra_20 . "\n";
echo "   FigureTwo (5 pos): $" . $figureTwoPayouts->cobra_5 . "\n";
echo "   FigureTwo (10 pos): $" . $figureTwoPayouts->cobra_10 . "\n";
echo "   FigureTwo (20 pos): $" . $figureTwoPayouts->cobra_20 . "\n\n";

// 1. Verificar jugadas de Harold en loterías vespertinas
echo "🎯 JUGADAS DE HAROLD EN VESPERTINO (18:00):\n";
$playsVespertino = ApusModel::where('user_id', $userId)
    ->whereDate('created_at', $date)
    ->where('lottery', 'LIKE', '%1800')
    ->get();

echo "Total jugadas vespertinas: " . $playsVespertino->count() . "\n\n";

if ($playsVespertino->count() > 0) {
    echo "📋 JUGADAS VESPERTINAS:\n";
    foreach ($playsVespertino as $play) {
        $playedNumber = str_replace('*', '', $play->number);
        $playedDigits = strlen($playedNumber);
        echo "   Ticket: {$play->ticket} | Lotería: {$play->lottery} | Número: {$play->number} | Pos: {$play->position} | Dígitos: {$playedDigits}\n";
    }
} else {
    echo "❌ Harold NO tiene jugadas en horario vespertino (18:00)\n";
}

echo "\n";

// 2. Verificar números ganadores vespertinos
echo "🏆 NÚMEROS GANADORES VESPERTINOS (18:00):\n";
$winningNumbersVespertino = Number::whereDate('date', $date)
    ->whereHas('extract', function($query) {
        $query->where('name', 'VESPERTINO');
    })
    ->with(['city', 'extract'])
    ->get();

echo "Total números ganadores vespertinos: " . $winningNumbersVespertino->count() . "\n\n";

if ($winningNumbersVespertino->count() > 0) {
    echo "📋 PRIMEROS 10 NÚMEROS GANADORES VESPERTINOS:\n";
    foreach ($winningNumbersVespertino->take(10) as $number) {
        echo "   {$number->city->name} - Pos {$number->index}: {$number->value} - Hora: {$number->extract->time}\n";
    }
} else {
    echo "❌ NO hay números ganadores cargados para VESPERTINO\n";
}

echo "\n";

// 3. Verificar resultados de Harold en vespertino
echo "💰 RESULTADOS DE HAROLD EN VESPERTINO:\n";
$resultsVespertino = Result::where('user_id', $userId)
    ->where('date', $date)
    ->whereHas('playsSent', function($query) {
        $query->whereHas('apus', function($subQuery) {
            $subQuery->where('lottery', 'LIKE', '%1800');
        });
    })
    ->get();

echo "Total resultados vespertinos: " . $resultsVespertino->count() . "\n\n";

if ($resultsVespertino->count() > 0) {
    echo "📋 RESULTADOS VESPERTINOS:\n";
    foreach ($resultsVespertino as $result) {
        $playedNumber = str_replace('*', '', $result->number);
        $playedDigits = strlen($playedNumber);
        echo "   Ticket: {$result->ticket} | Número: {$result->number} | Pos: {$result->position} | Dígitos: {$playedDigits} | Premio: $" . number_format($result->aciert, 2) . "\n";
    }
} else {
    echo "❌ Harold NO tiene resultados en vespertino\n";
}

echo "\n";

// 4. Análisis de coincidencias si hay jugadas vespertinas
if ($playsVespertino->count() > 0 && $winningNumbersVespertino->count() > 0) {
    echo "🔍 ANÁLISIS DE COINCIDENCIAS VESPERTINAS:\n\n";
    
    // Organizar números ganadores por código de ciudad y posición
    $organizedWinningNumbers = [];
    foreach ($winningNumbersVespertino as $number) {
        $key = $number->city->code . '_' . $number->index;
        $organizedWinningNumbers[$key] = $number;
    }
    
    $coincidenciasEncontradas = 0;
    $totalPremioCalculado = 0;
    
    foreach ($playsVespertino as $play) {
        $playedNumber = str_replace('*', '', $play->number);
        $playedDigits = strlen($playedNumber);
        
        // Buscar en los números ganadores
        $winningNumberKey = $play->lottery . '_' . $play->position;
        
        if (isset($organizedWinningNumbers[$winningNumberKey])) {
            $winningNumber = $organizedWinningNumbers[$winningNumberKey];
            $winnerValue = $winningNumber->value;
            
            // Verificar si la apuesta coincide con el número ganador
            if ($playedDigits > 0 && $playedDigits <= 4 && substr($winnerValue, -$playedDigits) == $playedNumber) {
                $coincidenciasEncontradas++;
                
                // Calcular premio según las reglas
                $prizeMultiplier = 0;
                
                if ($play->position == 1) {
                    // REGLA PRINCIPAL: Si sale en posición 1 (a la cabeza), SIEMPRE usar tabla Quiniela
                    $prizeMultiplier = $quinielaPayouts->{"cobra_{$playedDigits}_cifra"} ?? 0;
                } else {
                    // Otras posiciones usan tablas según dígitos
                    if ($playedDigits == 1) {
                        $prizeMultiplier = $quinielaPayouts->cobra_1_cifra ?? 0;
                    } elseif ($playedDigits == 2) {
                        if ($play->position <= 5) {
                            $prizeMultiplier = $prizesPayouts->cobra_5 ?? 0;
                        } elseif ($play->position <= 10) {
                            $prizeMultiplier = $prizesPayouts->cobra_10 ?? 0;
                        } elseif ($play->position <= 20) {
                            $prizeMultiplier = $prizesPayouts->cobra_20 ?? 0;
                        }
                    } elseif ($playedDigits == 3) {
                        if ($play->position <= 5) {
                            $prizeMultiplier = $figureOnePayouts->cobra_5 ?? 0;
                        } elseif ($play->position <= 10) {
                            $prizeMultiplier = $figureOnePayouts->cobra_10 ?? 0;
                        } elseif ($play->position <= 20) {
                            $prizeMultiplier = $figureOnePayouts->cobra_20 ?? 0;
                        }
                    } elseif ($playedDigits == 4) {
                        if ($play->position <= 5) {
                            $prizeMultiplier = $figureTwoPayouts->cobra_5 ?? 0;
                        } elseif ($play->position <= 10) {
                            $prizeMultiplier = $figureTwoPayouts->cobra_10 ?? 0;
                        } elseif ($play->position <= 20) {
                            $prizeMultiplier = $figureTwoPayouts->cobra_20 ?? 0;
                        }
                    }
                }
                
                $totalPrize = $play->import * $prizeMultiplier;
                $totalPremioCalculado += $totalPrize;
                
                // Determinar qué tabla se usaría
                $tablaUsada = "";
                if ($play->position == 1) {
                    $tablaUsada = "Quiniela";
                } else {
                    if ($playedDigits == 1) {
                        $tablaUsada = "Quiniela";
                    } elseif ($playedDigits == 2) {
                        $tablaUsada = "Prizes";
                    } elseif ($playedDigits == 3) {
                        $tablaUsada = "FigureOne";
                    } elseif ($playedDigits == 4) {
                        $tablaUsada = "FigureTwo";
                    }
                }
                
                echo "🎉 COINCIDENCIA VESPERTINA #{$coincidenciasEncontradas}:\n";
                echo "   Ticket: {$play->ticket}\n";
                echo "   Lotería: {$play->lottery}\n";
                echo "   Número jugado: {$play->number} (pos {$play->position})\n";
                echo "   Número ganador: {$winnerValue} (pos {$winningNumber->index})\n";
                echo "   Dígitos: {$playedDigits}\n";
                echo "   Tabla usada: {$tablaUsada}\n";
                echo "   Importe: $" . number_format($play->import, 2) . "\n";
                echo "   Multiplicador: {$prizeMultiplier}\n";
                echo "   PREMIO CALCULADO: $" . number_format($totalPrize, 2) . "\n";
                echo "   ---\n";
            }
        }
    }
    
    echo "\n💰 RESUMEN VESPERTINO:\n";
    echo "   Coincidencias encontradas: {$coincidenciasEncontradas}\n";
    echo "   Total premio calculado: $" . number_format($totalPremioCalculado, 2) . "\n";
    
    // Verificar si los resultados en la base de datos coinciden
    $totalPremioEnBD = $resultsVespertino->sum('aciert');
    echo "   Total premio en BD: $" . number_format($totalPremioEnBD, 2) . "\n";
    
    if (abs($totalPremioCalculado - $totalPremioEnBD) < 0.01) {
        echo "   ✅ PAGOS CORRECTOS\n";
    } else {
        echo "   ❌ ERROR EN PAGOS\n";
        echo "   Diferencia: $" . number_format($totalPremioCalculado - $totalPremioEnBD, 2) . "\n";
    }
}

echo "\n=== ANÁLISIS COMPLETADO ===\n";
