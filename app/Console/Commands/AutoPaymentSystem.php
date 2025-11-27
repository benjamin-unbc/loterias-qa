<?php

namespace App\Console\Commands;

use App\Models\City;
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
use App\Services\WinningNumbersService;
use App\Services\RedoblonaService;
use App\Services\LotteryCompletenessService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class AutoPaymentSystem extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'lottery:auto-payment {--interval=5 : Intervalo en segundos entre verificaciones}';

    /**
     * The console command description.
     */
    protected $description = 'Sistema automático de pagos que detecta turnos jugados y calcula resultados automáticamente';

    private $processedNumbers = [];
    private $payoutTables = null;
    private $redoblonaService;

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // ✅ CORRECCIÓN: Verificar si se pasó explícitamente --interval
        // Si no se pasa, es modo scheduler (ejecutar una vez)
        // Si se pasa con un valor, es modo interactivo (ejecutar en bucle)
        $argv = $_SERVER['argv'] ?? [];
        $hasIntervalParam = false;
        $interval = 5; // Default
        
        foreach ($argv as $arg) {
            if (strpos($arg, '--interval') !== false) {
                $hasIntervalParam = true;
                // Extraer el valor si está en el mismo argumento
                if (strpos($arg, '=') !== false) {
                    $parts = explode('=', $arg);
                    $interval = isset($parts[1]) ? (int)$parts[1] : 5;
                } else {
                    // Buscar el siguiente argumento como valor
                    $index = array_search($arg, $argv);
                    $interval = isset($argv[$index + 1]) ? (int)$argv[$index + 1] : 5;
                }
                break;
            }
        }
        
        // Si NO se pasó --interval, es modo scheduler (ejecutar una vez)
        if (!$hasIntervalParam) {
            // Modo scheduler: ejecutar una sola vez
            $this->info("🚀 Ejecutando sistema de pagos (modo scheduler)...");
            $this->info("📅 Fecha actual: " . Carbon::now()->format('Y-m-d H:i:s'));
            
            Log::info("AutoPaymentSystem - Ejecutando sistema de pagos (scheduler)");

            // Cargar tablas de pagos una sola vez
            $this->loadPayoutTables();
            
            // Inicializar servicio de redoblona
            $this->redoblonaService = new RedoblonaService();

            if ($this->isWithinOperatingHours()) {
                $this->processAutoPayments();
                $this->info("✅ Procesamiento completado");
            } else {
                $this->line("😴 Fuera del horario de funcionamiento (10:00-23:59)");
                Log::info("AutoPaymentSystem - Fuera del horario de funcionamiento");
            }
            
            return 0; // Terminar el comando
        }
        
        // Modo interactivo: ejecutar en bucle
        $this->info("🚀 Iniciando Sistema Automático de Pagos cada {$interval} segundos");
        $this->info("📅 Fecha actual: " . Carbon::now()->format('Y-m-d H:i:s'));
        $this->info("⏰ Horario de funcionamiento: 10:00 AM - 11:59 PM");
        $this->info("🎯 Detección automática de turnos jugados");
        $this->info("💰 Cálculo automático de pagos");
        $this->info("⏹️  Presiona Ctrl+C para detener");
        
        Log::info("AutoPaymentSystem - Iniciando sistema automático de pagos cada {$interval} segundos (modo interactivo)");

        // Cargar tablas de pagos una sola vez
        $this->loadPayoutTables();
        
        // Inicializar servicio de redoblona
        $this->redoblonaService = new RedoblonaService();

        while (true) {
            try {
                if ($this->isWithinOperatingHours()) {
                    $this->processAutoPayments();
                    $this->info("⏰ Esperando {$interval} segundos... (" . Carbon::now()->format('H:i:s') . ")");
                } else {
                    $this->line("😴 Fuera del horario de funcionamiento. Esperando...");
                    sleep(300);
                    continue;
                }
                
                sleep($interval);
            } catch (\Exception $e) {
                $this->error("❌ Error: " . $e->getMessage());
                Log::error("AutoPaymentSystem - Error: " . $e->getMessage());
                sleep(10);
            }
        }
    }

    /**
     * Carga las tablas de pagos una sola vez para optimizar rendimiento
     */
    private function loadPayoutTables()
    {
        $this->payoutTables = [
            'quiniela' => QuinielaModel::first(),
            'prizes' => PrizesModel::first(),
            'figureOne' => FigureOneModel::first(),
            'figureTwo' => FigureTwoModel::first(),
            'redoblona1toX' => BetCollectionRedoblonaModel::where('bet_amount', 1.00)->first(),
            'redoblona5to20' => BetCollection5To20Model::where('bet_amount', 1.00)->first(),
            'redoblona10to20' => BetCollection10To20Model::where('bet_amount', 1.00)->first(),
        ];

        foreach ($this->payoutTables as $key => $table) {
            if (!$table) {
                Log::error("AutoPaymentSystem - Falta tabla de pagos: {$key}");
                $this->error("❌ Falta tabla de pagos: {$key}");
                exit(1);
            }
        }

        $this->info("✅ Tablas de pagos cargadas correctamente");
        Log::info("AutoPaymentSystem - Tablas de pagos cargadas correctamente");
    }

    /**
     * Procesa pagos automáticamente detectando turnos jugados
     * ✅ MODIFICADO: Solo procesa loterías que tengan sus 20 números completos
     */
    private function processAutoPayments()
    {
        $today = Carbon::now()->format('Y-m-d');
        
        Log::info("AutoPaymentSystem - Verificando loterías completas para {$today}");

        // ✅ NUEVA LÓGICA: Obtener solo las loterías que tengan sus 20 números completos
        $completeLotteries = LotteryCompletenessService::getCompleteLotteries($today);

        if (empty($completeLotteries)) {
            Log::info("AutoPaymentSystem - No hay loterías completas para procesar en {$today}");
            return;
        }

        Log::info("AutoPaymentSystem - Loterías completas encontradas: " . implode(', ', $completeLotteries));

        $totalResultsInserted = 0;
        $totalPrizeAmount = 0;

        // Procesar cada lotería completa
        foreach ($completeLotteries as $lotteryCode) {
            $result = $this->processCompleteLottery($lotteryCode, $today);
            $totalResultsInserted += $result['resultsInserted'];
            $totalPrizeAmount += $result['totalPrize'];
        }

        if ($totalResultsInserted > 0) {
            $this->info("✅ Procesamiento completado: {$totalResultsInserted} resultados insertados - Total: $" . number_format($totalPrizeAmount, 2));
            Log::info("AutoPaymentSystem - Procesamiento completado: {$totalResultsInserted} resultados - Total: $" . number_format($totalPrizeAmount, 2));
        } else {
            Log::info("AutoPaymentSystem - No se encontraron jugadas ganadoras en las loterías completas");
        }
    }

    /**
     * ✅ NUEVO: Procesa una lotería completa (con sus 20 números)
     */
    private function processCompleteLottery($lotteryCode, $date)
    {
        // Verificar si ya procesamos esta lotería completa
        $processedKey = $date . '_' . $lotteryCode;
        if (in_array($processedKey, $this->processedNumbers)) {
            Log::info("AutoPaymentSystem - Lotería {$lotteryCode} ya fue procesada para {$date}");
            return ['resultsInserted' => 0, 'totalPrize' => 0];
        }

        Log::info("AutoPaymentSystem - Procesando lotería completa: {$lotteryCode} para {$date}");

        // Obtener todos los números ganadores de esta lotería completa
        $completeNumbers = LotteryCompletenessService::getCompleteLotteryNumbersCollection($lotteryCode, $date);
        
        if (!$completeNumbers) {
            Log::warning("AutoPaymentSystem - No se pudieron obtener los números completos para {$lotteryCode}");
            return ['resultsInserted' => 0, 'totalPrize' => 0];
        }

        // Buscar jugadas que puedan ser ganadoras con esta lotería completa
        $plays = ApusModel::whereDate('created_at', $date)
            ->where('lottery', 'LIKE', "%{$lotteryCode}%")
            ->get();

        if ($plays->isEmpty()) {
            Log::info("AutoPaymentSystem - No hay jugadas para la lotería completa {$lotteryCode}");
            return ['resultsInserted' => 0, 'totalPrize' => 0];
        }

        $this->info("🎯 Procesando lotería completa: {$lotteryCode} - {$plays->count()} jugadas encontradas");
        Log::info("AutoPaymentSystem - Procesando lotería completa: {$lotteryCode} - {$plays->count()} jugadas");

        $resultsInserted = 0;
        $totalPrize = 0;

        // Para cada jugada, verificar si es ganadora para esta lotería específica
        foreach ($plays as $play) {
            if ($this->isWinningPlayForLotteryComplete($play, $completeNumbers, $lotteryCode)) {
                // ✅ MODIFICADO: Obtener premio y times_won
                $prizeData = $this->calculatePrizeForLotteryComplete($play, $completeNumbers, $lotteryCode);
                $prize = $prizeData['prize'];
                $timesWon = $prizeData['times_won'];
                
                if ($prize > 0) {
                    // Obtener información del número ganador
                    $winningInfo = $this->getWinningNumberAndPosition($play, $completeNumbers, $lotteryCode);
                    
                    // Verificar si ya existe este resultado específico para esta lotería
                    $existingResult = Result::where('ticket', $play->ticket)
                        ->where('lottery', $lotteryCode)
                        ->where('number', $play->number)
                        ->where('position', $play->position)
                        ->where('date', $date)
                        ->first();

                    if (!$existingResult) {
                        // Usar ResultManager para inserción segura
                        $resultData = [
                            'user_id' => $play->user_id,
                            'ticket' => $play->ticket,
                            'lottery' => $lotteryCode,
                            'number' => $play->number,
                            'position' => $play->position,
                            'numR' => $play->numberR,
                            'posR' => $play->positionR,
                            'XA' => 'X',
                            'import' => $play->import,
                            'aciert' => $prize,
                            'times_won' => $timesWon, // ✅ Agregar times_won
                            'date' => $date,
                            'time' => $completeNumbers->first()->extract->time,
                            'numero_g' => $winningInfo['winningNumber'] ?? null,
                            'posicion_g' => $winningInfo['winningPosition'] ?? null,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];

                        $result = ResultManager::createResultSafely($resultData);
                        if ($result) {
                            $resultsInserted++;
                            $totalPrize += $prize;
                            Log::info("AutoPaymentSystem - Resultado insertado: Ticket {$play->ticket} - Lotería {$lotteryCode} - Premio: {$prize} - Veces: {$timesWon}");
                        }
                    }
                }
            }
        }

        if ($resultsInserted > 0) {
            $this->info("✅ Lotería {$lotteryCode}: {$resultsInserted} resultados insertados - Total: $" . number_format($totalPrize, 2));
            Log::info("AutoPaymentSystem - Lotería {$lotteryCode} completada: {$resultsInserted} resultados - Total: $" . number_format($totalPrize, 2));
        }

        // Marcar esta lotería como procesada
        $this->processedNumbers[] = $processedKey;

        return ['resultsInserted' => $resultsInserted, 'totalPrize' => $totalPrize];
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
                // A los 10: posiciones 2-10
                $allowedIndexes = range(2, 10);
                break;
            case 20:
                // A los 20: posiciones 2-20
                $allowedIndexes = range(2, 20);
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
     * ✅ MODIFICADO: Ahora cuenta múltiples apariciones y retorna array con prize y times_won
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
            // Posición 5: busca de 2-5
            // Posición 10: busca de 2-10
            // Posición 20: busca de 2-20
            $allowedIndexes = [];
            
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
                    // A los 10: posiciones 2-10
                    $allowedIndexes = range(2, 10);
                    break;
                case 20:
                    // A los 20: posiciones 2-20
                    $allowedIndexes = range(2, 20);
                    break;
                default:
                    // Para otras posiciones específicas, solo esa posición
                    $allowedIndexes = [$playedPosition];
            }

            // ✅ NUEVA LÓGICA: Contar cuántas veces sale el número en el rango válido
            $winningCount = 0;
            $winningPositions = [];
            $playNumber = str_replace('*', '', $play->number);
            $playLength = strlen($playNumber);
            
            Log::info("AutoPaymentSystem - Contando apariciones: {$play->number} (limpio: {$playNumber}, {$playLength} dígitos) posición {$playedPosition} en {$lotteryCode}");
            Log::info("AutoPaymentSystem - Rango permitido: " . implode(', ', $allowedIndexes));
            
            foreach ($completeNumbers as $number) {
                if (!in_array((int)$number->index, $allowedIndexes)) {
                    continue;
                }
                
                // Verificar números directamente
                $winningNumberStr = str_pad($number->value, 4, '0', STR_PAD_LEFT);
                $winningSuffix = substr($winningNumberStr, -$playLength);
                $numbersMatch = $playNumber === $winningSuffix;
                
                // Verificar posición
                $positionCorrect = $this->isPositionCorrect($playedPosition, $number->index);
                
                if ($numbersMatch && $positionCorrect) {
                    $winningCount++;
                    $winningPositions[] = $number->index;
                    Log::info("AutoPaymentSystem - ✅ Aparición #{$winningCount}: {$play->number} coincide con {$number->value} en posición {$number->index}");
                }
            }
            
            // Log para depuración
            Log::info("AutoPaymentSystem - Conteo final: {$play->number} posición {$playedPosition} en {$lotteryCode} - Veces: {$winningCount} - Posiciones: " . implode(', ', $winningPositions));
            
            if ($winningCount > 1) {
                Log::info("AutoPaymentSystem - ✅ MÚLTIPLES APARICIONES DETECTADAS: {$play->number} posición {$playedPosition} en {$lotteryCode} - Veces: {$winningCount}");
            }

            if ($winningCount > 0) {
                $timesWon = $winningCount; // Guardar el conteo de veces que salió
                // Posición 1 usa tabla Quiniela según dígitos apostados
                if ($playedPosition === 1) {
                    $payoutTable = $this->payoutTables['quiniela'] ?? null;
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
                        $payoutTable = $this->payoutTables['prizes'] ?? null;
                    } elseif ($playedDigits == 3) {
                        $payoutTable = $this->payoutTables['figureOne'] ?? null;
                    } else { // 4 dígitos
                        $payoutTable = $this->payoutTables['figureTwo'] ?? null;
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
        Log::info("AutoPaymentSystem - calculatePrizeForLotteryComplete retorna: prize = {$finalPrize}, times_won = {$timesWon}");
        
        return [
            'prize' => $finalPrize,
            'times_won' => $timesWon
        ];
    }

    /**
     * Calcula el resultado de una jugada específica
     */
    private function calculatePlayResult($play, $numbers, $date)
    {
        $mainPrize = 0;
        $redoblonaPrize = 0;

        // Buscar número ganador en el rango apropiado según la posición apostada
        $winningNumber = $this->findWinningNumberInRange($play, $numbers);
        
        if (!$winningNumber) {
            return null;
        }

        // IMPORTANTE: Si hay redoblona, NO se paga premio principal, solo redoblona
        if (!empty($play->numberR) && !empty($play->positionR)) {
            // Solo calcular premio de redoblona (se paga TODO como redoblona)
            $redoblonaPrize = $this->redoblonaService->calculateRedoblonaPrize($play, $date, $play->lottery);
        } else {
            // Solo calcular premio principal si NO hay redoblona
            if ($this->isWinningPlay($play, $winningNumber->value)) {
                $mainPrize = $this->calculateMainPrize($play, $winningNumber->value);
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
     * ✅ NUEVO: Obtiene el número ganador y la posición ganadora para una jugada
     * Retorna null si no es ganadora, o un array con isWinner, winningNumber y winningPosition
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
                // A los 10: posiciones 2-10
                $allowedIndexes = range(2, 10);
                break;
            case 20:
                // A los 20: posiciones 2-20
                $allowedIndexes = range(2, 20);
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
        $payoutTable = $this->payoutTables['quiniela'];
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
        $payoutTable = $this->payoutTables['prizes'];
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
        $payoutTable = $this->payoutTables['figureOne'];
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
        $payoutTable = $this->payoutTables['figureTwo'];
        if (!$payoutTable) {
            return 0;
        }

        $multiplier = $this->getPositionMultiplier($play->position, $payoutTable);
        return $play->import * $multiplier;
    }

    /**
     * Busca el número ganador en el rango apropiado según la posición apostada
     */
    private function findWinningNumberInRange($play, $numbers)
    {
        $apostadaPosition = $play->position;
        
        // Si apostaste a posición 1, buscar solo en posición 1
        if ($apostadaPosition == 1) {
            return $numbers->where('index', 1)->first();
        }
        
        // Para otras posiciones, buscar en el rango apropiado
        $searchRange = $this->getSearchRangeForPosition($apostadaPosition);
        
        // Buscar el número en todas las posiciones del rango
        foreach ($searchRange as $position) {
            $winningNumber = $numbers->where('index', $position)->first();
            if ($winningNumber) {
                // Verificar si este número coincide con la apuesta
                if ($this->isWinningPlay($play, $winningNumber->value)) {
                    return $winningNumber;
                }
            }
        }
        
        return null;
    }

    /**
     * Determina el rango de posiciones donde buscar el número ganador
     * basado en la posición apostada (RANGOS DISJUNTOS)
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
     * Verifica si la hora actual está dentro del horario de funcionamiento
     */
    private function isWithinOperatingHours()
    {
        $now = Carbon::now();
        $currentTime = $now->format('H:i:s');
        
        $startTime = '10:25:00';
        $endTime = '23:59:59';
        
        return $currentTime >= $startTime && $currentTime <= $endTime;
    }

    /**
     * MEJORA: Genera el código de lotería completo dinámicamente
     */
    private function generateLotteryCode($cityCode, $time): ?string
    {
        try {
            // CORRECCIÓN: Buscar la ciudad por código base y tiempo
            // El código de lotería ya está completo en la base de datos
            
            $city = \App\Models\City::where('code', 'LIKE', $cityCode . '%')
                ->where('time', $time)
                ->first();
            
            if (!$city) {
                Log::warning("AutoPaymentSystem - No se encontró ciudad con código base: {$cityCode} y tiempo: {$time}");
                return null;
            }
            
            $lotteryCode = $city->code;
            
            Log::info("AutoPaymentSystem - Código de lotería encontrado: {$lotteryCode} (Ciudad: {$city->name}, Tiempo: {$time})");
            
            return $lotteryCode;
            
        } catch (\Exception $e) {
            Log::error("AutoPaymentSystem - Error generando código de lotería: " . $e->getMessage());
            return null;
        }
    }

    /**
     * ✅ NUEVO: Verifica si una jugada es ganadora para una lotería específica
     */
    private function isWinningPlayForLottery($play, $numbers, $lotteryCode)
    {
        // Verificar que la jugada contenga esta lotería específica
        $playLotteries = explode(',', $play->lottery);
        $playLotteries = array_map('trim', $playLotteries);
        
        if (!in_array($lotteryCode, $playLotteries)) {
            return false;
        }
        
        // Verificar si los números coinciden con alguno de los números ganadores
        foreach ($numbers as $number) {
            if ($this->isWinningPlay($play, $number->value, $number->index)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * ✅ NUEVO: Calcula el premio para una lotería específica
     */
    private function calculatePrizeForLottery($play, $numbers, $lotteryCode)
    {
        $mainPrize = 0;
        $redoblonaPrize = 0;

        // IMPORTANTE: Si hay redoblona, NO se paga premio principal, solo redoblona
        if (!empty($play->numberR) && !empty($play->positionR)) {
            // Solo calcular premio de redoblona (se paga TODO como redoblona)
            $redoblonaPrize = $this->redoblonaService->calculateRedoblonaPrize($play, $numbers->first()->date, $lotteryCode);
        } else {
            // Solo calcular premio principal si NO hay redoblona
            foreach ($numbers as $number) {
                if ($this->isWinningPlay($play, $number->value, $number->index)) {
                    $mainPrize = $this->calculateMainPrize($play, $number->value);
                    break; // Solo calcular para el primer número que coincida
                }
            }
        }

        return $mainPrize + $redoblonaPrize;
    }
}
