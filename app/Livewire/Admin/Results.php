<?php

namespace App\Livewire\Admin;

use Illuminate\Support\Facades\DB;
use App\Models\PlaysSentModel;
use App\Models\Result; // Usar el modelo correcto 'Result'
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Support\Facades\Log; // Necesario para Log::info

class Results extends Component
{
    use WithPagination;

    #[Layout('layouts.app')]
    public int $cant = 55;

    public ?string $date = null;

    public function render()
    {
        if (!$this->date) {
            $this->date = date('Y-m-d');
        }
        Log::info("Results - Renderizando para fecha: {$this->date}");

        $user = auth()->user();

        // --- CÁLCULO DE TOTALES ---

        // 1. Total Recaudado (Debe ser igual al de Jugadas Enviadas)
        // Se calcula desde PlaysSentModel sumando el 'amount' total de la jugada.
        $playsSentQuery = PlaysSentModel::query()->whereDate('date', $this->date)->where('status', '!=', 'I');
        // Todos los usuarios solo ven sus propios resultados
        $playsSentQuery->where('user_id', $user->id);
        $totalImporte = (float) $playsSentQuery->sum('amount');

        // 2. Total Aciertos (Suma de los premios a pagar)
        // Se calcula desde la tabla de resultados (Result) sumando la columna 'aciert'.
        $resultsQueryForTotals = Result::query()->whereDate('date', $this->date);
        // Todos los usuarios solo ven sus propios resultados
        $resultsQueryForTotals->where('user_id', $user->id);
        $allResults = $resultsQueryForTotals->get();
        
        // Filtrar resultados según la configuración de quinielas
        $filteredResults = $this->filterResultsByQuinielasConfig($allResults);
        $totalAciertos = (float) $filteredResults->sum('aciert');

        // --- CONSULTA PARA LA TABLA PAGINADA ---
        // ✅ MODIFICADO: Mostrar resultados separados por lotería (sin agrupar)
        $resultsQuery = Result::query()
            ->select(
                'ticket',
                'lottery', // ✅ Mostrar cada lotería por separado
                'number',
                'position',
                'numR',
                'posR',
                'import',
                'user_id',
                'date',
                'aciert' // ✅ Mostrar el premio individual de cada lotería
            )
            ->whereDate('date', $this->date);

        // Todos los usuarios solo ven sus propios resultados
        $resultsQuery->where('user_id', $user->id);
        $allResultsPaginated = $resultsQuery->orderBy('ticket')->orderBy('lottery')->get();
        
        // Filtrar resultados paginados según la configuración de quinielas
        $filteredResultsPaginated = $this->filterResultsByQuinielasConfig($allResultsPaginated);
        
        // Crear una paginación manual con los resultados filtrados
        $results = new \Illuminate\Pagination\LengthAwarePaginator(
            $filteredResultsPaginated->forPage(\Illuminate\Pagination\Paginator::resolveCurrentPage(), $this->cant),
            $filteredResultsPaginated->count(),
            $this->cant,
            \Illuminate\Pagination\Paginator::resolveCurrentPage(),
            ['path' => request()->url()]
        );
        // --- LOGS PARA DEPURACIÓN ---
        Log::info("Results - Total Recaudado (PlaysSent) para {$this->date}: " . $totalImporte);
        Log::info("Results - Total Aciertos (Results) para {$this->date}: " . $totalAciertos);
        Log::info("Results - Resultados paginados: " . $results->count() . " de un total de " . $results->total());
        if ($results->isNotEmpty()) {
            Log::info("Results - Primer resultado de ejemplo: ", $results->first()->toArray());
        }

        return view('livewire.admin.results', [
            'results'       => $results,
            'totalImporte'  => $totalImporte,
            'totalAciertos' => $totalAciertos,
        ]);
    }

    public function search()
    {
        $this->resetPage();
        // Livewire se encarga de volver a renderizar automáticamente.
    }

    public function resetFilter()
    {
        $this->date = date('Y-m-d');
        $this->resetPage();
        // Livewire se encarga de volver a renderizar automáticamente.
    }

    /**
     * Filtra resultados según la configuración de quinielas
     * Solo muestra loterías que están configuradas en GlobalQuinielasConfiguration
     */
    private function filterResultsByQuinielasConfig($results)
    {
        // Obtener configuración de quinielas
        $savedPreferences = \App\Models\GlobalQuinielasConfiguration::all()
            ->keyBy('city_name')
            ->map(function($config) {
                return $config->selected_schedules;
            });

        // Mapeo de códigos de sistema a nombres de ciudad
        $systemCodeToCity = [
            'NAC' => 'CIUDAD', // Cambiado de 'BUENOS AIRES' a 'CIUDAD'
            'CHA' => 'CHACO', 
            'PRO' => 'PROVINCIA', // Cambiado de 'ENTRE RIOS' a 'PROVINCIA'
            'MZA' => 'MENDOZA',
            'CTE' => 'CORRIENTES',
            'SFE' => 'SANTA FE',
            'COR' => 'CORDOBA',
            'RIO' => 'ENTRE RIOS', // Cambiado de 'LA RIOJA' a 'ENTRE RIOS'
            'ORO' => 'MONTEVIDEO',
            'NQN' => 'NEUQUEN',
            'MIS' => 'MISIONES',
            'JUJ' => 'JUJUY',
            'Salt' => 'SALTA',
            'Rio' => 'Río Negro',
            'Tucu' => 'Tucuman',
            'San' => 'Santiago'
        ];

        return $results->filter(function($result) use ($savedPreferences, $systemCodeToCity) {
            // Si el campo lottery es un número (cantidad de loterías), permitir el resultado
            if (is_numeric($result->lottery)) {
                return true; // Permitir resultados con formato de número de loterías
            }
            
            // Si es un string con códigos de lotería, aplicar el filtro original
            $lotteryCodes = explode(',', $result->lottery);
            
            foreach ($lotteryCodes as $code) {
                $code = trim($code);
                
                // Extraer prefijo de ciudad del código de sistema (ej: "CHA" de "CHA1800")
                if (preg_match('/^([A-Za-z]+)\d{4}$/', $code, $matches)) {
                    $cityPrefix = $matches[1];
                    $cityName = $systemCodeToCity[$cityPrefix] ?? null;
                    
                    if ($cityName && isset($savedPreferences[$cityName])) {
                        $selectedSchedules = $savedPreferences[$cityName];
                        if (!empty($selectedSchedules)) {
                            return true; // Al menos una lotería está configurada
                        }
                    }
                }
            }
            
            return false; // Ninguna lotería está configurada
        });
    }
}