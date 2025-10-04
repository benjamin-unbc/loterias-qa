<?php

namespace App\Livewire\Admin;

use App\Models\City;
use App\Jobs\CalculateLotteryResults; // Importar el Job
use App\Models\Extract;
use App\Models\Number;
use App\Services\WinningNumbersService;
use Carbon\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log; // Añadir esta línea

class Extracts extends Component
{
    #[Layout('layouts.app')]

    public $cities;
    public $isAdmin;
    public $extracts;
    public $showApusModal = false;
    public $showFullExtract = false;
 // Nuevo modal para filtros
    public $action = '';
    public $filterDate;
    public $selectedDate;
    public $indexData;
    public $debugInfo = '';
    public $refreshInterval = 30; // 30 segundos
    public $lastAutoRefresh = null;
    
    // Filtros para administradores
    public $selectedCities = [];
    public $selectedExtracts = [];
    public $availableCities = [];
    public $availableExtracts = [];
    
    // Nuevos filtros jerárquicos
    public $selectedCityFilter = null; // Ciudad seleccionada para filtrar
    public $availableCityOptions = []; // Opciones de ciudades para el selector
    public $cityLotteries = []; // Loterías disponibles para la ciudad seleccionada
    public $selectedCityLotteries = []; // Loterías seleccionadas de la ciudad
    
    // Estado de filtros para verificar cambios
    public $savedCityFilter = null; // Ciudad guardada
    public $savedCityLotteries = []; // Loterías guardadas
    public $hasUnsavedChanges = false; // Indica si hay cambios sin guardar
    
    // Control global de todas las loterías
    public $allLotteries = []; // Todas las loterías de todas las ciudades
    public $selectedAllLotteries = []; // Loterías seleccionadas globalmente
    
    // Control de visibilidad de filtros
    public $showFilters = false; // Mostrar/ocultar sección de filtros

    public function mount()
    {
        // Mostrar por defecto los números de hoy (fecha actual)
        $this->filterDate = \Carbon\Carbon::today()->toDateString();
        $this->selectedDate = $this->filterDate; // Inicializar selectedDate
        // Ocultar ciudades específicas de la interfaz
        $hiddenCities = ['SAN LUIS', 'CHUBUT', 'FORMOSA', 'CATAMARCA', 'SAN JUAN'];
        $this->cities = City::with('numbers')
            ->whereNotIn('name', $hiddenCities)
            ->get();
        $this->isAdmin   = Auth::user()->hasRole('Administrador');
        $this->extracts  = Extract::all();
        
        // Inicializar filtros para administradores
        if ($this->isAdmin) {
            $this->initializeFilters();
        }
        
        $this->loadData(); // Cargar datos con la fecha inicial correcta
        
        // NO ejecutar búsqueda automática al ingresar - el usuario debe hacer clic en "Buscar" manualmente
    }

    public function searchDate()
    {
        // Solo cambiar la fecha y cargar datos existentes
        $this->selectedDate = $this->filterDate;
        $this->loadData();
        
        // No mostrar mensajes - el sistema trae datos automáticamente
    }

    /**
     * Refuerza la búsqueda automática cuando el usuario presiona "Buscar"
     * Solo para la fecha actual
     */
    public function reinforceAutomaticUpdate()
    {
        try {
            $todayDate = Carbon::today()->toDateString();
            
            // Verificar si ya hay números para hoy
            $existingCount = Number::where('date', $todayDate)->count();
            
            if ($existingCount > 0) {
                // Ya hay números, mostrar los existentes
                $this->dispatch('notify', message: "✅ Mostrando {$existingCount} números ganadores actualizados para hoy.", type: 'success');
                $this->selectedDate = $todayDate;
                $this->loadData();
                return;
            }
            
            // No hay números, intentar extraerlos desde la web
            $this->dispatch('notify', message: "No existen números ganadores. Intentando extraer desde la web...", type: 'warning');
            
            $winningNumbersService = new WinningNumbersService();
            $availableCities = $winningNumbersService->getAvailableCities();
            
            $totalInserted = 0;
            $errors = [];
            
            foreach ($availableCities as $cityName) {
                try {
                    $cityData = $winningNumbersService->extractWinningNumbers($cityName);
                    
                    if ($cityData && !empty($cityData['turns'])) {
                        foreach ($cityData['turns'] as $turnName => $numbers) {
                            if (!empty($numbers)) {
                                $result = $this->insertCityNumbersToDatabase($cityName, $turnName, $numbers, $todayDate);
                                $totalInserted += $result['inserted'];
                            }
                        }
                    }
                } catch (\Exception $e) {
                    $errors[] = "Error en {$cityName}: " . $e->getMessage();
                }
            }
            
            if ($totalInserted > 0) {
                $this->dispatch('notify', message: "✅ Extracción exitosa! Se insertaron {$totalInserted} números nuevos desde la web.", type: 'success');
            } else {
                $this->dispatch('notify', message: "⚠️ No se encontraron números ganadores en la web. Las loterías aún no han salido.", type: 'warning');
            }
            
            if (!empty($errors)) {
                $this->dispatch('notify', message: "Errores: " . implode(', ', $errors), type: 'error');
            }
            
            $this->selectedDate = $todayDate;
            $this->loadData();
            
        } catch (\Exception $e) {
            Log::error('Error en refuerzo automático: ' . $e->getMessage());
            $this->dispatch('notify', message: 'Error al reforzar búsqueda: ' . $e->getMessage(), type: 'error');
        }
    }

