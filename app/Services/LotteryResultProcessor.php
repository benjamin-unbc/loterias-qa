<?php

namespace App\Services;

use App\Models\ApusModel;
use App\Models\BetCollection10To20Model;
use App\Models\BetCollection5To20Model;
use App\Models\BetCollectionRedoblonaModel;
use App\Models\City;
use App\Models\FigureOneModel;
use App\Models\FigureTwoModel;
use App\Models\Number;
use App\Models\PlaysSentModel;
use App\Models\PrizesModel;
use App\Models\QuinielaModel;
use App\Models\Result; // Ensure this is the correct model for the results table
use App\Services\LotteryCompletenessService;
use App\Services\ResultManager;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class LotteryResultProcessor
{
    // This codes array is used in FetchAndDisplayPlaysSent and needs to be consistent
    public $codes = [
        'AB' => 'NAC1015', 'CH1' => 'CHA1015', 'QW' => 'PRO1015', 'M10' => 'MZA1015', '!' => 'CTE1015',
        'ER' => 'SFE1015', 'SD' => 'COR1015', 'RT' => 'RIO1015', 'Q' => 'NAC1200', 'CH2' => 'CHA1200',
        'W' => 'PRO1200', 'M1' => 'MZA1200', 'M' => 'CTE1200', 'R' => 'SFE1200', 'T' => 'COR1200',
        'K' => 'RIO1200', 'A' => 'NAC1500', 'CH3' => 'CHA1500', 'E' => 'PRO1500', 'M2' => 'MZA1500',
        'Ct3' => 'CTE1500', 'D' => 'SFE1500', 'L' => 'COR1500', 'J' => 'RIO1500', 'S' => 'ORO1800',
        'F' => 'NAC1800', 'CH4' => 'CHA1800', 'B' => 'PRO1800', 'M3' => 'MZA1800', 'Z' => 'CTE1800',
        'V' => 'SFE1800', 'H' => 'COR1800', 'U' => 'RIO1800', 'N' => 'NAC2100', 'CH5' => 'CHA2100',
        'P' => 'PRO2100', 'M4' => 'MZA2100', 'G' => 'CTE2100', 'I' => 'SFE2100', 'C' => 'COR2100',
        'Y' => 'RIO2100', 'O' => 'ORO2100',
        // Nuevos códigos cortos para las loterías adicionales
        'NQN1015' => 'NQ1', 'MIS1030' => 'MI1', 'Rio1015' => 'RN1', 'Tucu1130' => 'TU1', 'San1015' => 'SG1',
        'NQN1200' => 'NQ2', 'MIS1215' => 'MI2', 'JUJ1200' => 'JU1', 'Salt1130' => 'SA1', 'Rio1200' => 'RN2',
        'Tucu1430' => 'TU2', 'San1200' => 'SG2', 'NQN1500' => 'NQ3', 'MIS1500' => 'MI3', 'JUJ1500' => 'JU2',
        'Salt1400' => 'SA2', 'Rio1500' => 'RN3', 'Tucu1730' => 'TU3', 'San1500' => 'SG3', 'NQN1800' => 'NQ4',
        'MIS1800' => 'MI4', 'JUJ1800' => 'JU3', 'Salt1730' => 'SA3', 'Rio1800' => 'RN4', 'Tucu1930' => 'TU4',
        'San1945' => 'SG4', 'NQN2100' => 'NQ5', 'JUJ2100' => 'JU4', 'Rio2100' => 'RN5', 'Salt2100' => 'SA4',
        'Tucu2200' => 'TU5', 'MIS2115' => 'MI5', 'San2200' => 'SG5',
        'ORO1500' => 'ORO1800', // Mapeo especial para Montevideo 18:00
        'ORO1800' => 'ORO1800'  // Mapeo directo para Montevideo 18:00
    ];

    public function process(string $dateToCalculate): void
    {
        Log::info("LotteryResultProcessor - Iniciando procesamiento para la fecha: " . $dateToCalculate);

        // ✅ NUEVA LÓGICA: Solo procesar loterías que tengan sus 20 números completos
        Log::info("LotteryResultProcessor - Verificando loterías completas para la fecha {$dateToCalculate}.");

        // Obtener solo las loterías que tengan sus 20 números completos
        $completeLotteries = LotteryCompletenessService::getCompleteLotteries($dateToCalculate);

        if (empty($completeLotteries)) {
            Log::info("LotteryResultProcessor - No hay loterías completas para procesar en la fecha {$dateToCalculate}. Finalizando procesamiento.");
            return;
        }

        Log::info("LotteryResultProcessor - Loterías completas encontradas: " . implode(', ', $completeLotteries));

        // **SOLUCIÓN MEJORADA: Verificar duplicados individualmente en lugar de eliminar todo**
        // Esto preserva los resultados existentes y solo inserta los nuevos
        Log::info("LotteryResultProcessor - Verificando duplicados individualmente para {$dateToCalculate}.");

        // Load settings
        $quiniela = QuinielaModel::first();
        $prizes = PrizesModel::first();
        $figureOne = FigureOneModel::first();
        $figureTwo = FigureTwoModel::first();
        $betCollectionRedoblona = BetCollectionRedoblonaModel::where('bet_amount', '1.00')->first();
        $betCollection5To20 = BetCollection5To20Model::where('bet_amount', '1.00')->first();
        $betCollection10To20 = BetCollection10To20Model::where('bet_amount', '1.00')->first();

        if (!$quiniela || !$prizes || !$figureOne || !$figureTwo || !$betCollectionRedoblona || !$betCollection5To20 || !$betCollection10To20) {
            Log::error("LotteryResultProcessor - Faltan configuraciones de premios. Abortando cálculo.");
            return;
        }

        // Solo obtener números de las loterías completas
        $winningNumbers = Number::whereDate('date', Carbon::parse($dateToCalculate))
            ->with('city')
            ->whereHas('city', function($query) use ($completeLotteries) {
                $query->whereIn('code', $completeLotteries);
            })
            ->get();

        if ($winningNumbers->isEmpty()) {
            Log::warning("LotteryResultProcessor - No hay números ganadores para las loterías completas en la fecha " . $dateToCalculate);
            return;
        }

        // Group winning numbers by system lottery code and position (solo loterías completas)
        $groupedWinningNumbers = [];
        foreach ($winningNumbers as $wn) {
            if ($wn->city && in_array($wn->city->code, $completeLotteries)) {
                // This mapping is crucial. It assumes city->code is the system code.
                // Example: 'NAC1015', 'SFE1015', etc.
                $lotteryKey = $wn->city->code;
                if (!isset($groupedWinningNumbers[$lotteryKey])) {
                    $groupedWinningNumbers[$lotteryKey] = [];
                }
                $groupedWinningNumbers[$lotteryKey][$wn->index] = $wn->value;
            }
        }
        Log::info("LotteryResultProcessor - Números ganadores agrupados (solo loterías completas):", $groupedWinningNumbers);

        $playsSents = PlaysSentModel::whereDate('date', Carbon::parse($dateToCalculate))
            ->with('apus')
            ->get();

        if ($playsSents->isEmpty()) {
            Log::warning("LotteryResultProcessor - No hay jugadas enviadas para la fecha " . $dateToCalculate);
            return;
        }

        $matches = []; // For logging and potential batch insert

        foreach ($playsSents as $playSent) {
            foreach ($apu = $playSent->apus as $apu) { // Corrected loop variable
                $aciertValue = 0;
                $aciertValueR = 0; // For redoblona
                $redoblonaWinningCount = 0; // ✅ CORREGIDO: Inicializar contador de veces que salió la redoblona

                $playedNumberClean = $this->removeAsterisks($apu->number);
                $lotterySystemCode = $this->getSystemLotteryCode($apu->lottery); // Map UI code to system code

                if (is_null($lotterySystemCode)) {
                    Log::warning("LotteryResultProcessor - Saltando APU ID {$apu->id} (Lotería UI: {$apu->lottery}) porque no se pudo determinar el código de lotería del sistema.");
                    continue;
                }

                $winningNumbersForLottery = $groupedWinningNumbers[$lotterySystemCode] ?? null;

                if (!$winningNumbersForLottery) {
                    Log::info("LotteryResultProcessor - No hay números ganadores para la lotería del sistema {$lotterySystemCode} (UI: {$apu->lottery}).");
                    continue;
                }

                // Inicializar variables para el acierto principal
                $actualWinningPosition = null;
                $winningNumberAtPosition = null;
                
                // Inicializar variables para redoblona
                $actualWinningPositionR = null;
                $winningNumberAtPositionR = null;

                // --- Main Play (Quiniela, Prizes, Figures) ---
                if (!empty($playedNumberClean) && $apu->position !== null) {
                    // Determinar rango de búsqueda según posición apostada
                    $searchPositions = [];
                    if ($apu->position == 1) {
                        // Quiniela: solo posición 1
                        $searchPositions = [1];
                    } elseif ($apu->position >= 2 && $apu->position <= 5) {
                        // Tabla 2-5
                        $searchPositions = range(2, 5);
                    } elseif ($apu->position >= 6 && $apu->position <= 10) {
                        // Tabla 6-10
                        $searchPositions = range(6, 10);
                    } elseif ($apu->position >= 11 && $apu->position <= 20) {
                        // Tabla 11-20
                        $searchPositions = range(11, 20);
                    }
                    
                    $numDigitsPlayed = strlen($playedNumberClean);
                    $actualWinningPosition = null;
                    $winningNumberAtPosition = null;
                    
                    // Buscar el número en todas las posiciones del rango
                    foreach ($searchPositions as $pos) {
                        if (!isset($winningNumbersForLottery[$pos])) continue;
                        
                        $winningNum = str_pad((string)$winningNumbersForLottery[$pos], 4, '0', STR_PAD_LEFT);
                        $winningNumberLastDigits = substr($winningNum, -$numDigitsPlayed);
                        
                        if ($playedNumberClean === $winningNumberLastDigits) {
                            // ✅ VALIDAR que la posición donde salió es correcta según la posición apostada (misma lógica que redoblona)
                            if ($this->isPositionCorrect($apu->position, $pos)) {
                                $actualWinningPosition = $pos;
                                $winningNumberAtPosition = $winningNum;
                                break;
                            } else {
                                // Número encontrado pero en posición incorrecta según las reglas
                                Log::info("LotteryResultProcessor - Número principal encontrado pero posición inválida: Número {$playedNumberClean} apostado en posición {$apu->position} salió en posición {$pos} (NO válido según reglas) para lotería {$lotterySystemCode}");
                            }
                        }
                    }
                    
                    if ($actualWinningPosition && $winningNumberAtPosition) {
                        // ✅ CORRECCIÓN: Si position == 1, SIEMPRE es quiniela, independientemente de asteriscos
                        $ticketType = ($apu->position == 1) ? 'quiniela' : $this->getTicketType($apu->number);
                        
                        $multiplier = 0;
                        if ($ticketType === 'quiniela') {
                            if ($numDigitsPlayed == 4) $multiplier = $quiniela->cobra_4_cifra;
                            elseif ($numDigitsPlayed == 3) $multiplier = $quiniela->cobra_3_cifra;
                            elseif ($numDigitsPlayed == 2) $multiplier = $quiniela->cobra_2_cifra;
                            elseif ($numDigitsPlayed == 1) $multiplier = $quiniela->cobra_1_cifra;
                        } elseif ($ticketType === 'prizes') {
                            // Calcular premio basado en la posición donde realmente salió
                            if ($actualWinningPosition <= 5) $multiplier = $prizes->cobra_5;
                            elseif ($actualWinningPosition <= 10) $multiplier = $prizes->cobra_10;
                            else $multiplier = $prizes->cobra_20;
                        } elseif ($ticketType === 'figureOne') {
                            if ($actualWinningPosition <= 5) $multiplier = $figureOne->cobra_5;
                            elseif ($actualWinningPosition <= 10) $multiplier = $figureOne->cobra_10;
                            else $multiplier = $figureOne->cobra_20;
                        } elseif ($ticketType === 'figureTwo') {
                            if ($actualWinningPosition <= 5) $multiplier = $figureTwo->cobra_5;
                            elseif ($actualWinningPosition <= 10) $multiplier = $figureTwo->cobra_10;
                            else $multiplier = $figureTwo->cobra_20;
                        }
                        $aciertValue = (float)$apu->import * (float)$multiplier;
                        Log::info("LotteryResultProcessor - Acierto principal: {$playedNumberClean} en posición apostada {$apu->position}, salió en posición {$actualWinningPosition}, tipo: {$ticketType}, premio: {$aciertValue}");
                    }
                }

                // --- Redoblona (if applicable) ---
                if (!empty($apu->numberR) && $apu->positionR !== null) {
                    $playedNumberRClean = $this->removeAsterisks($apu->numberR);
                    $numDigitsPlayedR = strlen($playedNumberRClean);
                    
                    // ✅ VALIDACIÓN CRÍTICA: El número principal DEBE haber salido primero
                    // Si no hay número principal ganador, no se puede pagar redoblona
                    if (!$actualWinningPosition || !$winningNumberAtPosition) {
                        Log::info("LotteryResultProcessor - Redoblona descartada: El número principal {$apu->number} NO salió en posición {$apu->position}. No se puede pagar redoblona.");
                        // No calcular redoblona si el principal no ganó
                    } else {
                        // ✅ CORREGIDO: Obtener rango de posiciones para redoblona (2-5, 2-10, 2-20)
                        $redoblonaRange = $this->getRedoblonaPositionRange($apu->positionR);
                        
                        // ✅ CORREGIDO: Contar TODAS las veces que sale la redoblona en el rango válido
                        $redoblonaWinningCount = 0; // Reiniciar contador para esta jugada
                        $actualWinningPositionR = null;
                        $winningNumberAtPositionR = null;
                        $redoblonaPositions = [];
                        
                        // Buscar en el rango válido de posiciones de redoblona
                        foreach (range($redoblonaRange['min'], $redoblonaRange['max']) as $posR) {
                            // Verificar que exista el número en esa posición para esta lotería
                            if (!isset($winningNumbersForLottery[$posR])) {
                                continue;
                            }
                            
                            $winningNumR = str_pad((string)$winningNumbersForLottery[$posR], 4, '0', STR_PAD_LEFT);
                            $winningNumberLastDigitsR = substr($winningNumR, -$numDigitsPlayedR);
                            
                            // Si el número coincide, contar y guardar la primera ocurrencia para num_g_r y pos_g_r
                            if ($playedNumberRClean === $winningNumberLastDigitsR) {
                                $redoblonaWinningCount++;
                                $redoblonaPositions[] = $posR;
                                
                                // Guardar la primera ocurrencia para num_g_r y pos_g_r
                                if ($actualWinningPositionR === null) {
                                    $actualWinningPositionR = $posR;
                                    $winningNumberAtPositionR = $winningNumR;
                                }
                            }
                        }
                        
                        // ✅ VALIDAR que al menos una posición donde salió sea correcta según positionR
                        if ($redoblonaWinningCount > 0) {
                            // Validar que al menos una de las posiciones donde salió sea válida según positionR
                            $isValidPosition = false;
                            foreach ($redoblonaPositions as $pos) {
                                if ($this->isPositionCorrect($apu->positionR, $pos, true)) {
                                    $isValidPosition = true;
                                    break;
                                }
                            }
                            
                            if ($isValidPosition) {
                                // ✅ La redoblona es ganadora → calcular premio
                                $multiplierR = 0;
                                // ✅ CORREGIDO: Calcular premio basado en las posiciones APOSTADAS, no donde realmente salieron
                                $mainPosApostada = (int)$apu->position;
                                $redoblonaPosApostada = (int)$apu->positionR;
                                
                                if ($mainPosApostada == 1) {
                                    if ($redoblonaPosApostada >= 1 && $redoblonaPosApostada <= 5) $multiplierR = $betCollectionRedoblona->payout_1_to_5;
                                    elseif ($redoblonaPosApostada >= 6 && $redoblonaPosApostada <= 10) $multiplierR = $betCollectionRedoblona->payout_1_to_10;
                                    elseif ($redoblonaPosApostada >= 11 && $redoblonaPosApostada <= 20) $multiplierR = $betCollectionRedoblona->payout_1_to_20;
                                } elseif ($mainPosApostada >= 2 && $mainPosApostada <= 5) {
                                    if ($redoblonaPosApostada >= 1 && $redoblonaPosApostada <= 5) $multiplierR = $betCollection5To20->payout_5_to_5;
                                    elseif ($redoblonaPosApostada >= 6 && $redoblonaPosApostada <= 10) $multiplierR = $betCollection5To20->payout_5_to_10;
                                    elseif ($redoblonaPosApostada >= 11 && $redoblonaPosApostada <= 20) $multiplierR = $betCollection5To20->payout_5_to_20;
                                } elseif ($mainPosApostada >= 6 && $mainPosApostada <= 10) {
                                    if ($redoblonaPosApostada >= 6 && $redoblonaPosApostada <= 10) $multiplierR = $betCollection10To20->payout_10_to_10;
                                    elseif ($redoblonaPosApostada >= 11 && $redoblonaPosApostada <= 20) $multiplierR = $betCollection10To20->payout_10_to_20;
                                } elseif ($mainPosApostada >= 11 && $mainPosApostada <= 20) {
                                    if ($redoblonaPosApostada >= 11 && $redoblonaPosApostada <= 20) $multiplierR = $betCollection10To20->payout_20_to_20;
                                }
                                
                                // ✅ CORREGIDO: Multiplicar premio base × veces que salió × importe
                                $aciertValueR = (float)$apu->import * (float)$multiplierR * $redoblonaWinningCount;
                                Log::info("LotteryResultProcessor - ✅ Acierto redoblona válido: Principal {$apu->number} salió en posición {$actualWinningPosition}, Redoblona {$playedNumberRClean} apostada en posición {$apu->positionR} salió {$redoblonaWinningCount} vez(es) en posiciones " . implode(', ', $redoblonaPositions) . " para lotería {$lotterySystemCode}, premio: {$aciertValueR} (base: {$multiplierR} × veces: {$redoblonaWinningCount} × importe: {$apu->import})");
                            } else {
                                // ✅ Número encontrado pero ninguna posición es válida según positionR → NO es ganador
                                Log::info("LotteryResultProcessor - Redoblona número encontrado pero posiciones inválidas: NumR {$playedNumberRClean} apostado en PosR {$apu->positionR} salió en posiciones " . implode(', ', $redoblonaPositions) . " - NO válido según reglas para lotería {$lotterySystemCode}");
                                $aciertValueR = 0;
                                $redoblonaWinningCount = 0;
                            }
                        } else {
                            // No se encontró el número de redoblona
                            Log::info("LotteryResultProcessor - Redoblona NO ganadora: El número principal {$apu->number} sí salió en posición {$actualWinningPosition}, pero la redoblona {$apu->numberR} NO existe para lotería {$lotterySystemCode}");
                            $aciertValueR = 0;
                            $redoblonaWinningCount = 0;
                        }
                    }
                }

                // Save result if any acierto is found
                if ($aciertValue > 0 || $aciertValueR > 0) {
                    // Validar que cada tipo de acierto tenga su posición ganadora real
                    // Si hay acierto principal, debe tener posición ganadora real
                    if ($aciertValue > 0 && (!$actualWinningPosition || !$winningNumberAtPosition)) {
                        Log::warning("LotteryResultProcessor - Acierto principal sin posición ganadora real: Ticket {$apu->ticket} - Lotería {$lotterySystemCode} - Número {$apu->number}");
                        continue;
                    }
                    
                    // Si hay acierto redoblona, debe tener posición ganadora real
                    if ($aciertValueR > 0 && (!$actualWinningPositionR || !$winningNumberAtPositionR)) {
                        Log::warning("LotteryResultProcessor - Acierto redoblona sin posición ganadora real: Ticket {$apu->ticket} - Lotería {$lotterySystemCode} - Número R {$apu->numberR}");
                        continue;
                    }
                    
                    // ✅ VALIDACIÓN CRÍTICA: Si hay redoblona, el número principal DEBE haber salido primero
                    if ($aciertValueR > 0 && (!$actualWinningPosition || !$winningNumberAtPosition)) {
                        Log::warning("LotteryResultProcessor - ❌ Redoblona rechazada: El número principal {$apu->number} NO salió en posición {$apu->position}. Ticket {$apu->ticket} - Lotería {$lotterySystemCode}");
                        continue;
                    }
                    
                    // ✅ Asegurar que numero_g y posicion_g no sean null para el acierto principal
                    if ($aciertValue > 0) {
                        if (!$winningNumberAtPosition || !$actualWinningPosition) {
                            Log::warning("LotteryResultProcessor - numero_g o posicion_g son null para acierto principal: Ticket {$apu->ticket} - Lotería {$lotterySystemCode} - Número {$apu->number} - numero_g: {$winningNumberAtPosition} - posicion_g: {$actualWinningPosition}");
                            continue;
                        }
                    }
                    
                    // ✅ VALIDACIÓN CRÍTICA: Si hay redoblona apostada, DEBE encontrarse el número Y ser válida
                    // Si hay numR y posR pero no se encontró el número (num_g_r y pos_g_r son null), NO guardar el resultado
                    // Si se encontró el número pero pos_g_r NO es válida según positionR, NO guardar el resultado
                    if (!empty($apu->numberR) && $apu->positionR !== null) {
                        if (!$actualWinningPositionR || !$winningNumberAtPositionR) {
                            Log::warning("LotteryResultProcessor - ❌ Redoblona rechazada: Se apostó redoblona NumR {$apu->numberR} PosR {$apu->positionR} pero NO se encontró el número en ninguna posición para lotería {$lotterySystemCode}. Ticket {$apu->ticket} - NO se guardará el resultado.");
                            continue; // No guardar el resultado si la redoblona no se encontró
                        }
                        
                        // ✅ Validar que pos_g_r (posición real) sea correcta según positionR (posición apostada)
                        // Si pos_g_r NO es válida según las reglas, NO guardar el resultado
                        // ✅ CORREGIDO: Pasar true para indicar que es redoblona (usa rangos 2-10, 2-20)
                        if (!$this->isPositionCorrect($apu->positionR, $actualWinningPositionR, true)) {
                            Log::warning("LotteryResultProcessor - ❌ Redoblona rechazada: Se apostó redoblona NumR {$apu->numberR} PosR {$apu->positionR} pero salió en posición {$actualWinningPositionR} (pos_g_r) que NO es válida según las reglas para lotería {$lotterySystemCode}. Ticket {$apu->ticket} - NO se guardará el resultado.");
                            continue; // No guardar el resultado si pos_g_r no es válida
                        }
                    }
                    
                    // ✅ Guardar num_g_r y pos_g_r con los valores REALES donde salió el número de redoblona
                    // Si hay redoblona apostada, num_g_r y pos_g_r DEBEN tener valores (ya validado arriba)
                    $numGR = null;
                    $posGR = null;
                    if ($actualWinningPositionR && $winningNumberAtPositionR) {
                        // Guardar los valores REALES donde salió el número de redoblona
                        $numGR = $winningNumberAtPositionR;
                        $posGR = $actualWinningPositionR;
                    }
                    
                    // ✅ VALIDACIÓN FINAL: Si hay redoblona apostada, num_g_r y pos_g_r NO pueden ser NULL
                    // Si son NULL, significa que no se encontró o no es válida → NO insertar resultado
                    if (!empty($apu->numberR) && $apu->positionR !== null) {
                        if ($numGR === null || $posGR === null) {
                            Log::warning("LotteryResultProcessor - ❌ Redoblona rechazada: Se apostó redoblona pero num_g_r o pos_g_r son NULL. Ticket {$apu->ticket} - Lotería {$lotterySystemCode} - NumR: {$apu->numberR} PosR: {$apu->positionR} - NO se insertará el resultado.");
                            continue; // No insertar si num_g_r o pos_g_r son NULL
                        }
                    }
                    
                    $resultData = [
                        'ticket'      => $apu->ticket,
                        'lottery'     => $lotterySystemCode, // ✅ Usar código del sistema, no el código UI
                        'number'      => $apu->number,
                        'position'    => $apu->position, // Posición apostada
                        'numR'        => $apu->numberR ?? null,
                        'posR'        => $apu->positionR ?? null,
                        'XA'          => 'X',
                        'import'      => (float) $apu->import,
                        'aciert'      => $aciertValue + $aciertValueR, // Sum both aciertos
                        'times_won'   => $redoblonaWinningCount > 0 ? $redoblonaWinningCount : 1, // ✅ CORREGIDO: Usar conteo de veces que salió la redoblona, o 1 si no hay redoblona
                        'date'        => $dateToCalculate,
                        'time'        => $apu->timeApu,
                        'user_id'     => $apu->user_id,
                        'numero_g'    => $winningNumberAtPosition ?? null, // ✅ Número ganador real
                        'posicion_g'  => $actualWinningPosition ?? null, // ✅ Posición donde realmente salió
                        'num_g_r'     => $numGR, // ✅ Número real donde salió la redoblona (si se encontró)
                        'pos_g_r'     => $posGR, // ✅ Posición real donde salió la redoblona (si se encontró)
                        'created_at'  => now(),
                        'updated_at'  => now(),
                    ];
                    
                    // ✅ Log detallado antes de insertar
                    $logMessage = "LotteryResultProcessor - Datos antes de insertar: Ticket {$apu->ticket} - Lotería {$lotterySystemCode} - Número {$apu->number} - Posición apostada: {$apu->position} - Posición ganadora: {$actualWinningPosition} - Número ganador: {$winningNumberAtPosition}";
                    if ($numGR && $posGR) {
                        $logMessage .= " - Redoblona: NumR apostado {$apu->numberR} en PosR {$apu->positionR} → Num_g_r: {$numGR} en Pos_g_r: {$posGR}";
                    } else if (!empty($apu->numberR) && $apu->positionR !== null) {
                        $logMessage .= " - Redoblona apostada pero NO ganadora: NumR {$apu->numberR} PosR {$apu->positionR}";
                    }
                    $logMessage .= " - Premio: " . ($aciertValue + $aciertValueR);
                    Log::info($logMessage);
                    
                    // ✅ Usar ResultManager para inserción segura
                    $result = ResultManager::createResultSafely($resultData);
                    if ($result) {
                        $matches[] = $resultData;
                        // ✅ Verificar que se guardaron correctamente
                        $savedResult = Result::find($result->id);
                        Log::info("LotteryResultProcessor - ✅ Resultado insertado exitosamente ID: {$result->id} - Ticket {$apu->ticket} - Lotería {$lotterySystemCode} - numero_g guardado: {$savedResult->numero_g} - posicion_g guardado: {$savedResult->posicion_g} - Premio: " . ($aciertValue + $aciertValueR));
                    } else {
                        Log::warning("LotteryResultProcessor - ❌ No se pudo insertar resultado (duplicado o error): Ticket {$apu->ticket} - Lotería {$lotterySystemCode} - Número {$apu->number} - Posición {$apu->position} - APU ID: {$apu->id}");
                    }
                }
            }
        }

        if (!empty($matches)) {
            Log::info("LotteryResultProcessor - Resumen de aciertos para {$dateToCalculate}:", $matches);
        } else {
            Log::warning("LotteryResultProcessor - No hubo ganadores para la fecha " . $dateToCalculate);
        }

        Log::info("LotteryResultProcessor - Procesamiento finalizado para la fecha " . $dateToCalculate);
    }

    // Helper methods from FetchAndDisplayPlaysSent
    private function removeAsterisks(string $number): string
    {
        return ltrim($number, '*');
    }

    private function getTicketType(string $ticket): ?string
    {
        $asteriskCount = strlen($ticket) - strlen(ltrim($ticket, '*'));
        $clean = ltrim($ticket, '*');
        $digitCount = strlen($clean);

        if ($asteriskCount === 3) {
            return 'quiniela';
        } elseif ($asteriskCount === 2 && $digitCount === 2) {
            return 'prizes';
        } elseif ($asteriskCount === 1 && $digitCount === 3) {
            return 'figureOne';
        } elseif ($asteriskCount === 0 && $digitCount === 4) {
            return 'figureTwo';
        }
        return null;
    }

    // Helper method to map UI lottery code to system code
    private function getSystemLotteryCode(string $apuLotteryUiCode): ?string
    {
        // Si es un código UI (existe como clave en el array), mapearlo
        if (array_key_exists($apuLotteryUiCode, $this->codes)) {
            return $this->codes[$apuLotteryUiCode];
        }
        
        // Si no está en el mapeo, verificar si ya es un código del sistema (existe como código de ciudad en la BD)
        $city = City::where('code', $apuLotteryUiCode)->first();
        if ($city) {
            return $apuLotteryUiCode;
        }
        
        return null;
    }

    /**
     * ✅ Verifica si la posición apostada es correcta según las reglas de quiniela
     * Usa las mismas reglas que el número principal para validar la redoblona
     */
    private function isPositionCorrect($playedPosition, $winningPosition, $isRedoblona = false): bool
    {
        // ✅ CORREGIDO: Para redoblonas, usar rangos 2-5, 2-10, 2-20
        // Para jugadas normales, usar rangos 2-5, 6-10, 11-20
        
        switch ($playedPosition) {
            case 1:
                // Quiniela: solo gana si sale en posición 1
                return $winningPosition == 1;
                
            case 5:
                // A los 5: gana si sale en posiciones 2-5 (igual para normal y redoblona)
                return $winningPosition >= 2 && $winningPosition <= 5;
                
            case 10:
                if ($isRedoblona) {
                    // Redoblona: gana si sale en posiciones 2-10
                    return $winningPosition >= 2 && $winningPosition <= 10;
                } else {
                    // Jugada normal: gana si sale en posiciones 6-10
                    return $winningPosition >= 6 && $winningPosition <= 10;
                }
                
            case 20:
                if ($isRedoblona) {
                    // Redoblona: gana si sale en posiciones 2-20
                    return $winningPosition >= 2 && $winningPosition <= 20;
                } else {
                    // Jugada normal: gana si sale en posiciones 11-20
                    return $winningPosition >= 11 && $winningPosition <= 20;
                }
                
            default:
                // Para otras posiciones, verificar coincidencia exacta
                return $playedPosition == $winningPosition;
        }
    }

    /**
     * Obtiene el rango de posiciones para la redoblona
     * ✅ CORREGIDO: Posición 5 busca 2-5, posición 10 busca 2-10, posición 20 busca 2-20
     */
    private function getRedoblonaPositionRange($position): array
    {
        if ($position == 1) {
            return ['min' => 1, 'max' => 1];
        } elseif ($position == 5) {
            return ['min' => 2, 'max' => 5];
        } elseif ($position == 10) {
            return ['min' => 2, 'max' => 10];
        } elseif ($position == 20) {
            return ['min' => 2, 'max' => 20];
        } elseif ($position >= 2 && $position <= 5) {
            return ['min' => 2, 'max' => 5];
        } elseif ($position >= 6 && $position <= 10) {
            return ['min' => 2, 'max' => 10];
        } elseif ($position >= 11 && $position <= 20) {
            return ['min' => 2, 'max' => 20];
        }
        
        return ['min' => $position, 'max' => $position];
    }
}