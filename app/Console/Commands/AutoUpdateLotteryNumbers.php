<?php

namespace App\Console\Commands;

use App\Models\City;
use App\Models\Number;
use App\Models\SystemNotification;
use App\Models\User;
use App\Services\WinningNumbersService;
use App\Services\RedoblonaService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class AutoUpdateLotteryNumbers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'lottery:auto-update {--force : Forzar actualización incluso si ya hay números para hoy}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Actualiza automáticamente los números ganadores de lotería desde vivitusuerte.com';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔄 Iniciando actualización automática de números ganadores...');
        
        $force = $this->option('force');
        $todayDate = Carbon::today()->toDateString();
        
        // Verificar si estamos en horario de funcionamiento (a menos que sea forzado)
        if (!$force && !$this->isWithinOperatingHours()) {
            $this->info("😴 Fuera del horario de funcionamiento (10:30 AM - 23:59 PM). No se ejecutará la actualización.");
            return;
        }
        
        // Verificar si ya hay números para hoy (a menos que sea forzado)
        if (!$force) {
            $existingCount = Number::where('date', $todayDate)->count();
            if ($existingCount > 0) {
                $this->info("ℹ️  Ya existen {$existingCount} números para {$todayDate}. Verificando números faltantes por ciudad/turno...");
                
                // Verificar si hay ciudades/turnos sin números completos
                $missingNumbers = $this->checkMissingNumbers($todayDate);
                if (empty($missingNumbers)) {
                    $this->info("✅ Todos los números están completos para {$todayDate}. Procesando números existentes para calcular resultados...");
                    // No retornar, continuar para procesar números existentes
                } else {
                    $this->info("⚠️  Se encontraron números faltantes en: " . implode(', ', $missingNumbers));
                }
            }
        }
        
        try {
            $winningNumbersService = new WinningNumbersService();
            $availableCities = $winningNumbersService->getAvailableCities();
            
            $totalInserted = 0;
            $totalUpdated = 0;
            $errors = [];
            
            $this->info("🏙️  Procesando " . count($availableCities) . " ciudades...");
            
            foreach ($availableCities as $cityName) {
                try {
                    $this->line("📍 Procesando: {$cityName}");
                    
                    // Extraer números ganadores para esta ciudad
                    $cityData = $winningNumbersService->extractWinningNumbers($cityName);
                    
                    if (!$cityData) {
                        $this->warn("⚠️  No se obtuvieron datos para: {$cityName}");
                        continue;
                    }
                    
                    // Verificar si se saltó por fecha anterior
                    if (isset($cityData['skipped']) && $cityData['skipped']) {
                        $this->line("⏭️  {$cityName}: Saltado - {$cityData['reason']}");
                        continue;
                    }
                    
                    if (empty($cityData['turns'])) {
                        $this->warn("⚠️  No se obtuvieron turnos para: {$cityName}");
                        continue;
                    }
                    
                    // Insertar números para cada turno
                    foreach ($cityData['turns'] as $turnName => $numbers) {
                        if (empty($numbers)) {
                            continue; // Saltar turnos sin números
                        }
                        
                        $result = $this->insertCityNumbersToDatabase($cityName, $turnName, $numbers, $todayDate);
                        $totalInserted += $result['inserted'];
                        $totalUpdated += $result['updated'];
                        
                        if ($result['inserted'] > 0 || $result['updated'] > 0) {
                            $this->info("  ✅ {$turnName}: " . count($numbers) . " números procesados");
                        }
                    }
                    
                } catch (\Exception $e) {
                    $errors[] = "Error en {$cityName}: " . $e->getMessage();
                    $this->error("❌ Error en {$cityName}: " . $e->getMessage());
                }
            }
            
            // Si no se insertaron números nuevos, procesar números existentes para calcular resultados
            if ($totalInserted === 0 && $totalUpdated === 0) {
                $this->info("🔄 No hay números nuevos. Procesando números existentes para calcular resultados...");
                $this->processExistingNumbersForResults($todayDate);
            }
            
            // Crear notificación del sistema
            if ($totalInserted > 0 || $totalUpdated > 0) {
                $this->info("🎉 Actualización completada exitosamente!");
                $this->info("📊 Números nuevos: {$totalInserted}");
                $this->info("🔄 Números actualizados: {$totalUpdated}");
                $this->info("📅 Fecha: {$todayDate}");
                
                // Crear notificación de éxito
                SystemNotification::createNotification(
                    'success',
                    '🤖 Búsqueda Automática Completada',
                    "Se buscaron resultados ganadores y se insertaron {$totalInserted} números nuevos. Sistema funcionando correctamente.",
                    [
                        'inserted' => $totalInserted,
                        'updated' => $totalUpdated,
                        'date' => $todayDate,
                        'cities_processed' => count($availableCities)
                    ]
                );
                
                Log::info("Auto-update completado: {$totalInserted} nuevos, {$totalUpdated} actualizados");
            } else {
                $this->info("ℹ️  No se encontraron números nuevos para procesar.");
                
                // Crear notificación informativa
                SystemNotification::createNotification(
                    'info',
                    '🤖 Búsqueda Automática Realizada',
                    "Se intentó buscar actualizar números ganadores pero no se encontraron resultados. Esperando al próximo turno para ingresar números.",
                    [
                        'inserted' => 0,
                        'updated' => 0,
                        'date' => $todayDate,
                        'cities_processed' => count($availableCities)
                    ]
                );
            }
            
            // Mostrar errores si los hay
            if (!empty($errors)) {
                $this->error("❌ Errores encontrados:");
                foreach ($errors as $error) {
                    $this->error("  - {$error}");
                }
            }
            
        } catch (\Exception $e) {
            $this->error("💥 Error fatal: " . $e->getMessage());
            Log::error("Error en auto-update: " . $e->getMessage());
            return 1;
        }
        
        return 0;
    }
    
    /**
     * Inserta números de una ciudad en la base de datos
     */
    private function insertCityNumbersToDatabase($cityName, $turnName, $numbers, $date)
    {
        $inserted = 0;
        $updated = 0;
        
        try {
            // Mapear nombres de ciudades a códigos de BD (maneja tanto mayúsculas como formato correcto)
            $cityMapping = [
                'CIUDAD' => 'NAC',
                'Ciudad' => 'NAC',
                'SANTA FE' => 'SFE',
                'Santa Fé' => 'SFE',
                'PROVINCIA' => 'PRO',
                'Provincia' => 'PRO',
                'ENTRE RIOS' => 'RIO',
                'Entre Ríos' => 'RIO',
                'CORDOBA' => 'COR',
                'Córdoba' => 'COR',
                'CORRIENTES' => 'CTE',
                'Corrientes' => 'CTE',
                'CHACO' => 'CHA',
                'Chaco' => 'CHA',
                'NEUQUEN' => 'NQN',
                'Neuquén' => 'NQN',
                'MISIONES' => 'MIS',
                'Misiones' => 'MIS',
                'MENDOZA' => 'MZA',
                'Mendoza' => 'MZA',
                'RÍO NEGRO' => 'Rio',
                'Río Negro' => 'Rio',
                'TUCUMAN' => 'Tucu',
                'Tucuman' => 'Tucu',
                'Tucumán' => 'Tucu',
                'SANTIAGO' => 'San',
                'Santiago' => 'San',
                'JUJUY' => 'JUJ',
                'Jujuy' => 'JUJ',
                'SALTA' => 'Salt',
                'Salta' => 'Salt',
                'MONTEVIDEO' => 'ORO',
                'Montevideo' => 'ORO',
                'SAN LUIS' => 'SLU',
                'San Luis' => 'SLU',
                'CHUBUT' => 'CHU',
                'Chubut' => 'CHU',
                'FORMOSA' => 'FOR',
                'Formosa' => 'FOR',
                'CATAMARCA' => 'CAT',
                'Catamarca' => 'CAT',
                'SAN JUAN' => 'SJU',
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
                Log::warning("No se encontró mapeo para: {$cityName} - {$turnName}");
                return ['inserted' => 0, 'updated' => 0];
            }
            
            // Buscar la ciudad en la BD
            $city = City::where('code', 'LIKE', $cityCode . '%')
                       ->where('extract_id', $extractId)
                       ->first();
            
            if (!$city) {
                Log::warning("No se encontró ciudad en BD: {$cityCode} - extract_id: {$extractId}");
                return ['inserted' => 0, 'updated' => 0];
            }
            
            // Insertar cada número
            foreach ($numbers as $index => $number) {
                $position = $index + 1; // Las posiciones van de 1 a 20
                
                // NUEVA LÓGICA: Solo insertar el número 1 (cabeza) cuando estén los 20 números completos
                if ($position === 1) {
                    // Para el número 1, solo lo insertamos si ya tenemos los 20 números
                    $currentCount = Number::where('city_id', $city->id)
                                         ->where('date', $date)
                                         ->count();
                    
                    if ($currentCount < 19) {
                        // Aún no tenemos suficientes números, saltamos el número 1
                        continue;
                    }
                }
                
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
                    
                    // NUEVO: Calcular resultados inmediatamente después de insertar número
                    Log::info("AutoUpdateLotteryNumbers - Llamando calculateResultsForNumber para {$cityName} - Pos {$position} - Número {$number}");
                    $this->calculateResultsForNumber($city, $extractId, $position, $date, $number);
                }
            }
            
            // NUEVA LÓGICA: Solo notificar cabeza cuando estén los 20 números completos
            $totalNumbers = Number::where('city_id', $city->id)
                                 ->where('date', $date)
                                 ->count();
            
            if ($totalNumbers >= 20) {
                // Recién ahora verificar si hay número de cabeza para notificar
                $headNumber = Number::where('city_id', $city->id)
                                   ->where('date', $date)
                                   ->where('index', 1)
                                   ->first();
                
                if ($headNumber) {
                    // Verificar si ya se notificó esta cabeza para evitar spam
                    $existingNotification = \App\Models\SystemNotification::where('type', 'success')
                        ->where('data->type', 'head_number')
                        ->where('data->city', $cityName)
                        ->where('data->turn', $turnName)
                        ->where('data->number', $headNumber->value)
                        ->where('data->date', $date)
                        ->where('created_at', '>=', now()->subMinutes(5)) // Solo en los últimos 5 minutos
                        ->first();
                    
                    if (!$existingNotification) {
                        $this->createHeadNumberNotification($cityName, $turnName, $headNumber->value, $date, 'oficial');
                    }
                }
            }
            
        } catch (\Exception $e) {
            Log::error("Error insertando números para {$cityName} - {$turnName}: " . $e->getMessage());
        }
        
        return ['inserted' => $inserted, 'updated' => $updated];
    }


    /**
     * Procesa números existentes para calcular resultados
     */
    private function processExistingNumbersForResults($date)
    {
        try {
            $this->info("🔍 Buscando números existentes para procesar...");
            
            // Obtener todos los números de hoy
            $existingNumbers = Number::whereDate('date', $date)
                ->with('city')
                ->get();
            
            if ($existingNumbers->isEmpty()) {
                $this->info("ℹ️  No hay números existentes para procesar.");
                return;
            }
            
            $this->info("📊 Encontrados " . $existingNumbers->count() . " números existentes.");
            
            $processedCount = 0;
            
            foreach ($existingNumbers as $number) {
                $city = $number->city;
                if ($city) {
                    $this->calculateResultsForNumber($city, $number->extract_id, $number->index, $date, $number->value);
                    $processedCount++;
                }
            }
            
            $this->info("✅ Procesados {$processedCount} números existentes para calcular resultados.");
            
        } catch (\Exception $e) {
            $this->error("❌ Error procesando números existentes: " . $e->getMessage());
            Log::error("AutoUpdateLotteryNumbers - Error procesando números existentes: " . $e->getMessage());
        }
    }

    /**
     * Verifica si la hora actual está dentro del horario de funcionamiento
     * Horario: 24/7 (funciona siempre)
     */
    private function isWithinOperatingHours()
    {
        // Funciona 24/7 - siempre retorna true
        return true;
    }
    
    /**
     * Verifica si hay números faltantes por ciudad/turno
     */
    private function checkMissingNumbers($date)
    {
        $missingNumbers = [];
        
        try {
            // Obtener todas las ciudades disponibles
            $winningNumbersService = new WinningNumbersService();
            $availableCities = $winningNumbersService->getAvailableCities();
            
            // Mapear nombres de ciudades a códigos de BD (maneja tanto mayúsculas como formato correcto)
            $cityMapping = [
                'CIUDAD' => 'NAC',
                'Ciudad' => 'NAC',
                'SANTA FE' => 'SFE',
                'Santa Fé' => 'SFE',
                'PROVINCIA' => 'PRO',
                'Provincia' => 'PRO',
                'ENTRE RIOS' => 'RIO',
                'Entre Ríos' => 'RIO',
                'CORDOBA' => 'COR',
                'Córdoba' => 'COR',
                'CORRIENTES' => 'CTE',
                'Corrientes' => 'CTE',
                'CHACO' => 'CHA',
                'Chaco' => 'CHA',
                'NEUQUEN' => 'NQN',
                'Neuquén' => 'NQN',
                'MISIONES' => 'MIS',
                'Misiones' => 'MIS',
                'MENDOZA' => 'MZA',
                'Mendoza' => 'MZA',
                'RÍO NEGRO' => 'Rio',
                'Río Negro' => 'Rio',
                'TUCUMAN' => 'Tucu',
                'Tucuman' => 'Tucu',
                'Tucumán' => 'Tucu',
                'SANTIAGO' => 'San',
                'Santiago' => 'San',
                'JUJUY' => 'JUJ',
                'Jujuy' => 'JUJ',
                'SALTA' => 'Salt',
                'Salta' => 'Salt',
                'MONTEVIDEO' => 'ORO',
                'Montevideo' => 'ORO',
                'SAN LUIS' => 'SLU',
                'San Luis' => 'SLU',
                'CHUBUT' => 'CHU',
                'Chubut' => 'CHU',
                'FORMOSA' => 'FOR',
                'Formosa' => 'FOR',
                'CATAMARCA' => 'CAT',
                'Catamarca' => 'CAT',
                'SAN JUAN' => 'SJU',
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
            
            foreach ($availableCities as $cityName) {
                // Mapeo especial para Montevideo
                if ($cityName === 'Montevideo') {
                    $turnMapping['Matutina'] = 4; // Matutina de Montevideo va a Vespertina (extract_id 4)
                }
                $cityCode = $cityMapping[$cityName] ?? null;
                if (!$cityCode) continue;
                
                // Definir los turnos a verificar según la ciudad
                if (in_array($cityName, ['Jujuy', 'Salta'])) {
                    $turns = ['Primera', 'Matutina', 'Vespertina', 'Nocturna'];
                } else {
                    $turns = ['La Previa', 'Primera', 'Matutina', 'Vespertina', 'Nocturna'];
                }
                
                foreach ($turns as $turnName) {
                    $extractId = $turnMapping[$turnName] ?? null;
                    if (!$extractId) continue;
                    
                    // Buscar la ciudad en la BD
                    $city = City::where('code', 'LIKE', $cityCode . '%')
                               ->where('extract_id', $extractId)
                               ->first();
                    
                    if (!$city) continue;
                    
                    // Verificar si tiene 20 números para esta fecha
                    $numbersCount = Number::where('city_id', $city->id)
                                         ->where('date', $date)
                                         ->count();
                    
                    if ($numbersCount < 20) {
                        $missingNumbers[] = "{$cityName} - {$turnName} ({$numbersCount}/20)";
                    }
                }
            }
            
        } catch (\Exception $e) {
            Log::error("Error verificando números faltantes: " . $e->getMessage());
        }
        
        return $missingNumbers;
    }
    
    /**
     * Crea una notificación específica para números ganadores de cabeza
     */
    private function createHeadNumberNotification($cityName, $turnName, $number, $date, $action)
    {
        try {
            $actionText = $action === 'oficial' ? 'oficial confirmado' : $action;
            $emoji = $action === 'oficial' ? '🎯' : '🔄';
            
            $title = "{$emoji} Número de Cabeza {$actionText}";
            $message = $action === 'oficial' 
                ? "Número oficial de cabeza {$number} confirmado para el turno {$turnName} de la ciudad {$cityName} (20 números completos)"
                : "Se ha {$actionText} el resultado {$number} en el turno {$turnName} de la ciudad {$cityName}";
            
            SystemNotification::createNotification(
                'success',
                $title,
                $message,
                [
                    'type' => 'head_number',
                    'city' => $cityName,
                    'turn' => $turnName,
                    'number' => $number,
                    'date' => $date,
                    'action' => $action,
                    'position' => 1,
                    'is_official' => $action === 'oficial'
                ]
            );
            
            Log::info("Notificación de cabeza creada: {$cityName} - {$turnName} - {$number} ({$action})");
            
        } catch (\Exception $e) {
            Log::error("Error creando notificación de cabeza: " . $e->getMessage());
        }
    }

    /**
     * Calcula resultados inmediatamente después de insertar un número ganador
     */
    private function calculateResultsForNumber($city, $extractId, $position, $date, $winningNumber)
    {
        try {
            Log::info("AutoUpdateLotteryNumbers - Calculando resultados para: {$city->code} - Pos {$position} - Número {$winningNumber}");
            
            // Obtener el extract para el tiempo
            $extract = \App\Models\Extract::find($extractId);
            if (!$extract) {
                Log::warning("No se encontró extract con ID: {$extractId}");
                return;
            }
            
            // Usar el código completo de la ciudad (ya incluye el turno)
            // Ejemplo: CHA1800, NAC1500, TUCU2200, etc.
            $lotteryCode = $city->code;
            
            Log::info("AutoUpdateLotteryNumbers - Usando código completo de lotería: {$lotteryCode}");
            
            // Buscar jugadas que coincidan con este número ganador
            // Las jugadas se guardan con códigos como "JUJ1800,PRO1500" etc.
            // Ahora busca por el código completo (ej: CHA1800, TUCU2200)
            $matchingPlays = \App\Models\PlaysSentModel::whereDate('created_at', $date)
                                                      ->where('lot', 'LIKE', "%{$lotteryCode}%")
                                                      ->get();
            
            Log::info("AutoUpdateLotteryNumbers - Encontradas " . $matchingPlays->count() . " jugadas para posición {$position} y lotería {$lotteryCode}");
            Log::info("AutoUpdateLotteryNumbers - Buscando jugadas con lot LIKE '%{$lotteryCode}%' para fecha {$date}");
            
            // Mostrar detalles de las jugadas encontradas
            foreach ($matchingPlays as $play) {
                Log::info("AutoUpdateLotteryNumbers - Jugada encontrada: Ticket {$play->ticket}, Código: {$play->code}, Loterías: {$play->lot}");
            }
            
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
                    $aciertoValue = $this->calculatePrize($play, $winningNumber, $quinielaPayouts, $position);
                }
                
                // Calcular premio de redoblona si existe (PlaysSentModel no tiene redoblona)
                $redoblonaValue = 0;
                
                $totalPrize = $aciertoValue + $redoblonaValue;
                
                if ($totalPrize > 0) {
                    // Verificar si ya existe este resultado para evitar duplicados
                    $existingResult = \App\Models\Result::where('ticket', $play->ticket)
                                                       ->where('lottery', $play->lot)
                                                       ->where('number', $play->code) // PlaysSentModel usa 'code' en lugar de 'number'
                                                       ->where('position', $position)
                                                       ->where('date', $date)
                                                       ->first();
                    
                    if (!$existingResult) {
                        // Insertar resultado inmediatamente
                        \App\Models\Result::create([
                            'ticket' => $play->ticket,
                            'lottery' => $play->lot,
                            'number' => $play->code, // PlaysSentModel usa 'code'
                            'position' => $position,
                            'import' => $play->amount,
                            'aciert' => $totalPrize,
                            'date' => $date,
                            'time' => $extract->time,
                            'user_id' => $play->user_id,
                            'XA' => 'X',
                            'numero_g' => $winningNumber,
                            'posicion_g' => $position,
                            'numR' => null, // PlaysSentModel no tiene redoblona
                            'posR' => null, // PlaysSentModel no tiene redoblona
                            'num_g_r' => null,
                            'pos_g_r' => null,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                        
                        $resultsInserted++;
                        Log::info("AutoUpdateLotteryNumbers - Resultado insertado: Ticket {$play->ticket} - Premio principal: \${$aciertoValue} - Premio redoblona: \${$redoblonaValue} - Total: \${$totalPrize}");
                    }
                }
            }
            
            if ($resultsInserted > 0) {
                Log::info("AutoUpdateLotteryNumbers - Se insertaron {$resultsInserted} resultados para número ganador {$winningNumber}");
            }
            
        } catch (\Exception $e) {
            Log::error("AutoUpdateLotteryNumbers - Error calculando resultados: " . $e->getMessage());
        }
    }
    
    /**
     * Verifica si una jugada es ganadora
     */
    private function isWinningPlay($play, $winningNumber)
    {
        // PlaysSentModel ahora usa 'code' que contiene el número de quiniela (4 dígitos)
        $playedNumber = str_replace('*', '', $play->code);
        $playedDigits = strlen($playedNumber);
        
        Log::info("AutoUpdateLotteryNumbers - Verificando jugada: {$playedNumber} vs número ganador: {$winningNumber}");
        
        if ($playedDigits > 0 && $playedDigits <= 4) {
            $winningLastDigits = substr($winningNumber, -$playedDigits);
            $isWinner = $playedNumber === $winningLastDigits;
            Log::info("AutoUpdateLotteryNumbers - Coincidencia: {$playedNumber} === {$winningLastDigits} = " . ($isWinner ? 'SÍ' : 'NO'));
            return $isWinner;
        }
        
        return false;
    }
    
    /**
     * Calcula el premio para una jugada ganadora
     */
    private function calculatePrize($play, $winningNumber, $quinielaPayouts, $position)
    {
        // PlaysSentModel ahora usa 'code' que contiene el número de quiniela (4 dígitos)
        $playedNumber = str_replace('*', '', $play->code);
        $playedDigits = strlen($playedNumber);
        
        Log::info("AutoUpdateLotteryNumbers - Calculando premio para: {$playedNumber} (4 dígitos)");
        
        if ($playedDigits > 0 && $playedDigits <= 4) {
            // Determinar el tipo de jugada según el formato
            $ticketType = $this->getTicketType($play->code);
            
            // Obtener todas las tablas de pagos
            $prizes = \App\Models\PrizesModel::first();
            $figureOne = \App\Models\FigureOneModel::first();
            $figureTwo = \App\Models\FigureTwoModel::first();
            
            $prizeMultiplier = 0;
            
            // Aplicar la tabla correcta según el tipo de jugada
            if ($ticketType === 'quiniela') {
                $prizeMultiplier = $quinielaPayouts->{"cobra_{$playedDigits}_cifra"} ?? 0;
            } elseif ($ticketType === 'prizes') {
                // Usar la posición del número ganador que se está procesando
                if ($position >= 1 && $position <= 5) {
                    $prizeMultiplier = $prizes->cobra_5 ?? 0;
                } elseif ($position >= 6 && $position <= 10) {
                    $prizeMultiplier = $prizes->cobra_4 ?? 0;
                } elseif ($position >= 11 && $position <= 15) {
                    $prizeMultiplier = $prizes->cobra_3 ?? 0;
                } elseif ($position >= 16 && $position <= 19) {
                    $prizeMultiplier = $prizes->cobra_2 ?? 0;
                } else {
                    $prizeMultiplier = $prizes->cobra_1 ?? 0;
                }
            } elseif ($ticketType === 'figureOne') {
                if ($position >= 1 && $position <= 5) {
                    $prizeMultiplier = $figureOne->cobra_5 ?? 0;
                } elseif ($position >= 6 && $position <= 10) {
                    $prizeMultiplier = $figureOne->cobra_4 ?? 0;
                } elseif ($position >= 11 && $position <= 15) {
                    $prizeMultiplier = $figureOne->cobra_3 ?? 0;
                } elseif ($position >= 16 && $position <= 19) {
                    $prizeMultiplier = $figureOne->cobra_2 ?? 0;
                } else {
                    $prizeMultiplier = $figureOne->cobra_1 ?? 0;
                }
            } elseif ($ticketType === 'figureTwo') {
                if ($position >= 1 && $position <= 5) {
                    $prizeMultiplier = $figureTwo->cobra_5 ?? 0;
                } elseif ($position >= 6 && $position <= 10) {
                    $prizeMultiplier = $figureTwo->cobra_4 ?? 0;
                } elseif ($position >= 11 && $position <= 15) {
                    $prizeMultiplier = $figureTwo->cobra_3 ?? 0;
                } elseif ($position >= 16 && $position <= 19) {
                    $prizeMultiplier = $figureTwo->cobra_2 ?? 0;
                } else {
                    $prizeMultiplier = $figureTwo->cobra_1 ?? 0;
                }
            }
            
            Log::info("AutoUpdateLotteryNumbers - Tipo: {$ticketType}, Posición: {$position}, Multiplicador: {$prizeMultiplier}x");
            return (float) $play->amount * (float) $prizeMultiplier;
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
                    Log::info("AutoUpdateLotteryNumbers - Redoblona ganadora: Pos {$pos1}-{$pos2}, Números {$num1}-{$num2}, Multiplicador: {$prizeMultiplier}x, Premio: \${$redoblonaValue}");
                    return $redoblonaValue;
                }
            }
            
            return 0;
        } catch (\Exception $e) {
            Log::error("AutoUpdateLotteryNumbers - Error calculando redoblona: " . $e->getMessage());
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
}