<?php

namespace App\Console\Commands;

use App\Models\City;
use App\Models\Number;
use App\Services\WinningNumbersService;
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

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $interval = (int) $this->option('interval');
        $this->info("🔄 Iniciando extracción automática cada {$interval} segundos (detección rápida)...");
        $this->info("📅 Fecha actual: " . Carbon::now()->format('Y-m-d H:i:s'));
        $this->info("⏰ Horario de funcionamiento: 10:25 AM - 12:00 AM");
        $this->info("⏹️  Presiona Ctrl+C para detener");
        
        Log::info("AutoExtractNumbers - Iniciando extracción automática cada {$interval} segundos");

        while (true) {
            try {
                // Verificar si estamos en horario de funcionamiento
                if ($this->isWithinOperatingHours()) {
                    $this->extractNumbers();
                    $this->info("⏰ Esperando {$interval} segundos... (" . Carbon::now()->format('H:i:s') . ")");
                } else {
                    $this->line("😴 Fuera del horario de funcionamiento (10:25 AM - 12:00 AM). Esperando...");
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
                $turnMapping['Matutina'] = 4; // Matutina de Montevideo va a Vespertina (extract_id 4)
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
     * Horario: 10:25 AM - 12:00 AM (00:00)
     */
    private function isWithinOperatingHours()
    {
        $now = Carbon::now();
        $currentTime = $now->format('H:i:s');
        
        // Horario de funcionamiento: 10:25:00 - 23:59:59
        $startTime = '10:25:00';
        $endTime = '23:59:59';
        
        return $currentTime >= $startTime && $currentTime <= $endTime;
    }

    /**
     * Calcula resultados inmediatamente después de insertar un número ganador
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
            
            // Mapeo de códigos de ciudad a códigos de lotería
            $cityToLotteryMapping = [
                'BUE' => 'BUE',
                'COR' => 'COR', 
                'SFE' => 'SFE',
                'PRO' => 'PRO',
                'RIO' => 'RIO',
                'CTE' => 'CTE',
                'CHA' => 'CHA',
                'NQN' => 'NQN',
                'MIS' => 'MIS',
                'MZA' => 'MZA',
                'Rio' => 'Rio',
                'Tucu' => 'Tucu',
                'San' => 'San',
                'JUJ' => 'JUJ',
                'Salt' => 'Salt',
                'ORO' => 'ORO',
                'SLU' => 'SLU',
                'CHU' => 'CHU',
                'FOR' => 'FOR',
                'CAT' => 'CAT',
                'SJU' => 'SJU',
                'NAC' => 'NAC'
            ];
            
            $lotteryCode = $cityToLotteryMapping[$city->code] ?? null;
            if (!$lotteryCode) {
                Log::warning("No se encontró mapeo de lotería para ciudad: {$city->code}");
                return;
            }
            
            // Buscar jugadas que coincidan con este número ganador
            $matchingPlays = \App\Models\ApusModel::whereDate('created_at', $date)
                                                 ->where('position', $position)
                                                 ->where('lottery', $lotteryCode)
                                                 ->get();
            
            Log::info("AutoExtractNumbers - Encontradas " . $matchingPlays->count() . " jugadas para posición {$position} y lotería {$lotteryCode}");
            
            // Obtener configuraciones de premios
            $quinielaPayouts = \App\Models\QuinielaModel::first();
            $redoblona1toX = \App\Models\BetCollectionRedoblonaModel::first();
            $redoblona5to20 = \App\Models\BetCollection5To20Model::first();
            $redoblona10to20 = \App\Models\BetCollection10To20Model::first();
            
            if (!$quinielaPayouts || !$redoblona1toX || !$redoblona5to20 || !$redoblona10to20) {
                Log::error("No se encontraron configuraciones de premios");
                return;
            }
            
            $resultsInserted = 0;
            
            // Para cada jugada que coincida, calcular acierto
            foreach ($matchingPlays as $play) {
                // Calcular premio de jugada principal
                $aciertoValue = 0;
                $redoblonaValue = 0;
                
                if ($this->isWinningPlay($play, $winningNumber)) {
                    $aciertoValue = $this->calculatePrize($play, $winningNumber, $quinielaPayouts);
                }
                
                // Calcular premio de redoblona si existe
                if (!empty($play->numberR) && !empty($play->positionR)) {
                    $redoblonaValue = $this->calculateRedoblonaPrize($play, $date, $lotteryCode, $redoblona1toX, $redoblona5to20, $redoblona10to20);
                }
                
                $totalPrize = $aciertoValue + $redoblonaValue;
                
                if ($totalPrize > 0) {
                    // Verificar si ya existe este resultado para evitar duplicados
                    $existingResult = \App\Models\Result::where('ticket', $play->ticket)
                                                       ->where('lottery', $play->lottery)
                                                       ->where('number', $play->number)
                                                       ->where('position', $play->position)
                                                       ->where('date', $date)
                                                       ->first();
                    
                    if (!$existingResult) {
                        // Insertar resultado inmediatamente
                        \App\Models\Result::create([
                            'ticket' => $play->ticket,
                            'lottery' => $play->lottery,
                            'number' => $play->number,
                            'position' => $play->position,
                            'import' => $play->import,
                            'aciert' => $totalPrize,
                            'date' => $date,
                            'time' => $extract->time,
                            'user_id' => $play->user_id,
                            'XA' => 'X',
                            'numero_g' => $winningNumber,
                            'posicion_g' => $position,
                            'numR' => $play->numberR ?? null,
                            'posR' => $play->positionR ?? null,
                            'num_g_r' => null, // Se llenará si hay redoblona ganadora
                            'pos_g_r' => null, // Se llenará si hay redoblona ganadora
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                        
                        $resultsInserted++;
                        Log::info("AutoExtractNumbers - Resultado insertado: Ticket {$play->ticket} - Premio principal: \${$aciertoValue} - Premio redoblona: \${$redoblonaValue} - Total: \${$totalPrize}");
                        
                        // Notificar ganador encontrado
                        $this->notifyWinner($play, $totalPrize, $winningNumber, $position);
                    }
                }
            }
            
            if ($resultsInserted > 0) {
                Log::info("AutoExtractNumbers - Se insertaron {$resultsInserted} resultados para número ganador {$winningNumber}");
            }
            
        } catch (\Exception $e) {
            Log::error("AutoExtractNumbers - Error calculando resultados: " . $e->getMessage());
        }
    }
    
    /**
     * Verifica si una jugada es ganadora
     */
    private function isWinningPlay($play, $winningNumber)
    {
        $playedNumber = str_replace('*', '', $play->number);
        $playedDigits = strlen($playedNumber);
        
        if ($playedDigits > 0 && $playedDigits <= 4) {
            $winningLastDigits = substr($winningNumber, -$playedDigits);
            return $playedNumber === $winningLastDigits;
        }
        
        return false;
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
     * Calcula el premio de redoblona para una jugada
     */
    private function calculateRedoblonaPrize($play, $date, $lotteryCode, $redoblona1toX, $redoblona5to20, $redoblona10to20)
    {
        try {
            // Obtener todos los números ganadores del día para esta lotería
            $winningNumbers = \App\Models\Number::whereDate('date', $date)
                ->whereHas('city', function($query) use ($lotteryCode) {
                    $query->where('code', $lotteryCode);
                })
                ->with('city', 'extract')
                ->get()
                ->keyBy(function ($item) {
                    $time = str_replace(':', '', $item->extract->time);
                    return $item->city->code . $time . '_' . $item->index;
                });

            if ($winningNumbers->isEmpty()) {
                return 0;
            }

            $pos1 = min((int)$play->position, (int)$play->positionR);
            $pos2 = max((int)$play->position, (int)$play->positionR);
            
            $num1 = ($play->position < $play->positionR) ? $play->number : $play->numberR;
            $num2 = ($play->position < $play->positionR) ? $play->numberR : $play->number;

            // Redoblonas son siempre 2 cifras
            $num1 = str_pad(str_replace('*', '', $num1), 2, '0', STR_PAD_LEFT);
            $num2 = str_pad(str_replace('*', '', $num2), 2, '0', STR_PAD_LEFT);

            $key1 = $lotteryCode . '_' . $pos1;
            $key2 = $lotteryCode . '_' . $pos2;

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
                    
                    // Ajustar el multiplicador según el importe de la apuesta
                    $adjustedMultiplier = $this->getAdjustedRedoblonaMultiplier($play->import, $prizeMultiplier, $redoblona1toX, $redoblona5to20, $redoblona10to20);
                    $redoblonaValue = (float) $play->import * (float) $adjustedMultiplier;
                    Log::info("AutoExtractNumbers - Redoblona ganadora: Pos {$pos1}-{$pos2}, Números {$num1}-{$num2}, Multiplicador: {$prizeMultiplier}x, Premio: \${$redoblonaValue}");
                    return $redoblonaValue;
                }
            }
            
            return 0;
        } catch (\Exception $e) {
            Log::error("AutoExtractNumbers - Error calculando redoblona: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Ajusta el multiplicador de redoblona según el importe de la apuesta
     */
    private function getAdjustedRedoblonaMultiplier($import, $baseMultiplier, $redoblona1toX, $redoblona5to20, $redoblona10to20)
    {
        // Buscar el multiplicador correcto según el importe de la apuesta
        $redoblonaTables = [
            $redoblona1toX,
            $redoblona5to20, 
            $redoblona10to20
        ];
        
        foreach ($redoblonaTables as $table) {
            if ($table->bet_amount == $import) {
                // Encontrar el campo correcto según el multiplicador base
                if ($baseMultiplier == $redoblona1toX->payout_1_to_5 || 
                    $baseMultiplier == $redoblona1toX->payout_1_to_10 || 
                    $baseMultiplier == $redoblona1toX->payout_1_to_20) {
                    if ($baseMultiplier == $redoblona1toX->payout_1_to_5) return $table->payout_1_to_5;
                    if ($baseMultiplier == $redoblona1toX->payout_1_to_10) return $table->payout_1_to_10;
                    if ($baseMultiplier == $redoblona1toX->payout_1_to_20) return $table->payout_1_to_20;
                } elseif ($baseMultiplier == $redoblona5to20->payout_5_to_5 || 
                         $baseMultiplier == $redoblona5to20->payout_5_to_10 || 
                         $baseMultiplier == $redoblona5to20->payout_5_to_20) {
                    if ($baseMultiplier == $redoblona5to20->payout_5_to_5) return $table->payout_5_to_5;
                    if ($baseMultiplier == $redoblona5to20->payout_5_to_10) return $table->payout_5_to_10;
                    if ($baseMultiplier == $redoblona5to20->payout_5_to_20) return $table->payout_5_to_20;
                } elseif ($baseMultiplier == $redoblona10to20->payout_10_to_10 || 
                         $baseMultiplier == $redoblona10to20->payout_10_to_20 || 
                         $baseMultiplier == $redoblona10to20->payout_20_to_20) {
                    if ($baseMultiplier == $redoblona10to20->payout_10_to_10) return $table->payout_10_to_10;
                    if ($baseMultiplier == $redoblona10to20->payout_10_to_20) return $table->payout_10_to_20;
                    if ($baseMultiplier == $redoblona10to20->payout_20_to_20) return $table->payout_20_to_20;
                }
            }
        }
        
        // Si no se encuentra una coincidencia exacta, usar el multiplicador base
        return $baseMultiplier;
    }
    
    /**
     * Notifica cuando se encuentra un ganador
     */
    private function notifyWinner($play, $totalPrize, $winningNumber, $position)
    {
        try {
            // Crear notificación del sistema
            \App\Models\SystemNotification::create([
                'title' => '🎉 ¡GANADOR ENCONTRADO!',
                'message' => "Ticket: {$play->ticket} - Lotería: {$play->lottery} - Número: {$play->number} - Premio: $" . number_format($totalPrize, 2),
                'type' => 'winner',
                'data' => json_encode([
                    'ticket' => $play->ticket,
                    'lottery' => $play->lottery,
                    'number' => $play->number,
                    'position' => $play->position,
                    'winning_number' => $winningNumber,
                    'winning_position' => $position,
                    'prize' => $totalPrize,
                    'user_id' => $play->user_id
                ]),
                'is_read' => false,
                'created_at' => now(),
                'updated_at' => now()
            ]);
            
            Log::info("🎉 GANADOR NOTIFICADO: Ticket {$play->ticket} - Premio: $" . number_format($totalPrize, 2));
            
        } catch (\Exception $e) {
            Log::error("Error notificando ganador: " . $e->getMessage());
        }
    }
}
