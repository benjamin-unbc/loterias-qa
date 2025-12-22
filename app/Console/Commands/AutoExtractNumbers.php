<?php

namespace App\Console\Commands;

use App\Models\City;
use App\Models\Number;
use App\Services\WinningNumbersService;
use App\Services\RedoblonaService;
use App\Services\LotteryCompletenessService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class AutoExtractNumbers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'lottery:auto-extract {--interval=10 : Intervalo en segundos entre extracciones}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Extrae automáticamente números ganadores de lotería cada X segundos';

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
        $interval = 10; // Default
        
        foreach ($argv as $arg) {
            if (strpos($arg, '--interval') !== false) {
                $hasIntervalParam = true;
                // Extraer el valor si está en el mismo argumento
                if (strpos($arg, '=') !== false) {
                    $parts = explode('=', $arg);
                    $interval = isset($parts[1]) ? (int)$parts[1] : 10;
                } else {
                    // Buscar el siguiente argumento como valor
                    $index = array_search($arg, $argv);
                    $interval = isset($argv[$index + 1]) ? (int)$argv[$index + 1] : 10;
                }
                break;
            }
        }
        
        // Si NO se pasó --interval, es modo scheduler (ejecutar una vez)
        if (!$hasIntervalParam) {
            // Modo scheduler: ejecutar una sola vez
            $this->info("🔄 Ejecutando extracción única (modo scheduler)...");
            $this->info("📅 Fecha actual: " . Carbon::now()->format('Y-m-d H:i:s'));
            
            // Inicializar servicio de redoblona
            $this->redoblonaService = new RedoblonaService();
            
            Log::info("AutoExtractNumbers - Ejecutando extracción única (scheduler)");
            
            // Verificar si estamos en horario de funcionamiento
            if ($this->isWithinOperatingHours()) {
                $this->extractNumbers();
                $this->info("✅ Extracción completada");
            } else {
                $this->line("😴 Fuera del horario de funcionamiento. La extracción solo funciona entre 10:30-11:30, 12:00-13:00, 15:00-16:00, 18:00-19:00, 21:00-22:00, 22:00-23:00");
                Log::info("AutoExtractNumbers - Fuera del horario de funcionamiento");
            }
            
            return 0; // Terminar el comando
        }
        
        // Modo interactivo: ejecutar en bucle
        $this->info("🔄 Iniciando extracción automática cada {$interval} segundos (detección rápida)...");
        $this->info("📅 Fecha actual: " . Carbon::now()->format('Y-m-d H:i:s'));
        $this->info("⏰ Horarios de funcionamiento:");
        $this->info("   • 10:30-11:30 (Primera extracción)");
        $this->info("   • 12:00-13:00 (Segunda extracción)");
        $this->info("   • 15:00-16:00 (Tercera extracción)");
        $this->info("   • 18:00-19:00 (Cuarta extracción)");
        $this->info("   • 21:00-22:00 (Quinta extracción)");
        $this->info("   • 22:00-23:00 (Sexta extracción)");
        $this->info("⏹️  Presiona Ctrl+C para detener");
        
        // Inicializar servicio de redoblona
        $this->redoblonaService = new RedoblonaService();
        
        Log::info("AutoExtractNumbers - Iniciando extracción automática cada {$interval} segundos (modo interactivo)");

        while (true) {
            try {
                // Verificar si estamos en horario de funcionamiento
                if ($this->isWithinOperatingHours()) {
                    $this->extractNumbers();
                    $this->info("⏰ Esperando {$interval} segundos... (" . Carbon::now()->format('H:i:s') . ")");
                } else {
                    $this->line("😴 Fuera del horario de funcionamiento. Próximos horarios: 10:30-11:30, 12:00-13:00, 15:00-16:00, 18:00-19:00, 21:00-22:00, 22:00-23:00");
                    // Esperar 5 minutos cuando está fuera del horario
                    sleep(300);
                    continue;
                }
                
                sleep($interval);
            } catch (\Exception $e) {
                $this->error("❌ Error: " . $e->getMessage());
                Log::error("AutoExtractNumbers - Error: " . $e->getMessage());
                sleep(10); // Esperar 10 segundos antes de reintentar
            }
        }
    }

    /**
     * Extrae números ganadores de todas las ciudades
     */
    private function extractNumbers()
    {
        $todayDate = Carbon::today()->toDateString();
        $this->info("🔍 Extrayendo números para {$todayDate}...");
        
        $winningNumbersService = new WinningNumbersService();
        $availableCities = $winningNumbersService->getAvailableCities();
        
        $totalInserted = 0;
        $totalUpdated = 0;
        $errors = [];
        
        foreach ($availableCities as $cityName) {
            try {
                $cityData = $winningNumbersService->extractWinningNumbers($cityName);
                
                if ($cityData && !empty($cityData['turns'])) {
                    foreach ($cityData['turns'] as $turnName => $numbers) {
                        if (!empty($numbers)) {
                            $result = $this->insertCityNumbersToDatabase($cityName, $turnName, $numbers, $todayDate);
                            $totalInserted += $result['inserted'];
                            $totalUpdated += $result['updated'] ?? 0;
                        }
                    }
                }
            } catch (\Exception $e) {
                $errors[] = "Error en {$cityName}: " . $e->getMessage();
                Log::error("AutoExtractNumbers - Error en {$cityName}: " . $e->getMessage());
            }
        }
        
        if ($totalInserted > 0 || $totalUpdated > 0) {
            $this->info("✅ Extracción completada: {$totalInserted} nuevos, {$totalUpdated} actualizados");
            Log::info("AutoExtractNumbers - Extracción: {$totalInserted} nuevos, {$totalUpdated} actualizados");
            
            // ✅ NUEVA LÓGICA: Procesar loterías completas después de la extracción
            $this->processCompleteLotteries($todayDate);
        } else {
            $this->line("ℹ️  No se encontraron números nuevos");
        }
        
        if (!empty($errors)) {
            $this->warn("⚠️  Errores: " . implode(', ', $errors));
        }
    }

    /**
     * Inserta números de una ciudad en la base de datos
     */
    private function insertCityNumbersToDatabase($cityName, $turnName, $numbers, $date)
    {
        $inserted = 0;
        $updated = 0;
        
        try {
            // Mapear nombres de ciudades a códigos de BD
            $cityMapping = [
                'Ciudad' => 'NAC',
                'Santa Fé' => 'SFE',
                'Provincia' => 'PRO',
                'Entre Ríos' => 'RIO',
                'Córdoba' => 'COR',
                'Corrientes' => 'CTE',
                'Chaco' => 'CHA',
                'Neuquén' => 'NQN',
                'Misiones' => 'MIS',
                'Mendoza' => 'MZA',
                'Río Negro' => 'Rio',
                'Tucumán' => 'Tucu',
                'Santiago' => 'San',
                'Jujuy' => 'JUJ',
                'Salta' => 'Salt',
                'Montevideo' => 'ORO',
                'San Luis' => 'SLU',
                'Chubut' => 'CHU',
                'Formosa' => 'FOR',
                'Catamarca' => 'CAT',
                'San Juan' => 'SJU'
            ];
            
            // Mapear nombres de turnos a extract_id
            $turnMapping = [
                'La Previa' => 1,
                'Primera' => 2,
                'Matutina' => 3,
                'Vespertina' => 4,
                'Nocturna' => 5
            ];
            
            // Mapeo especial para Montevideo
            if ($cityName === 'Montevideo') {
                $turnMapping['Matutina'] = 3; // Matutina de Montevideo va a Matutina (extract_id 3)
            }
            
            $cityCode = $cityMapping[$cityName] ?? null;
            $extractId = $turnMapping[$turnName] ?? null;
            
            if (!$cityCode || !$extractId) {
                return ['inserted' => 0, 'updated' => 0];
            }
            
            // Buscar la ciudad en la BD
            $city = City::where('code', 'LIKE', $cityCode . '%')
                       ->where('extract_id', $extractId)
                       ->first();
            
            if (!$city) {
                return ['inserted' => 0, 'updated' => 0];
            }
            
            // Insertar cada número
            foreach ($numbers as $index => $number) {
                $position = $index + 1;
                
                // Verificar si ya existe
                $existingNumber = Number::where('city_id', $city->id)
                                      ->where('index', $position)
                                      ->where('date', $date)
                                      ->first();
                
                if ($existingNumber) {
                    // Actualizar si el número es diferente
                    if ($existingNumber->value !== $number) {
                        $existingNumber->value = $number;
                        $existingNumber->save();
                        $updated++;
                        Log::info("AutoExtractNumbers - Actualizado: {$cityName} - {$turnName} - Pos {$position}: {$number}");
                    }
                } else {
                    // Crear nuevo número
                    Number::create([
                        'city_id' => $city->id,
                        'extract_id' => $extractId,
                        'index' => $position,
                        'value' => $number,
                        'date' => $date
                    ]);
                    $inserted++;
                    Log::info("AutoExtractNumbers - Insertado: {$cityName} - {$turnName} - Pos {$position}: {$number}");
                    
                    // NUEVO: Calcular resultados inmediatamente después de insertar número
                    $this->calculateResultsForNumber($city, $extractId, $position, $date, $number);
                }
            }
            
        } catch (\Exception $e) {
            Log::error("AutoExtractNumbers - Error insertando números para {$cityName} - {$turnName}: " . $e->getMessage());
        }
        
        return ['inserted' => $inserted, 'updated' => $updated];
    }

    /**
     * Verifica si la hora actual está dentro del horario de funcionamiento
     * Horarios específicos: 10:30-11:30, 12:00-13:00, 15:00-16:00, 18:00-19:00, 21:00-22:00, 22:00-23:00
     */
    private function isWithinOperatingHours()
    {
        $now = Carbon::now();
        $currentTime = $now->format('H:i:s');
        
        // Definir los horarios de funcionamiento específicos
        $operatingHours = [
            ['10:30:00', '11:30:00'], // Primera extracción
            ['12:00:00', '13:00:00'], // Segunda extracción
            ['15:00:00', '16:00:00'], // Tercera extracción
            ['18:00:00', '19:00:00'], // Cuarta extracción
            ['21:00:00', '22:00:00'], // Quinta extracción
            ['22:00:00', '23:00:00']  // Sexta extracción
        ];
        
        // Verificar si la hora actual está dentro de alguno de los horarios
        foreach ($operatingHours as $timeSlot) {
            if ($currentTime >= $timeSlot[0] && $currentTime <= $timeSlot[1]) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Calcula resultados inmediatamente después de insertar un número ganador
     * ✅ MODIFICADO: Solo procesa cuando la lotería tenga sus 20 números completos
     */
    private function calculateResultsForNumber($city, $extractId, $position, $date, $winningNumber)
    {
        try {
            Log::info("AutoExtractNumbers - Calculando resultados para: {$city->code} - Pos {$position} - Número {$winningNumber}");

            // Obtener el extract para el tiempo
            $extract = \App\Models\Extract::find($extractId);
            if (!$extract) {
                Log::warning("No se encontró extract con ID: {$extractId}");
                return;
            }
            
            // Usar el código completo de la ciudad (ya incluye el turno)
            // Ejemplo: CHA1800, NAC1500, TUCU2200, etc.
            $lotteryCode = $city->code;
            
            Log::info("AutoExtractNumbers - Usando código completo de lotería: {$lotteryCode}");
            
            // ✅ ÚNICO FILTRO: Verificar que la lotería tenga sus 20 números completos
            $isComplete = \App\Services\LotteryCompletenessService::isLotteryComplete($lotteryCode, $date);
            
            if (!$isComplete) {
                Log::info("AutoExtractNumbers - Lotería {$lotteryCode} aún no está completa (tiene menos de 20 números). NO se procesarán resultados hasta que tenga los 20 números.");
                return;
            }
            
            Log::info("AutoExtractNumbers - ✅ Lotería {$lotteryCode} COMPLETA con 20 números. Procediendo con inserción de resultados...");
            
            // ✅ Obtener todos los 20 números completos de la lotería
            $completeNumbers = \App\Services\LotteryCompletenessService::getCompleteLotteryNumbersCollection($lotteryCode, $date);
            
            if (!$completeNumbers || $completeNumbers->count() < 20) {
                Log::warning("AutoExtractNumbers - No se pudieron obtener los 20 números completos para {$lotteryCode}");
                return;
            }
            
            // Buscar jugadas que coincidan con esta lotería (excluyendo anuladas)
            $matchingPlays = \App\Models\ApusModel::whereDate('created_at', $date)
                                                 ->whereRaw('FIND_IN_SET(?, lottery)', [$lotteryCode])
                                                 ->whereHas('playsSent', function($query) {
                                                     $query->where('status', '!=', 'I'); // Excluir jugadas anuladas
                                                 })
                                                 ->get();
            
            Log::info("AutoExtractNumbers - Encontradas " . $matchingPlays->count() . " jugadas para lotería {$lotteryCode}");
            
            // Obtener configuraciones de premios
            $quinielaPayouts = \App\Models\QuinielaModel::first();
            $redoblona1toX = \App\Models\BetCollectionRedoblonaModel::where('bet_amount', 1.00)->first();
            $redoblona5to20 = \App\Models\BetCollection5To20Model::where('bet_amount', 1.00)->first();
            $redoblona10to20 = \App\Models\BetCollection10To20Model::where('bet_amount', 1.00)->first();
            
            if (!$quinielaPayouts || !$redoblona1toX || !$redoblona5to20 || !$redoblona10to20) {
                Log::error("No se encontraron configuraciones de premios");
                return;
            }
            
            $resultsInserted = 0;
            
            // Para cada jugada, usar la misma lógica que NumberObserver
            foreach ($matchingPlays as $play) {
                // Usar el mismo método que NumberObserver para calcular premio y times_won
                $prizeData = $this->calculatePrizeForLotteryCompleteWithCount($play, $completeNumbers, $lotteryCode, $quinielaPayouts);
                $totalPrize = $prizeData['prize'];
                $timesWon = $prizeData['times_won'];
                $winningInfo = $prizeData['winningInfo'] ?? null;
                
                if ($totalPrize > 0 && $winningInfo) {
                    // Verificar si ya existe este resultado para evitar duplicados
                    $existingResult = \App\Models\Result::where('ticket', $play->ticket)
                                                       ->where('lottery', $lotteryCode)
                                                       ->where('number', $play->number)
                                                       ->where('position', $play->position)
                                                       ->where('date', $date)
                                                       ->first();
                    
                    // Insertar de forma segura (evita duplicados y descarta premio 0)
                    $result = \App\Services\ResultManager::createResultSafely([
                        'ticket' => $play->ticket,
                        'lottery' => $lotteryCode,
                        'number' => $play->number,
                        'position' => $play->position, // Posición apostada
                        'import' => $play->import,
                        'aciert' => $totalPrize,
                        'times_won' => $timesWon, // ✅ Agregar times_won
                        'date' => $date,
                        'time' => $extract->time,
                        'user_id' => $play->user_id,
                        'XA' => 'X',
                        'numero_g' => $winningInfo['winningNumber'] ?? null, // Número ganador real
                        'posicion_g' => $winningInfo['winningPosition'] ?? null, // Posición real donde salió
                        'numR' => $play->numberR ?? null,
                        'posR' => $play->positionR ?? null,
                        'num_g_r' => null,
                        'pos_g_r' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    if ($result) {
                        $resultsInserted++;
                        Log::info("AutoExtractNumbers - ✅ Resultado insertado: Ticket {$play->ticket} - Premio: \${$totalPrize} - Veces: {$timesWon}");
                        // Notificar ganador encontrado
                        if ($winningInfo['winningNumber'] ?? null) {
                            $this->notifyWinner($play, $totalPrize, $winningInfo['winningNumber'], $winningInfo['winningPosition']);
                        }
                    }
                }
            }
            
            if ($resultsInserted > 0) {
                Log::info("AutoExtractNumbers - Se insertaron {$resultsInserted} resultados para lotería {$lotteryCode}");
            }
            
        } catch (\Exception $e) {
            Log::error("AutoExtractNumbers - Error calculando resultados: " . $e->getMessage());
        }
    }
    
    /**
     * ✅ Busca en qué posición REAL salió el número apostado dentro de los 20 números completos
     * Busca en TODOS los 20 números sin importar la posición apostada
     */
    private function findWinningNumberAndPosition($play, $completeNumbers)
    {
        $playedNumber = str_replace('*', '', $play->number);
        $playedDigits = strlen($playedNumber);
        
        if ($playedDigits <= 0 || $playedDigits > 4) {
            return null;
        }
        
        // ✅ Buscar en TODOS los 20 números completos sin filtrar por posición
        // La verificación de posición se hace después con isPositionCorrect
        foreach ($completeNumbers as $number) {
            // Verificar si los números coinciden
            $winner4 = str_pad((string)$number->value, 4, '0', STR_PAD_LEFT);
            $winningLastDigits = substr($winner4, -$playedDigits);
            
            if ($playedNumber === $winningLastDigits) {
                return [
                    'isWinner' => true,
                    'winningNumber' => $number->value,
                    'winningPosition' => $number->index // Posición REAL donde salió
                ];
            }
        }
        
        return null;
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
     * Calcula el premio para una jugada ganadora
     */
    private function calculatePrize($play, $winningNumber, $quinielaPayouts)
    {
        $playedNumber = str_replace('*', '', $play->number);
        $playedDigits = strlen($playedNumber);
        
        if ($playedDigits > 0 && $playedDigits <= 4) {
            // Determinar el tipo de jugada según el formato
            $ticketType = $this->getTicketType($play->number);
            
            // Obtener todas las tablas de pagos
            $prizes = \App\Models\PrizesModel::first();
            $figureOne = \App\Models\FigureOneModel::first();
            $figureTwo = \App\Models\FigureTwoModel::first();
            
            $prizeMultiplier = 0;
            
            // Aplicar la tabla correcta según el tipo de jugada
            if ($ticketType === 'quiniela') {
                $prizeMultiplier = $quinielaPayouts->{"cobra_{$playedDigits}_cifra"} ?? 0;
            } elseif ($ticketType === 'prizes') {
                if ($play->position >= 1 && $play->position <= 5) {
                    $prizeMultiplier = $prizes->cobra_5 ?? 0;
                } elseif ($play->position >= 6 && $play->position <= 10) {
                    $prizeMultiplier = $prizes->cobra_10 ?? 0;
                } elseif ($play->position >= 11 && $play->position <= 20) {
                    $prizeMultiplier = $prizes->cobra_20 ?? 0;
                }
            } elseif ($ticketType === 'figureOne') {
                if ($play->position >= 1 && $play->position <= 5) {
                    $prizeMultiplier = $figureOne->cobra_5 ?? 0;
                } elseif ($play->position >= 6 && $play->position <= 10) {
                    $prizeMultiplier = $figureOne->cobra_10 ?? 0;
                } elseif ($play->position >= 11 && $play->position <= 20) {
                    $prizeMultiplier = $figureOne->cobra_20 ?? 0;
                }
            } elseif ($ticketType === 'figureTwo') {
                if ($play->position >= 1 && $play->position <= 5) {
                    $prizeMultiplier = $figureTwo->cobra_5 ?? 0;
                } elseif ($play->position >= 6 && $play->position <= 10) {
                    $prizeMultiplier = $figureTwo->cobra_10 ?? 0;
                } elseif ($play->position >= 11 && $play->position <= 20) {
                    $prizeMultiplier = $figureTwo->cobra_20 ?? 0;
                }
            }
            
            Log::info("AutoExtractNumbers - Tipo: {$ticketType}, Posición: {$play->position}, Multiplicador: {$prizeMultiplier}x");
            return (float) $play->import * (float) $prizeMultiplier;
        }
        
        return 0;
    }
    
    /**
     * Determina el tipo de jugada según el formato del número
     */
    private function getTicketType(string $ticket): ?string
    {
        $asteriskCount = strlen($ticket) - strlen(ltrim($ticket, '*'));
        $clean = ltrim($ticket, '*');
        $digitCount = strlen($clean);

        if ($asteriskCount === 3) {
            return 'quiniela';        // ***123
        } elseif ($asteriskCount === 2 && $digitCount === 2) {
            return 'prizes';          // **12
        } elseif ($asteriskCount === 1 && $digitCount === 3) {
            return 'figureOne';       // *123
        } elseif ($asteriskCount === 0 && $digitCount === 4) {
            return 'figureTwo';       // 1234
        }
        
        return null;
    }
    
    
    /**
     * Notifica cuando se encuentra un ganador
     */
    private function notifyWinner($play, $totalPrize, $winningNumber, $position)
    {
        try {
            // Notificación del sistema eliminada
            // Solo registrar en log
            Log::info("🎉 ¡GANADOR ENCONTRADO! Ticket: {$play->ticket} - Lotería: {$play->lottery} - Número: {$play->number} - Premio: $" . number_format($totalPrize, 2));
            
        } catch (\Exception $e) {
            Log::error("Error notificando ganador: " . $e->getMessage());
        }
    }

    /**
     * ✅ NUEVO: Procesa loterías completas después de la extracción
     */
    private function processCompleteLotteries($date)
    {
        try {
            // ✅ CORRECCIÓN: Si hay loterías completas, procesarlas inmediatamente
            Log::info("AutoExtractNumbers - Verificando loterías completas para {$date}");

            // Obtener solo las loterías que tengan sus 20 números completos
            $completeLotteries = LotteryCompletenessService::getCompleteLotteries($date);

            if (empty($completeLotteries)) {
                Log::info("AutoExtractNumbers - No hay loterías completas para procesar en {$date}");
                return;
            }

            Log::info("AutoExtractNumbers - Loterías completas encontradas: " . implode(', ', $completeLotteries));
            $this->info("🎯 Procesando loterías completas: " . implode(', ', $completeLotteries));

            $totalResultsInserted = 0;
            $totalPrizeAmount = 0;

            // Procesar cada lotería completa
            foreach ($completeLotteries as $lotteryCode) {
                $result = $this->processCompleteLottery($lotteryCode, $date);
                $totalResultsInserted += $result['resultsInserted'];
                $totalPrizeAmount += $result['totalPrize'];
            }

            if ($totalResultsInserted > 0) {
                $this->info("✅ Procesamiento completado: {$totalResultsInserted} resultados insertados - Total: $" . number_format($totalPrizeAmount, 2));
                Log::info("AutoExtractNumbers - Procesamiento completado: {$totalResultsInserted} resultados - Total: $" . number_format($totalPrizeAmount, 2));
            } else {
                Log::info("AutoExtractNumbers - No se encontraron jugadas ganadoras en las loterías completas");
            }

        } catch (\Exception $e) {
            Log::error("AutoExtractNumbers - Error procesando loterías completas: " . $e->getMessage());
        }
    }

    /**
     * ✅ NUEVO: Procesa una lotería completa (con sus 20 números)
     */
    private function processCompleteLottery($lotteryCode, $date)
    {
        try {
            // ✅ CORRECCIÓN: Si la lotería está completa, procesar inmediatamente
            Log::info("AutoExtractNumbers - Procesando lotería completa: {$lotteryCode} para {$date}");

            // Obtener todos los números ganadores de esta lotería completa
            $completeNumbers = LotteryCompletenessService::getCompleteLotteryNumbersCollection($lotteryCode, $date);
            
            if (!$completeNumbers) {
                Log::warning("AutoExtractNumbers - No se pudieron obtener los números completos para {$lotteryCode}");
                return ['resultsInserted' => 0, 'totalPrize' => 0];
            }

            // Buscar jugadas que puedan ser ganadoras con esta lotería completa (excluyendo anuladas)
            $plays = \App\Models\ApusModel::whereDate('created_at', $date)
                ->whereRaw('FIND_IN_SET(?, lottery)', [$lotteryCode])
                ->whereHas('playsSent', function($query) {
                    $query->where('status', '!=', 'I'); // Excluir jugadas anuladas
                })
                ->get();

            if ($plays->isEmpty()) {
                Log::info("AutoExtractNumbers - No hay jugadas para la lotería completa {$lotteryCode}");
                return ['resultsInserted' => 0, 'totalPrize' => 0];
            }

            Log::info("AutoExtractNumbers - Procesando lotería completa: {$lotteryCode} - {$plays->count()} jugadas");

            $resultsInserted = 0;
            $totalPrize = 0;

            // Obtener configuraciones de premios
            $quinielaPayouts = \App\Models\QuinielaModel::first();
            
            // Para cada jugada, usar la misma lógica que NumberObserver
            foreach ($plays as $play) {
                // Usar el mismo método que NumberObserver para calcular premio y times_won
                $prizeData = $this->calculatePrizeForLotteryCompleteWithCount($play, $completeNumbers, $lotteryCode, $quinielaPayouts);
                $prize = $prizeData['prize'];
                $timesWon = $prizeData['times_won'];
                $winningInfo = $prizeData['winningInfo'] ?? null;
                
                if ($prize > 0 && $winningInfo) {
                    // Verificar si ya existe este resultado específico para esta lotería
                    $existingResult = \App\Models\Result::where('ticket', $play->ticket)
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
                            'numero_g' => $winningInfo['winningNumber'] ?? null,
                            'posicion_g' => $winningInfo['winningPosition'] ?? null,
                            'date' => $date,
                            'time' => $completeNumbers->first()->extract->time,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];

                        $result = \App\Services\ResultManager::createResultSafely($resultData);
                        if ($result) {
                            $resultsInserted++;
                            $totalPrize += $prize;
                            Log::info("AutoExtractNumbers - Resultado insertado: Ticket {$play->ticket} - Lotería {$lotteryCode} - Premio: {$prize} - Veces: {$timesWon}");
                            
                            // Notificar ganador encontrado
                            if ($winningInfo['winningNumber'] ?? null) {
                                $this->notifyWinner($play, $prize, $winningInfo['winningNumber'], $winningInfo['winningPosition']);
                            }
                        }
                    }
                }
            }

            if ($resultsInserted > 0) {
                Log::info("AutoExtractNumbers - Lotería {$lotteryCode} completada: {$resultsInserted} resultados - Total: $" . number_format($totalPrize, 2));
            }

            return ['resultsInserted' => $resultsInserted, 'totalPrize' => $totalPrize];

        } catch (\Exception $e) {
            Log::error("AutoExtractNumbers - Error procesando lotería completa {$lotteryCode}: " . $e->getMessage());
            return ['resultsInserted' => 0, 'totalPrize' => 0];
        }
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
                // A los 5: posiciones 1-5 (ahora incluye posición 1)
                $allowedIndexes = range(1, 5);
                break;
            case 10:
                // A los 10: posiciones 1-10 (ahora incluye posición 1)
                $allowedIndexes = range(1, 10);
                break;
            case 20:
                // A los 20: posiciones 1-20 (ahora incluye posición 1)
                $allowedIndexes = range(1, 20);
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
                    $mainPrize = $this->calculateMainPrize($play, $number->value);
                    break; // Solo calcular para el primer número que coincida
                }
            }
        }

        return $mainPrize + $redoblonaPrize;
    }

    /**
     * ✅ NUEVO: Calcula el premio para una lotería completa contando múltiples apariciones
     * Usa la misma lógica que NumberObserver
     */
    private function calculatePrizeForLotteryCompleteWithCount($play, $completeNumbers, $lotteryCode, $quinielaPayouts)
    {
        $mainPrize = 0;
        $redoblonaPrize = 0;
        $timesWon = 1; // Por defecto 1 vez
        $winningInfo = null;

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
            // Posición 5: busca de 1-5 (ahora incluye posición 1)
            // Posición 10: busca de 1-10 (ahora incluye posición 1)
            // Posición 20: busca de 1-20 (ahora incluye posición 1)
            $allowedIndexes = [];
            
            switch ($playedPosition) {
                case 1:
                    // Quiniela: solo posición 1
                    $allowedIndexes = [1];
                    break;
                case 5:
                    // A los 5: posiciones 1-5 (ahora incluye posición 1)
                    $allowedIndexes = range(1, 5);
                    break;
                case 10:
                    // A los 10: posiciones 1-10 (ahora incluye posición 1)
                    $allowedIndexes = range(1, 10);
                    break;
                case 20:
                    // A los 20: posiciones 1-20 (ahora incluye posición 1)
                    $allowedIndexes = range(1, 20);
                    break;
                default:
                    // Para otras posiciones específicas, solo esa posición
                    $allowedIndexes = [$playedPosition];
            }

            // ✅ NUEVA LÓGICA: Contar cuántas veces sale el número en el rango válido
            $winningCount = 0;
            $winningPositions = [];
            
            foreach ($completeNumbers as $number) {
                if (!in_array((int)$number->index, $allowedIndexes)) {
                    continue;
                }
                
                // Verificar números directamente
                $winningNumberStr = str_pad($number->value, 4, '0', STR_PAD_LEFT);
                $winningSuffix = substr($winningNumberStr, -strlen($playedNumber));
                $numbersMatch = $playedNumber === $winningSuffix;
                
                // Verificar posición
                $positionCorrect = $this->isPositionCorrect($playedPosition, $number->index);
                
                if ($numbersMatch && $positionCorrect) {
                    $winningCount++;
                    $winningPositions[] = $number->index;
                    // Guardar el primer número ganador para winningInfo
                    if ($winningCount == 1) {
                        $winningInfo = [
                            'winningNumber' => $number->value,
                            'winningPosition' => $number->index
                        ];
                    }
                }
            }

            if ($winningCount > 0) {
                $timesWon = $winningCount; // Guardar el conteo de veces que salió
                // Posición 1 usa tabla Quiniela según dígitos apostados
                if ($playedPosition === 1) {
                    $mult = 0;
                    if ($playedDigits == 1) $mult = (float)($quinielaPayouts->cobra_1_cifra ?? 0);
                    elseif ($playedDigits == 2) $mult = (float)($quinielaPayouts->cobra_2_cifra ?? 0);
                    elseif ($playedDigits == 3) $mult = (float)($quinielaPayouts->cobra_3_cifra ?? 0);
                    elseif ($playedDigits == 4) $mult = (float)($quinielaPayouts->cobra_4_cifra ?? 0);
                    // Para posición 1, no se multiplica por veces (solo puede salir una vez)
                    $mainPrize = $play->import * $mult;
                } else {
                    // ✅ NUEVA LÓGICA: Para posiciones 5, 10, 20 usar pago base de la posición JUGADA
                    // Determinar tabla según dígitos
                    if ($playedDigits == 1 || $playedDigits == 2) {
                        $payoutTable = \App\Models\PrizesModel::first();
                    } elseif ($playedDigits == 3) {
                        $payoutTable = \App\Models\FigureOneModel::first();
                    } else { // 4 dígitos
                        $payoutTable = \App\Models\FigureTwoModel::first();
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

        return [
            'prize' => $mainPrize + $redoblonaPrize,
            'times_won' => $timesWon,
            'winningInfo' => $winningInfo
        ];
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
}