    /**
     * Detecta y muestra números nuevos que se han agregado en tiempo real
     * Siempre extrae desde la web y guarda en BD, independientemente de si están completos
     */
    public function detectAndShowNewNumbers()
    {
        try {
            $todayDate = Carbon::today()->toDateString();
            
            // SIEMPRE extraer números desde la web y guardarlos
            $this->dispatch('notify', message: "🔄 Extrayendo números ganadores desde la web...", type: 'info');
            
            $winningNumbersService = new WinningNumbersService();
            $availableCities = $winningNumbersService->getAvailableCities();
            
            $totalInserted = 0;
            $totalUpdated = 0;
            $errors = [];
            $extractedCities = [];
            
            foreach ($availableCities as $cityName) {
                try {
                    $cityData = $winningNumbersService->extractWinningNumbers($cityName);
                    
                    if ($cityData && !empty($cityData['turns'])) {
                        $cityInserted = 0;
                        $cityUpdated = 0;
                        
                        foreach ($cityData['turns'] as $turnName => $numbers) {
                            if (!empty($numbers)) {
                                $result = $this->insertCityNumbersToDatabase($cityName, $turnName, $numbers, $todayDate);
                                $cityInserted += $result['inserted'];
                                $cityUpdated += $result['updated'] ?? 0;
                            }
                        }
                        
                        if ($cityInserted > 0 || $cityUpdated > 0) {
                            $extractedCities[] = "{$cityName}: " . ($cityInserted + $cityUpdated) . " números";
                        }
                        
                        $totalInserted += $cityInserted;
                        $totalUpdated += $cityUpdated;
                    }
                } catch (\Exception $e) {
                    $errors[] = "Error en {$cityName}: " . $e->getMessage();
                    Log::error("Error extrayendo números para {$cityName}: " . $e->getMessage());
                }
            }
            
            // Cargar y mostrar los datos actualizados
            $this->selectedDate = $todayDate;
            $this->loadData();
            
            // Mostrar mensaje con resultados
            if ($totalInserted > 0 || $totalUpdated > 0) {
                $message = "✅ Extracción completada! ";
                if ($totalInserted > 0) $message .= "Nuevos: {$totalInserted}. ";
                if ($totalUpdated > 0) $message .= "Actualizados: {$totalUpdated}. ";
                if (!empty($extractedCities)) {
                    $message .= "Ciudades: " . implode(', ', $extractedCities);
                }
                
                $this->dispatch('notify', message: $message, type: 'success');
                Log::info("Extracts - detectAndShowNewNumbers: Extraídos {$totalInserted} nuevos, {$totalUpdated} actualizados para {$todayDate}");
                
            } else {
                $this->dispatch('notify', message: "⚠️ No se encontraron números nuevos en la web. Las loterías aún no han salido o no hay datos disponibles.", type: 'warning');
            }
            
            if (!empty($errors)) {
                $this->dispatch('notify', message: "Errores: " . implode(', ', $errors), type: 'error');
            }
            
        } catch (\Exception $e) {
            Log::error('Error detectando números nuevos: ' . $e->getMessage());
            $this->dispatch('notify', message: 'Error al detectar números: ' . $e->getMessage(), type: 'error');
        }
    }

    /**
     * Obtiene el nombre del turno basado en el extract_id
     */
    private function getTurnName($extractId)
    {
        $turnNames = [
            1 => 'La Previa',
            2 => 'Primera', 
            3 => 'Matutina',
            4 => 'Vespertina',
            5 => 'Nocturna'
        ];
        
        return $turnNames[$extractId] ?? 'Turno ' . $extractId;
    }

    public function resetFilters()
    {
        // Resetear a la fecha de hoy (que es donde se muestran los números actualizados)
        $today = Carbon::today()->toDateString();
        $this->filterDate   = $today;
        $this->selectedDate = $today;
        
        // Resetear filtros de ciudad y horarios si es administrador
        if ($this->isAdmin) {
            $this->initializeFilters();
            // Resetear filtros jerárquicos
            $this->selectedCityLotteries = [];
            $this->selectedCityFilter = null;
            $this->selectedAllLotteries = collect($this->allLotteries)->pluck('id')->toArray();
            $this->savedCityLotteries = [];
            $this->savedCityFilter = null;
            $this->hasUnsavedChanges = false;
        }
        
        $this->loadData();
    }
    
    /**
     * Inicializa los filtros con todas las opciones marcadas por defecto
     */
    public function initializeFilters()
    {
        // Obtener todas las ciudades disponibles (excluyendo las ocultas)
        $hiddenCities = ['SAN LUIS', 'CHUBUT', 'FORMOSA', 'CATAMARCA', 'SAN JUAN'];
        $this->availableCities = City::whereNotIn('name', $hiddenCities)
            ->select('id', 'name', 'code')
            ->get()
            ->toArray();
        
        // Obtener todos los extractos (horarios) disponibles
        $this->availableExtracts = Extract::select('id', 'name')
            ->get()
            ->toArray();
        
        // Inicializar con todas las opciones seleccionadas
        $this->selectedCities = collect($this->availableCities)->pluck('id')->toArray();
        $this->selectedExtracts = collect($this->availableExtracts)->pluck('id')->toArray();
        
        // Inicializar filtros jerárquicos
        $this->initializeHierarchicalFilters();
    }
    
    /**
     * Inicializa los filtros jerárquicos por ciudad
     */
    public function initializeHierarchicalFilters()
    {
        // Obtener ciudades únicas para el selector
        $hiddenCities = ['SAN LUIS', 'CHUBUT', 'FORMOSA', 'CATAMARCA', 'SAN JUAN'];
        $uniqueCities = City::whereNotIn('name', $hiddenCities)
            ->select('name')
            ->distinct()
            ->orderBy('name')
            ->get()
            ->pluck('name')
            ->toArray();
        
        $this->availableCityOptions = $uniqueCities;
        
        // Cargar todas las loterías de todas las ciudades
        $this->allLotteries = $this->loadAllLotteries();
        
        // Inicializar con todas las loterías seleccionadas
        $this->selectedAllLotteries = collect($this->allLotteries)->pluck('id')->toArray();
        
        // Inicializar con la primera ciudad seleccionada para el filtro
        if (!empty($this->availableCityOptions)) {
            $this->selectedCityFilter = $this->availableCityOptions[0];
            $this->loadCityLotteries();
        }
    }
    
    /**
     * Maneja el cambio de estado de los filtros de ciudad
     */
    public function toggleCityFilter($cityId)
    {
        if (!in_array($cityId, $this->selectedCities)) {
            $this->selectedCities[] = $cityId;
        } else {
            $this->selectedCities = array_diff($this->selectedCities, [$cityId]);
        }
        $this->loadData();
    }
    
