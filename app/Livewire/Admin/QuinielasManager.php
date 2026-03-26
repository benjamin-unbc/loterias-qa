<?php

namespace App\Livewire\Admin;

use App\Models\City;
use App\Models\GlobalQuinielasConfiguration;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

class QuinielasManager extends Component
{
    #[Layout('layouts.app')]

    // Propiedades para el selector de ciudad
    public $citySchedules = []; // Array con horarios por ciudad
    public $selectedCitySchedules = []; // Horarios seleccionados por ciudad
    public $appliedCitySchedules = []; // Horarios aplicados (después de guardar)
    public $hasUnsavedChanges = false; // Indica si hay cambios sin guardar
    
    // Propiedades para edición de horarios
    public $editingSchedule = null; // Array con cityName, oldTime, cityId cuando está editando
    public $newTimeValue = ''; // Nuevo valor del horario
    
    // Propiedades para agregar nuevos horarios a turnos vacíos
    public $addingNewSchedule = null; // Array con cityName, state cuando está agregando
    public $newScheduleTime = ''; // Nuevo horario a agregar

    public function mount()
    {
        // Cargar horarios por ciudad
        $this->loadCitySchedules();
    }

    /**
     * Mapeo de horarios a estados/turnos
     */
    protected function getScheduleState($time)
    {
        if (empty($time)) {
            return 'Otro';
        }
        
        $timeMinutes = $this->timeToMinutes($time);
        
        // Definir rangos por turno
        $ranges = [
            'Previa' => [540, 719],    // 09:00 - 11:59
            'Primera' => [660, 779],   // 11:00 - 12:59
            'Matutina' => [780, 959],  // 13:00 - 15:59
            'Vespertina' => [960, 1199], // 16:00 - 19:59
            'Nocturna' => [1200, 1439], // 20:00 - 23:59
        ];
        
        foreach ($ranges as $state => $range) {
            if ($timeMinutes >= $range[0] && $timeMinutes <= $range[1]) {
                return $state;
            }
        }
        
        return 'Otro';
    }

