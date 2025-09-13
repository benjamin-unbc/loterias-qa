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
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class LotteryResultProcessor
{
    // This codes array is used in FetchAndDisplayPlaysSent and needs to be consistent
    public $codes = [
        'AB' => 'NAC1015', 'CH1' => 'CHA1015', 'QW' => 'PRO1015', 'M10' => 'MZA1015', '!' => 'CTE1015',
        'ER' => 'SFE1015', 'SD' => 'COR1015', 'RT' => 'RIO1015', 'Q' => 'NAC1200', 'CH2' => 'CHA1200',
        'W' => 'PRO1200', 'M1' => 'MZA1200', 'M' => 'CTE1200', 'R' => 'SFE1200', 'T' => 'COR1200',
        'K' => 'RIO1200', 'A' => 'NAC1500', 'CH3' => 'CHA1500', 'E' => 'PRO1500', 'M2' => 'MZA1500', // Corrected 'Ct3' to 'CTE1500'
        'Ct3' => 'CTE1500', 'D' => 'SFE1500', 'L' => 'COR1500', 'J' => 'RIO1500', 'S' => 'ORO1500',
        'F' => 'NAC1800', 'CH4' => 'CHA1800', 'B' => 'PRO1800', 'M3' => 'MZA1800', 'Z' => 'CTE1800',
        'V' => 'SFE1800', 'H' => 'COR1800', 'U' => 'RIO1800', 'N' => 'NAC2100', 'CH5' => 'CHA2100',
        'P' => 'PRO2100', 'M4' => 'MZA2100', 'G' => 'CTE2100', 'I' => 'SFE2100', 'C' => 'COR2100',
        'Y' => 'RIO2100', 'O' => 'ORO2100'
    ];

    public function process(string $dateToCalculate): void
    {
        Log::info("LotteryResultProcessor - Iniciando procesamiento para la fecha: " . $dateToCalculate);

        // Delete existing results for the given date to ensure idempotency
        Result::whereDate('date', $dateToCalculate)->delete();
        Log::info("LotteryResultProcessor - Resultados existentes para {$dateToCalculate} eliminados.");

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

        $winningNumbers = Number::whereDate('date', Carbon::parse($dateToCalculate))
            ->with('city')
            ->get();

        if ($winningNumbers->isEmpty()) {
            Log::warning("LotteryResultProcessor - No hay números ganadores para la fecha " . $dateToCalculate);
            return;
        }

        // Group winning numbers by system lottery code and position
        $groupedWinningNumbers = [];
        foreach ($winningNumbers as $wn) {
            if ($wn->city) {
                // This mapping is crucial. It assumes city->code is the system code.
                // Example: 'NAC1015', 'SFE1015', etc.
                $lotteryKey = $wn->city->code;
                if (!isset($groupedWinningNumbers[$lotteryKey])) {
                    $groupedWinningNumbers[$lotteryKey] = [];
                }
                $groupedWinningNumbers[$lotteryKey][$wn->index] = $wn->value;
            }
        }
        Log::info("LotteryResultProcessor - Números ganadores agrupados:", $groupedWinningNumbers);

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

                // --- Main Play (Quiniela, Prizes, Figures) ---
                if (!empty($playedNumberClean) && $apu->position !== null && isset($winningNumbersForLottery[$apu->position])) {
                    $winningNumberAtPosition = $winningNumbersForLottery[$apu->position];
                    $numDigitsPlayed = strlen($playedNumberClean);
                    $winningNumberLastDigits = substr($winningNumberAtPosition, -$numDigitsPlayed);

                    if ($playedNumberClean === $winningNumberLastDigits) {
                        $ticketType = $this->getTicketType($apu->number); // This function needs to be part of this class or a helper

                        $multiplier = 0;
                        if ($ticketType === 'quiniela') {
                            if ($numDigitsPlayed == 4) $multiplier = $quiniela->cobra_4_cifra;
                            elseif ($numDigitsPlayed == 3) $multiplier = $quiniela->cobra_3_cifra;
                            elseif ($numDigitsPlayed == 2) $multiplier = $quiniela->cobra_2_cifra;
                            elseif ($numDigitsPlayed == 1) $multiplier = $quiniela->cobra_1_cifra;
                        } elseif ($ticketType === 'prizes') {
                            if ($apu->position >= 1 && $apu->position <= 5) $multiplier = $prizes->cobra_5;
                            elseif ($apu->position >= 6 && $apu->position <= 10) $multiplier = $prizes->cobra_10;
                            elseif ($apu->position >= 11 && $apu->position <= 20) $multiplier = $prizes->cobra_20;
                        } elseif ($ticketType === 'figureOne') {
                            if ($apu->position >= 1 && $apu->position <= 5) $multiplier = $figureOne->cobra_5;
                            elseif ($apu->position >= 6 && $apu->position <= 10) $multiplier = $figureOne->cobra_10;
                            elseif ($apu->position >= 11 && $apu->position <= 20) $multiplier = $figureOne->cobra_20;
                        } elseif ($ticketType === 'figureTwo') {
                            if ($apu->position >= 1 && $apu->position <= 5) $multiplier = $figureTwo->cobra_5;
                            elseif ($apu->position >= 6 && $apu->position <= 10) $multiplier = $figureTwo->cobra_10;
                            elseif ($apu->position >= 11 && $apu->position <= 20) $multiplier = $figureTwo->cobra_20;
                        }
                        $aciertValue = (float)$apu->import * (float)$multiplier;
                        Log::info("LotteryResultProcessor - Acierto principal calculado: {$aciertValue} para APU ID: {$apu->id}");
                    }
                }

                // --- Redoblona (if applicable) ---
                if (!empty($apu->numberR) && $apu->positionR !== null && isset($winningNumbersForLottery[$apu->positionR])) {
                    $playedNumberRClean = $this->removeAsterisks($apu->numberR);
                    $winningNumberAtPositionR = $winningNumbersForLottery[$apu->positionR];
                    $numDigitsPlayedR = strlen($playedNumberRClean);
                    $winningNumberLastDigitsR = substr($winningNumberAtPositionR, -$numDigitsPlayedR);

                    if ($playedNumberRClean === $winningNumberLastDigitsR) {
                        $multiplierR = 0;
                        if ($apu->position == 1) {
                            if ($apu->positionR >= 1 && $apu->positionR <= 5) $multiplierR = $betCollectionRedoblona->payout_1_to_5;
                            elseif ($apu->positionR >= 6 && $apu->positionR <= 10) $multiplierR = $betCollectionRedoblona->payout_1_to_10;
                            elseif ($apu->positionR >= 11 && $apu->positionR <= 20) $multiplierR = $betCollectionRedoblona->payout_1_to_20;
                        } elseif ($apu->position >= 2 && $apu->position <= 5) {
                            if ($apu->positionR >= 1 && $apu->positionR <= 5) $multiplierR = $betCollection5To20->payout_5_to_5;
                            elseif ($apu->positionR >= 6 && $apu->positionR <= 10) $multiplierR = $betCollection5To20->payout_5_to_10;
                            elseif ($apu->positionR >= 11 && $apu->positionR <= 20) $multiplierR = $betCollection5To20->payout_5_to_20;
                        } elseif ($apu->position >= 6 && $apu->position <= 10) {
                            if ($apu->positionR >= 6 && $apu->positionR <= 10) $multiplierR = $betCollection10To20->payout_10_to_10;
                            elseif ($apu->positionR >= 11 && $apu->positionR <= 20) $multiplierR = $betCollection10To20->payout_10_to_20;
                        } elseif ($apu->position >= 11 && $apu->position <= 20) {
                            if ($apu->positionR >= 6 && $apu->positionR <= 10) $multiplierR = $betCollection10To20->payout_10_to_20; // This seems like a copy-paste error, should be payout_20_to_10 if exists
                            elseif ($apu->positionR >= 11 && $apu->positionR <= 20) $multiplierR = $betCollection10To20->payout_20_to_20;
                        }
                        $aciertValueR = (float)$apu->import * (float)$multiplierR;
                        Log::info("LotteryResultProcessor - Acierto redoblona calculado: {$aciertValueR} para APU ID: {$apu->id}");
                    }
                }

                // Save result if any acierto is found
                if ($aciertValue > 0 || $aciertValueR > 0) {
                    $matchDetail = [
                        'ticket'      => $apu->ticket,
                        'lottery'     => $apu->lottery,
                        'number'      => $apu->number,
                        'position'    => $apu->position,
                        'numR'        => $apu->numberR,
                        'posR'        => $apu->positionR,
                        'XA'          => 'X', // Assuming 'X' or adjust based on logic
                        'import'      => (float) $apu->import,
                        'aciert'      => $aciertValue + $aciertValueR, // Sum both aciertos
                        'date'        => $dateToCalculate,
                        'time'        => $apu->timeApu,
                        'user_id'     => $apu->user_id,
                        // Add other fields from Result model if necessary, e.g., numero_g, posicion_g, num_g_r, pos_g_r
                        'numero_g'    => $winningNumbersForLottery[$apu->position] ?? null, // Winning number for main play
                        'posicion_g'  => $apu->position,
                        'num_g_r'     => $winningNumbersForLottery[$apu->positionR] ?? null, // Winning number for redoblona
                        'pos_g_r'     => $apu->positionR,
                    ];
                    Result::create($matchDetail);
                    $matches[] = $matchDetail;
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
        // This map needs to be consistent with the $codes array in this class
        // and the City codes in the database.
        if (array_key_exists($apuLotteryUiCode, $this->codes)) {
            return $this->codes[$apuLotteryUiCode];
        }
        return null;
    }
}