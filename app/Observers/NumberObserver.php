<?php

namespace App\Observers;

use App\Models\Number;
use App\Models\ApusModel;
use App\Models\Result;
use App\Services\ResultManager;
use App\Models\QuinielaModel;
use App\Models\PrizesModel;
use App\Models\FigureOneModel;
use App\Models\FigureTwoModel;
use App\Models\BetCollectionRedoblonaModel;
use App\Models\BetCollection5To20Model;
use App\Models\BetCollection10To20Model;
use App\Services\RedoblonaService;
use App\Services\LotteryCompletenessService;
use Illuminate\Support\Facades\Log;

class NumberObserver
{
    private static $payoutTables = null;
    private $redoblonaService;

    public function __construct()
    {
        $this->redoblonaService = new RedoblonaService();
    }

    /**
     * Handle the Number "created" event.
     */
    public function created(Number $number)
    {
        Log::info("NumberObserver - Nuevo número insertado: {$number->city->code} - Pos {$number->index} - Valor {$number->value}");
        
        // Procesar pagos automáticamente cuando se inserta un número
        $this->processAutoPaymentsForNumber($number);
    }

    /**
     * Handle the Number "updated" event.
     */
    public function updated(Number $number)
    {
        Log::info("NumberObserver - Número actualizado: {$number->city->code} - Pos {$number->index} - Valor {$number->value}");
        
        // Procesar pagos automáticamente cuando se actualiza un número
        $this->processAutoPaymentsForNumber($number);
    }

    /**
     * Procesa pagos automáticamente para un número específico
     * ✅ MODIFICADO: Solo procesa cuando la lotería tenga sus 20 números completos
     */
    private function processAutoPaymentsForNumber(Number $number)
    {
        try {
            // Cargar tablas de pagos si no están cargadas
            $this->loadPayoutTables();

            // MEJORA: Obtener el código de lotería completo dinámicamente
            $lotteryCode = $this->getLotteryCodeFromNumber($number);
            
            if (!$lotteryCode) {
                Log::warning("NumberObserver - No se pudo determinar el código de lotería para: {$number->city->code}");
                return;
            }

            Log::info("NumberObserver - Verificando completitud de lotería: {$lotteryCode} para ciudad: {$number->city->code}");

            // ✅ ÚNICO FILTRO: Verificar que la lotería tenga sus 20 números completos
            $isComplete = LotteryCompletenessService::isLotteryComplete($lotteryCode, $number->date);
            
            if (!$isComplete) {
                Log::info("NumberObserver - Lotería {$lotteryCode} aún no está completa (tiene menos de 20 números). NO se procesarán resultados hasta que tenga los 20 números.");
                return;
            }

            Log::info("NumberObserver - ✅ Lotería {$lotteryCode} COMPLETA con 20 números. Iniciando procesamiento...");

            // Obtener todos los números ganadores de esta lotería completa
            $completeNumbers = LotteryCompletenessService::getCompleteLotteryNumbersCollection($lotteryCode, $number->date);
            
            if (!$completeNumbers) {
                Log::warning("NumberObserver - No se pudieron obtener los números completos para {$lotteryCode}");
                return;
            }

            // Buscar jugadas que puedan ser ganadoras con esta lotería completa
            $matchingPlays = $this->getMatchingPlaysForLottery($lotteryCode, $number->date);

            if ($matchingPlays->isEmpty()) {
                Log::info("NumberObserver - No hay jugadas para la lotería completa {$lotteryCode}");
                return;
            }

            Log::info("NumberObserver - Procesando {$matchingPlays->count()} jugadas para lotería completa {$lotteryCode}");

            $resultsInserted = 0;
            $totalPrize = 0;

            foreach ($matchingPlays as $play) {
                // ✅ MODIFICADO: Verificar si esta jugada específica es ganadora para esta lotería específica
                // Obtener el número ganador y posición ganadora
                $winningInfo = $this->getWinningNumberAndPosition($play, $completeNumbers, $lotteryCode);
                
                if ($winningInfo && $winningInfo['isWinner']) {
                    $prizeData = $this->calculatePrizeForLotteryComplete($play, $completeNumbers, $lotteryCode);
                    $prize = $prizeData['prize'];
                    $timesWon = $prizeData['times_won'];
                    
                    if ($prize > 0) {
                        // ✅ Usar ResultManager para inserción segura
                        $resultData = [
                            'user_id' => $play->user_id,
                            'ticket' => $play->ticket,
                            'lottery' => $lotteryCode, // ✅ Solo la lotería específica donde salió el número
                            'number' => $play->number,
                            'position' => $play->position,
                            'numR' => $play->numberR,
                            'posR' => $play->positionR,
                            'XA' => 'X',
                            'import' => $play->import,
                            'aciert' => $prize, // ✅ Solo el premio de esta lotería específica
                            'times_won' => $timesWon, // ✅ Número de veces que salió
                            'date' => $number->date,
                            'time' => $number->extract->time,
                            'numero_g' => $winningInfo['winningNumber'], // ✅ Número ganador real
                            'posicion_g' => $winningInfo['winningPosition'], // ✅ Posición donde realmente salió
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];

                        Log::info("NumberObserver - ANTES de guardar: times_won en resultData = " . ($resultData['times_won'] ?? 'NO DEFINIDO'));
                        $result = ResultManager::createResultSafely($resultData);
                        if ($result) {
                            $resultsInserted++;
                            $totalPrize += $prize;
                            // Verificar el valor guardado en la BD
                            $savedTimesWon = $result->times_won ?? 'NO DEFINIDO';
                            Log::info("NumberObserver - Resultado insertado: Ticket {$play->ticket} - Lotería {$lotteryCode} - Premio: {$prize} - Veces calculado: {$timesWon} - Veces guardado en BD: {$savedTimesWon} - numero_g: {$winningInfo['winningNumber']} - posicion_g: {$winningInfo['winningPosition']}");
                            if ($timesWon != $savedTimesWon) {
                                Log::error("NumberObserver - ⚠️ DISCREPANCIA: times_won calculado ({$timesWon}) != times_won guardado ({$savedTimesWon})");
                            }
                        }
                    }
                }
            }

            if ($resultsInserted > 0) {
                Log::info("NumberObserver - ✅ Procesamiento completado para lotería {$lotteryCode}: {$resultsInserted} resultados insertados - Total: $" . number_format($totalPrize, 2));
            } else {
                Log::info("NumberObserver - No se encontraron jugadas ganadoras para la lotería completa {$lotteryCode}");
            }

        } catch (\Exception $e) {
            Log::error("NumberObserver - Error procesando pagos automáticos: " . $e->getMessage());
        }
    }

