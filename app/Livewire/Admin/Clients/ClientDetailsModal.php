<?php

namespace App\Livewire\Admin\Clients;

use App\Models\Client;
use App\Models\PlaysSentModel;
use App\Models\Result;
use App\Models\Extract;
use App\Models\City;
use App\Models\Number;
use App\Models\ClientPayment;
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
    
    /**
     * Cache de instancia para evitar recursión infinita al calcular anteriores
     */
    protected $anteriorCache = [];
    
    /**
     * Cache de instancia para almacenar arrastres calculados
     */
    protected $arrastreCache = [];

    protected $listeners = ['openClientDetails'];

    public function mount()
    {
        $this->jugadasDate = now()->toDateString();
        $this->resultadosDate = now()->toDateString();
        $this->extractosDate = now()->toDateString();
        $this->liquidacionesDate = now()->subDay()->toDateString(); // Ayer por defecto
    }

    public function openClientDetails(...$args)
    {
        try {
            // Obtener clientId de los argumentos
            $clientId = null;
            
            if (isset($args[0])) {
                $firstArg = $args[0];
                
                // Si es un array, obtener el primer elemento o buscar por clave
                if (is_array($firstArg)) {
                    $clientId = $firstArg['clientId'] ?? $firstArg['client_id'] ?? $firstArg[0] ?? null;
                } 
                // Si es un número directo
                elseif (is_numeric($firstArg)) {
                    $clientId = $firstArg;
                }
            }
            
            if (!$clientId) {
                return;
            }
            
            $this->client = Client::findOrFail($clientId);
            $this->showModal = true;
            $this->activeTab = 'jugadas';
            $this->resetPage();
        } catch (\Exception $e) {
            \Log::error('Error en openClientDetails: ' . $e->getMessage());
        }
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

        // ✅ MODIFICADO: Mostrar resultados separados por lotería (sin agrupar)
        $results = $query->select([
                'ticket',
                'lottery', // ✅ Mostrar cada lotería por separado
                'number',
                'position',
                'numR',
                'posR',
                'import',
                'aciert', // ✅ Mostrar el premio individual de cada lotería
                'date'
            ])
            ->orderBy('created_at', 'desc')
            ->get();

        // Crear paginación manual
        $currentPage = \Illuminate\Pagination\Paginator::resolveCurrentPage();
        $perPage = $this->resultadosPerPage;
        $offset = ($currentPage - 1) * $perPage;
        $paginatedResults = $results->slice($offset, $perPage);

        $paginator = new \Illuminate\Pagination\LengthAwarePaginator(
            $paginatedResults,
            $results->count(),
            $perPage,
            $currentPage,
            ['path' => request()->url()]
        );

        return $paginator;
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

        // ✅ MODIFICADO: Mostrar resultados separados por lotería (sin agrupar)
        $results = $query->orderBy('created_at', 'desc')->get();
        
        return $results;
    }

    /**
     * Calcula los datos de liquidación para una fecha específica
     */
    protected function computeClientLiquidationDataForDate(string $date, int $userId): array
    {
        if (!$this->client || !$userId) {
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
                'comiDejaSem' => 0,
                'udDio' => 0,
                'udRecibePayment' => 0,
            ];
        }

        $selectedDate = \Carbon\Carbon::parse($date);

        // Consulta de resultados filtrada por cliente
        $resultsQuery = Result::whereDate('date', $date)
                             ->where('user_id', $userId);
        $allResults = $resultsQuery->get();
        
        // ✅ MODIFICADO: Usar todos los resultados sin agrupar
        $totalAciert = (float) $allResults->sum('aciert');

        // Consulta de apuestas filtrada por cliente
        // ✅ Excluir jugadas anuladas (status != 'I' en plays_sent)
        $apusQuery = \App\Models\ApusModel::whereDate('created_at', $date)
                                         ->where('user_id', $userId)
                                         ->whereHas('playsSent', function($query) {
                                             $query->where('status', '!=', 'I');
                                         });
        $allApus = $apusQuery->get();
        
        // Mostrar todas las apuestas sin filtrar (igual que el módulo original)
        $filteredApus = $allApus;
        
        $previaTotalApus = (float) $filteredApus->where('timeApu', '10:15')->sum('import');
        $mananaTotalApus = (float) $filteredApus->where('timeApu', '12:00')->sum('import');
        $matutinaTotalApus = (float) $filteredApus->where('timeApu', '15:00')->sum('import');
        $tardeTotalApus = (float) $filteredApus->where('timeApu', '18:00')->sum('import');
        $nocheTotalApus = (float) $filteredApus->where('timeApu', '21:00')->sum('import');
        
        $totalApus = $previaTotalApus + $mananaTotalApus + $matutinaTotalApus + $tardeTotalApus + $nocheTotalApus;
        
        // Obtener la comisión personalizada del cliente
        $commissionPercentage = $this->client->commission_percentage ?? 20.00;
        $comision = $totalApus * ($commissionPercentage / 100);
        $totalGanaPase = $totalApus - $comision - $totalAciert;
        
        // Para clientes, calcular arrastre basado en sus datos históricos
        // Si es domingo, el anterior es 0 (no se juega)
        if ($selectedDate->isSunday()) {
            $prevClientDeja = 0;
        }
        // Si es lunes, obtener el anterior del sábado anterior y aplicar los pagos del sábado
        elseif ($selectedDate->isMonday()) {
            $saturdayDate = $selectedDate->copy()->subDays(2); // Sábado anterior
            // Usar cache para evitar recursión infinita
            // Buscar primero el anterior con pagos aplicados del sábado
            $cacheKey = $userId . '_' . $saturdayDate->format('Y-m-d');
            $cacheKeyWithPayments = $userId . '_' . $saturdayDate->format('Y-m-d') . '_with_payments';
            
            // Primero verificar si tenemos el anterior con pagos aplicados en cache
            if (isset($this->anteriorCache[$cacheKeyWithPayments]) && $this->anteriorCache[$cacheKeyWithPayments] !== null) {
                $prevClientDeja = $this->anteriorCache[$cacheKeyWithPayments];
            } elseif (isset($this->anteriorCache[$cacheKey]) && $this->anteriorCache[$cacheKey] !== null) {
                // Si no está con pagos, usar el sin pagos y aplicar los pagos del sábado
                $prevClientDeja = $this->anteriorCache[$cacheKey];
                $saturdayPayments = $this->getPaymentsForCurrentDate($saturdayDate->format('Y-m-d'));
                $prevClientDeja = $prevClientDeja - $saturdayPayments['udDio'] + $saturdayPayments['udRecibe'];
                // Guardar en cache con pagos aplicados
                $this->anteriorCache[$cacheKeyWithPayments] = $prevClientDeja;
            } else {
                // Marcar que estamos calculando para evitar recursión
                $this->anteriorCache[$cacheKey] = null; // Marcador temporal
                // Calcular solo el anterior del sábado sin recursión (ya incluye pagos aplicados)
                $prevClientDeja = $this->getAnteriorForDate($saturdayDate->format('Y-m-d'), $userId);
                // Guardar en cache (getAnteriorForDate ya aplicó los pagos)
                $this->anteriorCache[$cacheKeyWithPayments] = $prevClientDeja;
            }
            // Los pagos ya están aplicados, no aplicar de nuevo
        } else {
            // Para días que no son lunes, obtener el anterior del día anterior
            // Necesitamos el 'anteri' del día anterior, no el 'ud_deja'
            $previousDate = \Carbon\Carbon::parse($date)->subDay();
            // Si el día anterior es domingo, buscar el sábado anterior
            if ($previousDate->isSunday()) {
                $previousDate = $previousDate->copy()->subDay(); // Sábado anterior
            }
            
            // Usar cache para evitar recursión infinita
            // Buscar primero el anterior con pagos aplicados del día anterior
            $cacheKey = $userId . '_' . $previousDate->format('Y-m-d');
            $cacheKeyWithPayments = $userId . '_' . $previousDate->format('Y-m-d') . '_with_payments';
            
            // Primero verificar si tenemos el anterior con pagos aplicados en cache
            if (isset($this->anteriorCache[$cacheKeyWithPayments]) && $this->anteriorCache[$cacheKeyWithPayments] !== null) {
                $prevClientDeja = $this->anteriorCache[$cacheKeyWithPayments];
            } elseif (isset($this->anteriorCache[$cacheKey]) && $this->anteriorCache[$cacheKey] !== null) {
                // Si no está con pagos, usar el sin pagos y aplicar los pagos del día anterior
                $prevClientDeja = $this->anteriorCache[$cacheKey];
                $previousPayments = $this->getPaymentsForCurrentDate($previousDate->format('Y-m-d'));
                $prevClientDeja = $prevClientDeja - $previousPayments['udDio'] + $previousPayments['udRecibe'];
                // Guardar en cache con pagos aplicados
                $this->anteriorCache[$cacheKeyWithPayments] = $prevClientDeja;
            } else {
                // Marcar que estamos calculando para evitar recursión
                $this->anteriorCache[$cacheKey] = null; // Marcador temporal
                // Calcular solo el anterior del día anterior sin recursión (ya incluye pagos aplicados)
                $prevClientDeja = $this->getAnteriorForDate($previousDate->format('Y-m-d'), $userId);
                // Guardar en cache (getAnteriorForDate ya aplicó los pagos)
                $this->anteriorCache[$cacheKeyWithPayments] = $prevClientDeja;
            }
            // Los pagos ya están aplicados, no aplicar de nuevo
        }
        
        // Calcular arrastre individual del cliente
        // Obtener el porcentaje semanal del cliente
        $weeklyCommissionPercentage = $this->client->weekly_commission_percentage ?? 30.00;
        
        // Si es domingo, todo en 0 (no se juega)
        if ($selectedDate->isSunday()) {
            $udDeja = 0;
            $arrastre = 0;
            $comiDejaSem = null;
        }
        // Si no hay apuestas, UD Deja es 0 pero el arrastre mantiene el del día anterior
        elseif ($totalApus == 0) {
            $udDeja = 0; // UD Deja en 0 cuando no hay apuestas
            $comiDejaSem = null;
            
            // El arrastre mantiene el valor del día anterior (acumulativo)
            if ($selectedDate->isMonday()) {
                // Si es lunes y no hay apuestas, arrastre = 0
                $arrastre = 0;
            } else {
                // Para otros días, mantener el arrastre del día anterior
                $previousDate = $selectedDate->copy()->subDay();
                if ($previousDate->isSunday()) {
                    $previousDate = $previousDate->copy()->subDay(); // Sábado anterior
                }
                $prevArrastre = $this->getArrastreForDate($previousDate->format('Y-m-d'), $userId);
                $arrastre = $prevArrastre; // Mantener el arrastre anterior sin sumar nada
            }
        } elseif ($selectedDate->isSaturday()) {
            // Solo aplicar comisión semanal si el porcentaje es positivo
            if ($weeklyCommissionPercentage > 0) {
                $comiDejaSem = ($totalGanaPase + $prevClientDeja) * ($weeklyCommissionPercentage / 100);
                $udDeja = ($totalGanaPase + $prevClientDeja) - $comiDejaSem;
            } else {
                $comiDejaSem = 0;
                $udDeja = $totalGanaPase + $prevClientDeja;
            }
            // Arrastre del sábado = Arrastre del viernes + UD Deja del sábado
            $previousDate = $selectedDate->copy()->subDay();
            $prevArrastre = $this->getArrastreForDate($previousDate->format('Y-m-d'), $userId);
            $arrastre = $prevArrastre + $udDeja;
        } else {
            $comiDejaSem = null;
            // Calcular UD Deja
            $udDeja = $totalGanaPase + $prevClientDeja;
            
            // Calcular Arrastre según el día
            if ($selectedDate->isMonday()) {
                // Lunes: Arrastre = UD Deja (comienza en 0, luego es igual a UD Deja)
                $arrastre = $udDeja;
            } else {
                // Martes a Viernes: Arrastre = Arrastre del día anterior + UD Deja del día actual
                $previousDate = $selectedDate->copy()->subDay();
                $prevArrastre = $this->getArrastreForDate($previousDate->format('Y-m-d'), $userId);
                $arrastre = $prevArrastre + $udDeja;
            }
        }
        
        // Obtener los pagos registrados para la fecha actual
        $currentPayments = $this->getPaymentsForCurrentDate($date);
        
        // Guardar el anterior calculado en cache (solo si no es null, para evitar sobrescribir valores calculados)
        $cacheKey = $userId . '_' . $date;
        $cacheKeyWithPayments = $userId . '_' . $date . '_with_payments';
        
        // Guardar el anterior sin pagos aplicados
        if (!isset($this->anteriorCache[$cacheKey]) || $this->anteriorCache[$cacheKey] === null) {
            // El anterior sin pagos es el prevClientDeja antes de aplicar los pagos del día actual
            // Pero prevClientDeja ya tiene los pagos del día anterior aplicados
            // Necesitamos guardar el anterior con los pagos del día actual aplicados
            $anteriWithPayments = $prevClientDeja - $currentPayments['udDio'] + $currentPayments['udRecibe'];
            $this->anteriorCache[$cacheKeyWithPayments] = $anteriWithPayments;
        }
        
        // También guardar el anterior sin pagos (para referencia)
        if (!isset($this->anteriorCache[$cacheKey]) || $this->anteriorCache[$cacheKey] === null) {
            $this->anteriorCache[$cacheKey] = $prevClientDeja;
        }
        
        // Guardar el arrastre en cache para uso en días siguientes
        $arrastreCacheKey = $userId . '_' . $date . '_arrastre';
        $this->arrastreCache[$arrastreCacheKey] = $arrastre;
        
        return [
            'totalApus' => $totalApus,
            'comision' => $comision,
            'totalAciert' => $totalAciert,
            'totalGanaPase' => $totalGanaPase,
            'anteri' => $prevClientDeja,
            'udRecibe' => $totalAciert,
            'udDeja' => $udDeja,
            'arrastre' => $arrastre,
            'previaTotalApus' => $previaTotalApus,
            'mananaTotalApus' => $mananaTotalApus,
            'matutinaTotalApus' => $matutinaTotalApus,
            'tardeTotalApus' => $tardeTotalApus,
            'nocheTotalApus' => $nocheTotalApus,
            'comiDejaSem' => $comiDejaSem ?? 0,
            'udDio' => $currentPayments['udDio'],
            'udRecibePayment' => $currentPayments['udRecibe'],
        ];
    }
    
    /**
     * Obtiene el anterior para una fecha específica sin recursión
     * Calcula directamente desde los datos sin llamar a computeClientLiquidationDataForDate
     */
    protected function getAnteriorForDate(string $date, int $userId): float
    {
        $selectedDate = \Carbon\Carbon::parse($date);
        
        // Si es domingo, el anterior es 0
        if ($selectedDate->isSunday()) {
            return 0;
        }
        
        // Determinar la fecha del día anterior
        if ($selectedDate->isMonday()) {
            $previousDate = $selectedDate->copy()->subDays(2); // Sábado anterior
        } else {
            $previousDate = $selectedDate->copy()->subDay();
            if ($previousDate->isSunday()) {
                $previousDate = $previousDate->copy()->subDay(); // Sábado anterior
            }
        }
        
        // Verificar cache primero - el cache debe contener el anterior CON los pagos aplicados
        $cacheKey = $userId . '_' . $previousDate->format('Y-m-d');
        $cacheKeyWithPayments = $userId . '_' . $previousDate->format('Y-m-d') . '_with_payments';
        
        // Primero verificar si tenemos el anterior con pagos aplicados en cache
        if (isset($this->anteriorCache[$cacheKeyWithPayments]) && $this->anteriorCache[$cacheKeyWithPayments] !== null) {
            return $this->anteriorCache[$cacheKeyWithPayments];
        }
        
        // Si no está en cache con pagos, pero sí está en cache sin pagos, calcular los pagos y guardarlos
        if (isset($this->anteriorCache[$cacheKey]) && $this->anteriorCache[$cacheKey] !== null) {
            $anteri = $this->anteriorCache[$cacheKey];
            // Aplicar pagos del día anterior
            $previousPayments = $this->getPaymentsForCurrentDate($previousDate->format('Y-m-d'));
            $anteri = $anteri - $previousPayments['udDio'] + $previousPayments['udRecibe'];
            // Guardar en cache con pagos aplicados
            $this->anteriorCache[$cacheKeyWithPayments] = $anteri;
            return $anteri;
        }
        
        // Si es lunes y no está en cache, calcular el anterior del sábado desde los datos
        if ($selectedDate->isMonday() && !isset($this->anteriorCache[$cacheKey])) {
            // Calcular el anterior del sábado desde los datos
            $saturdayResultsQuery = Result::whereDate('date', $previousDate->format('Y-m-d'))->where('user_id', $userId);
            $saturdayTotalAciert = (float) $saturdayResultsQuery->sum('aciert');
            
            $saturdayApusQuery = \App\Models\ApusModel::whereDate('created_at', $previousDate->format('Y-m-d'))
                                                     ->where('user_id', $userId)
                                                     ->whereHas('playsSent', function($query) {
                                                         $query->where('status', '!=', 'I');
                                                     });
            $saturdayTotalApus = (float) $saturdayApusQuery->sum('import');
            
            $commissionPercentage = $this->client->commission_percentage ?? 20.00;
            $saturdayComision = $saturdayTotalApus * ($commissionPercentage / 100);
            $saturdayTotalGanaPase = $saturdayTotalApus - $saturdayComision - $saturdayTotalAciert;
            
            // Obtener el anterior del viernes (o 0 si no está en cache)
            $fridayDate = $previousDate->copy()->subDay();
            $fridayCacheKey = $userId . '_' . $fridayDate->format('Y-m-d') . '_with_payments';
            $fridayAnteri = isset($this->anteriorCache[$fridayCacheKey]) && $this->anteriorCache[$fridayCacheKey] !== null 
                ? $this->anteriorCache[$fridayCacheKey] 
                : 0;
            
            // Calcular UD Deja del sábado
            $weeklyCommissionPercentage = $this->client->weekly_commission_percentage ?? 30.00;
            if ($weeklyCommissionPercentage > 0) {
                $comiDejaSem = ($saturdayTotalGanaPase + $fridayAnteri) * ($weeklyCommissionPercentage / 100);
                $saturdayUdDeja = ($saturdayTotalGanaPase + $fridayAnteri) - $comiDejaSem;
            } else {
                $saturdayUdDeja = $saturdayTotalGanaPase + $fridayAnteri;
            }
            
            // El anterior del sábado es su UD Deja (sin pagos aún)
            $saturdayAnteri = $saturdayUdDeja;
            
            // Aplicar pagos del sábado
            $saturdayPayments = $this->getPaymentsForCurrentDate($previousDate->format('Y-m-d'));
            $saturdayAnteri = $saturdayAnteri - $saturdayPayments['udDio'] + $saturdayPayments['udRecibe'];
            
            // Guardar en cache
            $this->anteriorCache[$cacheKey] = $saturdayUdDeja;
            $this->anteriorCache[$cacheKeyWithPayments] = $saturdayAnteri;
            
            return $saturdayAnteri;
        }
        
        // Si no está en cache, calcularlo
        if (isset($this->anteriorCache[$cacheKey]) && $this->anteriorCache[$cacheKey] !== null) {
            $anteri = $this->anteriorCache[$cacheKey];
        } else {
            // Calcular el anterior del día anterior directamente desde los datos
            // Sin llamar a computeClientLiquidationDataForDate para evitar recursión
            $prevResultsQuery = Result::whereDate('date', $previousDate->format('Y-m-d'))->where('user_id', $userId);
            $prevTotalAciert = (float) $prevResultsQuery->sum('aciert');
            
            $prevApusQuery = \App\Models\ApusModel::whereDate('created_at', $previousDate->format('Y-m-d'))
                                     ->where('user_id', $userId)
                                     ->whereHas('playsSent', function($query) {
                                         $query->where('status', '!=', 'I');
                                     });
            $prevTotalApus = (float) $prevApusQuery->sum('import');
            
            $commissionPercentage = $this->client->commission_percentage ?? 20.00;
            $prevComision = $prevTotalApus * ($commissionPercentage / 100);
            $prevTotalGanaPase = $prevTotalApus - $prevComision - $prevTotalAciert;
            
            // Obtener el anterior del día anterior (sin recursión, usar 0 si no está en cache)
            // Esto evita la recursión infinita - si no está en cache, asumimos 0
            $prevPrevDate = $previousDate->copy()->subDay();
            if ($prevPrevDate->isSunday()) {
                $prevPrevDate = $prevPrevDate->copy()->subDay();
            }
            if ($previousDate->isMonday()) {
                $prevPrevDate = $previousDate->copy()->subDays(2);
            }
            
            $prevPrevCacheKey = $userId . '_' . $prevPrevDate->format('Y-m-d');
            $prevPrevCacheKeyWithPayments = $userId . '_' . $prevPrevDate->format('Y-m-d') . '_with_payments';
            // Usar el anterior con pagos aplicados si está disponible
            $prevPrevAnteri = isset($this->anteriorCache[$prevPrevCacheKeyWithPayments]) && $this->anteriorCache[$prevPrevCacheKeyWithPayments] !== null 
                ? $this->anteriorCache[$prevPrevCacheKeyWithPayments] 
                : (isset($this->anteriorCache[$prevPrevCacheKey]) && $this->anteriorCache[$prevPrevCacheKey] !== null 
                    ? $this->anteriorCache[$prevPrevCacheKey] 
                    : 0);
            
            // Calcular udDeja del día anterior
            $weeklyCommissionPercentage = $this->client->weekly_commission_percentage ?? 30.00;
            if ($previousDate->isSaturday() && $weeklyCommissionPercentage > 0) {
                $comiDejaSem = ($prevTotalGanaPase + $prevPrevAnteri) * ($weeklyCommissionPercentage / 100);
                $prevUdDeja = ($prevTotalGanaPase + $prevPrevAnteri) - $comiDejaSem;
            } else {
                $prevUdDeja = $prevTotalGanaPase + $prevPrevAnteri;
            }
            
            // El anterior es el udDeja del día anterior (sin pagos aún)
            // PERO: Si el día anterior no tiene apuestas, el anterior debería ser el anterior del día anterior a ese
            if ($prevTotalApus == 0 && $prevTotalAciert == 0) {
                // Si no hay apuestas ni aciertos, el anterior es el anterior del día anterior (ya calculado arriba)
                $anteri = $prevPrevAnteri;
            } else {
                // Si hay apuestas, el anterior es el udDeja del día anterior
                $anteri = $prevUdDeja;
            }
            $this->anteriorCache[$cacheKey] = $anteri;
        }
        
        // Aplicar pagos del día anterior
        $previousPayments = $this->getPaymentsForCurrentDate($previousDate->format('Y-m-d'));
        $anteri = $anteri - $previousPayments['udDio'] + $previousPayments['udRecibe'];
        
        // Guardar en cache el anterior CON pagos aplicados
        $this->anteriorCache[$cacheKeyWithPayments] = $anteri;
        
        return $anteri;
    }
    
    /**
     * Obtiene el arrastre para una fecha específica
     * Si está en cache, lo retorna. Si no, calcula la liquidación del día para obtener el arrastre
     */
    protected function getArrastreForDate(string $date, int $userId): float
    {
        $selectedDate = \Carbon\Carbon::parse($date);
        
        // Si es domingo, el arrastre es 0 (no se juega)
        if ($selectedDate->isSunday()) {
            return 0;
        }
        
        // Verificar cache primero
        $arrastreCacheKey = $userId . '_' . $date . '_arrastre';
        if (isset($this->arrastreCache[$arrastreCacheKey]) && $this->arrastreCache[$arrastreCacheKey] !== null) {
            return $this->arrastreCache[$arrastreCacheKey];
        }
        
        // Si no está en cache, calcular la liquidación del día para obtener el arrastre
        $liquidationData = $this->computeClientLiquidationDataForDate($date, $userId);
        $arrastre = $liquidationData['arrastre'] ?? 0;
        
        // Guardar en cache
        $this->arrastreCache[$arrastreCacheKey] = $arrastre;
        
        return $arrastre;
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

        // Limpiar el cache antes de calcular la liquidación para asegurar cálculos correctos
        $this->anteriorCache = [];
        $this->arrastreCache = [];

        $userId = $this->client->associatedUser->id;
        return $this->computeClientLiquidationDataForDate($this->liquidacionesDate, $userId);
    }

    protected function getClientPreviousLiquidation(int $userId, string $currentDate, bool $skipRecursion = false): ?array
    {
        $currentDateCarbon = \Carbon\Carbon::parse($currentDate);
        
        // Si es lunes, buscar el sábado anterior (2 días atrás)
        // Si es cualquier otro día, buscar el día anterior normal
        if ($currentDateCarbon->isMonday()) {
            $previousDate = $currentDateCarbon->copy()->subDays(2); // Sábado anterior
        } else {
            $previousDate = $currentDateCarbon->copy()->subDay(); // Día anterior
        }
        
        $prevDateStr = $previousDate->format('Y-m-d');
        
        // Consulta de resultados filtrada por cliente
        $prevResultsQuery = Result::whereDate('date', $prevDateStr)->where('user_id', $userId);
        $prevTotalAciert = (float) $prevResultsQuery->sum('aciert');
        
        $prevApusQuery = \App\Models\ApusModel::whereDate('created_at', $prevDateStr)
                                             ->where('user_id', $userId)
                                             ->whereHas('playsSent', function($query) {
                                                 $query->where('status', '!=', 'I');
                                             });
        $prevTotalApus = (float) $prevApusQuery->sum('import');
        
        $commissionPercentage = $this->client->commission_percentage ?? 20.00;
        
        // Si el día anterior no tiene apuestas, buscar recursivamente hacia atrás
        // hasta encontrar el último día con datos y usar su udDeja como arrastre
        if ($prevTotalApus == 0) {
            // Si skipRecursion es true, no buscar más hacia atrás
            if ($skipRecursion) {
                return null;
            }
            
            // Buscar recursivamente el último día con datos
            $prevPrevLiquidation = $this->getClientPreviousLiquidation($userId, $prevDateStr, false);
            
            // Si encontramos un día anterior con datos, usar su udDeja como arrastre
            if ($prevPrevLiquidation) {
                return [
                    'ud_deja' => (float) $prevPrevLiquidation['ud_deja'],
                    'total_apus' => 0,
                    'total_aciert' => 0,
                    'total_gana_pase' => 0,
                ];
            }
            
            // Si no hay ningún día anterior con datos, retornar null
            return null;
        }
        
        $prevComision = $prevTotalApus * ($commissionPercentage / 100);
        $prevTotalGanaPase = $prevTotalApus - $prevComision - $prevTotalAciert;
        
        // Si skipRecursion es true, solo devolver el totalGanaPase (sin arrastre)
        // Si es false, calcular recursivamente el udDeja del día anterior
        if ($skipRecursion) {
            $prevUdDeja = $prevTotalGanaPase;
        } else {
            // Calcular recursivamente el udDeja del día anterior
            $prevPrevLiquidation = $this->getClientPreviousLiquidation($userId, $prevDateStr, true);
            $prevPrevDeja = $prevPrevLiquidation ? (float) $prevPrevLiquidation['ud_deja'] : 0;
            
            // Calcular udDeja del día anterior
            if ($previousDate->isSaturday()) {
                $comiDejaSem = ($prevTotalGanaPase + $prevPrevDeja) * 0.30;
                $prevUdDeja = ($prevTotalGanaPase + $prevPrevDeja) - $comiDejaSem;
            } else {
                $prevUdDeja = $prevTotalGanaPase + $prevPrevDeja;
            }
        }
        
        return [
            'ud_deja' => $prevUdDeja,
            'total_apus' => $prevTotalApus,
            'total_aciert' => $prevTotalAciert,
            'total_gana_pase' => $prevTotalGanaPase,
        ];
    }
    
    /**
     * Obtiene el ajuste de pagos para una fecha específica
     * Retorna el monto que se debe aplicar al udDeja del día siguiente
     */
    protected function getPaymentsForDate(string $date): float
    {
        try {
            if (!$this->client) {
                return 0.0;
            }
            
            $payments = ClientPayment::where('client_id', $this->client->id)
                ->whereDate('payment_date', $date)
                ->get();
            
            if ($payments->isEmpty()) {
                return 0.0;
            }
            
            // Calcular el ajuste total
            // Si es pago al cliente (paid_to_client), se resta del udDeja (retorna negativo)
            // Si es pago del cliente (received_from_client), se suma al udDeja (retorna positivo, reduce deuda negativa)
            $adjustment = 0;
            foreach ($payments as $payment) {
                if ($payment->type === 'paid_to_client') {
                    $adjustment -= $payment->amount; // Se resta del udDeja
                } else {
                    $adjustment += $payment->amount; // Se suma al udDeja (reduce deuda negativa)
                }
            }
            
            return (float) $adjustment;
        } catch (\Exception $e) {
            // Si hay algún error, retornar 0
            \Log::warning('Error al obtener pagos para fecha en ClientDetailsModal: ' . $e->getMessage());
            return 0.0;
        }
    }
    
    /**
     * Obtiene los pagos registrados para una fecha específica
     * Retorna un array con udDio y udRecibe según el tipo de pago
     * 
     * @param string $date Fecha de la liquidación
     * @return array ['udDio' => float, 'udRecibe' => float]
     */
    
    /**
     * Busca recursivamente el anterior de días anteriores hasta encontrar uno con valores
     * 
     * @param int $userId
     * @param Carbon $date
     * @param int $depth Profundidad de recursión (máximo 30 días)
     * @return float
     */
    protected function getPreviousAnteriorRecursive(int $userId, Carbon $date, int $depth = 0): float
    {
        // Límite de recursión para evitar bucles infinitos
        if ($depth >= 30) {
            return 0;
        }
        
        // Ir un día atrás
        $previousDate = $date->copy()->subDay();
        
        // Si es domingo, saltar al sábado
        if ($previousDate->isSunday()) {
            $previousDate = $previousDate->copy()->subDay();
        }
        
        // Si es lunes, ir al sábado anterior
        if ($previousDate->isMonday()) {
            $previousDate = $previousDate->copy()->subDays(2);
        }
        
        // Calcular la liquidación del día anterior
        $previousLiquidation = $this->computeClientLiquidationDataForDate($previousDate->format('Y-m-d'), $userId);
        $anteri = $previousLiquidation['anteri'] ?? 0;
        
        // Verificar si el día anterior tiene datos
        $hasData = ($previousLiquidation['totalApus'] ?? 0) > 0 || 
                   ($previousLiquidation['totalAciert'] ?? 0) > 0 ||
                   isset($previousLiquidation['anteri']);
        
        // Si encontramos datos (aunque el anterior sea 0), retornar el anterior
        // Si el anterior es 0 pero hay datos, significa que se pagó todo, retornar 0
        if ($hasData) {
            return $anteri;
        }
        
        // Si no hay datos, buscar recursivamente
        return $this->getPreviousAnteriorRecursive($userId, $previousDate, $depth + 1);
    }
    
    protected function getPaymentsForCurrentDate(string $date): array
    {
        try {
            if (!$this->client) {
                return ['udDio' => 0.0, 'udRecibe' => 0.0];
            }
            
            $payments = ClientPayment::where('client_id', $this->client->id)
                ->whereDate('payment_date', $date)
                ->get();
            
            $udDio = 0.0;
            $udRecibe = 0.0;
            
            foreach ($payments as $payment) {
                if ($payment->type === 'paid_to_client') {
                    // Si el cliente debe pagar (paid_to_client), se suma a UD.DIO
                    $udDio += (float) $payment->amount;
                } else {
                    // Si el cliente debe cobrar (received_from_client), se suma a UD.RECIBE
                    $udRecibe += (float) $payment->amount;
                }
            }
            
            return [
                'udDio' => $udDio,
                'udRecibe' => $udRecibe,
            ];
        } catch (\Exception $e) {
            \Log::warning('Error al obtener pagos para fecha actual en ClientDetailsModal: ' . $e->getMessage());
            return [
                'udDio' => 0.0,
                'udRecibe' => 0.0,
            ];
        }
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

    /**
     * Filtra apuestas según la configuración de quinielas
     * Solo muestra apuestas de loterías que están configuradas en GlobalQuinielasConfiguration
     */
    private function filterApusByQuinielasConfig($apus)
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

        return $apus->filter(function($apu) use ($savedPreferences, $systemCodeToCity) {
            $lotteryCode = $apu->lottery;
            
            // Extraer prefijo de ciudad del código de sistema (ej: "CHA" de "CHA1800")
            if (preg_match('/^([A-Za-z]+)\d{4}$/', $lotteryCode, $matches)) {
                $cityPrefix = $matches[1];
                $cityName = $systemCodeToCity[$cityPrefix] ?? null;
                
                if ($cityName && isset($savedPreferences[$cityName])) {
                    $selectedSchedules = $savedPreferences[$cityName];
                    return !empty($selectedSchedules);
                }
            }
            
            return false; // Lotería no está configurada
        });
    }

    public function render()
    {
        return view('livewire.admin.clients.client-details-modal');
    }
}