    /**
     * Carga los horarios disponibles por ciudad
     */
    protected function loadCitySchedules()
    {
        // Obtener todas las ciudades con sus extractos (horarios)
        $cities = City::with('extract')
            ->orderBy('extract_id')
            ->orderBy('name')
            ->get();

        $this->citySchedules = [];
        $this->selectedCitySchedules = [];
        $this->appliedCitySchedules = [];
        
        // Cargar configuración global guardada
        $globalConfig = GlobalQuinielasConfiguration::all()
            ->keyBy('city_name')
            ->map(function($config) {
                return $config->selected_schedules;
            });
        
        // Loterías que deben estar preseleccionadas por defecto
        $defaultSelectedLotteries = [
            'CIUDAD',      // NAC
            'CHACO',       // CHA
            'PROVINCIA',   // PRO
            'MENDOZA',     // MZA
            'CORRIENTES',  // CTE
            'SANTA FE',    // SFE
            'CORDOBA',     // COR
            'ENTRE RIOS',  // RIO
            'MONTEVIDEO'   // ORO
        ];
        
        // Ordenar las ciudades según el orden específico solicitado: NAC, CHA, PRO, MZA, CTE, SFE, COR, RIO, ORO
        $orderedCities = [];
        $citiesGrouped = $cities->groupBy('name');
        
        // Primero agregar las ciudades en el orden específico
        foreach ($defaultSelectedLotteries as $cityName) {
            if ($citiesGrouped->has($cityName)) {
                $orderedCities[$cityName] = $citiesGrouped[$cityName];
            }
        }
        
        // Luego agregar las ciudades restantes en orden alfabético
        foreach ($citiesGrouped as $cityName => $cityData) {
            if (!in_array($cityName, $defaultSelectedLotteries)) {
                $orderedCities[$cityName] = $cityData;
            }
        }
        
        foreach ($orderedCities as $cityName => $cityData) {
            $schedules = $cityData->pluck('time')->unique()->sort()->values()->toArray();
            
            // Filtrar ORO1800 (Montevideo 18:00) del módulo de quinielas
            if ($cityName === 'MONTEVIDEO') {
                $schedules = array_filter($schedules, function($time) {
                    return $time !== '18:00';
                });
                $schedules = array_values($schedules); // Reindexar el array
            }
            
            // Filtrar horario 19:30 de Tucumán, dejar solo 17:30
            if (strtoupper($cityName) === 'TUCUMAN' || $cityName === 'Tucuman') {
                $schedules = array_filter($schedules, function($time) {
                    return $time !== '19:30';
                });
                $schedules = array_values($schedules); // Reindexar el array
            }
            
            // Agregar estado a cada horario
            // ✅ CORREGIDO: Usar extract_id de la BD para determinar el turno, no el horario
            $schedulesWithState = [];
            foreach ($schedules as $schedule) {
                // Obtener el registro completo de la ciudad para este horario específico
                $cityRecord = $cityData->where('time', $schedule)->first();
                $cityId = $cityRecord->id ?? null;
                
                // ✅ IMPORTANTE: Determinar el turno desde el extract_id de la BD, no desde el horario
                // Esto asegura que si cambias el horario, el turno se mantiene según extract_id
                $state = $this->getStateByExtractId($cityRecord->extract_id ?? null);
                
                $schedulesWithState[] = [
                    'time' => $schedule,
                    'state' => $state,
                    'display' => $schedule . ' (' . $state . ')',
                    'cityId' => $cityId
                ];
            }
            
            $this->citySchedules[$cityName] = $schedulesWithState;
            
            // Cargar configuración global guardada o usar configuración por defecto
            if ($globalConfig->has($cityName)) {
                $this->selectedCitySchedules[$cityName] = $globalConfig[$cityName];
                $this->appliedCitySchedules[$cityName] = $globalConfig[$cityName];
            } else {
                // Configuración por defecto: solo las loterías especificadas con todos sus horarios
                if (in_array($cityName, $defaultSelectedLotteries)) {
                    $this->selectedCitySchedules[$cityName] = array_column($schedulesWithState, 'time');
                    $this->appliedCitySchedules[$cityName] = array_column($schedulesWithState, 'time');
                } else {
                    // Las demás loterías desmarcadas por defecto
                    $this->selectedCitySchedules[$cityName] = [];
                    $this->appliedCitySchedules[$cityName] = [];
                }
            }
        }
    }

    /**
     * Actualiza los horarios seleccionados para una ciudad específica
     */
    public function updatedSelectedCitySchedules()
    {
        $this->checkForUnsavedChanges();
    }

    /**
     * Toggle para seleccionar/deseleccionar todos los horarios de una ciudad
     */
    public function toggleCitySchedules($cityName)
    {
        $allSchedules = $this->citySchedules[$cityName] ?? [];
        $selectedSchedules = $this->selectedCitySchedules[$cityName] ?? [];
        
        if (count($selectedSchedules) === count($allSchedules)) {
            $this->selectedCitySchedules[$cityName] = [];
        } else {
            $this->selectedCitySchedules[$cityName] = array_column($allSchedules, 'time');
        }
        $this->checkForUnsavedChanges();
    }

    /**
     * Guarda los cambios aplicados globalmente
     */
    public function saveScheduleChanges()
    {
        // Guardar la configuración global en la base de datos
        foreach ($this->selectedCitySchedules as $cityName => $schedules) {
            GlobalQuinielasConfiguration::updateOrCreate(
                [
                    'city_name' => $cityName
                ],
                [
                    'selected_schedules' => $schedules
                ]
            );
        }
        
        $this->appliedCitySchedules = $this->selectedCitySchedules;
        $this->hasUnsavedChanges = false;
        
        // Invalidar el cache de configuración global para todos los usuarios
        // Esto asegura que los cambios se reflejen inmediatamente en el Gestor de Jugadas
        $users = \App\Models\User::pluck('id');
        foreach ($users as $userId) {
            \Cache::forget('global_quinielas_config_' . $userId);
        }
        
        // Mostrar notificación de éxito
        $this->dispatch('notify', message: 'Configuración global de quinielas guardada correctamente. Los cambios se aplicarán para todos los usuarios en el Gestor de Jugadas.', type: 'success');
        
        // Redirigir al Gestor de Jugadas después de guardar
        return redirect()->route('plays-manager');
    }