    /**
     * Carga las tablas de pagos
     */
    private function loadPayoutTables()
    {
        if (self::$payoutTables === null) {
            self::$payoutTables = [
                'quiniela' => QuinielaModel::first(),
                'prizes' => PrizesModel::first(),
                'figureOne' => FigureOneModel::first(),
                'figureTwo' => FigureTwoModel::first(),
                'redoblona1toX' => BetCollectionRedoblonaModel::where('bet_amount', 1.00)->first(),
                'redoblona5to20' => BetCollection5To20Model::where('bet_amount', 1.00)->first(),
                'redoblona10to20' => BetCollection10To20Model::where('bet_amount', 1.00)->first(),
            ];
        }
    }

    /**
     * Calcula el resultado de una jugada específica
     */
    private function calculatePlayResult($play, $number)
    {
        $mainPrize = 0;
        $redoblonaPrize = 0;

        // IMPORTANTE: Si hay redoblona, NO se paga premio principal, solo redoblona
        if (!empty($play->numberR) && !empty($play->positionR)) {
            // Solo calcular premio de redoblona (se paga TODO como redoblona)
            $redoblonaPrize = $this->redoblonaService->calculateRedoblonaPrize($play, $number->date, $play->lottery);
        } else {
            // Solo calcular premio principal si NO hay redoblona
            if ($this->isWinningPlay($play, $number->value, $number->index)) {
                $mainPrize = $this->calculateMainPrize($play, $number->value);
            }
        }

        return [
            'mainPrize' => $mainPrize,
            'redoblonaPrize' => $redoblonaPrize,
            'totalPrize' => $mainPrize + $redoblonaPrize
        ];
    }

