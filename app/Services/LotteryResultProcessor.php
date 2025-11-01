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
                            $actualWinningPosition = $pos;
                            $winningNumberAtPosition = $winningNum;
                            break;
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
                    
                    // Determinar rango de búsqueda para redoblona según posición apostada
                    $searchPositionsR = [];
                    if ($apu->positionR == 1) {
                        $searchPositionsR = [1];
                    } elseif ($apu->positionR >= 2 && $apu->positionR <= 5) {
                        $searchPositionsR = range(2, 5);
                    } elseif ($apu->positionR >= 6 && $apu->positionR <= 10) {
                        $searchPositionsR = range(6, 10);
                    } elseif ($apu->positionR >= 11 && $apu->positionR <= 20) {
                        $searchPositionsR = range(11, 20);
                    }
                    
                    // Buscar el número de redoblona en todas las posiciones del rango
                    foreach ($searchPositionsR as $posR) {
                        if (!isset($winningNumbersForLottery[$posR])) continue;
                        
                        $winningNumR = str_pad((string)$winningNumbersForLottery[$posR], 4, '0', STR_PAD_LEFT);
                        $winningNumberLastDigitsR = substr($winningNumR, -$numDigitsPlayedR);
                        
                        if ($playedNumberRClean === $winningNumberLastDigitsR) {
                            $actualWinningPositionR = $posR;
                            $winningNumberAtPositionR = $winningNumR;
                            break;
                        }
                    }
                    
                    if ($actualWinningPositionR && $winningNumberAtPositionR) {
                        $multiplierR = 0;
                        // Calcular premio basado en las posiciones apostadas y donde realmente salieron
                        $mainWinningPos = $actualWinningPosition ?? $apu->position; // Usar posición real o apostada si no hay acierto principal
                        
                        if ($apu->position == 1) {
                            if ($actualWinningPositionR <= 5) $multiplierR = $betCollectionRedoblona->payout_1_to_5;
                            elseif ($actualWinningPositionR <= 10) $multiplierR = $betCollectionRedoblona->payout_1_to_10;
                            elseif ($actualWinningPositionR <= 20) $multiplierR = $betCollectionRedoblona->payout_1_to_20;
                        } elseif ($apu->position >= 2 && $apu->position <= 5) {
                            if ($actualWinningPositionR >= 2 && $actualWinningPositionR <= 5) $multiplierR = $betCollection5To20->payout_5_to_5;
                            elseif ($actualWinningPositionR >= 6 && $actualWinningPositionR <= 10) $multiplierR = $betCollection5To20->payout_5_to_10;
                            elseif ($actualWinningPositionR >= 11 && $actualWinningPositionR <= 20) $multiplierR = $betCollection5To20->payout_5_to_20;
                        } elseif ($apu->position >= 6 && $apu->position <= 10) {
                            if ($actualWinningPositionR >= 6 && $actualWinningPositionR <= 10) $multiplierR = $betCollection10To20->payout_10_to_10;
                            elseif ($actualWinningPositionR >= 11 && $actualWinningPositionR <= 20) $multiplierR = $betCollection10To20->payout_10_to_20;
                        } elseif ($apu->position >= 11 && $apu->position <= 20) {
                            if ($actualWinningPositionR >= 11 && $actualWinningPositionR <= 20) $multiplierR = $betCollection10To20->payout_20_to_20;
                        }
                        $aciertValueR = (float)$apu->import * (float)$multiplierR;
                        Log::info("LotteryResultProcessor - Acierto redoblona: {$playedNumberRClean} en posición apostada {$apu->positionR}, salió en posición {$actualWinningPositionR}, premio: {$aciertValueR}");
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
                    
                    // ✅ Asegurar que numero_g y posicion_g no sean null para el acierto principal
                    if ($aciertValue > 0) {
                        if (!$winningNumberAtPosition || !$actualWinningPosition) {
                            Log::warning("LotteryResultProcessor - numero_g o posicion_g son null para acierto principal: Ticket {$apu->ticket} - Lotería {$lotterySystemCode} - Número {$apu->number} - numero_g: {$winningNumberAtPosition} - posicion_g: {$actualWinningPosition}");
                            continue;
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
                        'date'        => $dateToCalculate,
                        'time'        => $apu->timeApu,
                        'user_id'     => $apu->user_id,
                        'numero_g'    => $winningNumberAtPosition ?? null, // ✅ Número ganador real
                        'posicion_g'  => $actualWinningPosition ?? null, // ✅ Posición donde realmente salió
                        'num_g_r'     => $winningNumberAtPositionR ?? null, // Para redoblona
                        'pos_g_r'     => $actualWinningPositionR ?? null, // Para redoblona
                        'created_at'  => now(),
                        'updated_at'  => now(),
                    ];
                    
                    // ✅ Log detallado antes de insertar
                    Log::info("LotteryResultProcessor - Datos antes de insertar: Ticket {$apu->ticket} - Lotería {$lotterySystemCode} - Número {$apu->number} - Posición apostada: {$apu->position} - Posición ganadora: {$actualWinningPosition} - Número ganador: {$winningNumberAtPosition} - Premio: " . ($aciertValue + $aciertValueR));
                    
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
}