    /**
     * Verifica si hay cambios sin guardar
     */
    protected function checkForUnsavedChanges()
    {
        $this->hasUnsavedChanges = ($this->selectedCitySchedules !== $this->appliedCitySchedules);
    }

    /**
     * Obtiene los horarios por defecto según el extract_id (turno)
     * ✅ NUEVO: Define los horarios originales por defecto para cada turno
     */
    protected function getDefaultTimeByExtractId($extractId)
    {
        $defaultTimes = [
            1 => '10:15',  // Previa
            2 => '12:00',  // Primera
            3 => '15:00',  // Matutina
            4 => '18:00',  // Vespertina
            5 => '21:00',  // Nocturna
        ];

        return $defaultTimes[$extractId] ?? null;
    }

    /**
     * Aplica la configuración por defecto (solo las loterías principales)
     * ✅ MODIFICADO: Ahora también restaura los horarios editados a sus valores por defecto
     */
    public function applyDefaultConfiguration()
    {
        $defaultSelectedLotteries = [
            'CIUDAD',      // NAC
            'CHACO',       // CHA
            'PROVINCIA',   // PRO
            'MENDOZA',     // MZA
            'CORRIENTES',  // CTE
            'SANTA FE',    // SFE
            'CORDOBA',     // COR
            'ENTRE RIOS',  // RIO
            'MONTEVIDEO'   // ORO
        ];
        
        try {
            // ✅ NUEVO: Restaurar horarios editados a sus valores por defecto
            // Obtener todas las ciudades de las loterías por defecto
            $cities = City::with('extract')
                ->whereIn('name', $defaultSelectedLotteries)
                ->get();
            
            $restoredCount = 0;
            foreach ($cities as $city) {
                $defaultTime = $this->getDefaultTimeByExtractId($city->extract_id);
                
                // Si el horario actual es diferente al por defecto, restaurarlo
                if ($defaultTime && $city->time !== $defaultTime) {
                    $oldTime = $city->time;
                    $newCode = $this->generateCityCode($city->name, $defaultTime);
                    
                    // Verificar que el nuevo código no exista (excepto el actual)
                    $existingCode = City::where('code', $newCode)
                        ->where('id', '!=', $city->id)
                        ->first();
                    
                    if (!$existingCode) {
                        $city->update([
                            'time' => $defaultTime,
                            'code' => $newCode
                        ]);
                        
                        // Actualizar la configuración global si existe
                        $globalConfig = GlobalQuinielasConfiguration::where('city_name', $city->name)->first();
                        if ($globalConfig && !empty($globalConfig->selected_schedules)) {
                            $selectedSchedules = $globalConfig->selected_schedules;
                            $key = array_search($oldTime, $selectedSchedules);
                            if ($key !== false) {
                                $selectedSchedules[$key] = $defaultTime;
                                $globalConfig->update(['selected_schedules' => $selectedSchedules]);
                            }
                        }
                        
                        $restoredCount++;
                    }
                }
            }
            
            // Recargar horarios después de restaurar
            $this->loadCitySchedules();
            
            // Aplicar selección de horarios por defecto
            foreach ($this->citySchedules as $cityName => $schedules) {
                if (in_array($cityName, $defaultSelectedLotteries)) {
                    $this->selectedCitySchedules[$cityName] = array_column($schedules, 'time');
                } else {
                    $this->selectedCitySchedules[$cityName] = [];
                }
            }
            
            $this->checkForUnsavedChanges();
            
            $message = 'Configuración por defecto aplicada';
            if ($restoredCount > 0) {
                $message .= ". {$restoredCount} horario(s) restaurado(s) a sus valores por defecto";
            }
            $message .= '. Recuerda guardar los cambios.';
            
            $this->dispatch('notify', message: $message, type: 'info');
        } catch (\Exception $e) {
            $this->dispatch('notify', message: 'Error al aplicar configuración por defecto: ' . $e->getMessage(), type: 'error');
        }
    }

    /**
     * Desmarca todas las loterías y horarios
     */
    public function deselectAll()
    {
        foreach ($this->citySchedules as $cityName => $schedules) {
            $this->selectedCitySchedules[$cityName] = [];
        }
        
        $this->checkForUnsavedChanges();
        $this->dispatch('notify', message: 'Todas las loterías han sido desmarcadas. Recuerda guardar los cambios.', type: 'warning');
    }