    /**
     * Verifica si una jugada es ganadora
     * ✅ MODIFICADO: Ahora verifica tanto los números como las posiciones correctas
     */
    private function isWinningPlay($play, $winningNumber, $winningPosition = null)
    {
        $playNumber = str_replace('*', '', $play->number);
        $winningNumberStr = str_pad($winningNumber, 4, '0', STR_PAD_LEFT);
        
        $playLength = strlen($playNumber);
        $winningSuffix = substr($winningNumberStr, -$playLength);
        
        // Verificar que los números coincidan
        $numbersMatch = $playNumber === $winningSuffix;
        
        if (!$numbersMatch) {
            return false;
        }
        
        // Si no se proporciona la posición ganadora, solo verificar números (comportamiento anterior)
        if ($winningPosition === null) {
            return true;
        }
        
        // Verificar que la posición sea correcta según las reglas de quiniela
        return $this->isPositionCorrect($play->position, $winningPosition);
    }
    
    /**
     * ✅ NUEVO: Verifica si la posición apostada es correcta según las reglas de quiniela
     * ✅ MODIFICADO: Usa nuevos rangos (posición 10 busca 2-10, posición 20 busca 2-20)
     */
    private function isPositionCorrect($playedPosition, $winningPosition)
    {
        // ✅ NUEVA LÓGICA:
        // - Posición 1 (Quiniela): Solo gana si sale en posición 1
        // - Posición 5: Gana si sale en posiciones 2-5
        // - Posición 10: Gana si sale en posiciones 2-10
        // - Posición 20: Gana si sale en posiciones 2-20
        
        switch ($playedPosition) {
            case 1:
                // Quiniela: solo gana si sale en posición 1
                return $winningPosition == 1;
                
            case 5:
                // A los 5: gana si sale en posiciones 2-5
                return $winningPosition >= 2 && $winningPosition <= 5;
                
            case 10:
                // A los 10: gana si sale en posiciones 2-10
                return $winningPosition >= 2 && $winningPosition <= 10;
                
            case 20:
                // A los 20: gana si sale en posiciones 2-20
                return $winningPosition >= 2 && $winningPosition <= 20;
                
            default:
                // Para otras posiciones, verificar coincidencia exacta
                return $playedPosition == $winningPosition;
        }
    }

    /**
     * Obtiene las jugadas que pueden ser ganadoras con un número específico
     * ✅ MODIFICADO: Busca jugadas que contengan la lotería específica (pueden tener múltiples loterías)
     */
    private function getMatchingPlaysForNumber(Number $number, $lotteryCode)
    {
        // Traer únicamente por lotería EXACTA y la fecha; la validación de posición se hará con isPositionCorrect
        return ApusModel::whereDate('created_at', $number->date)
            ->whereRaw('FIND_IN_SET(?, lottery)', [$lotteryCode])
            ->get();
    }

    /**
     * Calcula el premio principal con la lógica correcta
     */
    private function calculateMainPrize($play, $winningNumber)
    {
        $playNumber = str_replace('*', '', $play->number);
        $playLength = strlen($playNumber);
        
        // REGLA FUNDAMENTAL: POSICIÓN 1 = A LA CABEZA = SIEMPRE TABLA QUINIELA
        if ($play->position == 1) {
            return $this->calculateQuinielaPrize($play, $playLength);
        }
        
        // Para posiciones 2-20, usar tablas específicas según dígitos
        if ($playLength == 2) {
            return $this->calculatePrizesPrize($play);
        } elseif ($playLength == 3) {
            return $this->calculateFigureOnePrize($play);
        } elseif ($playLength == 4) {
            return $this->calculateFigureTwoPrize($play);
        }

        return 0;
    }

    /**
     * Calcula premio usando tabla Quiniela (solo para posición 1)
     */
    private function calculateQuinielaPrize($play, $playLength)
    {
        $payoutTable = self::$payoutTables['quiniela'];
        if (!$payoutTable) {
            return 0;
        }

        $multiplier = 0;
        
        // Tabla Quiniela: según cantidad de dígitos
        if ($playLength == 1) {
            $multiplier = (float) ($payoutTable->cobra_1_cifra ?? 0);
        } elseif ($playLength == 2) {
            $multiplier = (float) ($payoutTable->cobra_2_cifra ?? 0);
        } elseif ($playLength == 3) {
            $multiplier = (float) ($payoutTable->cobra_3_cifra ?? 0);
        } elseif ($playLength == 4) {
            $multiplier = (float) ($payoutTable->cobra_4_cifra ?? 0);
        }

        return $play->import * $multiplier;
    }

