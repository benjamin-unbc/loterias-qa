<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Number;
use App\Services\WinningNumbersService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AutoUpdateController extends Controller
{
    /**
     * Verifica si hay nuevos números disponibles y los inserta automáticamente
     */
    public function checkNewNumbers(Request $request)
    {
        try {
            $todayDate = Carbon::today()->toDateString();
            
            // Verificar si ya hay números para hoy
            $existingCount = Number::where('date', $todayDate)->count();
            
            // Si ya hay números, solo verificar si hay actualizaciones
            if ($existingCount > 0) {
                return response()->json([
                    'hasNewNumbers' => false,
                    'message' => 'Números ya actualizados para hoy',
                    'existingCount' => $existingCount
                ]);
            }
            
            // Si no hay números, intentar extraerlos
            $winningNumbersService = new WinningNumbersService();
            $availableCities = $winningNumbersService->getAvailableCities();
            
            $totalInserted = 0;
            $hasNewData = false;
            
            foreach ($availableCities as $cityName) {
                try {
                    $cityData = $winningNumbersService->extractWinningNumbers($cityName);
                    
                    if ($cityData && !empty($cityData['turns'])) {
                        foreach ($cityData['turns'] as $turnName => $numbers) {
                            if (!empty($numbers)) {
                                $result = $this->insertCityNumbersToDatabase($cityName, $turnName, $numbers, $todayDate);
                                $totalInserted += $result['inserted'];
                                
                                if ($result['inserted'] > 0) {
                                    $hasNewData = true;
                                }
                            }
                        }
                    }
                } catch (\Exception $e) {
                    Log::error("Error en auto-check para {$cityName}: " . $e->getMessage());
                }
            }
            
            return response()->json([
                'hasNewNumbers' => $hasNewData,
                'message' => $hasNewData ? "Se insertaron {$totalInserted} números nuevos" : "No hay números nuevos disponibles",
                'insertedCount' => $totalInserted
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error en checkNewNumbers: ' . $e->getMessage());
            
            return response()->json([
                'hasNewNumbers' => false,
                'message' => 'Error al verificar números: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Inserta números de una ciudad en la base de datos
     */
    private function insertCityNumbersToDatabase($cityName, $turnName, $numbers, $date)
    {
        $inserted = 0;
        
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
                return ['inserted' => 0];
            }
            
            // Buscar la ciudad en la BD
            $city = \App\Models\City::where('code', 'LIKE', $cityCode . '%')
                       ->where('extract_id', $extractId)
                       ->first();
            
            if (!$city) {
                return ['inserted' => 0];
            }
            
            // Insertar cada número
            foreach ($numbers as $index => $number) {
                $position = $index + 1;
                
                // Verificar si ya existe
                $existingNumber = Number::where('city_id', $city->id)
                                      ->where('index', $position)
                                      ->where('date', $date)
                                      ->first();
                
                if (!$existingNumber) {
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
        
        return ['inserted' => $inserted];
    }
}