    /**
     * Inicia la edición de un horario específico
     */
    public function startEditingSchedule($cityName, $time, $cityId)
    {
        // No permitir editar turnos vacíos
        if (empty($time)) {
            $this->dispatch('notify', message: 'No se puede editar un turno vacío', type: 'error');
            return;
        }
        
        $this->editingSchedule = [
            'cityName' => $cityName,
            'oldTime' => $time,
            'cityId' => $cityId
        ];
        $this->newTimeValue = $time;
    }

    /**
     * Cancela la edición del horario
     */
    public function cancelEditingSchedule()
    {
        $this->editingSchedule = null;
        $this->newTimeValue = '';
    }

    /**
     * Guarda el cambio de horario
     * ✅ MODIFICADO: Permite cambiar el horario visualmente sin restricción de rango
     * Mantiene el extract_id original para que siga funcionando con los mismos códigos y mapeos
     */
    public function saveTimeChange()
    {
        if (!$this->editingSchedule) {
            return;
        }

        // Validar formato de hora
        if (!preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $this->newTimeValue)) {
            $this->dispatch('notify', message: 'Formato de hora inválido. Use HH:MM (ej: 10:30)', type: 'error');
            return;
        }

        // Obtener el horario anterior
        $oldTime = $this->editingSchedule['oldTime'] ?? '';
        if (empty($oldTime)) {
            $this->dispatch('notify', message: 'No se puede editar un turno vacío', type: 'error');
            return;
        }