    /**
     * Calcula premio usando tabla Prizes (2 dígitos, posiciones 2-20)
     */
    private function calculatePrizesPrize($play)
    {
        $payoutTable = self::$payoutTables['prizes'];
        if (!$payoutTable) {
            return 0;
        }

        $multiplier = $this->getPositionMultiplier($play->position, $payoutTable);
        return $play->import * $multiplier;
    }

    /**
     * Calcula premio usando tabla FigureOne (3 dígitos, posiciones 2-20)
     */
    private function calculateFigureOnePrize($play)
    {
        $payoutTable = self::$payoutTables['figureOne'];
        if (!$payoutTable) {
            return 0;
        }

        $multiplier = $this->getPositionMultiplier($play->position, $payoutTable);
        return $play->import * $multiplier;
    }

    /**
     * Calcula premio usando tabla FigureTwo (4 dígitos, posiciones 2-20)
     */
    private function calculateFigureTwoPrize($play)
    {
        $payoutTable = self::$payoutTables['figureTwo'];
        if (!$payoutTable) {
            return 0;
        }

        $multiplier = $this->getPositionMultiplier($play->position, $payoutTable);
        return $play->import * $multiplier;
    }

    /**
     * Obtiene el multiplicador según la posición
     */
    private function getPositionMultiplier($position, $payoutTable)
    {
        if ($position <= 5) {
            return (float) ($payoutTable->cobra_5 ?? 0);
        } elseif ($position <= 10) {
            return (float) ($payoutTable->cobra_10 ?? 0);
        } elseif ($position <= 20) {
            return (float) ($payoutTable->cobra_20 ?? 0);
        }
        
        return 0;
    }


    /**
     * MEJORA: Obtiene el código de lotería completo dinámicamente desde la base de datos
     */
    private function getLotteryCodeFromNumber(Number $number): ?string
    {
        try {
            // CORRECCIÓN: El código de lotería ya está completo en city->code
            // Ejemplo: NAC1015, CHA1200, PRO1500, etc.
            
            $lotteryCode = $number->city->code;
            
            Log::info("NumberObserver - Código de lotería: {$lotteryCode} (Ciudad: {$number->city->name}, Tiempo: {$number->city->time})");
            
            return $lotteryCode;
            
        } catch (\Exception $e) {
            Log::error("NumberObserver - Error obteniendo código de lotería: " . $e->getMessage());
            return null;
        }
    }

    /**
     * ✅ NUEVO: Obtiene las jugadas que pueden ser ganadoras para una lotería específica
     * ✅ MODIFICADO: Excluye jugadas anuladas (status 'I' en PlaysSentModel)
     */
    private function getMatchingPlaysForLottery($lotteryCode, $date)
    {
        return ApusModel::whereDate('created_at', $date)
            ->whereRaw('FIND_IN_SET(?, lottery)', [$lotteryCode])
            ->whereHas('playsSent', function($query) {
                $query->where('status', '!=', 'I'); // Excluir jugadas anuladas
            })
            ->get();
    }

    /**
     * ✅ NUEVO: Verifica si una jugada es ganadora para una lotería completa
     * CORREGIDO: Ahora verifica correctamente las posiciones según reglas de quiniela
     */
    private function isWinningPlayForLotteryComplete($play, $completeNumbers, $lotteryCode)
    {
        $winningInfo = $this->getWinningNumberAndPosition($play, $completeNumbers, $lotteryCode);
        return $winningInfo && $winningInfo['isWinner'];
    }

