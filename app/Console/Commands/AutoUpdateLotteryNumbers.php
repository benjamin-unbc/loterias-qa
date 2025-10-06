<?php

namespace App\Console\Commands;

use App\Models\City;
use App\Models\Number;
use App\Models\SystemNotification;
use App\Models\User;
use App\Services\WinningNumbersService;
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
                    $this->info("✅ Todos los números están completos para {$todayDate}.");
                    return;
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
            
            foreach ($availableCities as $cityName) {
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
}