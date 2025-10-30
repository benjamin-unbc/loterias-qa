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
use App\Services\AnalysisSchedule;
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
            // Respetar ventanas de análisis horarias
            if (!AnalysisSchedule::isWithinAnalysisWindow()) {
                Log::info("NumberObserver - Fuera de ventana de análisis. Se omite procesamiento.");
                return;
            }
            // Cargar tablas de pagos si no están cargadas
            $this->loadPayoutTables();

            // MEJORA: Obtener el código de lotería completo dinámicamente
            $lotteryCode = $this->getLotteryCodeFromNumber($number);
            
            if (!$lotteryCode) {
                Log::warning("NumberObserver - No se pudo determinar el código de lotería para: {$number->city->code}");
                return;
            }

            Log::info("NumberObserver - Verificando completitud de lotería: {$lotteryCode} para ciudad: {$number->city->code}");

            // ✅ NUEVA LÓGICA: Verificar si la lotería tiene sus 20 números completos
            if (!LotteryCompletenessService::isLotteryComplete($lotteryCode, $number->date)) {
                Log::info("NumberObserver - Lotería {$lotteryCode} aún no está completa (no tiene 20 números). Esperando...");
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
                if ($this->isWinningPlayForLotteryComplete($play, $completeNumbers, $lotteryCode)) {
                    $prize = $this->calculatePrizeForLotteryComplete($play, $completeNumbers, $lotteryCode);
                    
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
                            'date' => $number->date,
                            'time' => $number->extract->time,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];

                        $result = ResultManager::createResultSafely($resultData);
                        if ($result) {
                            $resultsInserted++;
                            $totalPrize += $prize;
                            Log::info("NumberObserver - Resultado insertado: Ticket {$play->ticket} - Lotería {$lotteryCode} - Premio: {$prize}");
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
                'redoblona1toX' => BetCollectionRedoblonaModel::first(),
                'redoblona5to20' => BetCollection5To20Model::first(),
                'redoblona10to20' => BetCollection10To20Model::first(),
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
     */
    private function isPositionCorrect($playedPosition, $winningPosition)
    {
        // Reglas de quiniela:
        // - Posición 1 (Quiniela): Solo gana si sale en posición 1
        // - Posición 5: Gana si sale en posiciones 2-5
        // - Posición 10: Gana si sale en posiciones 6-10  
        // - Posición 20: Gana si sale en posiciones 11-20
        
        switch ($playedPosition) {
            case 1:
                // Quiniela: solo gana si sale en posición 1
                return $winningPosition == 1;
                
            case 5:
                // A los 5: gana si sale en posiciones 2-5
                return $winningPosition >= 2 && $winningPosition <= 5;
                
            case 10:
                // A los 10: gana si sale en posiciones 6-10
                return $winningPosition >= 6 && $winningPosition <= 10;
                
            case 20:
                // A los 20: gana si sale en posiciones 11-20
                return $winningPosition >= 11 && $winningPosition <= 20;
                
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
     */
    private function getMatchingPlaysForLottery($lotteryCode, $date)
    {
        return ApusModel::whereDate('created_at', $date)
            ->whereRaw('FIND_IN_SET(?, lottery)', [$lotteryCode])
            ->get();
    }

    /**
     * ✅ NUEVO: Verifica si una jugada es ganadora para una lotería completa
     * CORREGIDO: Ahora verifica correctamente las posiciones según reglas de quiniela
     */
    private function isWinningPlayForLotteryComplete($play, $completeNumbers, $lotteryCode)
    {
        // Verificar que la jugada contenga esta lotería específica
        $playLotteries = explode(',', $play->lottery);
        $playLotteries = array_map('trim', $playLotteries);
        
        if (!in_array($lotteryCode, $playLotteries)) {
            return false;
        }
        
        // ✅ CORREGIDO: Determinar rango permitido según posición apostada (REGLAS DE QUINIELA)
        $allowedIndexes = [];
        $playedPosition = (int)$play->position;
        
        switch ($playedPosition) {
            case 1:
                // Quiniela: solo posición 1
                $allowedIndexes = [1];
                break;
            case 5:
                // A los 5: posiciones 2-5
                $allowedIndexes = range(2, 5);
                break;
            case 10:
                // A los 10: posiciones 6-10
                $allowedIndexes = range(6, 10);
                break;
            case 20:
                // A los 20: posiciones 11-20
                $allowedIndexes = range(11, 20);
                break;
            default:
                // Para otras posiciones específicas, solo esa posición
                $allowedIndexes = [$playedPosition];
        }

        // Verificar si los números coinciden con alguno de los números ganadores completos EN POSICIÓN VÁLIDA
        foreach ($completeNumbers as $number) {
            if (!in_array((int)$number->index, $allowedIndexes)) {
                continue;
            }
            // ✅ Verificar tanto números como posición correcta
            if ($this->isWinningPlay($play, $number->value, $number->index)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * ✅ NUEVO: Calcula el premio para una lotería completa
     */
    private function calculatePrizeForLotteryComplete($play, $completeNumbers, $lotteryCode)
    {
        $mainPrize = 0;
        $redoblonaPrize = 0;

        // IMPORTANTE: Si hay redoblona, NO se paga premio principal, solo redoblona
        if (!empty($play->numberR) && !empty($play->positionR)) {
            // Solo calcular premio de redoblona (se paga TODO como redoblona)
            $redoblonaPrize = $this->redoblonaService->calculateRedoblonaPrize($play, $completeNumbers->first()->date, $lotteryCode);
        } else {
            // Solo calcular premio principal si NO hay redoblona
            $playedNumber = str_replace('*', '', $play->number);
            $playedDigits = strlen($playedNumber);

            // ✅ CORREGIDO: Determinar rango permitido según posición apostada (REGLAS DE QUINIELA)
            $allowedIndexes = [];
            $playedPosition = (int)$play->position;
            
            switch ($playedPosition) {
                case 1:
                    // Quiniela: solo posición 1
                    $allowedIndexes = [1];
                    break;
                case 5:
                    // A los 5: posiciones 2-5
                    $allowedIndexes = range(2, 5);
                    break;
                case 10:
                    // A los 10: posiciones 6-10
                    $allowedIndexes = range(6, 10);
                    break;
                case 20:
                    // A los 20: posiciones 11-20
                    $allowedIndexes = range(11, 20);
                    break;
                default:
                    // Para otras posiciones específicas, solo esa posición
                    $allowedIndexes = [$playedPosition];
            }

            foreach ($completeNumbers as $number) {
                if (!in_array((int)$number->index, $allowedIndexes)) {
                    continue;
                }
                // ✅ Verificar tanto números como posición correcta
                if ($this->isWinningPlay($play, $number->value, $number->index)) {
                    // Posición 1 usa tabla Quiniela según dígitos apostados
                    if ((int)$play->position === 1) {
                        $payoutTable = self::$payoutTables['quiniela'] ?? null;
                        if ($payoutTable) {
                            $mult = 0;
                            if ($playedDigits == 1) $mult = (float)($payoutTable->cobra_1_cifra ?? 0);
                            elseif ($playedDigits == 2) $mult = (float)($payoutTable->cobra_2_cifra ?? 0);
                            elseif ($playedDigits == 3) $mult = (float)($payoutTable->cobra_3_cifra ?? 0);
                            elseif ($playedDigits == 4) $mult = (float)($payoutTable->cobra_4_cifra ?? 0);
                            $mainPrize = $play->import * $mult;
                        }
                    } else {
                        // Para posiciones 2-20, pagar según la POSICIÓN REAL donde salió
                        if ($playedDigits == 1 || $playedDigits == 2) {
                            $payoutTable = self::$payoutTables['prizes'] ?? null;
                        } elseif ($playedDigits == 3) {
                            $payoutTable = self::$payoutTables['figureOne'] ?? null;
                        } else { // 4 dígitos
                            $payoutTable = self::$payoutTables['figureTwo'] ?? null;
                        }
                        if ($payoutTable) {
                            $mainPrize = $play->import * $this->getPositionMultiplier((int)$number->index, $payoutTable);
                        }
                    }
                    break;
                }
            }
        }

        return $mainPrize + $redoblonaPrize;
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