    /**
     * ✅ NUEVO: Obtiene el número ganador y la posición ganadora para una jugada
     * Retorna null si no es ganadora, o un array con isWinner, winningNumber y winningPosition
     * ✅ MODIFICADO: Usa nuevos rangos (posición 10 busca 2-10, posición 20 busca 2-20)
     */
    private function getWinningNumberAndPosition($play, $completeNumbers, $lotteryCode)
    {
        // Verificar que la jugada contenga esta lotería específica
        $playLotteries = explode(',', $play->lottery);
        $playLotteries = array_map('trim', $playLotteries);
        
        if (!in_array($lotteryCode, $playLotteries)) {
            return null;
        }
        
        // ✅ NUEVA LÓGICA: Determinar rango permitido según posición apostada
        // Posición 5: busca de 2-5
        // Posición 10: busca de 2-10
        // Posición 20: busca de 2-20
        $allowedIndexes = [];
        $playedPosition = (int)$play->position;
        
        switch ($playedPosition) {
            case 1:
                // Quiniela: solo posición 1
                $allowedIndexes = [1];
                break;
            case 5:
                // A los 5: posiciones 1-5 (ahora incluye posición 1)
                $allowedIndexes = range(1, 5);
                break;
            case 10:
                // A los 10: posiciones 1-10 (ahora incluye posición 1)
                $allowedIndexes = range(1, 10);
                break;
            case 20:
                // A los 20: posiciones 1-20 (ahora incluye posición 1)
                $allowedIndexes = range(1, 20);
                break;
            default:
                // Para otras posiciones específicas, solo esa posición
                $allowedIndexes = [$playedPosition];
        }

        // Verificar si los números coinciden con alguno de los números ganadores completos EN POSICIÓN VÁLIDA
        // Retornar el primero que encuentre (para mantener compatibilidad con código existente)
        foreach ($completeNumbers as $number) {
            if (!in_array((int)$number->index, $allowedIndexes)) {
                continue;
            }
            // ✅ Verificar tanto números como posición correcta
            if ($this->isWinningPlay($play, $number->value, $number->index)) {
                return [
                    'isWinner' => true,
                    'winningNumber' => $number->value,
                    'winningPosition' => $number->index
                ];
            }
        }
        
        return null;
    }

