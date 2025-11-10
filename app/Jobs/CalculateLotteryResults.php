<?php

namespace App\Jobs;

use App\Models\ApusModel;
use App\Models\BetCollection10To20Model;
use App\Models\BetCollection5To20Model;
use App\Models\BetCollectionRedoblonaModel;
use App\Models\Number as WinningNumber;
use App\Services\RedoblonaService;
use App\Services\LotteryCompletenessService;
use App\Livewire\Admin\PlaysManager;
use App\Models\QuinielaModel;
use App\Models\PrizesModel;
use App\Models\FigureOneModel;
use App\Models\FigureTwoModel;
use App\Models\Result;
use App\Services\ResultManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CalculateLotteryResults implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $date;

    /**
     * Create a new job instance.
     */
    public function __construct(string $date)
    {
        $this->date = $date;
    }

    /**
     * Execute the job.
     * ✅ MODIFICADO: Solo procesa loterías que tengan sus 20 números completos
     */
    public function handle(): void
    {
        Log::info("CalculateLotteryResults Job: Iniciando para la fecha {$this->date}.");

        // ✅ NUEVA LÓGICA: Solo procesar loterías que tengan sus 20 números completos
        Log::info("CalculateLotteryResults Job: Verificando loterías completas para la fecha {$this->date}.");

        // Obtener solo las loterías que tengan sus 20 números completos
        $completeLotteries = LotteryCompletenessService::getCompleteLotteries($this->date);

        if (empty($completeLotteries)) {
            Log::info("CalculateLotteryResults Job: No hay loterías completas para procesar en la fecha {$this->date}. Finalizando job.");
            return;
        }

        Log::info("CalculateLotteryResults Job: Loterías completas encontradas: " . implode(', ', $completeLotteries));

        // Obtener todas las tablas de pagos
        $quinielaPayouts = QuinielaModel::first();
        $prizesPayouts = PrizesModel::first(); // Tabla para apuestas de 2 dígitos (**XX)
        $figureOnePayouts = FigureOneModel::first(); // Tabla para apuestas de 3 dígitos (*XXX)
        $figureTwoPayouts = FigureTwoModel::first(); // Tabla para apuestas de 4 dígitos (XXXX)
        $redoblona1toX = BetCollectionRedoblonaModel::first();
        $redoblona5to20 = BetCollection5To20Model::first();
        $redoblona10to20 = BetCollection10To20Model::first();

        if (!$quinielaPayouts || !$prizesPayouts || !$figureOnePayouts || !$figureTwoPayouts || !$redoblona1toX || !$redoblona5to20 || !$redoblona10to20) {
            Log::error("CalculateLotteryResults Job: Faltan una o más tablas de pago. Finalizando job.");
            return;
        }

        $winningPlays = [];
        $totalResultsInserted = 0;

        // Procesar cada lotería completa
        foreach ($completeLotteries as $lotteryCode) {
            $result = $this->processCompleteLottery($lotteryCode, $quinielaPayouts, $prizesPayouts, $figureOnePayouts, $figureTwoPayouts, $redoblona1toX, $redoblona5to20, $redoblona10to20);
            $winningPlays = array_merge($winningPlays, $result['winningPlays']);
            $totalResultsInserted += $result['resultsInserted'];
        }

        // Insertar todos los aciertos en la base de datos de forma segura
        if (!empty($winningPlays)) {
            $createdCount = ResultManager::createMultipleResultsSafely($winningPlays);
            Log::info("🎉 Job CalculateLotteryResults: Se procesaron " . count($winningPlays) . " aciertos, creados {$createdCount} para la fecha {$this->date}.");
        } else {
            Log::info("Job CalculateLotteryResults: No se encontraron nuevos aciertos para la fecha {$this->date}.");
        }

        Log::info("Job CalculateLotteryResults finalizado para la fecha {$this->date}.");
    }

    /**
     * Calcula el premio basado en la posición donde sale el número ganador
     * 
     * @param int $position Posición donde salió el número ganador
     * @param object $payoutTable Tabla de pagos (PrizesModel, FigureOneModel, o FigureTwoModel)
     * @return float Multiplicador del premio
     */
    private function calculatePositionBasedPrize(int $position, $payoutTable): float
    {
        // Determinar el rango de posición y devolver el multiplicador correspondiente
        if ($position <= 5) {
            return (float) ($payoutTable->cobra_5 ?? 0);
        } elseif ($position <= 10) {
            return (float) ($payoutTable->cobra_10 ?? 0);
        } elseif ($position <= 20) {
            return (float) ($payoutTable->cobra_20 ?? 0);
        }
        
        return 0.0; // No hay premio si sale después de la posición 20
    }

    /**
     * Determina el rango de posiciones donde buscar el número ganador
     * basado en la posición apostada
     * 
     * @param int $apostadaPosition Posición apostada
     * @return array Array de posiciones donde buscar
     */
    private function getSearchRangeForPosition(int $apostadaPosition): array
    {
        // Quiniela: solo posición 1
        if ($apostadaPosition === 1) {
            return [1];
        }
        // Tabla 2-5: posiciones 2-5
        elseif ($apostadaPosition >= 2 && $apostadaPosition <= 5) {
            return range(2, 5);
        }
        // Tabla 6-10: posiciones 6-10
        elseif ($apostadaPosition >= 6 && $apostadaPosition <= 10) {
            return range(6, 10);
        }
        // Tabla 11-20: posiciones 11-20
        elseif ($apostadaPosition >= 11 && $apostadaPosition <= 20) {
            return range(11, 20);
        }
        
        // Si apostaste a una posición fuera de rango, no hay premio
        return [];
    }

    /**
     * ✅ NUEVO: Obtiene el rango de posiciones según la posición apostada
     * Posición 1 → solo posición 1
     * Posición 5 → rango 2-5
     * Posición 10 → rango 6-10
     * Posición 20 → rango 11-20
     * 
     * @param int $position Posición apostada
     * @return array Array con 'min' y 'max' del rango
     */
    private function getPositionRange(int $position): array
    {
        if ($position == 1) {
            return ['min' => 1, 'max' => 1];
        } elseif ($position >= 2 && $position <= 5) {
            return ['min' => 2, 'max' => 5];
        } elseif ($position >= 6 && $position <= 10) {
            return ['min' => 6, 'max' => 10];
        } elseif ($position >= 11 && $position <= 20) {
            return ['min' => 11, 'max' => 20];
        }
        
        return ['min' => $position, 'max' => $position];
    }

    /**
     * ✅ NUEVO: Procesa una lotería completa (con sus 20 números)
     */
    private function processCompleteLottery($lotteryCode, $quinielaPayouts, $prizesPayouts, $figureOnePayouts, $figureTwoPayouts, $redoblona1toX, $redoblona5to20, $redoblona10to20)
    {
        try {
            Log::info("CalculateLotteryResults Job - Procesando lotería completa: {$lotteryCode} para {$this->date}");

            // Obtener todos los números ganadores de esta lotería completa
            $completeNumbers = LotteryCompletenessService::getCompleteLotteryNumbersCollection($lotteryCode, $this->date);
            
            if (!$completeNumbers) {
                Log::warning("CalculateLotteryResults Job - No se pudieron obtener los números completos para {$lotteryCode}");
                return ['winningPlays' => [], 'resultsInserted' => 0];
            }

            // Obtener todas las jugadas (apus) del día para esta lotería
            $plays = ApusModel::whereDate('created_at', $this->date)
                ->where('lottery', 'LIKE', "%{$lotteryCode}%")
                ->get();

            if ($plays->isEmpty()) {
                Log::info("CalculateLotteryResults Job - No hay jugadas para la lotería completa {$lotteryCode}");
                return ['winningPlays' => [], 'resultsInserted' => 0];
            }

            Log::info("CalculateLotteryResults Job - Procesando lotería completa: {$lotteryCode} - {$plays->count()} jugadas");

            $winningPlays = [];
            $resultsInserted = 0;

            // Procesar cada jugada para ver si es ganadora
            foreach ($plays as $play) {
                // Verificar que la jugada contenga esta lotería específica
                $playLotteries = explode(',', $play->lottery);
                $playLotteries = array_map('trim', $playLotteries);
                
                if (!in_array($lotteryCode, $playLotteries)) {
                    continue;
                }

                $aciertoValue = 0;
                $winningNumberData = null;

                // Lógica para Redoblona
                if (!empty($play->numberR) && !empty($play->positionR)) {
                    // ✅ CORREGIDO: Buscar en rangos según posición apostada
                    // Posición 1 → solo posición 1
                    // Posición 5 → rango 2-5
                    // Posición 10 → rango 6-10
                    // Posición 20 → rango 11-20
                    
                    // Obtener rangos para el número principal y la redoblona
                    $mainRange = $this->getPositionRange((int)$play->position);
                    $redoblonaRange = $this->getPositionRange((int)$play->positionR);
                    
                    // Limpiar y formatear números (redoblonas son siempre 2 cifras)
                    $mainNumber = str_pad(str_replace('*', '', $play->number), 2, '0', STR_PAD_LEFT);
                    $redoblonaNumber = str_pad(str_replace('*', '', $play->numberR), 2, '0', STR_PAD_LEFT);
                    
                    // Buscar el número principal en su rango
                    $mainWinner = null;
                    foreach ($completeNumbers as $number) {
                        if ($number->index >= $mainRange['min'] && $number->index <= $mainRange['max']) {
                            $numberSuffix = substr(str_pad((string)$number->value, 4, '0', STR_PAD_LEFT), -2);
                            if ($numberSuffix == $mainNumber) {
                                $mainWinner = $number;
                                break;
                            }
                        }
                    }
                    
                    // Buscar la redoblona en su rango
                    $redoblonaWinner = null;
                    foreach ($completeNumbers as $number) {
                        if ($number->index >= $redoblonaRange['min'] && $number->index <= $redoblonaRange['max']) {
                            $numberSuffix = substr(str_pad((string)$number->value, 4, '0', STR_PAD_LEFT), -2);
                            if ($numberSuffix == $redoblonaNumber) {
                                $redoblonaWinner = $number;
                                break;
                            }
                        }
                    }
                    
                    // Si ambos números son ganadores en sus respectivos rangos, calcular premio
                    if ($mainWinner && $redoblonaWinner) {
                        $prizeMultiplier = 0;
                        $mainPos = $mainWinner->index;
                        $redoblonaPos = $redoblonaWinner->index;
                        
                        // Determinar multiplicador según las posiciones donde realmente salieron
                        if ($mainPos == 1) {
                            if ($redoblonaPos >= 2 && $redoblonaPos <= 5) {
                                $prizeMultiplier = $redoblona1toX->payout_1_to_5 ?? 0;
                            } elseif ($redoblonaPos >= 6 && $redoblonaPos <= 10) {
                                $prizeMultiplier = $redoblona1toX->payout_1_to_10 ?? 0;
                            } elseif ($redoblonaPos >= 11 && $redoblonaPos <= 20) {
                                $prizeMultiplier = $redoblona1toX->payout_1_to_20 ?? 0;
                            }
                        } elseif ($mainPos >= 2 && $mainPos <= 5) {
                            if ($redoblonaPos >= 2 && $redoblonaPos <= 5) {
                                $prizeMultiplier = $redoblona5to20->payout_5_to_5 ?? 0;
                            } elseif ($redoblonaPos >= 6 && $redoblonaPos <= 10) {
                                $prizeMultiplier = $redoblona5to20->payout_5_to_10 ?? 0;
                            } elseif ($redoblonaPos >= 11 && $redoblonaPos <= 20) {
                                $prizeMultiplier = $redoblona5to20->payout_5_to_20 ?? 0;
                            }
                        } elseif ($mainPos >= 6 && $mainPos <= 10) {
                            if ($redoblonaPos >= 6 && $redoblonaPos <= 10) {
                                $prizeMultiplier = $redoblona10to20->payout_10_to_10 ?? 0;
                            } elseif ($redoblonaPos >= 11 && $redoblonaPos <= 20) {
                                $prizeMultiplier = $redoblona10to20->payout_10_to_20 ?? 0;
                            }
                        } elseif ($mainPos >= 11 && $mainPos <= 20) {
                            if ($redoblonaPos >= 11 && $redoblonaPos <= 20) {
                                $prizeMultiplier = $redoblona10to20->payout_20_to_20 ?? 0;
                            }
                        }
                        
                        if ($prizeMultiplier > 0) {
                            $aciertoValue = (float) $play->import * (float) $prizeMultiplier;
                            $winningNumberData = $mainWinner; // Usar el número principal como referencia para el tiempo
                            Log::info("Redoblona ganadora: Principal {$play->number} en posición {$mainPos}, Redoblona {$play->numberR} en posición {$redoblonaPos}, Multiplicador: {$prizeMultiplier}");
                        }
                    }
                }
                // Lógica para Quiniela Simple
                else {
                    $playedNumber = str_replace('*', '', $play->number);
                    $playedDigits = strlen($playedNumber);
                    $prizeMultiplier = 0;
                    $actualWinningPosition = null;

                    // REGLA PRINCIPAL: Si apostaste a posición 1 (a la cabeza), SIEMPRE usar tabla Quiniela
                    if ($play->position == 1) {
                        // Para posición 1, buscar exactamente en esa posición
                        $winningNumber = $completeNumbers->where('index', $play->position)->first();
                        if ($winningNumber) {
                            $winnerValue = $winningNumber->value;
                            
                            // Verificar si la apuesta coincide con el número ganador
                            if ($playedDigits > 0 && $playedDigits <= 4 && substr($winnerValue, -$playedDigits) == $playedNumber) {
                                $prizeMultiplier = $quinielaPayouts->{"cobra_{$playedDigits}_cifra"} ?? 0;
                                $actualWinningPosition = $play->position;
                                $winningNumberData = $winningNumber;
                                Log::info("Acierto POSICIÓN 1 (A LA CABEZA): Apuesta {$play->number} ({$playedDigits} dígitos) en posición {$play->position}, número ganador {$winnerValue}, multiplicador Quiniela: {$prizeMultiplier}");
                            }
                        }
                    } else {
                        // Para otras posiciones (2-20): Buscar en el rango de la tabla apostada
                        $searchRange = $this->getSearchRangeForPosition($play->position);
                        
                        // Buscar el número en todas las posiciones del rango
                        foreach ($searchRange as $position) {
                            $winningNumber = $completeNumbers->where('index', $position)->first();
                            if ($winningNumber) {
                                $winnerValue = $winningNumber->value;
                                
                                // Verificar si la apuesta coincide con el número ganador
                                if ($playedDigits > 0 && $playedDigits <= 4 && substr($winnerValue, -$playedDigits) == $playedNumber) {
                                    $actualWinningPosition = $position;
                                    $winningNumberData = $winningNumber;
                                    
                                    // Calcular premio basado en la posición donde realmente salió
                                    if ($playedDigits == 1 || $playedDigits == 2) {
                                        // Apuesta de 1-2 dígitos (***X, **XX) - Tabla Prizes (A los 5, 10, 20)
                                        $prizeMultiplier = $this->calculatePositionBasedPrize($position, $prizesPayouts);
                                        Log::info("Acierto {$playedDigits} dígito(s): Apuesta {$play->number} apostada en posición {$play->position}, salió en posición {$position}, número ganador {$winnerValue}, multiplicador Prizes: {$prizeMultiplier}");
                                    } elseif ($playedDigits == 3) {
                                        // Apuesta de 3 dígitos (*XXX) - Tabla FigureOne (Terminación 3 cifras)
                                        $prizeMultiplier = $this->calculatePositionBasedPrize($position, $figureOnePayouts);
                                        Log::info("Acierto 3 dígitos: Apuesta {$play->number} apostada en posición {$play->position}, salió en posición {$position}, número ganador {$winnerValue}, multiplicador FigureOne: {$prizeMultiplier}");
                                    } elseif ($playedDigits == 4) {
                                        // Apuesta de 4 dígitos (XXXX) - Tabla FigureTwo (Terminación 4 cifras)
                                        $prizeMultiplier = $this->calculatePositionBasedPrize($position, $figureTwoPayouts);
                                        Log::info("Acierto 4 dígitos: Apuesta {$play->number} apostada en posición {$play->position}, salió en posición {$position}, número ganador {$winnerValue}, multiplicador FigureTwo: {$prizeMultiplier}");
                                    }
                                    break; // Salir del bucle una vez encontrado el acierto
                                }
                            }
                        }
                    }
                    
                    if ($prizeMultiplier > 0 && $winningNumberData) {
                        $aciertoValue = (float) $play->import * (float) $prizeMultiplier;
                        Log::info("Cálculo final: Importe {$play->import} x Multiplicador {$prizeMultiplier} = Acierto {$aciertoValue} (Apostado en pos {$play->position}, salió en pos {$actualWinningPosition})");
                    }
                }

                if ($aciertoValue > 0 && $winningNumberData) {
                    $winningPlays[] = [
                        'user_id' => $play->user_id,
                        'ticket' => $play->ticket,
                        'lottery' => $lotteryCode, // Usar solo la lotería específica donde salió el número
                        'number' => $play->number,
                        'position' => $play->position,
                        'numR' => $play->numberR,
                        'posR' => $play->positionR,
                        'XA' => 'X', // Este valor parece ser estático
                        'import' => $play->import,
                        'aciert' => $aciertoValue,
                        'date' => $this->date,
                        'time' => $winningNumberData->extract->time,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                    $resultsInserted++;
                }
            }

            Log::info("CalculateLotteryResults Job - Lotería {$lotteryCode} completada: {$resultsInserted} resultados encontrados");

            return ['winningPlays' => $winningPlays, 'resultsInserted' => $resultsInserted];

        } catch (\Exception $e) {
            Log::error("CalculateLotteryResults Job - Error procesando lotería completa {$lotteryCode}: " . $e->getMessage());
            return ['winningPlays' => [], 'resultsInserted' => 0];
        }
    }
}