        try {
            // Guardar valores antes de limpiar
            $newTime = $this->newTimeValue;
            $cityName = $this->editingSchedule['cityName'];
            $cityId = $this->editingSchedule['cityId'];
            
            // ✅ Obtener el registro actual para mantener el extract_id original
            $currentCity = City::find($cityId);
            if (!$currentCity) {
                $this->dispatch('notify', message: 'No se encontró el registro de la ciudad', type: 'error');
                return;
            }
            
            // ✅ Generar nuevo código basado en el nuevo horario
            $newCode = $this->generateCityCode($cityName, $newTime);
            
            // ✅ Verificar si el nuevo código ya existe para otra ciudad/horario
            $existingCode = City::where('code', $newCode)
                ->where('id', '!=', $cityId)
                ->first();
            
            if ($existingCode) {
                $this->dispatch('notify', message: "El código {$newCode} ya existe para otra ciudad/horario", type: 'error');
                return;
            }
            
            // ✅ Actualizar time y code, pero MANTENER el extract_id original
            // Esto asegura que siga funcionando con los mismos códigos y mapeos del turno
            $currentCity->update([
                'time' => $newTime,
                'code' => $newCode
            ]);

            // Actualizar la configuración global si existe
            $globalConfig = GlobalQuinielasConfiguration::where('city_name', $cityName)->first();
            if ($globalConfig && !empty($globalConfig->selected_schedules)) {
                $selectedSchedules = $globalConfig->selected_schedules;
                $key = array_search($oldTime, $selectedSchedules);
                if ($key !== false) {
                    $selectedSchedules[$key] = $newTime;
                    $globalConfig->update(['selected_schedules' => $selectedSchedules]);
                }
            }

            // Recargar horarios y limpiar estado de edición
            $this->loadCitySchedules();
            
            // Actualizar la selección si el horario anterior estaba seleccionado
            if (isset($this->selectedCitySchedules[$cityName])) {
                $key = array_search($oldTime, $this->selectedCitySchedules[$cityName]);
                if ($key !== false) {
                    $this->selectedCitySchedules[$cityName][$key] = $newTime;
                    sort($this->selectedCitySchedules[$cityName]);
                }
            }
            
            // ✅ NUEVO: Guardar automáticamente la configuración global
            // Esto asegura que los cambios se apliquen inmediatamente sin necesidad de hacer clic en "Guardar cambios"
            foreach ($this->selectedCitySchedules as $cityNameToSave => $schedules) {
                GlobalQuinielasConfiguration::updateOrCreate(
                    [
                        'city_name' => $cityNameToSave
                    ],
                    [
                        'selected_schedules' => $schedules
                    ]
                );
            }
            
            // Actualizar appliedCitySchedules para que no muestre cambios sin guardar
            $this->appliedCitySchedules = $this->selectedCitySchedules;
            $this->hasUnsavedChanges = false;
            
            // Invalidar el cache de configuración global para todos los usuarios
            // Esto asegura que los cambios se reflejen inmediatamente en el Gestor de Jugadas
            $users = \App\Models\User::pluck('id');
            foreach ($users as $userId) {
                \Cache::forget('global_quinielas_config_' . $userId);
            }
            
            $this->editingSchedule = null;
            $this->newTimeValue = '';
            
            $this->dispatch('notify', message: "Horario actualizado correctamente de {$oldTime} a {$newTime}. El turno se mantiene igual. Configuración guardada automáticamente.", type: 'success');
        } catch (\Exception $e) {
            $this->dispatch('notify', message: 'Error al actualizar el horario: ' . $e->getMessage(), type: 'error');
        }
    }

    /**
     * Obtiene el rango de horas válidas para un turno específico
     */
    protected function getScheduleStateRange($state)
    {
        $ranges = [
            'Previa' => ['09:00', '11:59'],
            'Primera' => ['11:00', '12:59'],
            'Matutina' => ['13:00', '15:59'],
            'Vespertina' => ['16:00', '19:59'],
            'Nocturna' => ['20:00', '23:59'],
        ];

        return $ranges[$state] ?? null;
    }

    /**
     * Valida si un horario está dentro del rango permitido para un turno
     */
    protected function validateTimeForState($time, $state)
    {
        // No validar turnos vacíos o estados no válidos
        if (empty($time) || $state === 'Otro') {
            return false;
        }
        
        $range = $this->getScheduleStateRange($state);
        if (!$range) {
            return false;
        }

        $timeMinutes = $this->timeToMinutes($time);
        $startMinutes = $this->timeToMinutes($range[0]);
        $endMinutes = $this->timeToMinutes($range[1]);

        return $timeMinutes >= $startMinutes && $timeMinutes <= $endMinutes;
    }

    /**
     * Convierte tiempo HH:MM a minutos para comparación
     */
    protected function timeToMinutes($time)
    {
        if (empty($time)) {
            return 0;
        }
        list($hours, $minutes) = explode(':', $time);
        return (int)$hours * 60 + (int)$minutes;
    }

    /**
     * Inicia el proceso de agregar un nuevo horario a un turno vacío
     */
    public function startAddingSchedule($cityName, $state)
    {
        // Si ya hay uno en proceso, cancelarlo primero
        if ($this->addingNewSchedule) {
            $this->cancelAddingSchedule();
        }
        
        $this->addingNewSchedule = [
            'cityName' => $cityName,
            'state' => $state
        ];
        $this->newScheduleTime = '';
    }

    /**
     * Cancela el proceso de agregar nuevo horario
     */
    public function cancelAddingSchedule()
    {
        $this->addingNewSchedule = null;
        $this->newScheduleTime = '';
    }

    /**
     * Guarda el nuevo horario creado para un turno vacío
     */
    public function saveNewSchedule()
    {
        // Validar que hay un proceso de agregado activo
        if (!$this->addingNewSchedule) {
            $this->dispatch('notify', message: 'No hay un horario en proceso de agregado', type: 'error');
            return;
        }
        
        if (empty($this->newScheduleTime)) {
            $this->dispatch('notify', message: 'Debe ingresar un horario válido', type: 'error');
            return;
        }

        $cityName = $this->addingNewSchedule['cityName'];
        $state = $this->addingNewSchedule['state'];
        $time = $this->newScheduleTime;

        // Validar formato de hora
        if (!preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $time)) {
            $this->dispatch('notify', message: 'Formato de hora inválido. Use HH:MM (ej: 10:30)', type: 'error');
            return;
        }

        // Validar que el horario esté dentro del rango del turno
        if (!$this->validateTimeForState($time, $state)) {
            $range = $this->getScheduleStateRange($state);
            if ($range) {
                $this->dispatch('notify', message: "El horario debe estar entre {$range[0]} y {$range[1]} para el turno {$state}", type: 'error');
            } else {
                $this->dispatch('notify', message: "El horario no es válido para el turno {$state}", type: 'error');
            }
            return;
        }

        // Verificar si ya existe un horario para esta ciudad y extract_id
        $extractId = $this->getExtractIdByState($state);
        $existingCity = City::where('name', $cityName)
            ->where('extract_id', $extractId)
            ->first();

        try {
            // Generar código de ciudad
            $cityCode = $this->generateCityCode($cityName, $time);
            
            if ($existingCity) {
                // Si ya existe, actualizar el horario existente
                $oldTime = $existingCity->time;
                $existingCity->update([
                    'time' => $time,
                    'code' => $cityCode,
                ]);
                
                // Actualizar la configuración global si existe
                $globalConfig = GlobalQuinielasConfiguration::where('city_name', $cityName)->first();
                if ($globalConfig && !empty($globalConfig->selected_schedules)) {
                    $selectedSchedules = $globalConfig->selected_schedules;
                    $key = array_search($oldTime, $selectedSchedules);
                    if ($key !== false) {
                        $selectedSchedules[$key] = $time;
                        $globalConfig->update(['selected_schedules' => $selectedSchedules]);
                    }
                }
                
                $action = 'actualizado';
            } else {
                // Verificar si el código ya existe (por si acaso)
                $existingCode = City::where('code', $cityCode)->first();
                if ($existingCode) {
                    $this->dispatch('notify', message: "El código {$cityCode} ya existe para otra ciudad/horario", type: 'error');
                    return;
                }
                
                // Crear el nuevo registro en la tabla cities
                City::create([
                    'extract_id' => $extractId,
                    'name' => $cityName,
                    'code' => $cityCode,
                    'time' => $time,
                ]);
                
                $action = 'agregado';
            }

            // Limpiar estado ANTES de recargar para evitar conflictos
            $savedCityName = $cityName;
            $savedState = $state;
            $savedTime = $time;
            
            $this->addingNewSchedule = null;
            $this->newScheduleTime = '';
            
            // Recargar horarios después de limpiar el estado
            $this->loadCitySchedules();
            
            // Si la ciudad está en las loterías por defecto, agregar el nuevo horario a la selección
            $defaultSelectedLotteries = [
                'CIUDAD', 'CHACO', 'PROVINCIA', 'MENDOZA', 'CORRIENTES', 
                'SANTA FE', 'CORDOBA', 'ENTRE RIOS', 'MONTEVIDEO'
            ];
            
            if (in_array($savedCityName, $defaultSelectedLotteries)) {
                if (!isset($this->selectedCitySchedules[$savedCityName])) {
                    $this->selectedCitySchedules[$savedCityName] = [];
                }
                if (!in_array($savedTime, $this->selectedCitySchedules[$savedCityName])) {
                    $this->selectedCitySchedules[$savedCityName][] = $savedTime;
                    sort($this->selectedCitySchedules[$savedCityName]);
                }
            }
            
            $message = $action === 'actualizado' 
                ? "Horario {$savedTime} actualizado correctamente para {$savedCityName} ({$savedState})"
                : "Horario {$savedTime} agregado correctamente para {$savedCityName} ({$savedState})";
            
            $this->dispatch('notify', message: $message, type: 'success');
        } catch (\Illuminate\Database\QueryException $e) {
            // Error de base de datos (duplicado, constraint, etc.)
            if ($e->getCode() == 23000) {
                $this->dispatch('notify', message: 'Ya existe un registro con estos datos. El horario podría estar duplicado.', type: 'error');
            } else {
                $this->dispatch('notify', message: 'Error de base de datos al agregar el horario: ' . $e->getMessage(), type: 'error');
            }
            // Limpiar estado incluso si hay error
            $this->addingNewSchedule = null;
            $this->newScheduleTime = '';
        } catch (\Exception $e) {
            $this->dispatch('notify', message: 'Error al agregar el horario: ' . $e->getMessage(), type: 'error');
            // Limpiar estado incluso si hay error
            $this->addingNewSchedule = null;
            $this->newScheduleTime = '';
        }
    }

    /**
     * Obtiene el extract_id según el estado/turno
     */
    protected function getExtractIdByState($state)
    {
        $mapping = [
            'Previa' => 1,
            'Primera' => 2,
            'Matutina' => 3,
            'Vespertina' => 4,
            'Nocturna' => 5,
        ];

        return $mapping[$state] ?? null;
    }

    /**
     * ✅ NUEVO: Obtiene el estado/turno según el extract_id de la BD
     * Esto asegura que el turno se determine desde la BD, no desde el horario
     */
    protected function getStateByExtractId($extractId)
    {
        if ($extractId === null) {
            return 'Otro';
        }
        
        $mapping = [
            1 => 'Previa',
            2 => 'Primera',
            3 => 'Matutina',
            4 => 'Vespertina',
            5 => 'Nocturna',
        ];

        return $mapping[$extractId] ?? 'Otro';
    }

    /**
     * Genera el código de ciudad basado en el nombre y la hora
     */
    protected function generateCityCode($cityName, $time)
    {
        // Mapeo de nombres de ciudades a códigos base
        $cityCodes = [
            'CIUDAD' => 'NAC',
            'SANTA FE' => 'SFE',
            'PROVINCIA' => 'PRO',
            'ENTRE RIOS' => 'RIO',
            'CORDOBA' => 'COR',
            'CORRIENTES' => 'CTE',
            'CHACO' => 'CHA',
            'NEUQUEN' => 'NQN',
            'MISIONES' => 'MIS',
            'MENDOZA' => 'MZA',
            'Río Negro' => 'Rio',
            'Tucuman' => 'Tucu',
            'Santiago' => 'San',
            'JUJUY' => 'JUJ',
            'SALTA' => 'Salt',
            'MONTEVIDEO' => 'ORO',
            'SAN LUIS' => 'SLU',
            'CHUBUT' => 'CHU',
            'FORMOSA' => 'FOR',
            'CATAMARCA' => 'CAT',
            'SAN JUAN' => 'SJU'
        ];

        $cityCode = $cityCodes[$cityName] ?? substr($cityName, 0, 3);
        $timeCode = str_replace(':', '', $time);
        return strtoupper($cityCode . $timeCode);
    }

    /**
     * Elimina un horario/turno de una ciudad
     */
    public function deleteSchedule($cityName, $time, $cityId)
    {
        if (empty($time) || !$cityId) {
            $this->dispatch('notify', message: 'No se puede eliminar un turno vacío', type: 'error');
            return;
        }

        try {
            // Eliminar el registro de la tabla cities
            $deleted = City::where('id', $cityId)->delete();

            if ($deleted) {
                // Actualizar la configuración global si existe
                $globalConfig = GlobalQuinielasConfiguration::where('city_name', $cityName)->first();
                if ($globalConfig && !empty($globalConfig->selected_schedules)) {
                    $selectedSchedules = $globalConfig->selected_schedules;
                    $key = array_search($time, $selectedSchedules);
                    if ($key !== false) {
                        unset($selectedSchedules[$key]);
                        $selectedSchedules = array_values($selectedSchedules); // Reindexar
                        $globalConfig->update(['selected_schedules' => $selectedSchedules]);
                    }
                }

                // Recargar horarios
                $this->loadCitySchedules();
                
                // Remover el horario eliminado de la selección si estaba seleccionado
                if (isset($this->selectedCitySchedules[$cityName])) {
                    $key = array_search($time, $this->selectedCitySchedules[$cityName]);
                    if ($key !== false) {
                        unset($this->selectedCitySchedules[$cityName][$key]);
                        $this->selectedCitySchedules[$cityName] = array_values($this->selectedCitySchedules[$cityName]);
                    }
                }
                
                $this->dispatch('notify', message: "Horario {$time} eliminado correctamente para {$cityName}", type: 'success');
            } else {
                $this->dispatch('notify', message: 'No se pudo eliminar el horario', type: 'error');
            }
        } catch (\Exception $e) {
            $this->dispatch('notify', message: 'Error al eliminar el horario: ' . $e->getMessage(), type: 'error');
        }
    }

    public function render()
    {
        return view('livewire.admin.quinielas-manager');
    }
}
