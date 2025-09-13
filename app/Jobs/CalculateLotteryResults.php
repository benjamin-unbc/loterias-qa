<?php

namespace App\Jobs;

use App\Models\ApusModel;
use App\Models\BetCollection10To20Model;
use App\Models\BetCollection5To20Model;
use App\Models\BetCollectionRedoblonaModel;
use App\Models\Number as WinningNumber;
use App\Livewire\Admin\PlaysManager;
use App\Models\QuinielaModel;
use App\Models\Result;
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

        // **LA SOLUCIÓN CLAVE: Eliminar resultados antiguos para evitar duplicados**
        // Esto asegura que no se acumulen registros repetidos si el job se ejecuta varias veces.
        Result::where('date', $this->date)->delete();
        Log::info("CalculateLotteryResults Job: Resultados existentes para la fecha {$this->date} eliminados.");

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

        // 4. Obtener la tabla de pagos de quiniela
        $quinielaPayouts = QuinielaModel::first();
        $redoblona1toX = BetCollectionRedoblonaModel::first();
        $redoblona5to20 = BetCollection5To20Model::first();
        $redoblona10to20 = BetCollection10To20Model::first();

        if (!$quinielaPayouts || !$redoblona1toX || !$redoblona5to20 || !$redoblona10to20) {
            Log::error("CalculateLotteryResults Job: Faltan una o más tablas de pago. Finalizando job.");
            return;
        }


        $winningPlays = [];

        // 5. Iterar sobre cada jugada para ver si es ganadora
        foreach ($plays as $play) {
       if (!isset($codesTicket[$play->lottery])) continue;

            $systemCode = $codesTicket[$play->lottery];
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
                $winningNumberKey = $systemCode . '_' . $play->position;
                if (isset($winningNumbers[$winningNumberKey])) {
                    $winningNumberData = $winningNumbers[$winningNumberKey];
                    $playedNumber = str_replace('*', '', $play->number);
                    $winnerValue = $winningNumberData->value;
                    $playedDigits = strlen($playedNumber);

                    if ($playedDigits > 0 && $playedDigits <= 4 && substr($winnerValue, -$playedDigits) == $playedNumber) {
                        $prizeMultiplier = $quinielaPayouts->{"cobra_{$playedDigits}_cifra"} ?? 0;
                        $aciertoValue = (float) $play->import * (float) $prizeMultiplier;
                    }
                }
            }

            if ($aciertoValue > 0 && $winningNumberData) {
                $winningPlays[] = [
                    'ticket' => $play->ticket,
                    'lottery' => $play->lottery,
                    'number' => $play->number,
                    'position' => $play->position,
                    'import' => $play->import,
                    'aciert' => $aciertoValue,
                    'date' => $this->date,
                    'time' => $winningNumberData->extract->time,
                    'numR' => $play->numberR,
                    'posR' => $play->positionR,
                    'XA' => 'X', // Este valor parece ser estático
                    'user_id' => $play->user_id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        // 5. Insertar todos los aciertos en la base de datos de una sola vez.
        if (!empty($winningPlays)) {
            Result::insert($winningPlays);
            Log::info("🎉 Job CalculateLotteryResults: Se insertaron " . count($winningPlays) . " aciertos para la fecha {$this->date}.");
        } else {
            Log::info("Job CalculateLotteryResults: No se encontraron nuevos aciertos para la fecha {$this->date}.");
        }

        Log::info("Job CalculateLotteryResults finalizado para la fecha {$this->date}.");
    }
}