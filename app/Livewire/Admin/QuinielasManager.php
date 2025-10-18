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
        $scheduleStates = [
            '09:00' => 'Previa',
            '10:15' => 'Previa',
            '10:30' => 'Previa',
            '11:00' => 'Primera',
            '11:30' => 'Primera',
            '12:00' => 'Primera',
            '12:15' => 'Primera',
            '13:00' => 'Matutina',
            '14:00' => 'Matutina',
            '14:30' => 'Matutina',
            '15:00' => 'Matutina',
            '16:00' => 'Vespertina',
            '17:00' => 'Vespertina',
            '17:30' => 'Vespertina',
            '18:00' => 'Vespertina',
            '19:00' => 'Vespertina',
            '19:30' => 'Vespertina',
            '19:45' => 'Vespertina',
            '20:00' => 'Nocturna',
            '21:00' => 'Nocturna',
            '22:00' => 'Nocturna',
            '22:30' => 'Nocturna',
        ];

        return $scheduleStates[$time] ?? 'Otro';
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
                $schedulesWithState[] = [
                    'time' => $schedule,
                    'state' => $state,
                    'display' => $schedule . ' (' . $state . ')'
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


    public function render()
    {
        return view('livewire.admin.quinielas-manager');
    }
}