    /**
     * ✅ NUEVO: Calcula el premio para una lotería completa
     * ✅ MODIFICADO: Ahora cuenta múltiples apariciones y usa pago base de la posición jugada
     * ✅ MODIFICADO: Retorna array con premio y times_won
     */
    private function calculatePrizeForLotteryComplete($play, $completeNumbers, $lotteryCode)
    {
        $mainPrize = 0;
        $redoblonaPrize = 0;
        $timesWon = 1; // Por defecto 1 vez

        // IMPORTANTE: Si hay redoblona, NO se paga premio principal, solo redoblona
        if (!empty($play->numberR) && !empty($play->positionR)) {
            // Solo calcular premio de redoblona (se paga TODO como redoblona)
            $redoblonaData = $this->redoblonaService->calculateRedoblonaPrizeWithCount($play, $completeNumbers->first()->date, $lotteryCode);
            $redoblonaPrize = $redoblonaData['prize'];
            $timesWon = $redoblonaData['times_won'];
        } else {
            // Solo calcular premio principal si NO hay redoblona
            $playedNumber = str_replace('*', '', $play->number);
            $playedDigits = strlen($playedNumber);
            $playedPosition = (int)$play->position;

            // ✅ NUEVA LÓGICA: Determinar rango permitido según posición apostada
            // Posición 5: busca de 1-5 (ahora incluye posición 1)
            // Posición 10: busca de 1-10 (ahora incluye posición 1)
            // Posición 20: busca de 1-20 (ahora incluye posición 1)
            $allowedIndexes = [];
            
            switch ($playedPosition) {
                case 1:
                    // Quiniela: solo posición 1
                    $allowedIndexes = [1];
                    break;
                case 5:
                    // A los 5: posiciones 1-5 (ahora incluye posición 1)
                    $allowedIndexes = range(1, 5);
                    break;
                case 10:
                    // A los 10: posiciones 1-10 (ahora incluye posición 1)
                    $allowedIndexes = range(1, 10);
                    break;
                case 20:
                    // A los 20: posiciones 1-20 (ahora incluye posición 1)
                    $allowedIndexes = range(1, 20);
                    break;
                default:
                    // Para otras posiciones específicas, solo esa posición
                    $allowedIndexes = [$playedPosition];
            }

            // ✅ NUEVA LÓGICA: Contar cuántas veces sale el número en el rango válido
            $winningCount = 0;
            $winningPositions = [];
            
            // ✅ MEJORADO: Limpiar y normalizar el número jugado
            $playNumber = trim(str_replace('*', '', $play->number));
            $playLength = strlen($playNumber);
            
            // Validar que el número jugado tenga entre 1 y 4 dígitos
            if ($playLength < 1 || $playLength > 4) {
                Log::warning("NumberObserver - Número jugado inválido: {$play->number} (longitud: {$playLength})");
                $winningCount = 0; // No se encontraron coincidencias porque el número es inválido
            } else {
                Log::info("NumberObserver - Contando apariciones: {$play->number} (limpio: '{$playNumber}', {$playLength} dígitos) posición {$playedPosition} en {$lotteryCode}");
                Log::info("NumberObserver - Rango permitido: " . implode(', ', $allowedIndexes));
                Log::info("NumberObserver - Total números completos: " . $completeNumbers->count());
                
                // ✅ MEJORADO: Verificar que completeNumbers tenga datos
                if ($completeNumbers->isEmpty()) {
                    Log::warning("NumberObserver - ⚠️ completeNumbers está vacío para {$lotteryCode}");
                    $winningCount = 0; // No se encontraron coincidencias porque no hay números
                } else {
                    foreach ($completeNumbers as $number) {
                        // ✅ MEJORADO: Asegurar que index sea un entero válido
                        $numberIndex = (int)$number->index;
                        
                        // ✅ MEJORADO: Validar que el índice esté en rango válido (1-20)
                        if ($numberIndex < 1 || $numberIndex > 20) {
                            Log::warning("NumberObserver - ⚠️ Índice inválido: {$numberIndex} para número {$number->value}");
                            continue;
                        }
                        
                        // ✅ MEJORADO: Verificar si está en el rango permitido ANTES de procesar
                        if (!in_array($numberIndex, $allowedIndexes)) {
                            continue;
                        }
                        
                        // ✅ MEJORADO: Limpiar y normalizar el número ganador
                        $winningValue = trim((string)$number->value);
                        if (empty($winningValue)) {
                            Log::warning("NumberObserver - ⚠️ Número ganador vacío en posición {$numberIndex}");
                            continue;
                        }
                        
                        // ✅ MEJORADO: Normalizar el número ganador a 4 dígitos con ceros a la izquierda
                        $winningNumberStr = str_pad($winningValue, 4, '0', STR_PAD_LEFT);
                        
                        // ✅ MEJORADO: Extraer el sufijo del número ganador
                        $winningSuffix = substr($winningNumberStr, -$playLength);
                        
                        // ✅ MEJORADO: Comparación estricta (case-sensitive, pero ambos son números)
                        $numbersMatch = ($playNumber === $winningSuffix);
                        
                        // ✅ MEJORADO: Verificar posición con validación adicional
                        $positionCorrect = $this->isPositionCorrect($playedPosition, $numberIndex);
                        
                        // Log detallado de la comparación
                        Log::info("NumberObserver - Comparación pos {$numberIndex}: jugado '{$playNumber}' vs ganador '{$winningSuffix}' (completo: '{$winningValue}' -> '{$winningNumberStr}') - Coincide: " . ($numbersMatch ? 'SÍ' : 'NO') . " - Posición correcta: " . ($positionCorrect ? 'SÍ' : 'NO'));
                        
                        if ($numbersMatch && $positionCorrect) {
                            $winningCount++;
                            $winningPositions[] = $numberIndex;
                            Log::info("NumberObserver - ✅ Aparición #{$winningCount}: {$play->number} coincide con {$winningValue} en posición {$numberIndex}");
                        } else {
                            if ($numbersMatch && !$positionCorrect) {
                                Log::info("NumberObserver - ⚠️ Número coincide pero posición incorrecta: {$play->number} vs {$winningValue} en posición {$numberIndex} (apostado: {$playedPosition})");
                            } elseif (!$numbersMatch && $positionCorrect) {
                                Log::info("NumberObserver - ⚠️ Posición correcta pero número no coincide: '{$playNumber}' vs '{$winningSuffix}' (completo: '{$winningValue}') en posición {$numberIndex}");
                            }
                        }
                    }
                }
            }
            
            // Log para depuración
            Log::info("NumberObserver - Conteo final: {$play->number} posición {$playedPosition} en {$lotteryCode} - Veces: {$winningCount} - Posiciones: " . implode(', ', $winningPositions));
            
            if ($winningCount > 1) {
                Log::info("NumberObserver - ✅ MÚLTIPLES APARICIONES DETECTADAS: {$play->number} posición {$playedPosition} en {$lotteryCode} - Veces: {$winningCount}");
            } elseif ($winningCount == 0) {
                Log::warning("NumberObserver - ⚠️ NO SE ENCONTRARON COINCIDENCIAS: {$play->number} posición {$playedPosition} en {$lotteryCode} - times_won se quedará en 1 (valor por defecto)");
            }

            // ✅ IMPORTANTE: Actualizar timesWon SOLO si se encontraron coincidencias
            if ($winningCount > 0) {
                $timesWon = $winningCount; // Guardar el conteo de veces que salió
                Log::info("NumberObserver - ✅ Actualizando times_won a {$winningCount} para {$play->number} posición {$playedPosition}");
            } else {
                Log::warning("NumberObserver - ⚠️ winningCount es 0, times_won se mantiene en 1 (valor por defecto) para {$play->number} posición {$playedPosition}");
            }
            
            if ($winningCount > 0) {
                // Posición 1 usa tabla Quiniela según dígitos apostados
                if ($playedPosition === 1) {
                    $payoutTable = self::$payoutTables['quiniela'] ?? null;
                    if ($payoutTable) {
                        $mult = 0;
                        if ($playedDigits == 1) $mult = (float)($payoutTable->cobra_1_cifra ?? 0);
                        elseif ($playedDigits == 2) $mult = (float)($payoutTable->cobra_2_cifra ?? 0);
                        elseif ($playedDigits == 3) $mult = (float)($payoutTable->cobra_3_cifra ?? 0);
                        elseif ($playedDigits == 4) $mult = (float)($payoutTable->cobra_4_cifra ?? 0);
                        // Para posición 1, no se multiplica por veces (solo puede salir una vez)
                        $mainPrize = $play->import * $mult;
                    }
                } else {
                    // ✅ NUEVA LÓGICA: Para posiciones 5, 10, 20 usar pago base de la posición JUGADA
                    // Determinar tabla según dígitos
                    if ($playedDigits == 1 || $playedDigits == 2) {
                        $payoutTable = self::$payoutTables['prizes'] ?? null;
                    } elseif ($playedDigits == 3) {
                        $payoutTable = self::$payoutTables['figureOne'] ?? null;
                    } else { // 4 dígitos
                        $payoutTable = self::$payoutTables['figureTwo'] ?? null;
                    }
                    
                    if ($payoutTable) {
                        // ✅ Usar pago base de la posición JUGADA, no de donde salió
                        $baseMultiplier = $this->getPositionMultiplier($playedPosition, $payoutTable);
                        // Multiplicar: pago base × veces que salió × importe
                        $mainPrize = $baseMultiplier * $winningCount * $play->import;
                    }
                }
            }
        }

        $finalPrize = $mainPrize + $redoblonaPrize;
        Log::info("NumberObserver - calculatePrizeForLotteryComplete retorna: prize = {$finalPrize}, times_won = {$timesWon}");
        
        return [
            'prize' => $finalPrize,
            'times_won' => $timesWon
        ];
    }

