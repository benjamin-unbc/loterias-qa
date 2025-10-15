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
        
        foreach ($cities->groupBy('name') as $cityName => $cityData) {
            $schedules = $cityData->pluck('time')->unique()->sort()->values()->toArray();
            
            // Filtrar visualmente el horario 18:00 de Montevideo
            if ($cityName === 'MONTEVIDEO') {
                $schedules = array_filter($schedules, function($time) {
                    return $time !== '18:00';
                });
                $schedules = array_values($schedules); // Reindexar el array
            }
            
            $this->citySchedules[$cityName] = $schedules;
            
            // Cargar configuración global guardada o usar configuración por defecto
            if ($globalConfig->has($cityName)) {
                $this->selectedCitySchedules[$cityName] = $globalConfig[$cityName];
                $this->appliedCitySchedules[$cityName] = $globalConfig[$cityName];
            } else {
                // Configuración por defecto: solo las loterías especificadas con todos sus horarios
                if (in_array($cityName, $defaultSelectedLotteries)) {
                    $this->selectedCitySchedules[$cityName] = $schedules;
                    $this->appliedCitySchedules[$cityName] = $schedules;
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
            $this->selectedCitySchedules[$cityName] = $allSchedules;
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
                $this->selectedCitySchedules[$cityName] = $schedules;
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
