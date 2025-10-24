<?php

namespace App\Console\Commands;

use App\Models\City;
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
use App\Services\WinningNumbersService;
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

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $interval = (int) $this->option('interval');
        $this->info("🚀 Iniciando Sistema Automático de Pagos cada {$interval} segundos");
        $this->info("📅 Fecha actual: " . Carbon::now()->format('Y-m-d H:i:s'));
        $this->info("⏰ Horario de funcionamiento: 10:25 AM - 12:00 AM");
        $this->info("🎯 Detección automática de turnos jugados");
        $this->info("💰 Cálculo automático de pagos");
        $this->info("⏹️  Presiona Ctrl+C para detener");
        
        Log::info("AutoPaymentSystem - Iniciando sistema automático de pagos cada {$interval} segundos");

        // Cargar tablas de pagos una sola vez
        $this->loadPayoutTables();

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
            'redoblona1toX' => BetCollectionRedoblonaModel::first(),
            'redoblona5to20' => BetCollection5To20Model::first(),
            'redoblona10to20' => BetCollection10To20Model::first(),
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
     */
    private function processAutoPayments()
    {
        $today = Carbon::now()->format('Y-m-d');
        
        // 1. Obtener todos los números ganadores del día
        $winningNumbers = Number::with(['city', 'extract'])
            ->whereDate('date', $today)
            ->get()
            ->groupBy(function($number) {
                return $number->city->code . '_' . $number->extract->time;
            });

        if ($winningNumbers->isEmpty()) {
            return;
        }

        // 2. Para cada turno con números ganadores, verificar si hay jugadas
        foreach ($winningNumbers as $turnKey => $numbers) {
            $this->processTurnPayments($turnKey, $numbers, $today);
        }
    }

    /**
     * Procesa pagos para un turno específico
     */
    private function processTurnPayments($turnKey, $numbers, $date)
    {
        // Extraer información del turno
        $parts = explode('_', $turnKey);
        $cityCode = $parts[0];
        $time = $parts[1];

        // Verificar si ya procesamos este turno
        $processedKey = $date . '_' . $turnKey;
        if (in_array($processedKey, $this->processedNumbers)) {
            return;
        }

        // MEJORA: Generar código de lotería completo dinámicamente
        $lotteryCode = $this->generateLotteryCode($cityCode, $time);
        
        if (!$lotteryCode) {
            Log::warning("AutoPaymentSystem - No se pudo generar código de lotería para: {$cityCode}_{$time}");
            return;
        }

        Log::info("AutoPaymentSystem - Procesando turno: {$turnKey} -> Código: {$lotteryCode}");

        // Buscar jugadas para este turno específico usando código completo
        $plays = ApusModel::whereDate('created_at', $date)
            ->where('lottery', $lotteryCode)  // Usar código completo dinámico
            ->get();

        if ($plays->isEmpty()) {
            Log::info("AutoPaymentSystem - No hay jugadas para el turno {$turnKey}");
            return;
        }

        $this->info("🎯 Procesando turno: {$turnKey} - {$plays->count()} jugadas encontradas");
        Log::info("AutoPaymentSystem - Procesando turno: {$turnKey} - {$plays->count()} jugadas");

        $resultsInserted = 0;
        $totalPrize = 0;

        // 3. Para cada jugada, verificar si es ganadora
        foreach ($plays as $play) {
            $result = $this->calculatePlayResult($play, $numbers, $date);
            
            if ($result && $result['totalPrize'] > 0) {
                // Verificar si ya existe este resultado
                $existingResult = Result::where('ticket', $play->ticket)
                    ->where('lottery', $play->lottery)
                    ->where('number', $play->number)
                    ->where('position', $play->position)
                    ->where('date', $date)
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
                        'date' => $date,
                        'time' => $time,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    $resultsInserted++;
                    $totalPrize += $result['totalPrize'];

                    Log::info("AutoPaymentSystem - Resultado insertado: Ticket {$play->ticket} - Premio: {$result['totalPrize']}");
                }
            }
        }

        if ($resultsInserted > 0) {
            $this->info("✅ Turno {$turnKey}: {$resultsInserted} resultados insertados - Total: $" . number_format($totalPrize, 2));
            Log::info("AutoPaymentSystem - Turno {$turnKey} completado: {$resultsInserted} resultados - Total: $" . number_format($totalPrize, 2));
        }

        // Marcar este turno como procesado
        $this->processedNumbers[] = $processedKey;
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

        // Verificar si la jugada principal es ganadora
        if ($this->isWinningPlay($play, $winningNumber->value)) {
            $mainPrize = $this->calculateMainPrize($play, $winningNumber->value);
        }

        // Calcular premio de redoblona si existe
        if (!empty($play->numberR) && !empty($play->positionR)) {
            $redoblonaPrize = $this->calculateRedoblonaPrize($play, $date, $play->lottery);
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
     * basado en la posición apostada
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
            return $this->payoutTables['redoblona1toX'];
        } elseif ($position >= 5 && $position <= 20) {
            return $this->payoutTables['redoblona5to20'];
        }
        
        return null;
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
}
