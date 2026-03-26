<?php

namespace App\Console\Commands;

use App\Models\City;
use App\Models\Number;
use App\Models\ApusModel;
use App\Models\Result;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class TestAutoPaymentSystem extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'lottery:test-auto-payment {--date= : Fecha para probar (YYYY-MM-DD)}';

    /**
     * The console command description.
     */
    protected $description = 'Prueba el sistema automático de pagos con todas las loterías configuradas';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $date = $this->option('date') ?: Carbon::now()->format('Y-m-d');
        
        $this->info("🧪 Probando Sistema Automático de Pagos");
        $this->info("📅 Fecha de prueba: {$date}");
        $this->newLine();

        try {
            // 1. Listar todas las loterías configuradas
            $this->listConfiguredLotteries();
            
            // 2. Verificar jugadas existentes
            $this->checkExistingPlays($date);
            
            // 3. Verificar números ganadores
            $this->checkWinningNumbers($date);
            
            // 4. Simular inserción de número y verificar detección
            $this->simulateNumberInsertion($date);
            
            // 5. Verificar resultados generados
            $this->checkGeneratedResults($date);

        } catch (\Exception $e) {
            $this->error("❌ Error: " . $e->getMessage());
            Log::error("TestAutoPaymentSystem - Error: " . $e->getMessage());
        }
    }

    /**
     * Lista todas las loterías configuradas
     */
    private function listConfiguredLotteries()
    {
        $this->info("🔍 1. Loterías configuradas dinámicamente:");
        
        $cities = City::with('extract')
            ->orderBy('name')
            ->orderBy('extract_id')
            ->get();

        $lotteries = [];
        foreach ($cities as $city) {
            $timeFormatted = str_replace(':', '', $city->extract->time);
            $fullCode = $city->code . $timeFormatted;
            $lotteries[] = [
                'Ciudad' => $city->name,
                'Código' => $city->code,
                'Horario' => $city->extract->time,
                'Código Completo' => $fullCode
            ];
        }

        $this->table(['Ciudad', 'Código', 'Horario', 'Código Completo'], $lotteries);
        $this->info("✅ Total: " . count($lotteries) . " loterías configuradas");
        $this->newLine();
    }

    /**
     * Verifica jugadas existentes
     */
    private function checkExistingPlays($date)
    {
        $this->info("🎯 2. Verificando jugadas existentes para {$date}:");
        
        $plays = ApusModel::whereDate('created_at', $date)->get();
        
        if ($plays->isEmpty()) {
            $this->warn("⚠️  No hay jugadas para la fecha {$date}");
            $this->newLine();
            return;
        }

        $playsByLottery = $plays->groupBy('lottery');
        
        $this->info("📊 Jugadas por lotería:");
        foreach ($playsByLottery as $lottery => $lotteryPlays) {
            $this->line("  • {$lottery}: " . $lotteryPlays->count() . " jugadas");
        }
        
        $this->info("✅ Total: " . $plays->count() . " jugadas encontradas");
        $this->newLine();
    }

    /**
     * Verifica números ganadores
     */
    private function checkWinningNumbers($date)
    {
        $this->info("🎲 3. Verificando números ganadores para {$date}:");
        
        $numbers = Number::with(['city', 'extract'])
            ->whereDate('date', $date)
            ->get();

        if ($numbers->isEmpty()) {
            $this->warn("⚠️  No hay números ganadores para la fecha {$date}");
            $this->newLine();
            return;
        }

        $numbersByLottery = $numbers->groupBy(function($number) {
            $timeFormatted = str_replace(':', '', $number->extract->time);
            return $number->city->code . $timeFormatted;
        });

        $this->info("📊 Números ganadores por lotería:");
        foreach ($numbersByLottery as $lottery => $lotteryNumbers) {
            $this->line("  • {$lottery}: " . $lotteryNumbers->count() . " números");
        }
        
        $this->info("✅ Total: " . $numbers->count() . " números ganadores encontrados");
        $this->newLine();
    }

    /**
     * Simula inserción de número y verifica detección
     */
    private function simulateNumberInsertion($date)
    {
        $this->info("🔄 4. Simulando detección automática:");
        
        $numbers = Number::with(['city', 'extract'])
            ->whereDate('date', $date)
            ->get();

        if ($numbers->isEmpty()) {
            $this->warn("⚠️  No hay números para simular detección");
            $this->newLine();
            return;
        }

        $detectedLotteries = [];
        
        foreach ($numbers as $number) {
            $timeFormatted = str_replace(':', '', $number->extract->time);
            $lotteryCode = $number->city->code . $timeFormatted;
            
            // Verificar si hay jugadas para esta lotería
            $matchingPlays = ApusModel::whereDate('created_at', $date)
                ->where('lottery', $lotteryCode)
                ->get();

            if (!$matchingPlays->isEmpty()) {
                $detectedLotteries[] = [
                    'Lotería' => $lotteryCode,
                    'Ciudad' => $number->city->name,
                    'Horario' => $number->extract->time,
                    'Jugadas' => $matchingPlays->count(),
                    'Números' => $numbers->where('city_id', $number->city_id)->count()
                ];
            }
        }

        if (!empty($detectedLotteries)) {
            $this->table(['Lotería', 'Ciudad', 'Horario', 'Jugadas', 'Números'], $detectedLotteries);
            $this->info("✅ " . count($detectedLotteries) . " loterías con jugadas detectadas");
        } else {
            $this->warn("⚠️  No se detectaron loterías con jugadas");
        }
        
        $this->newLine();
    }

    /**
     * Verifica resultados generados
     */
    private function checkGeneratedResults($date)
    {
        $this->info("💰 5. Verificando resultados generados para {$date}:");
        
        $results = Result::whereDate('date', $date)->get();
        
        if ($results->isEmpty()) {
            $this->warn("⚠️  No hay resultados generados para la fecha {$date}");
            $this->newLine();
            return;
        }

        $resultsByLottery = $results->groupBy('lottery');
        $totalPrize = $results->sum('aciert');
        
        $this->info("📊 Resultados por lotería:");
        foreach ($resultsByLottery as $lottery => $lotteryResults) {
            $lotteryPrize = $lotteryResults->sum('aciert');
            $this->line("  • {$lottery}: " . $lotteryResults->count() . " resultados - $" . number_format($lotteryPrize, 2));
        }
        
        $this->info("✅ Total: " . $results->count() . " resultados - $" . number_format($totalPrize, 2));
        $this->newLine();
    }
}