    /**
     * Maneja el cambio de estado de los filtros de horarios
     */
    public function toggleExtractFilter($extractId)
    {
        if (!in_array($extractId, $this->selectedExtracts)) {
            $this->selectedExtracts[] = $extractId;
        } else {
            $this->selectedExtracts = array_diff($this->selectedExtracts, [$extractId]);
        }
        $this->loadData();
    }
    
    /**
     * Maneja el cambio de ciudad en el filtro jerárquico
     */
    public function updatedSelectedCityFilter()
    {
        $this->loadCityLotteries();
        
        // No auto-seleccionar loterías, mantener el estado actual
        // Solo cargar las loterías de la ciudad seleccionada para mostrarlas
        
        // Marcar que hay cambios sin guardar (solo si cambió la ciudad)
        $this->checkForChanges();
    }
    
    /**
     * Maneja el toggle de loterías globales (todas las ciudades)
     */
    public function toggleAllLotteryFilter($lotteryId)
    {
        if (!in_array($lotteryId, $this->selectedAllLotteries)) {
            $this->selectedAllLotteries[] = $lotteryId;
        } else {
            $this->selectedAllLotteries = array_diff($this->selectedAllLotteries, [$lotteryId]);
        }
        
        // Marcar que hay cambios sin guardar
        $this->checkForChanges();
    }
    
    /**
     * Toggle para mostrar/ocultar la sección de filtros
     */
    public function toggleFilters()
    {
        $this->showFilters = !$this->showFilters;
    }
    
    /**
     * Carga las loterías disponibles para la ciudad seleccionada
     */
    public function loadCityLotteries()
    {
        if (!$this->selectedCityFilter) {
            $this->cityLotteries = [];
            return;
        }
        
        // Obtener todas las loterías (ciudades) para la ciudad seleccionada
        $hiddenCities = ['SAN LUIS', 'CHUBUT', 'FORMOSA', 'CATAMARCA', 'SAN JUAN'];
        $this->cityLotteries = City::whereNotIn('name', $hiddenCities)
            ->where('name', $this->selectedCityFilter)
            ->select('id', 'name', 'code', 'extract_id')
            ->with('extract:id,name')
            ->get()
            ->map(function($city) {
                return [
                    'id' => $city->id,
                    'name' => $city->name,
                    'code' => $city->code,
                    'extract_id' => $city->extract_id,
                    'extract_name' => $city->extract->name,
                    'time' => $city->time ?? 'N/A'
                ];
            })
            ->toArray();
    }
    
    /**
     * Carga TODAS las loterías de TODAS las ciudades para control global
     */
    public function loadAllLotteries()
    {
        $hiddenCities = ['SAN LUIS', 'CHUBUT', 'FORMOSA', 'CATAMARCA', 'SAN JUAN'];
        $allLotteries = City::whereNotIn('name', $hiddenCities)
            ->select('id', 'name', 'code', 'extract_id')
            ->with('extract:id,name')
            ->get()
            ->map(function($city) {
                return [
                    'id' => $city->id,
                    'name' => $city->name,
                    'code' => $city->code,
                    'extract_id' => $city->extract_id,
                    'extract_name' => $city->extract->name,
                    'time' => $city->time ?? 'N/A'
                ];
            })
            ->toArray();
        
        return $allLotteries;
    }
    
    /**
     * Maneja el toggle de loterías específicas de la ciudad
     */
    public function toggleCityLotteryFilter($lotteryId)
    {
        if (!in_array($lotteryId, $this->selectedCityLotteries)) {
            $this->selectedCityLotteries[] = $lotteryId;
        } else {
            $this->selectedCityLotteries = array_diff($this->selectedCityLotteries, [$lotteryId]);
        }
        
        // Marcar que hay cambios sin guardar
        $this->checkForChanges();
    }
    
    /**
     * Verifica si hay cambios sin guardar
     */
    public function checkForChanges()
    {
        $lotteriesChanged = $this->selectedAllLotteries !== $this->savedCityLotteries;
        
        $this->hasUnsavedChanges = $lotteriesChanged;
    }
    
    /**
     * Guarda los filtros actuales y los aplica
     */
    public function saveFilters()
    {
        // Guardar el estado actual de todas las loterías
        $this->savedCityLotteries = $this->selectedAllLotteries;
        $this->hasUnsavedChanges = false;
        
        // Aplicar el filtro
        $this->applyCityFilter();
        
        // Mostrar notificación
        $this->dispatch('notify', message: 'Filtros guardados y aplicados correctamente.', type: 'success');
    }
    
    /**
     * Aplica el filtro de ciudad a los datos mostrados
     */
    public function applyCityFilter()
    {
        // Siempre cargar todos los datos
        $this->loadData();
        
        // Si no hay filtros guardados, mostrar todo
        if (empty($this->savedCityLotteries)) {
            return;
        }
        
        // Filtrar las loterías que NO están en la lista de guardadas
        $this->cities = $this->cities->filter(function($city) {
            // Solo mostrar las ciudades que están en la lista de guardadas
            return in_array($city->id, $this->savedCityLotteries);
        });
    }

