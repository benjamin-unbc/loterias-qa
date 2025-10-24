<?php

namespace App\Livewire;

use App\Services\WinningNumbersService;
use App\Models\Number;
use App\Models\City;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Livewire\Component;

class WinningNumbersInterface extends Component
{
    public $selectedCity = 'Ciudad';
    public $winningNumbers = null;
    public $loading = false;
    public $error = '';
    public $lastUpdate = '';
    public $autoRefresh = true;
    public $refreshInterval = 300; // 5 minutos

    public function mount()
    {
        // Cargar datos automáticamente al montar
        $this->loadWinningNumbers();
    }

    public function selectCity($city)
    {
        $this->selectedCity = $city;
        $this->loadWinningNumbers();
    }

    public function loadWinningNumbers()
    {
        $this->loading = true;
        $this->error = '';
        $this->winningNumbers = null;

        try {
            $service = new WinningNumbersService();
            $this->winningNumbers = $service->extractWinningNumbers($this->selectedCity);
            
            if (!$this->winningNumbers) {
                $this->error = 'No se pudieron cargar los números ganadores para ' . $this->selectedCity;
            } else {
                $this->lastUpdate = now()->format('H:i:s');
                
                // Guardar automáticamente los números en la base de datos
                $this->saveNumbersToDatabase();
            }
            
        } catch (\Exception $e) {
            $this->error = 'Error al cargar números ganadores: ' . $e->getMessage();
        }

        $this->loading = false;
    }

    public function refreshData()
    {
        $this->loadWinningNumbers();
    }

    public function toggleAutoRefresh()
    {
        $this->autoRefresh = !$this->autoRefresh;
        $this->emit('autoRefreshToggled');
    }

    public function getAvailableCities()
    {
        $service = new WinningNumbersService();
        return $service->getAvailableCities();
    }

    public function getTurnDisplayName($turn)
    {
        $turnNames = [
            'La Previa' => 'La Previa (09:00-11:00)',
            'Primera' => 'El Primero (11:00-13:00)',
            'Matutina' => 'Matutina (13:00-15:00)',
            'Vespertina' => 'Vespertina (15:00-17:00)',
            'Nocturna' => 'Nocturna (17:00-19:00)'
        ];

        return $turnNames[$turn] ?? $turn;
    }

    /**
     * Guarda los números ganadores en la base de datos
     */
    private function saveNumbersToDatabase()
    {
        if (!$this->winningNumbers || empty($this->winningNumbers['turns'])) {
            return;
        }

        $todayDate = Carbon::today()->toDateString();
        $totalInserted = 0;

        try {
            foreach ($this->winningNumbers['turns'] as $turnName => $numbers) {
                if (!empty($numbers)) {
                    $result = $this->insertCityNumbersToDatabase($this->selectedCity, $turnName, $numbers, $todayDate);
                    $totalInserted += $result['inserted'];
                }
            }

            if ($totalInserted > 0) {
                Log::info("WinningNumbersInterface - Se guardaron {$totalInserted} números para {$this->selectedCity}");
            }

        } catch (\Exception $e) {
            Log::error("WinningNumbersInterface - Error guardando números: " . $e->getMessage());
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
                Log::warning("WinningNumbersInterface - No se encontró mapeo para {$cityName} - {$turnName}");
                return ['inserted' => 0];
            }
            
            // Buscar la ciudad en la BD
            $city = City::where('code', 'LIKE', $cityCode . '%')
                       ->where('extract_id', $extractId)
                       ->first();
            
            if (!$city) {
                Log::warning("WinningNumbersInterface - No se encontró ciudad en BD: {$cityCode} - extract_id: {$extractId}");
                return ['inserted' => 0];
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
                        Log::info("WinningNumbersInterface - Número actualizado: {$cityName} - {$turnName} - Pos {$position}: {$number}");
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
                    Log::info("WinningNumbersInterface - Número insertado: {$cityName} - {$turnName} - Pos {$position}: {$number}");
                }
            }
            
        } catch (\Exception $e) {
            Log::error("WinningNumbersInterface - Error insertando números para {$cityName} - {$turnName}: " . $e->getMessage());
        }
        
        return ['inserted' => $inserted];
    }

    public function render()
    {
        return view('livewire.winning-numbers-interface')
            ->layout('layouts.app');
    }
}
