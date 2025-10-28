<?php

namespace App\Jobs;

use App\Models\ApusModel;
use App\Models\BetCollection10To20Model;
use App\Models\BetCollection5To20Model;
use App\Models\BetCollectionRedoblonaModel;
use App\Models\Number as WinningNumber;
use App\Services\RedoblonaService;
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
     */
    public function handle(): void
    {
        Log::info("CalculateLotteryResults Job: Iniciando para la fecha {$this->date}.");

        // **SOLUCIÓN MEJORADA: Verificar duplicados individualmente en lugar de eliminar todo**
        // Esto preserva los resultados existentes y solo inserta los nuevos
        Log::info("CalculateLotteryResults Job: Verificando duplicados individualmente para la fecha {$this->date}.");

        // 1. Obtener todos los números ganadores del día y organizarlos para una búsqueda rápida.
        $winningNumbers = WinningNumber::with('city', 'extract')
            ->where('date', $this->date)
            ->get()
            ->keyBy(function ($item) {
                if (is_null($item->city) || is_null($item->extract)) {
                    return null; // Evitar error si hay datos inconsistentes
                }
                $time = str_replace(':', '', $item->extract->time);
                return $item->city->code . $time . '_' . $item->index;
            })->filter(); // filter() para remover nulos

        if ($winningNumbers->isEmpty()) {
            Log::info("CalculateLotteryResults Job: No se encontraron números ganadores para la fecha {$this->date}. Finalizando job.");
            return;
        }

        // 2. Obtener todas las jugadas (apus) del día
        $plays = ApusModel::whereDate('created_at', $this->date)->get();

        if ($plays->isEmpty()) {
            Log::info("CalculateLotteryResults Job: No se encontraron jugadas para la fecha {$this->date}. Finalizando job.");
            return;
        }

        // 3. Mapeo de códigos de UI a códigos de sistema
        $codesTicket = (new PlaysManager)->codesTicket;

        // 4. Obtener todas las tablas de pagos
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

        // 5. Iterar sobre cada jugada para ver si es ganadora
        foreach ($plays as $play) {
            // ✅ Procesar cada lotería individualmente (separadas por comas)
            $lotteryCodes = explode(',', $play->lottery);
            
            foreach ($lotteryCodes as $lotteryCode) {
                $lotteryCode = trim($lotteryCode); // Limpiar espacios
                if (!isset($codesTicket[$lotteryCode])) continue;

                $systemCode = $codesTicket[$lotteryCode];
            $aciertoValue = 0;
            $winningNumberData = null;

            // Lógica para Redoblona
            if (!empty($play->numberR) && !empty($play->positionR)) {
                $pos1 = min((int)$play->position, (int)$play->positionR);
                $pos2 = max((int)$play->position, (int)$play->positionR);
                
                $num1 = ($play->position < $play->positionR) ? $play->number : $play->numberR;
                $num2 = ($play->position < $play->positionR) ? $play->numberR : $play->number;

                // Redoblonas son siempre 2 cifras
                $num1 = str_pad(str_replace('*', '', $num1), 2, '0', STR_PAD_LEFT);
                $num2 = str_pad(str_replace('*', '', $num2), 2, '0', STR_PAD_LEFT);

                $key1 = $systemCode . '_' . $pos1;
                $key2 = $systemCode . '_' . $pos2;

                if (isset($winningNumbers[$key1], $winningNumbers[$key2])) {
                    $winner1 = $winningNumbers[$key1];
                    $winner2 = $winningNumbers[$key2];

                    if (substr($winner1->value, -2) == $num1 && substr($winner2->value, -2) == $num2) {
                        $prizeMultiplier = 0;
                        if ($pos1 <= 1) {
                            if ($pos2 <= 5) $prizeMultiplier = $redoblona1toX->payout_1_to_5 ?? 0;
                            elseif ($pos2 <= 10) $prizeMultiplier = $redoblona1toX->payout_1_to_10 ?? 0;
                            elseif ($pos2 <= 20) $prizeMultiplier = $redoblona1toX->payout_1_to_20 ?? 0;
                        } elseif ($pos1 <= 5) {
                            if ($pos2 <= 5) $prizeMultiplier = $redoblona5to20->payout_5_to_5 ?? 0;
                            elseif ($pos2 <= 10) $prizeMultiplier = $redoblona5to20->payout_5_to_10 ?? 0;
                            elseif ($pos2 <= 20) $prizeMultiplier = $redoblona5to20->payout_5_to_20 ?? 0;
                        } elseif ($pos1 <= 10) {
                            if ($pos2 <= 10) $prizeMultiplier = $redoblona10to20->payout_10_to_10 ?? 0;
                            elseif ($pos2 <= 20) $prizeMultiplier = $redoblona10to20->payout_10_to_20 ?? 0;
                        } elseif ($pos1 <= 20) {
                            if ($pos2 <= 20) $prizeMultiplier = $redoblona10to20->payout_20_to_20 ?? 0;
                        }
                        
                        $aciertoValue = (float) $play->import * (float) $prizeMultiplier;
                        $winningNumberData = $winner1; // Usar el primer ganador como referencia para el tiempo
                    }
                }
            }
            // Lógica para Quiniela Simple
            else {
                $playedNumber = str_replace('*', '', $play->number);
                $playedDigits = strlen($playedNumber);
                $prizeMultiplier = 0;
                $winningNumberData = null;
                $actualWinningPosition = null;

                // REGLA PRINCIPAL: Si apostaste a posición 1 (a la cabeza), SIEMPRE usar tabla Quiniela
                if ($play->position == 1) {
                    // Para posición 1, buscar exactamente en esa posición (comportamiento actual)
                    $winningNumberKey = $systemCode . '_' . $play->position;
                    if (isset($winningNumbers[$winningNumberKey])) {
                        $winningNumberData = $winningNumbers[$winningNumberKey];
                        $winnerValue = $winningNumberData->value;
                        
                        // Verificar si la apuesta coincide con el número ganador
                        if ($playedDigits > 0 && $playedDigits <= 4 && substr($winnerValue, -$playedDigits) == $playedNumber) {
                            $prizeMultiplier = $quinielaPayouts->{"cobra_{$playedDigits}_cifra"} ?? 0;
                            $actualWinningPosition = $play->position;
                            Log::info("Acierto POSICIÓN 1 (A LA CABEZA): Apuesta {$play->number} ({$playedDigits} dígitos) en posición {$play->position}, número ganador {$winnerValue}, multiplicador Quiniela: {$prizeMultiplier}");
                        }
                    }
                } else {
                    // Para otras posiciones (2-20): Buscar en el rango de la tabla apostada
                    $searchRange = $this->getSearchRangeForPosition($play->position);
                    
                    // Buscar el número en todas las posiciones del rango
                    foreach ($searchRange as $position) {
                        $winningNumberKey = $systemCode . '_' . $position;
                        if (isset($winningNumbers[$winningNumberKey])) {
                            $winningNumberData = $winningNumbers[$winningNumberKey];
                            $winnerValue = $winningNumberData->value;
                            
                            // Verificar si la apuesta coincide con el número ganador
                            if ($playedDigits > 0 && $playedDigits <= 4 && substr($winnerValue, -$playedDigits) == $playedNumber) {
                                $actualWinningPosition = $position;
                                
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
                        'lottery' => $lotteryCode, // ✅ Usar solo la lotería específica donde salió el número
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
                }
            } // ✅ Cerrar el bucle foreach de loterías individuales
        }

        // 5. Insertar todos los aciertos en la base de datos de forma segura.
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
        // Si apostaste a posición 5, buscar en posiciones 1-5
        if ($apostadaPosition <= 5) {
            return range(1, 5);
        }
        // Si apostaste a posición 10, buscar en posiciones 1-10
        elseif ($apostadaPosition <= 10) {
            return range(1, 10);
        }
        // Si apostaste a posición 20, buscar en posiciones 1-20
        elseif ($apostadaPosition <= 20) {
            return range(1, 20);
        }
        
        // Si apostaste a una posición mayor a 20, no hay premio
        return [];
    }
}