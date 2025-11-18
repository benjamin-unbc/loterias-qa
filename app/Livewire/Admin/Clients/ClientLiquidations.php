<?php

namespace App\Livewire\Admin\Clients;

use App\Models\Client;
use App\Models\Result;
use App\Models\ApusModel;
use App\Models\ClientPayment;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class ClientLiquidations extends Component
{
    use WithPagination;
    
    #[Layout('layouts.app')]
    
    public $client;
    public $clientId;
    public $showWeekModal = false;
    public $selectedDate = null;
    public $weekDates = [];
    public $weekLiquidations = [];
    public $showFullLiquidationModal = false;
    public $fullLiquidationDate = null;
    
    // Propiedades para el modal de pago
    public $showPaymentModal = false;
    public $paymentDate = null;
    public $paymentAmount = 0;
    public $paymentNotes = '';
    public $currentUdDeja = 0;
    
    public function mount($id)
    {
        $this->clientId = $id;
        $this->client = Client::findOrFail($id);
    }
    
    /**
     * Obtiene la primera fecha de liquidación del cliente
     */
    public function getFirstLiquidationDateProperty()
    {
        if (!$this->client || !$this->client->associatedUser) {
            return null;
        }
        
        $firstResult = Result::where('user_id', $this->client->associatedUser->id)
            ->orderBy('date', 'asc')
            ->first();
            
        return $firstResult ? $firstResult->date : null;
    }
    
    /**
     * Obtiene todas las fechas únicas con liquidaciones del cliente agrupadas por semanas
     * Ahora incluye TODOS los días hasta hoy, incluso sin jugadas
     */
    public function getLiquidationWeeksProperty()
    {
        if (!$this->client || !$this->client->associatedUser) {
            return collect();
        }
        
        $userId = $this->client->associatedUser->id;
        
        // Obtener todas las fechas que tienen datos (Result o ApusModel)
        $resultDates = Result::where('user_id', $userId)
            ->select('date')
            ->distinct()
            ->get()
            ->pluck('date')
            ->map(function($date) {
                return Carbon::parse($date)->format('Y-m-d');
            })
            ->unique()
            ->values();
        
        $apusDates = ApusModel::where('user_id', $userId)
            ->selectRaw('DATE(created_at) as date')
            ->distinct()
            ->get()
            ->pluck('date')
            ->map(function($date) {
                return Carbon::parse($date)->format('Y-m-d');
            })
            ->unique()
            ->values();
        
        // Combinar todas las fechas que tienen datos
        $allDatesWithData = $resultDates->merge($apusDates)->unique()->sort()->values();
        
        if ($allDatesWithData->isEmpty()) {
            return collect();
        }
        
        // Obtener la primera y última fecha con datos
        $firstDate = Carbon::parse($allDatesWithData->first());
        $lastDate = Carbon::parse($allDatesWithData->last());
        
        // Extender hasta hoy si la última fecha es anterior a hoy
        $today = Carbon::today();
        if ($lastDate->lt($today)) {
            $lastDate = $today;
        }
        
        // Obtener el lunes de la primera semana
        $firstMonday = $firstDate->copy()->startOfWeek();
        // Obtener el lunes de la última semana
        $lastMonday = $lastDate->copy()->startOfWeek();
        
        // Agrupar fechas por semanas (lunes a sábado)
        $weeks = collect();
        
        // Limpiar el cache antes de calcular todas las semanas
        // Las semanas se calcularán en orden cronológico, manteniendo el cache entre ellas
        $this->anteriorCache = [];
        
        // Iterar desde la primera semana hasta la última
        $currentMonday = $firstMonday->copy();
        
        while ($currentMonday->lte($lastMonday)) {
            $saturday = $currentMonday->copy()->addDays(5);
            
            // Generar TODAS las fechas de la semana (lunes a sábado)
            // Incluir todos los días hasta hoy, incluso si no tienen jugadas
            $weekDates = [];
            for ($i = 0; $i < 6; $i++) {
                $day = $currentMonday->copy()->addDays($i);
                $dayStr = $day->format('Y-m-d');
                
                // Solo incluir días hasta hoy (no futuros)
                if ($day->lte($today)) {
                    $weekDates[] = $dayStr;
                }
            }
            
            // Agregar la semana si tiene al menos un día (hasta hoy)
            if (!empty($weekDates)) {
                // Detectar si es la semana actual
                $currentWeekMonday = $today->copy()->startOfWeek();
                $isCurrentWeek = $currentMonday->format('Y-m-d') === $currentWeekMonday->format('Y-m-d');
                
                // Obtener el último día de la semana (hasta hoy) para calcular clienteDeja
                $lastDateOfWeek = end($weekDates);
                
                // IMPORTANTE: Calcular todos los días de la semana en orden cronológico
                // para asegurar que el cache tenga todos los valores necesarios
                // Esto es especialmente importante para que el anterior del último día
                // use correctamente el anterior del día anterior con pagos aplicados
                $lastLiquidationData = null;
                foreach ($weekDates as $weekDate) {
                    $lastLiquidationData = $this->computeLiquidationDataForDate($weekDate, $userId);
                }
                
                // Obtener el anterior del último día
                // El anterior que se muestra es el anterior del día anterior con pagos aplicados
                // que es exactamente lo que computeLiquidationDataForDate retorna en 'anteri'
                // Usamos el último resultado calculado en lugar de recalcular
                $anterior = $lastLiquidationData['anteri'] ?? 0;
                
                $weeks->push([
                    'monday' => $currentMonday->format('Y-m-d'),
                    'saturday' => $saturday->format('Y-m-d'),
                    'mondayFormatted' => $currentMonday->format('d-m-Y'),
                    'saturdayFormatted' => $saturday->format('d-m-Y'),
                    'dates' => $weekDates,
                    'label' => "Semana {$currentMonday->format('d-m-Y')} hasta {$saturday->format('d-m-Y')}",
                    'lastDate' => $lastDateOfWeek,
                    'clienteDeja' => $anterior, // Mantener el nombre de la clave para compatibilidad, pero ahora contiene el anterior
                    'anterior' => $anterior,
                    'isCurrentWeek' => $isCurrentWeek
                ]);
            }
            
            // Avanzar a la siguiente semana (7 días después)
            $currentMonday->addWeek();
        }
        
        return $weeks->sortByDesc('monday')->values();
    }
    
    /**
     * Cache de instancia para evitar recursión infinita al calcular anteriores
     */
    protected $anteriorCache = [];
    
    /**
     * Calcula los datos de liquidación para una fecha específica
     */
    public function computeLiquidationDataForDate($date, $userId, $skipAnteriorCalculation = false)
    {
        if (!$userId) {
            return [
                'date' => $date,
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
        
        $selectedDate = Carbon::parse($date);
        $today = Carbon::today();
        
        // Verificar si la liquidación está liberada (se libera a las 00:00 del día siguiente)
        // Si la fecha es hoy, la liquidación aún no está liberada
        $isLiquidationReleased = $selectedDate->lt($today);
        
        // Consulta de resultados filtrada por cliente
        $resultsQuery = Result::whereDate('date', $date)
                             ->where('user_id', $userId);
        $allResults = $resultsQuery->get();
        $totalAciert = (float) $allResults->sum('aciert');
        
        // Consulta de apuestas filtrada por cliente
        $apusQuery = ApusModel::whereDate('created_at', $date)
                             ->where('user_id', $userId)
                             ->whereHas('playsSent', function($query) {
                                 $query->where('status', '!=', 'I');
                             });
        $allApus = $apusQuery->get();
        
        $previaTotalApus = (float) $allApus->where('timeApu', '10:15')->sum('import');
        $mananaTotalApus = (float) $allApus->where('timeApu', '12:00')->sum('import');
        $matutinaTotalApus = (float) $allApus->where('timeApu', '15:00')->sum('import');
        $tardeTotalApus = (float) $allApus->where('timeApu', '18:00')->sum('import');
        $nocheTotalApus = (float) $allApus->where('timeApu', '21:00')->sum('import');
        
        $totalApus = $previaTotalApus + $mananaTotalApus + $matutinaTotalApus + $tardeTotalApus + $nocheTotalApus;
        
        // Si la liquidación no está liberada (es el día actual), todo en 0 excepto el anterior
        if (!$isLiquidationReleased) {
            $totalApus = 0;
            $previaTotalApus = 0;
            $mananaTotalApus = 0;
            $matutinaTotalApus = 0;
            $tardeTotalApus = 0;
            $nocheTotalApus = 0;
            $totalAciert = 0;
        }
        
        // Obtener la comisión personalizada del cliente
        $commissionPercentage = $this->client->commission_percentage ?? 20.00;
        $comision = $totalApus * ($commissionPercentage / 100);
        $totalGanaPase = $totalApus - $comision - $totalAciert;
        
        // Si la liquidación no está liberada, también poner comisión y totalGanaPase en 0
        if (!$isLiquidationReleased) {
            $comision = 0;
            $totalGanaPase = 0;
        }
        
        // Calcular arrastre
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
            $previousDate = Carbon::parse($date)->subDay();
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
        
        // Obtener el porcentaje semanal del cliente
        $weeklyCommissionPercentage = $this->client->weekly_commission_percentage ?? 30.00;
        
        // Si la liquidación no está liberada (es el día actual), todo en 0 excepto el anterior
        if (!$isLiquidationReleased) {
            $udDeja = 0;
            $arrastre = 0;
            $comiDejaSem = null;
        }
        // Si es domingo, todo en 0 (no se juega)
        elseif ($selectedDate->isSunday()) {
            $udDeja = 0;
            $arrastre = 0;
            $comiDejaSem = null;
        }
        // Si no hay apuestas, todo parte en 0 excepto el anterior
        elseif ($totalApus == 0) {
            $udDeja = 0; // UD Deja en 0 cuando no hay apuestas
            $arrastre = 0; // Arrastre en 0 cuando no hay apuestas
            $comiDejaSem = null;
        } elseif ($selectedDate->isSaturday()) {
            // Solo aplicar comisión semanal si el porcentaje es positivo
            if ($weeklyCommissionPercentage > 0) {
                $comiDejaSem = ($totalGanaPase + $prevClientDeja) * ($weeklyCommissionPercentage / 100);
                $udDeja = ($totalGanaPase + $prevClientDeja) - $comiDejaSem;
            } else {
                $comiDejaSem = 0;
                $udDeja = $totalGanaPase + $prevClientDeja;
            }
            $arrastre = 0;
        } else {
            $comiDejaSem = null;
            // Si es lunes y el porcentaje semanal del sábado anterior fue 0 o negativo, no aplicar arrastre
            if ($selectedDate->isMonday()) {
                // Verificar el porcentaje semanal del cliente
                if ($weeklyCommissionPercentage <= 0) {
                    $udDeja = $totalGanaPase;
                    $arrastre = 0;
                } else {
                    $udDeja = $totalGanaPase + $prevClientDeja;
                    $arrastre = $udDeja;
                }
            } else {
                $udDeja = $totalGanaPase + $prevClientDeja;
                $arrastre = $udDeja;
            }
        }
        
        // Obtener los pagos registrados para la fecha actual
        $currentPayments = $this->getPaymentsForCurrentDate($date);
        
        // Guardar el anterior calculado en cache ANTES del return
        // Esto asegura que cuando se calcule el siguiente día, tenga el anterior con pagos aplicados
        $cacheKey = $userId . '_' . $date;
        $cacheKeyWithPayments = $userId . '_' . $date . '_with_payments';
        
        // Guardar el anterior sin pagos aplicados (el anterior que viene del día anterior)
        if (!isset($this->anteriorCache[$cacheKey]) || $this->anteriorCache[$cacheKey] === null) {
            $this->anteriorCache[$cacheKey] = $prevClientDeja;
        }
        
        // Guardar el anterior CON los pagos del día actual aplicados
        // Este es el valor que se usará como anterior para el día siguiente
        $anteriWithPayments = $prevClientDeja - $currentPayments['udDio'] + $currentPayments['udRecibe'];
        $this->anteriorCache[$cacheKeyWithPayments] = $anteriWithPayments;
        
        return [
            'date' => $date,
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
     * Calcula directamente desde los datos sin llamar a computeLiquidationDataForDate
     */
    protected function getAnteriorForDate(string $date, int $userId): float
    {
        $selectedDate = Carbon::parse($date);
        
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
        // Este es el valor que se guardó cuando se calculó el día anterior
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
        
        // Si no está en cache, calcularlo desde los datos
        // Calcular el anterior del día anterior directamente desde los datos
        // Sin llamar a computeLiquidationDataForDate para evitar recursión
        $prevResultsQuery = Result::whereDate('date', $previousDate->format('Y-m-d'))->where('user_id', $userId);
        $prevTotalAciert = (float) $prevResultsQuery->sum('aciert');
        
        $prevApusQuery = ApusModel::whereDate('created_at', $previousDate->format('Y-m-d'))
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
        $anteri = $prevUdDeja;
        $this->anteriorCache[$cacheKey] = $anteri;
        
        // Aplicar pagos del día anterior
        $previousPayments = $this->getPaymentsForCurrentDate($previousDate->format('Y-m-d'));
        $anteri = $anteri - $previousPayments['udDio'] + $previousPayments['udRecibe'];
        
        // Guardar en cache el anterior CON pagos aplicados
        $this->anteriorCache[$cacheKeyWithPayments] = $anteri;
        
        return $anteri;
    }
    
    protected function getClientPreviousLiquidation(int $userId, string $currentDate, bool $skipRecursion = false): ?array
    {
        $currentDateCarbon = Carbon::parse($currentDate);
        
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
        
        $prevApusQuery = ApusModel::whereDate('created_at', $prevDateStr)
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
            // Obtener el porcentaje semanal del cliente
            $prevWeeklyCommissionPercentage = $this->client->weekly_commission_percentage ?? 30.00;
            
            if ($previousDate->isSaturday()) {
                // Solo aplicar comisión semanal si el porcentaje es positivo
                if ($prevWeeklyCommissionPercentage > 0) {
                    $comiDejaSem = ($prevTotalGanaPase + $prevPrevDeja) * ($prevWeeklyCommissionPercentage / 100);
                    $prevUdDeja = ($prevTotalGanaPase + $prevPrevDeja) - $comiDejaSem;
                } else {
                    $prevUdDeja = $prevTotalGanaPase + $prevPrevDeja;
                }
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
     * Abre el modal de semana para una semana específica
     * Incluye todos los días hasta hoy, incluso si no tienen jugadas
     */
    public function openWeekModal($mondayDate)
    {
        $this->selectedDate = $mondayDate;
        $monday = Carbon::parse($mondayDate);
        $today = Carbon::today();
        
        // Limpiar el cache antes de calcular la semana para asegurar cálculos correctos
        $this->anteriorCache = [];
        
        // Generar fechas de lunes a sábado, solo hasta hoy
        $this->weekDates = [];
        for ($i = 0; $i < 6; $i++) {
            $day = $monday->copy()->addDays($i);
            
            // Solo incluir días hasta hoy (no futuros)
            if ($day->lte($today)) {
                $this->weekDates[] = [
                    'date' => $day->format('Y-m-d'),
                    'formatted' => $day->format('d/m/Y'),
                    'dayName' => $day->locale('es')->dayName,
                    'carbon' => $day
                ];
            }
        }
        
        // Calcular liquidaciones para cada día de la semana (incluso sin jugadas)
        // IMPORTANTE: Calcular en orden cronológico para que el cache se inicialice correctamente
        $this->weekLiquidations = [];
        if ($this->client && $this->client->associatedUser) {
            foreach ($this->weekDates as $weekDay) {
                $this->weekLiquidations[$weekDay['date']] = $this->computeLiquidationDataForDate(
                    $weekDay['date'],
                    $this->client->associatedUser->id
                );
            }
        }
        
        $this->showWeekModal = true;
    }
    
    /**
     * Calcula los totales de una semana
     */
    public function computeWeekTotals($weekDates)
    {
        if (!$this->client || !$this->client->associatedUser) {
            return [
                'totalApus' => 0,
                'totalComision' => 0,
                'totalAciert' => 0,
                'totalGanaPase' => 0,
                'totalUdDeja' => 0,
                'totalArrastre' => 0,
            ];
        }
        
        $totals = [
            'totalApus' => 0,
            'totalComision' => 0,
            'totalAciert' => 0,
            'totalGanaPase' => 0,
            'totalUdDeja' => 0,
            'totalArrastre' => 0,
        ];
        
        foreach ($weekDates as $date) {
            $data = $this->computeLiquidationDataForDate($date, $this->client->associatedUser->id);
            $totals['totalApus'] += $data['totalApus'];
            $totals['totalComision'] += $data['comision'];
            $totals['totalAciert'] += $data['totalAciert'];
            $totals['totalGanaPase'] += $data['totalGanaPase'];
            $totals['totalUdDeja'] += $data['udDeja'];
            $totals['totalArrastre'] += $data['arrastre'];
        }
        
        return $totals;
    }
    
    public function closeWeekModal()
    {
        $this->showWeekModal = false;
        $this->selectedDate = null;
        $this->weekDates = [];
        $this->weekLiquidations = [];
    }
    
    /**
     * Abre el modal de liquidación completa
     */
    public function openFullLiquidationModal($date)
    {
        $this->fullLiquidationDate = $date;
        $this->showFullLiquidationModal = true;
    }
    
    public function closeFullLiquidationModal()
    {
        $this->showFullLiquidationModal = false;
        $this->fullLiquidationDate = null;
    }
    
    /**
     * Obtiene los resultados para una fecha específica
     */
    public function getFullLiquidationResultsProperty()
    {
        if (!$this->fullLiquidationDate || !$this->client || !$this->client->associatedUser) {
            return collect();
        }
        
        $results = Result::whereDate('date', $this->fullLiquidationDate)
            ->where('user_id', $this->client->associatedUser->id)
            ->get();
        
        // Ordenar por turno (de más temprano a más tarde)
        return $this->sortResultsByTurn($results);
    }
    
    /**
     * Ordena los resultados por turno (de más temprano a más tarde)
     */
    protected function sortResultsByTurn($results)
    {
        return $results->sortBy(function ($result) {
            // Extraer el turno del código de lotería (ej: NAC1800 -> 1800)
            $lotteryCode = trim(explode(',', $result->lottery)[0] ?? '');
            $turn = null;
            
            // Intentar extraer el turno del código de lotería (últimos 4 dígitos)
            if (preg_match('/(\d{4})$/', $lotteryCode, $matches)) {
                $turn = (int)$matches[1];
            } else {
                // Si no se puede extraer del código, usar el campo time
                if ($result->time) {
                    $timeParts = explode(':', $result->time);
                    if (count($timeParts) >= 2) {
                        $turn = (int)($timeParts[0] . $timeParts[1]);
                    }
                }
            }
            
            // Si no se pudo determinar el turno, usar un valor alto para ponerlo al final
            return $turn ?? 9999;
        })->values();
    }
    
    /**
     * Obtiene el "Cliente Deja" (UD Deja) del día anterior
     * La liquidación del día actual solo se desbloquea al día siguiente
     * Ejemplo: Si hoy es 14, muestra el valor del 13
     */
    public function getCurrentDayUdDejaProperty()
    {
        if (!$this->client || !$this->client->associatedUser) {
            return 0;
        }
        
        $userId = $this->client->associatedUser->id;
        $today = Carbon::today();
        
        // Obtener la fecha de ayer (día anterior)
        // Si es lunes, el día anterior es el sábado (2 días atrás)
        if ($today->isMonday()) {
            $previousDate = $today->copy()->subDays(2); // Sábado anterior
        } else {
            $previousDate = $today->copy()->subDay(); // Día anterior
        }
        
        $previousDateStr = $previousDate->format('Y-m-d');
        
        // Calcular el UD Deja del día anterior
        $liquidationData = $this->computeLiquidationDataForDate($previousDateStr, $userId);
        
        return $liquidationData['udDeja'] ?? 0;
    }
    
    /**
     * Obtiene el ajuste de pagos para una fecha específica
     * Retorna el monto que se debe aplicar al udDeja del día siguiente
     * Negativo si se resta (pago al cliente), positivo si se suma (pago del cliente)
     */
    protected function getPaymentsForDate(string $date): float
    {
        try {
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
            // Si hay algún error (tabla no existe, etc.), retornar 0
            \Log::warning('Error al obtener pagos para fecha: ' . $e->getMessage());
            return 0.0;
        }
    }
    
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
        $previousLiquidation = $this->computeLiquidationDataForDate($previousDate->format('Y-m-d'), $userId);
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
    
    /**
     * Obtiene los pagos registrados para una fecha específica
     * Retorna un array con udDio y udRecibe según el tipo de pago
     * 
     * @param string $date Fecha de la liquidación
     * @return array ['udDio' => float, 'udRecibe' => float]
     */
    protected function getPaymentsForCurrentDate(string $date): array
    {
        try {
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
            \Log::warning('Error al obtener pagos para fecha actual: ' . $e->getMessage());
            return [
                'udDio' => 0.0,
                'udRecibe' => 0.0,
            ];
        }
    }
    
    /**
     * Abre el modal de pago para una fecha específica
     */
    public function openPaymentModal($date)
    {
        $this->paymentDate = $date;
        $liquidationData = $this->computeLiquidationDataForDate($date, $this->client->associatedUser->id ?? null);
        // Usar el anterior en lugar del udDeja para determinar el tipo de pago
        $this->currentUdDeja = $liquidationData['anteri'] ?? 0;
        $this->paymentAmount = 0;
        $this->paymentNotes = '';
        $this->showPaymentModal = true;
    }
    
    /**
     * Cierra el modal de pago
     */
    public function closePaymentModal()
    {
        $this->showPaymentModal = false;
        $this->paymentDate = null;
        $this->paymentAmount = 0;
        $this->paymentNotes = '';
        $this->currentUdDeja = 0;
    }
    
    /**
     * Guarda un pago
     */
    public function savePayment()
    {
        $this->validate([
            'paymentDate' => 'required|date',
            'paymentAmount' => 'required|numeric|min:0.01',
        ], [
            'paymentDate.required' => 'La fecha es requerida',
            'paymentAmount.required' => 'El monto es requerido',
            'paymentAmount.numeric' => 'El monto debe ser un número',
            'paymentAmount.min' => 'El monto debe ser mayor a 0',
        ]);
        
        // Verificar si ya existe un pago registrado HOY (fecha actual)
        // Solo se permite un pago por día natural, y el siguiente solo se puede hacer a las 00:00 del día siguiente
        // Usamos la fecha actual del servidor para evitar problemas de zona horaria
        $today = Carbon::today();
        $existingPaymentToday = ClientPayment::where('client_id', $this->client->id)
            ->whereDate('created_at', $today->format('Y-m-d'))
            ->first();
        
        if ($existingPaymentToday) {
            // Cerrar el modal
            $this->closePaymentModal();
            
            // Mostrar mensaje de error
            $this->dispatch('payment-error', message: 'Ya se registró un pago hoy. Debe esperar hasta las 00:00 del día siguiente para registrar otro pago.');
            return;
        }
        
        // Determinar el tipo de pago basado en el UD Deja actual
        $type = $this->currentUdDeja >= 0 ? 'paid_to_client' : 'received_from_client';
        
        // Crear el pago
        ClientPayment::create([
            'client_id' => $this->client->id,
            'payment_date' => $this->paymentDate,
            'amount' => $this->paymentAmount,
            'type' => $type,
            'notes' => $this->paymentNotes,
            'created_by' => Auth::id(),
        ]);
        
        // Cerrar el modal
        $this->closePaymentModal();
        
        // Mostrar mensaje de éxito con SweetAlert
        $this->dispatch('payment-saved', message: 'Pago registrado correctamente. Se verá reflejado en la siguiente liquidación.');
    }
    
    public function render()
    {
        $weeks = $this->liquidationWeeks;
        $firstDate = $this->firstLiquidationDate;
        
        return view('livewire.admin.clients.client-liquidations', [
            'weeks' => $weeks,
            'firstDate' => $firstDate
        ]);
    }
}

