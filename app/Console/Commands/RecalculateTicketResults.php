<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Result;
use App\Models\ApusModel;
use App\Services\LotteryCompletenessService;
use App\Models\QuinielaModel;
use App\Models\PrizesModel;
use App\Models\FigureOneModel;
use App\Models\FigureTwoModel;
use App\Services\RedoblonaService;
use Carbon\Carbon;

class RecalculateTicketResults extends Command
{
    protected $signature = 'recalculate:ticket-results {ticket} {--date=}';
    protected $description = 'Recalcula los resultados de un ticket específico con la nueva lógica de múltiples ganadores';

    private $payoutTables = null;
    private $redoblonaService;

    public function handle()
    {
        $ticket = $this->argument('ticket');
        $date = $this->option('date') ?: Carbon::today()->format('Y-m-d');

        $this->info("=== RECALCULANDO RESULTADOS DEL TICKET {$ticket} ===\n");
        $this->info("Fecha: {$date}\n");

        // Cargar tablas de pagos
        $this->payoutTables = [
            'quiniela' => QuinielaModel::first(),
            'prizes' => PrizesModel::first(),
            'figureOne' => FigureOneModel::first(),
            'figureTwo' => FigureTwoModel::first(),
        ];

        $this->redoblonaService = new RedoblonaService();

        // Obtener todas las jugadas del ticket
        $plays = ApusModel::where('ticket', $ticket)
            ->whereDate('created_at', $date)
            ->get();

        if ($plays->isEmpty()) {
            $this->error("No se encontraron jugadas para el ticket {$ticket} en la fecha {$date}");
            return 1;
        }

        $this->info("Jugadas encontradas: {$plays->count()}\n");

        // Obtener todos los resultados actuales del ticket
        $currentResults = Result::where('ticket', $ticket)
            ->whereDate('date', $date)
            ->get();

        $this->info("Resultados actuales: {$currentResults->count()}\n");

        // Procesar cada jugada
        $updated = 0;
        $errors = 0;

        foreach ($plays as $play) {
            $playLotteries = explode(',', $play->lottery);
            $playLotteries = array_map('trim', $playLotteries);

            foreach ($playLotteries as $lotteryCode) {
                // Normalizar código de lotería (CHA15 -> CHA1500)
                $normalizedLottery = $this->normalizeLotteryCode($lotteryCode);
                
                // Verificar si la lotería está completa
                if (!LotteryCompletenessService::isLotteryComplete($normalizedLottery, $date)) {
                    continue;
                }

                // Obtener números completos
                $completeNumbers = LotteryCompletenessService::getCompleteLotteryNumbersCollection($normalizedLottery, $date);
                
                if (!$completeNumbers || $completeNumbers->count() < 20) {
                    continue;
                }

                // Calcular premio con nueva lógica
                $prizeData = $this->calculatePrizeForPlay($play, $completeNumbers, $normalizedLottery, $date);
                
                if ($prizeData['prize'] > 0) {
                    // Buscar resultado existente
                    $result = Result::where('ticket', $ticket)
                        ->where('lottery', $normalizedLottery)
                        ->where('number', $play->number)
                        ->where('position', $play->position)
                        ->where('numR', $play->numberR ?? null)
                        ->where('posR', $play->positionR ?? null)
                        ->whereDate('date', $date)
                        ->first();

                    if ($result) {
                        $oldTimesWon = $result->times_won ?? 1;
                        $oldPrize = $result->aciert;
                        
                        // Actualizar resultado
                        $result->aciert = $prizeData['prize'];
                        $result->times_won = $prizeData['times_won'];
                        $result->save();

                        if ($oldTimesWon != $prizeData['times_won'] || abs($oldPrize - $prizeData['prize']) > 0.01) {
                            $this->line("✅ Actualizado: {$play->number} posición {$play->position} en {$normalizedLottery}");
                            $this->line("   Veces: {$oldTimesWon} → {$prizeData['times_won']}");
                            $this->line("   Premio: $" . number_format($oldPrize, 2) . " → $" . number_format($prizeData['prize'], 2));
                            $updated++;
                        }
                    }
                }
            }
        }

        $this->newLine();
        $this->info("=== RESUMEN ===");
        $this->info("Resultados actualizados: {$updated}");

        return 0;
    }

