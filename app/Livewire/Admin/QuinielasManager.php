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
            
            // Filtrar visualmente el horario 18:00 de Montevideo
            if ($cityName === 'MONTEVIDEO') {
                $schedules = array_filter($schedules, function($time) {
                    return $time !== '18:00';
                });
                $schedules = array_values($schedules); // Reindexar el array
            }
            
            // Agregar estado a cada horario
            $schedulesWithState = [];
            foreach ($schedules as $schedule) {
                $state = $this->getScheduleState($schedule);
                // Obtener el ID de la ciudad para este horario específico
                $cityId = $cityData->where('time', $schedule)->first()->id ?? null;
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
     * Aplica la configuración por defecto (solo las loterías principales)
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
        
        foreach ($this->citySchedules as $cityName => $schedules) {
            if (in_array($cityName, $defaultSelectedLotteries)) {
                $this->selectedCitySchedules[$cityName] = array_column($schedules, 'time');
            } else {
                $this->selectedCitySchedules[$cityName] = [];
            }
        }
        
        $this->checkForUnsavedChanges();
        $this->dispatch('notify', message: 'Configuración por defecto aplicada. Recuerda guardar los cambios.', type: 'info');
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

        // Obtener el estado/turno actual del horario
        $oldTime = $this->editingSchedule['oldTime'] ?? '';
        if (empty($oldTime)) {
            $this->dispatch('notify', message: 'No se puede editar un turno vacío', type: 'error');
            return;
        }
        
        $currentState = $this->getScheduleState($oldTime);
        
        // Validar que el nuevo horario esté dentro del rango del turno actual
        if (!$this->validateTimeForState($this->newTimeValue, $currentState)) {
            $range = $this->getScheduleStateRange($currentState);
            if ($range) {
                $this->dispatch('notify', message: "El horario debe estar entre {$range[0]} y {$range[1]} para el turno {$currentState}", type: 'error');
            } else {
                $this->dispatch('notify', message: "El horario no es válido para el turno {$currentState}", type: 'error');
            }
            return;
        }

        try {
            // Guardar valores antes de limpiar
            $newTime = $this->newTimeValue;
            $cityName = $this->editingSchedule['cityName'];
            
            // Actualizar solo el campo time en la tabla cities usando el id
            City::where('id', $this->editingSchedule['cityId'])
                ->update(['time' => $this->newTimeValue]);

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
            $this->editingSchedule = null;
            $this->newTimeValue = '';
            
            $this->dispatch('notify', message: "Horario actualizado correctamente de {$oldTime} a {$newTime}", type: 'success');
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


    public function render()
    {
        return view('livewire.admin.quinielas-manager');
    }
}
