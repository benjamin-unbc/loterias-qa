<?php

namespace App\Livewire\Admin\Clients;

use App\Models\Client;
use App\Models\PlaysSentModel;
use App\Models\Result;
use App\Models\Extract;
use App\Models\City;
use App\Models\Number;
use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class ClientDetailsModal extends Component
{
    use WithPagination;

    public $showModal = false;
    public $client = null;
    public $activeTab = 'jugadas';
    
    // Filtros para jugadas enviadas
    public $jugadasDate = '';
    public $jugadasType = '';
    
    // Filtros para resultados
    public $resultadosDate = '';
    
    // Filtros para extractos
    public $extractosDate = '';
    
    // Filtros para liquidaciones
    public $liquidacionesDate = '';
    
    // Paginación
    public $jugadasPerPage = 10;
    public $resultadosPerPage = 10;
    
    // Vista de extractos
    public $showFullExtract = false;
    
    // Modal de ticket
    public $showTicketModal = false;
    public $selectedTicket = null;

    protected $listeners = ['openClientDetails' => 'openModal'];

    public function mount()
    {
        $this->jugadasDate = now()->toDateString();
        $this->resultadosDate = now()->toDateString();
        $this->extractosDate = now()->toDateString();
        $this->liquidacionesDate = now()->subDay()->toDateString(); // Ayer por defecto
    }

    public function openModal($clientId)
    {
        $this->client = Client::findOrFail($clientId);
        $this->showModal = true;
        $this->activeTab = 'jugadas';
        $this->resetPage();
    }

    public function closeModal()
    {
        $this->showModal = false;
        $this->client = null;
        $this->resetPage();
    }

    public function setActiveTab($tab)
    {
        $this->activeTab = $tab;
        $this->resetPage();
    }

    public function updatedJugadasDate()
    {
        $this->resetPage();
    }

    public function updatedResultadosDate()
    {
        $this->resetPage();
    }

    public function updatedExtractosDate()
    {
        $this->resetPage();
    }

    public function updatedLiquidacionesDate()
    {
        $this->resetPage();
    }

    public function updatedJugadasType()
    {
        $this->resetPage();
    }

    public function getJugadasEnviadasProperty()
    {
        if (!$this->client || !$this->client->associatedUser) {
            return collect();
        }

        $query = PlaysSentModel::where('user_id', $this->client->associatedUser->id);

        if ($this->jugadasDate) {
            $query->whereDate('date', $this->jugadasDate);
        }

        if ($this->jugadasType) {
            $query->where('type', $this->jugadasType);
        }

        return $query->orderBy('created_at', 'desc')
                    ->paginate($this->jugadasPerPage);
    }

    public function getResultadosProperty()
    {
        if (!$this->client || !$this->client->associatedUser) {
            return collect();
        }

        $query = Result::where('user_id', $this->client->associatedUser->id);

        if ($this->resultadosDate) {
            $query->whereDate('date', $this->resultadosDate);
        }

        $results = $query->select([
                'ticket',
                'lottery',
                'number',
                'position',
                'numR',
                'posR',
                'import',
                'aciert',
                'date'
            ])
            ->orderBy('created_at', 'desc')
            ->paginate($this->resultadosPerPage);

        // Filtrar resultados según la configuración de quinielas
        $filteredResults = $this->filterResultsByQuinielasConfig($results->getCollection());
        $results->setCollection($filteredResults);

        return $results;
    }

    public function getExtractosProperty()
    {
        if (!$this->extractosDate) {
            return collect();
        }

        // Obtener extractos con ciudades y números (igual que en el módulo de extractos original)
        $hiddenCities = ['SAN LUIS', 'CHUBUT', 'FORMOSA', 'CATAMARCA', 'SAN JUAN'];
        $extracts = Extract::with(['cities' => function($query) use ($hiddenCities) {
            $query->whereNotIn('name', $hiddenCities)
                  ->with(['numbers' => function($subQuery) {
                      $subQuery->where('date', $this->extractosDate);
                  }]);
        }])->get();

        // Filtrar ciudades usando la misma lógica que el módulo de extractos original
        $extracts->each(function($extract) {
            $extract->cities = $extract->cities->filter(function($city) use ($extract) {
                return $this->isCityAndScheduleConfiguredInQuinielas($city->name, $extract->name);
            });
        });

        return $extracts;
    }

    public function getTotalJugadasProperty()
    {
        if (!$this->client || !$this->client->associatedUser) {
            return 0;
        }

        $query = PlaysSentModel::where('user_id', $this->client->associatedUser->id)
                              ->where('status', '!=', 'I');

        if ($this->jugadasDate) {
            $query->whereDate('date', $this->jugadasDate);
        }

        if ($this->jugadasType) {
            $query->where('type', $this->jugadasType);
        }

        return $query->sum('amount');
    }

    public function getTotalResultadosProperty()
    {
        if (!$this->client || !$this->client->associatedUser) {
            return 0;
        }

        $query = Result::where('user_id', $this->client->associatedUser->id);

        if ($this->resultadosDate) {
            $query->whereDate('date', $this->resultadosDate);
        }

        return $query->sum('aciert');
    }
    
    public function getTotalImporteResultadosProperty()
    {
        if (!$this->client || !$this->client->associatedUser) {
            return 0;
        }

        $query = Result::where('user_id', $this->client->associatedUser->id);

        if ($this->resultadosDate) {
            $query->whereDate('date', $this->resultadosDate);
        }

        return $query->sum('import');
    }
    
    public function getTotalAciertosResultadosProperty()
    {
        if (!$this->client || !$this->client->associatedUser) {
            return 0;
        }

        $query = Result::where('user_id', $this->client->associatedUser->id);

        if ($this->resultadosDate) {
            $query->whereDate('date', $this->resultadosDate);
        }

        return $query->sum('aciert');
    }

    public function getLiquidacionesProperty()
    {
        if (!$this->client || !$this->client->associatedUser) {
            return collect();
        }

        $query = Result::where('user_id', $this->client->associatedUser->id);

        if ($this->liquidacionesDate) {
            $query->whereDate('date', $this->liquidacionesDate);
        }

        $results = $query->orderBy('created_at', 'desc')->get();

        // Filtrar resultados según la configuración de quinielas
        return $this->filterResultsByQuinielasConfig($results);
    }

    public function getLiquidacionDataProperty()
    {
        if (!$this->client || !$this->client->associatedUser || !$this->liquidacionesDate) {
            return [
                'totalApus' => 0,
                'comision' => 0,
                'totalAciert' => 0,
                'totalGanaPase' => 0,
                'anteri' => 0,
                'udRecibe' => 0,
                'udDeja' => 0,
                'arrastre' => 0,
                'previaTotalApus' => 0,
                'mananaTotalApus' => 0,
                'matutinaTotalApus' => 0,
                'tardeTotalApus' => 0,
                'nocheTotalApus' => 0,
            ];
        }

        $userId = $this->client->associatedUser->id;
        $selectedDate = \Carbon\Carbon::parse($this->liquidacionesDate);

        // Consulta de resultados filtrada por cliente
        $totalAciert = (float) Result::whereDate('date', $this->liquidacionesDate)
                                   ->where('user_id', $userId)
                                   ->sum('aciert');

        // Consulta de apuestas filtrada por cliente
        $apusQuery = \App\Models\ApusModel::whereDate('created_at', $this->liquidacionesDate)
                                         ->where('user_id', $userId);
        
        $previaTotalApus = (float) (clone $apusQuery)->where('timeApu', '10:15')->sum('import');
        $mananaTotalApus = (float) (clone $apusQuery)->where('timeApu', '12:00')->sum('import');
        $matutinaTotalApus = (float) (clone $apusQuery)->where('timeApu', '15:00')->sum('import');
        $tardeTotalApus = (float) (clone $apusQuery)->where('timeApu', '18:00')->sum('import');
        $nocheTotalApus = (float) (clone $apusQuery)->where('timeApu', '21:00')->sum('import');
        
        $totalApus = $previaTotalApus + $mananaTotalApus + $matutinaTotalApus + $tardeTotalApus + $nocheTotalApus;
        
        // Obtener la comisión personalizada del cliente
        $commissionPercentage = $this->client->commission_percentage ?? 20.00;
        $comision = $totalApus * ($commissionPercentage / 100);
        $totalGanaPase = $totalApus - $comision - $totalAciert;
        
        // Para clientes, calcular arrastre basado en sus datos históricos
        $clientPrevLiquidation = $this->getClientPreviousLiquidation($userId, $this->liquidacionesDate);
        $prevClientDeja = $clientPrevLiquidation ? (float) $clientPrevLiquidation['ud_deja'] : 0;
        
        // Calcular arrastre individual del cliente
        if ($selectedDate->isSaturday()) {
            $comiDejaSem = ($totalGanaPase + $prevClientDeja) * 0.30;
            $udDeja = ($totalGanaPase + $prevClientDeja) - $comiDejaSem;
            $arrastre = 0;
        } else {
            $comiDejaSem = null;
            $udDeja = $totalGanaPase + $prevClientDeja;
            $arrastre = $udDeja;
        }
        
        return [
            'totalApus' => $totalApus,
            'comision' => $comision,
            'totalAciert' => $totalAciert,
            'totalGanaPase' => $totalGanaPase,
            'anteri' => $prevClientDeja,
            'udRecibe' => $totalApus,
            'udDeja' => $udDeja,
            'arrastre' => $arrastre,
            'previaTotalApus' => $previaTotalApus,
            'mananaTotalApus' => $mananaTotalApus,
            'matutinaTotalApus' => $matutinaTotalApus,
            'tardeTotalApus' => $tardeTotalApus,
            'nocheTotalApus' => $nocheTotalApus,
            'comiDejaSem' => $comiDejaSem ?? 0,
        ];
    }

    protected function getClientPreviousLiquidation(int $userId, string $currentDate): ?array
    {
        // Buscar la fecha anterior con datos del cliente
        $previousDate = \Carbon\Carbon::parse($currentDate)->subDay();
        
        // Calcular liquidación del día anterior para este cliente específico
        $prevResultsQuery = Result::whereDate('date', $previousDate)->where('user_id', $userId);
        $prevTotalAciert = (float) $prevResultsQuery->sum('aciert');
        
        $prevApusQuery = \App\Models\ApusModel::whereDate('created_at', $previousDate)->where('user_id', $userId);
        $prevTotalApus = (float) $prevApusQuery->sum('import');
        
        if ($prevTotalApus == 0) {
            return null; // No hay datos del cliente en la fecha anterior
        }
        
        // Obtener la comisión personalizada del cliente para el cálculo anterior
        $commissionPercentage = $this->client->commission_percentage ?? 20.00;
        $prevComision = $prevTotalApus * ($commissionPercentage / 100);
        $prevTotalGanaPase = $prevTotalApus - $prevComision - $prevTotalAciert;
        
        // Para simplificar, asumimos que el cliente no tiene arrastre previo
        // En un sistema más complejo, se podría almacenar el arrastre por cliente
        $prevUdDeja = $prevTotalGanaPase;
        
        return [
            'ud_deja' => $prevUdDeja,
            'total_apus' => $prevTotalApus,
            'total_aciert' => $prevTotalAciert,
            'total_gana_pase' => $prevTotalGanaPase,
        ];
    }

    public function toggleExtractView()
    {
        $this->showFullExtract = !$this->showFullExtract;
    }
    
    public function viewTicket($jugadaId)
    {
        try {
            // Buscar la jugada enviada
            $this->selectedTicket = PlaysSentModel::with(['apus', 'ticket'])->find($jugadaId);
            
            if ($this->selectedTicket) {
                $this->showTicketModal = true;
                \Log::info('Ticket modal opened for ID: ' . $jugadaId, [
                    'ticket' => $this->selectedTicket->ticket,
                    'apus_count' => $this->selectedTicket->apus->count()
                ]);
            } else {
                \Log::warning('Ticket not found for ID: ' . $jugadaId);
            }
        } catch (\Exception $e) {
            \Log::error('Error opening ticket modal: ' . $e->getMessage());
        }
    }
    
    public function closeTicketModal()
    {
        $this->showTicketModal = false;
        $this->selectedTicket = null;
    }

    public function shareTicket($ticketNumber)
    {
        // Implementar lógica de compartir ticket si es necesario
        \Log::info('Sharing ticket: ' . $ticketNumber);
    }
    
    public function search()
    {
        // Método para buscar resultados
        $this->resetPage();
    }
    
    public function resetFilter()
    {
        // Método para resetear filtros
        $this->resultadosDate = now()->toDateString();
        $this->resetPage();
    }

    /**
     * Verifica si una ciudad y horario específico están configurados en Quinielas
     * (Copiado exactamente del módulo de extractos)
     */
    public function isCityAndScheduleConfiguredInQuinielas($cityName, $extractName)
    {
        try {
            $config = \App\Models\GlobalQuinielasConfiguration::where('city_name', $cityName)->first();
            if (!$config || empty($config->selected_schedules)) {
                return false;
            }
            
            // Mapeo específico por ciudad y extracto
            $cityExtractMapping = [
                'Tucuman' => [
                    'PREVIA' => ['11:30'],      // Tucu1130
                    'PRIMERO' => ['14:30'],     // Tucu1430
                    'MATUTINO' => ['17:30'],    // Tucu1730
                    'VESPERTINO' => ['19:30'],  // Tucu1930
                    'NOCTURNO' => ['22:00']     // Tucu2200
                ],
                'Santiago' => [
                    'PREVIA' => ['10:15'],      // San1015
                    'PRIMERO' => ['12:00'],     // San1200
                    'MATUTINO' => ['15:00'],    // San1500
                    'VESPERTINO' => ['19:45'],  // San1945
                    'NOCTURNO' => ['22:00']     // San2200
                ],
                'SALTA' => [
                    'PREVIA' => ['11:30'],      // Salt1130
                    'PRIMERO' => ['14:00'],     // Salt1400
                    'MATUTINO' => ['17:30'],    // Salt1730
                    'VESPERTINO' => ['21:00'],  // Salt2100
                    'NOCTURNO' => []
                ],
                'JUJUY' => [
                    'PREVIA' => [],
                    'PRIMERO' => ['12:00'],     // JUJ1200
                    'MATUTINO' => ['15:00'],    // JUJ1500
                    'VESPERTINO' => ['18:00'],  // JUJ1800
                    'NOCTURNO' => ['21:00']     // JUJ2100
                ],
                'MISIONES' => [
                    'PREVIA' => ['10:30'],      // MIS1030
                    'PRIMERO' => ['12:15'],     // MIS1215
                    'MATUTINO' => ['15:00'],    // MIS1500
                    'VESPERTINO' => ['18:00'],  // MIS1800
                    'NOCTURNO' => ['21:15']     // MIS2115
                ],
                'NEUQUEN' => [
                    'PREVIA' => ['10:15'],      // NQN1015
                    'PRIMERO' => ['12:00'],     // NQN1200
                    'MATUTINO' => ['15:00'],    // NQN1500
                    'VESPERTINO' => ['18:00'],  // NQN1800
                    'NOCTURNO' => ['21:00']     // NQN2100
                ],
                'Río Negro' => [
                    'PREVIA' => ['10:15'],      // Rio1015
                    'PRIMERO' => ['12:00'],     // Rio1200
                    'MATUTINO' => ['15:00'],    // Rio1500
                    'VESPERTINO' => ['18:00'],  // Rio1800
                    'NOCTURNO' => ['21:00']     // Rio2100
                ]
            ];
            
            // Mapeo por defecto para ciudades estándar
            $defaultMapping = [
                'PREVIA' => ['10:15'],
                'PRIMERO' => ['12:00'],
                'MATUTINO' => ['15:00'],
                'VESPERTINO' => ['18:00'],
                'NOCTURNO' => ['21:00']
            ];
            
            // Obtener el mapeo específico para la ciudad o usar el por defecto
            $cityMapping = $cityExtractMapping[$cityName] ?? $defaultMapping;
            $schedulesForExtract = $cityMapping[$extractName] ?? [];
            
            // Verificar si alguno de los horarios del extracto está seleccionado
            foreach ($schedulesForExtract as $schedule) {
                if (in_array($schedule, $config->selected_schedules)) {
                    return true;
                }
            }
            
            return false;
            
        } catch (\Exception $e) {
            \Log::error("Error verificando configuración de Quinielas para {$cityName} - {$extractName}: " . $e->getMessage());
            return true; // Por defecto, mostrar si hay error
        }
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
            'NAC' => 'BUENOS AIRES',
            'CHA' => 'CHACO', 
            'PRO' => 'ENTRE RIOS',
            'MZA' => 'MENDOZA',
            'CTE' => 'CORRIENTES',
            'SFE' => 'SANTA FE',
            'COR' => 'CORDOBA',
            'RIO' => 'LA RIOJA',
            'ORO' => 'MONTEVIDEO',
            'NQN' => 'NEUQUEN',
            'MIS' => 'MISIONES',
            'JUJ' => 'JUJUY',
            'Salt' => 'SALTA',
            'Rio' => 'RIO NEGRO',
            'Tucu' => 'TUCUMAN',
            'San' => 'SAN LUIS'
        ];

        return $results->filter(function($result) use ($savedPreferences, $systemCodeToCity) {
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

    public function render()
    {
        return view('livewire.admin.clients.client-details-modal');
    }
}