    private function normalizeLotteryCode($code)
    {
        // Convertir CHA15 -> CHA1500, NAC15 -> NAC1500, etc.
        if (preg_match('/([A-Z]+)(\d+)/', $code, $matches)) {
            $prefix = $matches[1];
            $number = $matches[2];
            
            if (strlen($number) == 2) {
                return $prefix . $number . '00';
            }
        }
        
        return $code;
    }

    private function calculatePrizeForPlay($play, $completeNumbers, $lotteryCode, $date)
    {
        $mainPrize = 0;
        $redoblonaPrize = 0;
        $timesWon = 1;

        // Si hay redoblona
        if (!empty($play->numberR) && !empty($play->positionR)) {
            $redoblonaData = $this->redoblonaService->calculateRedoblonaPrizeWithCount($play, $date, $lotteryCode);
            $redoblonaPrize = $redoblonaData['prize'];
            $timesWon = $redoblonaData['times_won'];
        } else {
            // Jugada normal
            $playedNumber = str_replace('*', '', $play->number);
            $playedDigits = strlen($playedNumber);
            $playedPosition = (int)$play->position;

            // Determinar rango
            $allowedIndexes = [];
            switch ($playedPosition) {
                case 1:
                    $allowedIndexes = [1];
                    break;
                case 5:
                    $allowedIndexes = range(2, 5);
                    break;
                case 10:
                    $allowedIndexes = range(2, 10);
                    break;
                case 20:
                    $allowedIndexes = range(2, 20);
                    break;
                default:
                    $allowedIndexes = [$playedPosition];
            }

            // Contar apariciones
            $winningCount = 0;
            foreach ($completeNumbers as $number) {
                if (!in_array((int)$number->index, $allowedIndexes)) {
                    continue;
                }
                
                $playNumber = str_replace('*', '', $play->number);
                $winningNumberStr = str_pad($number->value, 4, '0', STR_PAD_LEFT);
                $playLength = strlen($playNumber);
                $winningSuffix = substr($winningNumberStr, -$playLength);
                
                if ($playNumber === $winningSuffix) {
                    $winningCount++;
                }
            }

            if ($winningCount > 0) {
                $timesWon = $winningCount;
                
                if ($playedPosition === 1) {
                    $payoutTable = $this->payoutTables['quiniela'];
                    if ($payoutTable) {
                        $mult = 0;
                        if ($playedDigits == 1) $mult = (float)($payoutTable->cobra_1_cifra ?? 0);
                        elseif ($playedDigits == 2) $mult = (float)($payoutTable->cobra_2_cifra ?? 0);
                        elseif ($playedDigits == 3) $mult = (float)($payoutTable->cobra_3_cifra ?? 0);
                        elseif ($playedDigits == 4) $mult = (float)($payoutTable->cobra_4_cifra ?? 0);
                        $mainPrize = $play->import * $mult;
                    }
                } else {
                    if ($playedDigits == 1 || $playedDigits == 2) {
                        $payoutTable = $this->payoutTables['prizes'];
                    } elseif ($playedDigits == 3) {
                        $payoutTable = $this->payoutTables['figureOne'];
                    } else {
                        $payoutTable = $this->payoutTables['figureTwo'];
                    }
                    
                    if ($payoutTable) {
                        $baseMultiplier = $this->getPositionMultiplier($playedPosition, $payoutTable);
                        $mainPrize = $baseMultiplier * $winningCount * $play->import;
                    }
                }
            }
        }

        return [
            'prize' => $mainPrize + $redoblonaPrize,
            'times_won' => $timesWon
        ];
    }

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
}

