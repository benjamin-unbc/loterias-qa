<?php

namespace App\Observers;

use App\Models\Number;
use App\Models\ApusModel;
use App\Models\Result;
use App\Models\QuinielaModel;
use App\Models\PrizesModel;
use App\Models\FigureOneModel;
use App\Models\FigureTwoModel;
use App\Models\BetCollectionRedoblonaModel;
use App\Models\BetCollection5To20Model;
use App\Models\BetCollection10To20Model;
use Illuminate\Support\Facades\Log;

class NumberObserver
{
    private static $payoutTables = null;

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

            Log::info("NumberObserver - Procesando lotería: {$lotteryCode} para ciudad: {$number->city->code}");

            // Buscar jugadas que puedan ser ganadoras con este número
            // Para posición 1 (quiniela), buscar solo en posición 1
            // Para otras posiciones, buscar en el rango apropiado
            $matchingPlays = $this->getMatchingPlaysForNumber($number, $lotteryCode);

            if ($matchingPlays->isEmpty()) {
                Log::info("NumberObserver - No hay jugadas para {$number->city->code} - Pos {$number->index}");
                return;
            }

            Log::info("NumberObserver - Procesando {$matchingPlays->count()} jugadas para {$number->city->code} - Pos {$number->index}");

            $resultsInserted = 0;
            $totalPrize = 0;

            foreach ($matchingPlays as $play) {
                $result = $this->calculatePlayResult($play, $number);
                
                if ($result && $result['totalPrize'] > 0) {
                    // Verificar si ya existe este resultado
                    $existingResult = Result::where('ticket', $play->ticket)
                        ->where('lottery', $play->lottery)
                        ->where('number', $play->number)
                        ->where('position', $play->position)
                        ->where('date', $number->date)
                        ->first();

                    if (!$existingResult) {
                        // Insertar resultado automáticamente
                        Result::create([
                            'user_id' => $play->user_id,
                            'ticket' => $play->ticket,
                            'lottery' => $play->lottery,
                            'number' => $play->number,
                            'position' => $play->position,
                            'numR' => $play->numberR,
                            'posR' => $play->positionR,
                            'XA' => 'X',
                            'import' => $play->import,
                            'aciert' => $result['totalPrize'],
                            'date' => $number->date,
                            'time' => $number->extract->time,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);

                        $resultsInserted++;
                        $totalPrize += $result['totalPrize'];

                        Log::info("NumberObserver - Resultado automático insertado: Ticket {$play->ticket} - Premio: {$result['totalPrize']}");
                    }
                }
            }

            if ($resultsInserted > 0) {
                Log::info("NumberObserver - Procesamiento completado: {$resultsInserted} resultados insertados - Total: $" . number_format($totalPrize, 2));
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

        // Verificar si la jugada principal es ganadora
        if ($this->isWinningPlay($play, $number->value)) {
            $mainPrize = $this->calculateMainPrize($play, $number->value);
        }

        // Calcular premio de redoblona si existe
        if (!empty($play->numberR) && !empty($play->positionR)) {
            $redoblonaPrize = $this->calculateRedoblonaPrize($play, $number->date, $play->lottery);
        }

        return [
            'mainPrize' => $mainPrize,
            'redoblonaPrize' => $redoblonaPrize,
            'totalPrize' => $mainPrize + $redoblonaPrize
        ];
    }

    /**
     * Verifica si una jugada es ganadora
     */
    private function isWinningPlay($play, $winningNumber)
    {
        $playNumber = str_replace('*', '', $play->number);
        $winningNumberStr = str_pad($winningNumber, 4, '0', STR_PAD_LEFT);
        
        $playLength = strlen($playNumber);
        $winningSuffix = substr($winningNumberStr, -$playLength);
        
        return $playNumber === $winningSuffix;
    }

    /**
     * Obtiene las jugadas que pueden ser ganadoras con un número específico
     */
    private function getMatchingPlaysForNumber(Number $number, $lotteryCode)
    {
        $winningPosition = $number->index;
        
        // Si el número salió en posición 1, solo buscar jugadas apostadas en posición 1
        if ($winningPosition == 1) {
            return ApusModel::whereDate('created_at', $number->date)
                ->where('position', 1)
                ->where('lottery', $lotteryCode)
                ->get();
        }
        
        // Para otras posiciones, buscar jugadas en el rango apropiado
        $searchRanges = [];
        
        // Si salió en posición 2-5, buscar jugadas apostadas en posiciones 1-5
        if ($winningPosition >= 2 && $winningPosition <= 5) {
            $searchRanges[] = [1, 5];
        }
        
        // Si salió en posición 6-10, buscar jugadas apostadas en posiciones 1-10
        if ($winningPosition >= 6 && $winningPosition <= 10) {
            $searchRanges[] = [1, 10];
        }
        
        // Si salió en posición 11-20, buscar jugadas apostadas en posiciones 1-20
        if ($winningPosition >= 11 && $winningPosition <= 20) {
            $searchRanges[] = [1, 20];
        }
        
        $matchingPlays = collect();
        
        foreach ($searchRanges as $range) {
            $plays = ApusModel::whereDate('created_at', $number->date)
                ->whereBetween('position', $range)
                ->where('lottery', $lotteryCode)
                ->get();
            
            $matchingPlays = $matchingPlays->merge($plays);
        }
        
        return $matchingPlays->unique('id');
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
     * Calcula el premio de redoblona
     */
    private function calculateRedoblonaPrize($play, $date, $lotteryCode)
    {
        // Buscar número ganador en la posición de redoblona
        $redoblonaNumber = Number::with(['city', 'extract'])
            ->whereHas('city', function($query) use ($lotteryCode) {
                $query->where('code', $lotteryCode);
            })
            ->where('index', $play->positionR)
            ->whereDate('date', $date)
            ->first();

        if (!$redoblonaNumber) {
            return 0;
        }

        // Verificar si la redoblona es ganadora
        $playNumberR = str_replace('*', '', $play->numberR);
        $winningNumberStr = str_pad($redoblonaNumber->value, 4, '0', STR_PAD_LEFT);
        $playLength = strlen($playNumberR);
        $winningSuffix = substr($winningNumberStr, -$playLength);

        if ($playNumberR !== $winningSuffix) {
            return 0;
        }

        // Calcular premio de redoblona
        $redoblonaTable = $this->getRedoblonaTable($play->positionR);
        if (!$redoblonaTable) {
            return 0;
        }

        return $play->import * (float) $redoblonaTable->multiplier;
    }

    /**
     * Obtiene la tabla de redoblona según la posición
     */
    private function getRedoblonaTable($position)
    {
        if ($position >= 1 && $position <= 4) {
            return self::$payoutTables['redoblona1toX'];
        } elseif ($position >= 5 && $position <= 20) {
            return self::$payoutTables['redoblona5to20'];
        }
        
        return null;
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
}
