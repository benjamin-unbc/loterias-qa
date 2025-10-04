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
                $this->info("ℹ️  Ya existen {$existingCount} números para {$todayDate}. Usa --force para actualizar de todos modos.");
                return;
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
            
        } catch (\Exception $e) {
            Log::error("Error insertando números para {$cityName} - {$turnName}: " . $e->getMessage());
        }
        
        return ['inserted' => $inserted, 'updated' => $updated];
    }

    /**
     * Verifica si la hora actual está dentro del horario de funcionamiento
     * Horario: 10:30 AM - 23:59 PM
     */
    private function isWithinOperatingHours()
    {
        $now = Carbon::now();
        $currentTime = $now->format('H:i:s');
        
        // Horario de funcionamiento: 10:30:00 - 23:59:59
        $startTime = '10:30:00';
        $endTime = '23:59:59';
        
        return $currentTime >= $startTime && $currentTime <= $endTime;
    }
}