    /**
     * Inserta números de una ciudad en la base de datos (para refuerzo)
     */
    private function insertCityNumbersToDatabase($cityName, $turnName, $numbers, $date)
    {
        $inserted = 0;
        $updated = 0;
        
        try {
            // Mapear nombres de ciudades y turnos a códigos exactos de BD
            $cityTurnMapping = [
                'Ciudad' => [
                    'La Previa' => 'NAC1015',
                    'Primera' => 'NAC1200',
                    'Matutina' => 'NAC1500',
                    'Vespertina' => 'NAC1800',
                    'Nocturna' => 'NAC2100'
                ],
                'Santa Fé' => [
                    'La Previa' => 'SFE1015',
                    'Primera' => 'SFE1200',
                    'Matutina' => 'SFE1500',
                    'Vespertina' => 'SFE1800',
                    'Nocturna' => 'SFE2100'
                ],
                'Provincia' => [
                    'La Previa' => 'PRO1015',
                    'Primera' => 'PRO1200',
                    'Matutina' => 'PRO1500',
                    'Vespertina' => 'PRO1800',
                    'Nocturna' => 'PRO2100'
                ],
                'Entre Ríos' => [
                    'La Previa' => 'RIO1015',
                    'Primera' => 'RIO1200',
                    'Matutina' => 'RIO1500',
                    'Vespertina' => 'RIO1800',
                    'Nocturna' => 'RIO2100'
                ],
                'Córdoba' => [
                    'La Previa' => 'COR1015',
                    'Primera' => 'COR1200',
                    'Matutina' => 'COR1500',
                    'Vespertina' => 'COR1800',
                    'Nocturna' => 'COR2100'
                ],
                'Corrientes' => [
                    'La Previa' => 'CTE1015',
                    'Primera' => 'CTE1200',
                    'Matutina' => 'CTE1500',
                    'Vespertina' => 'CTE1800',
                    'Nocturna' => 'CTE2100'
                ],
                'Chaco' => [
                    'La Previa' => 'CHA1015',
                    'Primera' => 'CHA1200',
                    'Matutina' => 'CHA1500',
                    'Vespertina' => 'CHA1800',
                    'Nocturna' => 'CHA2100'
                ],
                'Neuquén' => [
                    'La Previa' => 'NQN1015',
                    'Primera' => 'NQN1200',
                    'Matutina' => 'NQN1500',
                    'Vespertina' => 'NQN1800',
                    'Nocturna' => 'NQN2100'
                ],
                'Misiones' => [
                    'La Previa' => 'MIS1030',
                    'Primera' => 'MIS1215',
                    'Matutina' => 'MIS1500',
                    'Vespertina' => 'MIS1800',
                    'Nocturna' => 'MIS2115'
                ],
                'Mendoza' => [
                    'La Previa' => 'MZA1015',
                    'Primera' => 'MZA1200',
                    'Matutina' => 'MZA1500',
                    'Vespertina' => 'MZA1800',
                    'Nocturna' => 'MZA2100'
                ],
                'Río Negro' => [
                    'La Previa' => 'Rio1015',
                    'Primera' => 'Rio1200',
                    'Matutina' => 'Rio1500',
                    'Vespertina' => 'Rio1800',
                    'Nocturna' => 'Rio2100'
                ],
                'Tucumán' => [
                    'La Previa' => 'Tucu1130',
                    'Primera' => 'Tucu1430',
                    'Matutina' => 'Tucu1730',
                    'Vespertina' => 'Tucu1930',
                    'Nocturna' => 'Tucu2200'
                ],
                'Santiago' => [
                    'La Previa' => 'San1015',
                    'Primera' => 'San1200',
                    'Matutina' => 'San1500',
                    'Vespertina' => 'San1945',
                    'Nocturna' => 'San2200'
                ],
                'Jujuy' => [
                    'Primera' => 'JUJ1200',
                    'Matutina' => 'JUJ1500',
                    'Vespertina' => 'JUJ1800',
                    'Nocturna' => 'JUJ2100'
                ],
                'Salta' => [
                    'Primera' => 'Salt1130',
                    'Matutina' => 'Salt1400',
                    'Vespertina' => 'Salt1730',
                    'Nocturna' => 'Salt2100'
                ],
                'Montevideo' => [
                    'La Previa' => 'ORO1015',
                    'Primera' => 'ORO1500',
                    'Matutina' => 'ORO1500',
                    'Vespertina' => 'ORO1500',
                    'Nocturna' => 'ORO2100'
                ],
                'San Luis' => [
                    'La Previa' => 'SLU1015',
                    'Primera' => 'SLU1200',
                    'Matutina' => 'SLU1500',
                    'Vespertina' => 'SLU1800',
                    'Nocturna' => 'SLU2100'
                ],
                'Chubut' => [
                    'La Previa' => 'CHU1015',
                    'Primera' => 'CHU1200',
                    'Matutina' => 'CHU1500',
                    'Vespertina' => 'CHU1800',
                    'Nocturna' => 'CHU2100'
                ],
                'Formosa' => [
                    'La Previa' => 'FOR1015',
                    'Primera' => 'FOR1200',
                    'Matutina' => 'FOR1500',
                    'Vespertina' => 'FOR1800',
                    'Nocturna' => 'FOR2100'
                ],
                'Catamarca' => [
                    'La Previa' => 'CAT1015',
                    'Primera' => 'CAT1200',
                    'Matutina' => 'CAT1500',
                    'Vespertina' => 'CAT1800',
                    'Nocturna' => 'CAT2100'
                ],
                'San Juan' => [
                    'La Previa' => 'SJU1015',
                    'Primera' => 'SJU1200',
                    'Matutina' => 'SJU1500',
                    'Vespertina' => 'SJU1800',
                    'Nocturna' => 'SJU2100'
                ]
            ];
            
            $cityCode = $cityTurnMapping[$cityName][$turnName] ?? null;
            
            if (!$cityCode) {
                Log::warning("Extracts - No se encontró mapeo para {$cityName} - {$turnName}");
                return ['inserted' => 0, 'updated' => 0];
            }
            
            // Buscar la ciudad en la BD por código exacto
            $city = City::where('code', $cityCode)->first();
            
            if (!$city) {
                Log::warning("Extracts - No se encontró ciudad en BD: {$cityCode}");
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
                        Log::info("Extracts - Número actualizado: {$cityName} - {$turnName} - Pos {$position}: {$number}");
                    }
                } else {
                    // Crear nuevo número
                    Number::create([
                        'city_id' => $city->id,
                        'extract_id' => $city->extract_id,
                        'index' => $position,
                        'value' => $number,
                        'date' => $date
                    ]);
                    $inserted++;
                    Log::info("Extracts - Número insertado: {$cityName} - {$turnName} - Pos {$position}: {$number}");
                }
            }
            
        } catch (\Exception $e) {
            Log::error("Error insertando números para {$cityName} - {$turnName}: " . $e->getMessage());
        }
        
        return ['inserted' => $inserted, 'updated' => $updated];
    }

    public function loadData()
    {
        // Asegurarse que selectedDate tenga un valor antes de usarlo en la consulta.
        // Si selectedDate está vacío por alguna razón, usar la fecha de ayer como fallback.
        $dateForQuery = $this->selectedDate ?: Carbon::yesterday()->toDateString();
        
        Log::info("Extracts - Cargando datos para fecha: {$dateForQuery}");
        
        // Ocultar ciudades específicas de la interfaz
        $hiddenCities = ['SAN LUIS', 'CHUBUT', 'FORMOSA', 'CATAMARCA', 'SAN JUAN'];
        
        $query = City::with(['numbers' => function ($query) use ($dateForQuery) {
            $query->where('date', $dateForQuery);
        }])->whereNotIn('name', $hiddenCities);
        
        // Aplicar filtros si es administrador
        if ($this->isAdmin) {
            // Filtrar por ciudades seleccionadas
            if (!empty($this->selectedCities)) {
                $query->whereIn('id', $this->selectedCities);
            }
            
            // Filtrar por extractos (horarios) seleccionados
            if (!empty($this->selectedExtracts)) {
                $query->whereIn('extract_id', $this->selectedExtracts);
            }
        }
        
        $this->cities = $query->get();
        
        // Log para debug
        $totalNumbers = 0;
        foreach ($this->cities as $city) {
            $numbersCount = $city->numbers->count();
            $totalNumbers += $numbersCount;
            if ($numbersCount > 0) {
                Log::info("Extracts - Ciudad {$city->name} ({$city->code}): {$numbersCount} números para {$dateForQuery}");
            }
        }
        
        Log::info("Extracts - Total números cargados: {$totalNumbers} para fecha {$dateForQuery}");
    }

    public function storeNumber($cityId, $extractId, $index, $value)
    {
        $dateToStore = $this->filterDate ?: Carbon::today()->toDateString(); // Usar la fecha del filtro

        // Verificar si ya existe un registro para ese índice, ciudad y extract en la fecha actual.
        $existing = Number::where('city_id', $cityId)
            ->where('extract_id', $extractId)
            ->where('index', $index)
            ->where('date', $dateToStore)
            ->first();

        if ($existing) {
            $this->dispatch('notify', message: "El campo $index ya fue registrado para la fecha seleccionada.", type: 'warning');
            return;
        }

        // Crear el nuevo registro
        $this->indexData = Number::create([
            'city_id'    => $cityId,
            'extract_id' => $extractId,
            'index'      => $index,
            'value'      => $value,
            'date'       => $dateToStore,
        ]);

        // PREMIACIÓN INMEDIATA: Buscar jugadas premiadas y crear Result
        $city = \App\Models\City::find($cityId);
        if ($city) {
            $lotteryCode = $city->code;
            $cityInitial = substr($lotteryCode, 0, 1);
            \Log::info('Premiación rápida: buscando jugadas', [
                'lotteryCode' => $lotteryCode,
                'cityInitial' => $cityInitial,
                'index' => $index,
                'value' => $value,
                'date' => $dateToStore
            ]);
            $playsQuery = \App\Models\ApusModel::whereIn('lottery', [$lotteryCode, $cityInitial])
                ->where('position', $index)
                ->where('number', $value)
                ->where('timeApu', $city->time);
            
            // Filtrar por cliente si no es administrador
            if (!$this->isAdmin) {
                $playsQuery->where('user_id', auth()->id());
            }
            
            $plays = $playsQuery->get();
            \Log::info('Jugadas encontradas', ['count' => $plays->count(), 'ids' => $plays->pluck('id')]);
            $premiados = [];
            foreach ($plays as $play) {
                $resultData = [
                    'user_id'    => $play->user_id,
                    'ticket'     => $play->ticket,
                    'lottery'    => $play->lottery,
                    'number'     => $play->number,
                    'position'   => $play->position,
                    'numero_g'   => null,
                    'posicion_g' => null,
                    'numR'       => $play->numberR,
                    'posR'       => $play->positionR,
                    'num_g_r'    => null,
                    'pos_g_r'    => null,
                    'XA'         => null,
                    'import'     => $play->import,
                    'aciert'     => $play->import,
                    'date'       => isset($this->indexData) ? $this->indexData->date : $dateToStore,
                    'time'       => $play->timeApu,
                ];
                \Log::info('Intentando crear Result', $resultData);
                try {
                    $result = \App\Models\Result::create($resultData);
                    \Log::info('Result creado', ['id' => $result->id]);
                } catch (\Exception $e) {
                    \Log::error('Error al crear Result', ['error' => $e->getMessage()]);
                }
                $premiados[] = $play->ticket;
            }
            if (count($premiados) > 0) {
                $this->dispatch('notify', message: '¡Tickets premiados: ' . implode(', ', $premiados) . '!', type: 'success');
            }
        }

        // Actualizar los datos para refrescar la vista
        $this->loadData(); // Recargar datos para la fecha actual del filtro

    // Log para confirmar que se intenta despachar el Job
    Log::info("Extracts - Intentando despachar CalculateLotteryResults para fecha: " . $dateToStore);

        // Disparar el Job para calcular los resultados
        // CalculateLotteryResults::dispatch($dateToStore); // Desactivado para no borrar resultados inmediatos

        $this->dispatch('notify', message: "Valor registrado correctamente en el campo $index.", type: 'success');
    }

    public function printPDF()
    {
        $this->dispatch('printContent');
    }

    public function descargarImagen()
    {
        $this->dispatch('descargar-imagen');
    }

    public function showModal($action)
    {
        $this->action = $action; // Guardar la acción seleccionada
        $this->showApusModal = true;
    }

    public function toggleExtractView()
    {
        $this->showFullExtract = !$this->showFullExtract;
    }


    public function updateNumber($numeroId, $newValue)
    {
        $numero = Number::findOrFail($numeroId);
        $numero->value = $newValue;
        $numero->save();

        // PREMIACIÓN INMEDIATA: Buscar jugadas premiadas y crear Result
        $city = $numero->city;
        if ($city) {
            $lotteryCode = $city->code;
            $cityInitial = substr($lotteryCode, 0, 1);
            \Log::info('Premiación rápida (update): buscando jugadas', [
                'lotteryCode' => $lotteryCode,
                'cityInitial' => $cityInitial,
                'index' => $numero->index,
                'value' => $newValue,
                'date' => $numero->date
            ]);
            $playsQuery = \App\Models\ApusModel::whereIn('lottery', [$lotteryCode, $cityInitial])
                ->where('position', $numero->index)
                ->where('number', $newValue)
                ->where('timeApu', $city->time);
            
            // Filtrar por cliente si no es administrador
            if (!$this->isAdmin) {
                $playsQuery->where('user_id', auth()->id());
            }
            
            $plays = $playsQuery->get();
            \Log::info('Jugadas encontradas (update)', ['count' => $plays->count(), 'ids' => $plays->pluck('id')]);
            $premiados = [];
            foreach ($plays as $play) {
                $resultData = [
                    'user_id'    => $play->user_id,
                    'ticket'     => $play->ticket,
                    'lottery'    => $play->lottery,
                    'number'     => $play->number,
                    'position'   => $play->position,
                    'numero_g'   => null,
                    'posicion_g' => null,
                    'numR'       => $play->numberR,
                    'posR'       => $play->positionR,
                    'num_g_r'    => null,
                    'pos_g_r'    => null,
                    'XA'         => null,
                    'import'     => $play->import,
                    'aciert'     => $play->import,
                    'date'       => $numero->date,
                    'time'       => $play->timeApu,
                ];
                \Log::info('Intentando crear Result', $resultData);
                try {
                    $result = \App\Models\Result::create($resultData);
                    \Log::info('Result creado', ['id' => $result->id]);
                } catch (\Exception $e) {
                    \Log::error('Error al crear Result', ['error' => $e->getMessage()]);
                }
                $premiados[] = $play->ticket;
            }
            if (count($premiados) > 0) {
                $this->dispatch('notify', message: '¡Tickets premiados: ' . implode(', ', $premiados) . '!', type: 'success');
            }
        }

    // Log para confirmar que se intenta despachar el Job después de actualizar
    Log::info("Extracts - Intentando despachar CalculateLotteryResults (update) para fecha: " . $numero->date);

        // Disparar el Job después de actualizar también
        // CalculateLotteryResults::dispatch($numero->date); // Desactivado para no borrar resultados inmediatos

        $this->loadData(); // Recargar datos para asegurar que la vista se actualice si es necesario
        $this->dispatch('notify', message: 'Valor de extracto actualizado.', type: 'success');
    }



    /**
     * Procesa las tablas extraídas y extrae los números ganadores (MÉTODO LEGACY - YA NO SE USA)
     */
    private function processExtractedTables($content)
    {
        $extractedNumbers = [];
        
        // Buscar todas las tablas en el contenido HTML
        preg_match_all('/<table[^>]*>.*?<\/table>/s', $content, $tables);
        
        Log::info('Tablas encontradas:', ['count' => count($tables[0])]);
        
        foreach ($tables[0] as $tableIndex => $table) {
            Log::info("Procesando tabla {$tableIndex}:", ['table_html' => substr($table, 0, 500)]);
            
            $tableData = $this->parseTable($table);
            if (!empty($tableData)) {
                Log::info("Tabla {$tableIndex} parseada:", ['headers' => $tableData['headers'], 'rows_count' => count($tableData['rows'])]);
                
                // Mapear índice de tabla a extract_id (turno)
                $extractId = $this->mapTableIndexToExtractId($tableIndex);
                Log::info("Tabla {$tableIndex} mapeada a extract_id: {$extractId}");
                
                $numbers = $this->extractNumbersFromTable($tableData, $tableIndex, $extractId);
                $extractedNumbers = array_merge($extractedNumbers, $numbers);
                
                Log::info("Números extraídos de tabla {$tableIndex} (extract_id {$extractId}):", ['count' => count($numbers)]);
            } else {
                Log::info("Tabla {$tableIndex} vacía o no parseable");
            }
        }
        
        Log::info('Total números extraídos de todas las tablas:', ['count' => count($extractedNumbers)]);
        return $extractedNumbers;
    }

    /**
     * Mapea el índice de tabla al extract_id correcto
     */
    private function mapTableIndexToExtractId($tableIndex)
    {
        // Mapeo de índice de tabla a extract_id (turno)
        $tableToExtractMapping = [
            0 => 1, // Tabla 1 = PREVIA
            1 => 2, // Tabla 2 = PRIMERO  
            2 => 3, // Tabla 3 = MATUTINA
            3 => 4, // Tabla 4 = VESPERTINA
            4 => 5, // Tabla 5 = NOCTURNA
        ];
        
        return $tableToExtractMapping[$tableIndex] ?? 1; // Default a PREVIA si no se encuentra
    }

    /**
     * Parsea una tabla HTML y extrae sus datos
     */
    private function parseTable($table)
    {
        $headers = [];
        $rows = [];
        
        // Extraer headers (th)
        preg_match_all('/<th[^>]*>(.*?)<\/th>/s', $table, $headerMatches);
        foreach ($headerMatches[1] as $header) {
            $cleanHeader = str_replace(['<br>', '<br/>', '<br />'], ' ', $header);
            $cleanHeader = strip_tags($cleanHeader);
            $cleanHeader = trim(preg_replace('/\s+/', ' ', $cleanHeader));
            $headers[] = $cleanHeader;
        }
        
        // Extraer filas (tr)
        preg_match_all('/<tr[^>]*>(.*?)<\/tr>/s', $table, $rowMatches);
        
        foreach ($rowMatches[1] as $row) {
            preg_match_all('/<(td|th)[^>]*>(.*?)<\/(td|th)>/s', $row, $cellMatches);
            $cells = [];
            foreach ($cellMatches[2] as $cell) {
                $cleanCell = str_replace(['<br>', '<br/>', '<br />'], ' ', $cell);
                $cleanCell = strip_tags($cleanCell);
                $cleanCell = trim(preg_replace('/\s+/', ' ', $cleanCell));
                $cells[] = $cleanCell;
            }
            
            if (!empty($cells)) {
                $rows[] = $cells;
            }
        }
        
        return [
            'headers' => $headers,
            'rows' => $rows
        ];
    }

    /**
     * Extrae números ganadores de una tabla procesada
     */
    private function extractNumbersFromTable($tableData, $tableIndex = 0, $extractId = 1)
    {
        $numbers = [];
        $headers = $tableData['headers'];
        $rows = $tableData['rows'];
        
        // Mapeo de ciudades conocidas a sus códigos (basado en los nombres exactos de notitimba.com)
        $cityMapping = [
            'La Ciudad' => 'NAC',
            'La Provincia' => 'PRO', 
            'Santa Fe' => 'SFE',
            'Córdoba' => 'COR',
            'Entre Ríos' => 'RIO',
            'Montevideo' => 'ORO',
            'Mendoza' => 'MZA',
            'Santiago' => 'San',
            'Salta' => 'Salt',
            'Corrientes' => 'CTE',
            'Misiones' => 'MIS',
            'Jujuy' => 'JUJ',
            'Río Negro' => 'Rio',
            'Tucumán' => 'Tucu',
            'Chaco' => 'CHA',
            'Neuquén' => 'NQN',
            'Chubut' => 'CHU',
            'Catamarca' => 'CAT',
            'Santa Cruz' => 'SCZ',
            'Formosa' => 'FOR',
            'San Juan' => 'SJU',
            'La Rioja' => 'LRI',
            'San Luis' => 'SLU',
            'La Pampa' => 'LPA',
            'T. del fuego' => 'TDF'
        ];
        
        Log::info("Procesando tabla {$tableIndex} para extract_id {$extractId}:", ['headers' => $headers, 'rows_count' => count($rows)]);
        
        foreach ($rows as $rowIndex => $row) {
            if (empty($row)) continue;
            
            $cityName = trim($row[0]);
            Log::info("Tabla {$tableIndex} (extract_id {$extractId}) - Fila {$rowIndex}:", ['city_name' => $cityName, 'row' => $row]);
            
            // Buscar coincidencia exacta primero
            $cityCodePrefix = $cityMapping[$cityName] ?? null;
            
            // Si no hay coincidencia exacta, buscar coincidencia parcial
            if (!$cityCodePrefix) {
                foreach ($cityMapping as $mappedName => $code) {
                    if (stripos($cityName, $mappedName) !== false || stripos($mappedName, $cityName) !== false) {
                        $cityCodePrefix = $code;
                        Log::info("Coincidencia parcial encontrada:", ['city_name' => $cityName, 'mapped_name' => $mappedName, 'code' => $code]);
                        break;
                    }
                }
            }
            
            if (!$cityCodePrefix) {
                Log::info("No se encontró mapeo para:", ['city_name' => $cityName]);
                continue;
            }
            
            // Buscar SOLO las ciudades que pertenecen al extract_id (turno) correcto
            $cities = City::where('code', 'LIKE', $cityCodePrefix . '%')
                         ->where('extract_id', $extractId)
                         ->get();
            
            if ($cities->isEmpty()) {
                Log::info("No se encontraron ciudades en BD para:", ['city_name' => $cityName, 'code_prefix' => $cityCodePrefix, 'extract_id' => $extractId]);
                continue;
            }
            
            Log::info("Ciudades encontradas para {$cityName} en extract_id {$extractId}:", ['count' => $cities->count(), 'cities' => $cities->pluck('name', 'code')->toArray()]);
            
            // Para cada ciudad encontrada, extraer los números
            foreach ($cities as $city) {
                Log::info("Procesando ciudad:", ['city_id' => $city->id, 'city_name' => $city->name, 'city_code' => $city->code, 'extract_id' => $city->extract_id]);
                
                // Extraer números de las columnas (asumiendo que están en las columnas 1-20)
                for ($i = 1; $i <= 20 && $i < count($row); $i++) {
                    $value = trim($row[$i]);
                    
                    // Verificar si es un número de 4 dígitos
                    if (preg_match('/^\d{4}$/', $value)) {
                        $numbers[] = [
                            'city_id' => $city->id,
                            'extract_id' => $city->extract_id, // Usar el extract_id de la ciudad (que debe coincidir con el de la tabla)
                            'index' => $i,
                            'value' => $value
                        ];
                        Log::info("Número extraído:", ['city' => $city->name, 'extract_id' => $city->extract_id, 'index' => $i, 'value' => $value]);
                    }
                }
            }
        }
        
        Log::info("Total números extraídos de tabla {$tableIndex} (extract_id {$extractId}):", ['count' => count($numbers)]);
        return $numbers;
    }

    /**
     * Inserta los números extraídos en la base de datos
     */
    private function insertNumbersToDatabase($numbers, $date)
    {
        $insertedCount = 0;
        $skippedCount = 0;
        $insertedCities = [];
        $skippedCities = [];
        
        foreach ($numbers as $numberData) {
            // Verificar si ya existe un número para esta ciudad, extracto, índice y fecha
            $existing = Number::where('city_id', $numberData['city_id'])
                ->where('extract_id', $numberData['extract_id'])
                ->where('index', $numberData['index'])
                ->where('date', $date)
                ->first();
            
            if (!$existing) {
                Number::create([
                    'city_id' => $numberData['city_id'],
                    'extract_id' => $numberData['extract_id'],
                    'index' => $numberData['index'],
                    'value' => $numberData['value'],
                    'date' => $date
                ]);
                $insertedCount++;
                
                // Obtener nombre de la ciudad para el reporte
                $city = City::find($numberData['city_id']);
                $cityName = $city ? $city->name : 'Ciudad desconocida';
                $insertedCities[] = $cityName;
            } else {
                $skippedCount++;
                
                // Obtener nombre de la ciudad para el reporte
                $city = City::find($numberData['city_id']);
                $cityName = $city ? $city->name : 'Ciudad desconocida';
                $skippedCities[] = $cityName;
            }
        }
        
        // Crear mensaje informativo
        $message = $this->createInsertionReport($insertedCount, $skippedCount, $insertedCities, $skippedCities, $date);
        
        return [
            'inserted_count' => $insertedCount,
            'skipped_count' => $skippedCount,
            'message' => $message
        ];
    }
    
    /**
     * Crea un reporte de inserción
     */
    private function createInsertionReport($insertedCount, $skippedCount, $insertedCities, $skippedCities, $date)
    {
        if ($insertedCount > 0 && $skippedCount > 0) {
            // Algunos se insertaron, algunos ya existían
            $uniqueInserted = array_unique($insertedCities);
            $uniqueSkipped = array_unique($skippedCities);
            
            $message = "Se insertaron {$insertedCount} números nuevos para la fecha {$date}. ";
            $message .= "Ciudades actualizadas: " . implode(', ', $uniqueInserted) . ". ";
            $message .= "Ciudades que ya tenían números: " . implode(', ', $uniqueSkipped) . ".";
            
            return $message;
        } elseif ($insertedCount > 0) {
            // Todos se insertaron
            $uniqueInserted = array_unique($insertedCities);
            $message = "Se insertaron {$insertedCount} números ganadores para la fecha {$date}. ";
            $message .= "Ciudades actualizadas: " . implode(', ', $uniqueInserted) . ".";
            
            return $message;
        } elseif ($skippedCount > 0) {
            // Todos ya existían
            $uniqueSkipped = array_unique($skippedCities);
            $message = "Todas las loterías ya tienen sus resultados correspondientes para la fecha {$date}. ";
            $message .= "Ciudades con números existentes: " . implode(', ', $uniqueSkipped) . ".";
            
            return $message;
        } else {
            return "No se encontraron números para insertar para la fecha {$date}.";
        }
    }

    /**
     * Método para mostrar números de ayer
     */
    public function showYesterdayNumbers()
    {
        if (!$this->isAdmin) {
            $this->dispatch('notify', message: 'No tienes permisos para realizar esta acción.', type: 'error');
            return;
        }

        $yesterdayDate = Carbon::yesterday()->toDateString();
        $numbersCount = Number::where('date', $yesterdayDate)->count();
        
        if ($numbersCount > 0) {
            // Cambiar la fecha mostrada a la fecha de ayer
            $this->filterDate = $yesterdayDate;
            $this->selectedDate = $yesterdayDate;
            $this->loadData();
            
            $this->dispatch('notify', message: "Mostrando {$numbersCount} números ganadores para la fecha {$yesterdayDate}.", type: 'success');
        } else {
            $this->dispatch('notify', message: "No hay números ganadores para la fecha {$yesterdayDate}.", type: 'warning');
        }
    }

    /**
     * Método para depurar nombres de ciudades en las tablas
     */
    public function debugCityNames()
    {
        if (!$this->isAdmin) {
            $this->dispatch('notify', message: 'No tienes permisos para realizar esta acción.', type: 'error');
            return;
        }

        try {
            $this->debugInfo = '';
            
            // Extraer datos de la URL de notitimba
            $extractorService = new ArticleExtractorService();
            $articleData = $extractorService->extractArticle('https://www.notitimba.com/lots/');
            
            if (!$articleData || !isset($articleData['content'])) {
                $this->dispatch('notify', message: 'No se pudieron extraer los datos del artículo.', type: 'error');
                return;
            }

            // Buscar todas las tablas en el contenido HTML
            preg_match_all('/<table[^>]*>.*?<\/table>/s', $articleData['content'], $tables);
            
            $this->debugInfo .= "=== NOMBRES DE CIUDADES ENCONTRADOS ===\n\n";
            
            foreach ($tables[0] as $tableIndex => $table) {
                $tableData = $this->parseTable($table);
                if (!empty($tableData)) {
                    $this->debugInfo .= "TABLA {$tableIndex}:\n";
                    
                    foreach ($tableData['rows'] as $rowIndex => $row) {
                        if (!empty($row)) {
                            $cityName = trim($row[0]);
                            $this->debugInfo .= "  Fila {$rowIndex}: '{$cityName}'\n";
                        }
                    }
                    $this->debugInfo .= "\n";
                }
            }
            
            $this->dispatch('notify', message: 'Depuración completada. Revisa la información abajo.', type: 'success');
            
        } catch (\Exception $e) {
            $this->debugInfo = 'Error: ' . $e->getMessage();
            $this->dispatch('notify', message: 'Error: ' . $e->getMessage(), type: 'error');
        }
    }

    /**
     * Ejecuta búsqueda automática inmediatamente al ingresar al módulo
     */
    public function executeInitialSearch()
    {
        if (!$this->isAdmin) {
            return;
        }

        try {
            // Mostrar mensaje de búsqueda
            $this->dispatch('notify', message: "🔄 Buscando nuevos resultados automáticamente...", type: 'info');
            
            // Ejecutar la búsqueda automática
            $this->detectAndShowNewNumbers();
            
            // Iniciar auto-refresh continuo solo si estamos viendo la fecha de hoy
            if ($this->filterDate === Carbon::today()->toDateString()) {
                $this->startAutoRefresh();
            }
            
            Log::info("Extracts - Búsqueda inicial ejecutada al ingresar al módulo");
            
        } catch (\Exception $e) {
            Log::error("Extracts - Error en búsqueda inicial: " . $e->getMessage());
            $this->dispatch('notify', message: 'Error en búsqueda automática: ' . $e->getMessage(), type: 'error');
        }
    }

    /**
     * Inicia el auto-refresh automático
     */
    public function startAutoRefresh()
    {
        if ($this->isAdmin) {
            $this->dispatch('start-auto-refresh', interval: $this->refreshInterval * 1000);
            Log::info("Extracts - Auto-refresh automático iniciado cada {$this->refreshInterval} segundos");
        }
    }

    /**
     * Ejecuta la búsqueda automática (llamado desde JavaScript)
     * Usa exactamente el mismo método que el botón "Buscar"
     */
    public function executeAutoRefresh()
    {
        if (!$this->isAdmin) {
            return;
        }

        // Solo auto-refresh si estamos viendo la fecha de hoy
        if ($this->filterDate !== Carbon::today()->toDateString()) {
            return;
        }

        try {
            $this->lastAutoRefresh = now();
            
            // Usar exactamente el mismo método que el botón "Buscar"
            $this->detectAndShowNewNumbers();
            
            Log::info("Extracts - Auto-refresh ejecutado a las " . now()->format('H:i:s'));
            
        } catch (\Exception $e) {
            Log::error("Extracts - Error en auto-refresh: " . $e->getMessage());
        }
    }



    public function render()
    {
        return view('livewire.admin.extracts', [
            'cities' => $this->cities,
            'isAdmin' => $this->isAdmin,
            'extracts' => $this->extracts,
            'availableCities' => $this->availableCities,
            'availableExtracts' => $this->availableExtracts,
            'selectedCities' => $this->selectedCities,
            'selectedExtracts' => $this->selectedExtracts,
        ]);
    }
}