    /**
     * ✅ NUEVO: Verifica si una jugada es ganadora para una lotería específica
     */
    private function isWinningPlayForLottery($play, $number, $lotteryCode)
    {
        // Verificar que la jugada contenga esta lotería específica
        $playLotteries = explode(',', $play->lottery);
        $playLotteries = array_map('trim', $playLotteries);
        
        if (!in_array($lotteryCode, $playLotteries)) {
            return false;
        }
        
        // Verificar si los números coinciden
        return $this->isWinningPlay($play, $number->value, $number->index);
    }

    /**
     * ✅ NUEVO: Calcula el premio para una lotería específica
     */
    private function calculatePrizeForLottery($play, $number, $lotteryCode)
    {
        $mainPrize = 0;
        $redoblonaPrize = 0;

        // IMPORTANTE: Si hay redoblona, NO se paga premio principal, solo redoblona
        if (!empty($play->numberR) && !empty($play->positionR)) {
            // Solo calcular premio de redoblona (se paga TODO como redoblona)
            $redoblonaPrize = $this->redoblonaService->calculateRedoblonaPrize($play, $number->date, $lotteryCode);
        } else {
            // Solo calcular premio principal si NO hay redoblona
            if ($this->isWinningPlay($play, $number->value, $number->index)) {
                $mainPrize = $this->calculateMainPrize($play, $number->value);
            }
        }

        return $mainPrize + $redoblonaPrize;
    }
}
