<?php

namespace App\Livewire\Admin;

use App\Models\Result; // Use the correct Result model
use App\Models\DailyLiquidation;
use App\Models\ClientPayment;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;
use Carbon\Carbon;

class Liquidations extends Component
{
    use WithPagination;
    
    #[Layout('layouts.app')]

    public string $date;
    public int $cant = 15;

    /**
     * Cache de instancia para evitar recursión infinita al calcular anteriores
     */
    protected $anteriorCache = [];
    
    /**
     * Cache de instancia para almacenar arrastres calculados (global)
     */
    protected $arrastreCacheGlobal = [];
    
    /**
     * Cache de instancia para almacenar arrastres calculados (por cliente)
     */
    protected $arrastreCache = [];

    public function mount()
    {
        $this->date = Carbon::yesterday()->format('Y-m-d');
    }

    public function printPDF()
    {
        $this->dispatch('printLiquidation');
    }

    public function descargarImagen()
    {
        $this->dispatch('downloadLiquidation');
    }

    /**
     * Calcula los datos de liquidación para el usuario autenticado
     * Todos los usuarios (incluyendo administradores) solo ven sus propios resultados
     * 
     * @return array Datos de liquidación filtrados por usuario
     */
    protected function computeLiquidationData(): array
    {
        if (!$this->date) {
            $this->date = Carbon::yesterday()->format('Y-m-d');
        }
        
        $selectedDate = Carbon::parse($this->date);
        $user = auth()->user();
        
        // Todos los usuarios solo ven sus propios resultados
        return $this->computeClientLiquidationData($user, $selectedDate);
    }

    /**
     * Calcula liquidación global para administradores
     * Mantiene la lógica original del sistema
     * 
     * @param Carbon $selectedDate Fecha seleccionada
     * @return array Datos de liquidación global
     */
    protected function computeGlobalLiquidationData(Carbon $selectedDate): array
    {
        // Consulta global de resultados (sin filtro de usuario)
        $baseQuery = Result::query()->whereDate('date', $this->date);
        $results = (clone $baseQuery)->get();
        
        // ✅ Ordenar por turno (de más temprano a más tarde)
        $results = $this->sortResultsByTurn($results);
        $totalAciert = (float) (clone $baseQuery)->sum('aciert');
        
        // Consulta global de apuestas (sin filtro de usuario)
        // ✅ Excluir jugadas anuladas (status != 'I' en plays_sent)
        $apusQuery = \App\Models\ApusModel::query()
            ->whereDate('created_at', $this->date)
            ->whereHas('playsSent', function($query) {
                $query->where('status', '!=', 'I');
            });
        $previaTotalApus   = (float) (clone $apusQuery)->where('timeApu', '10:15')->sum('import');
        $mananaTotalApus   = (float) (clone $apusQuery)->where('timeApu', '12:00')->sum('import');
        $matutinaTotalApus = (float) (clone $apusQuery)->where('timeApu', '15:00')->sum('import');
        $tardeTotalApus    = (float) (clone $apusQuery)->where('timeApu', '18:00')->sum('import');
        $nocheTotalApus    = (float) (clone $apusQuery)->where('timeApu', '21:00')->sum('import');
        $totalApus = $previaTotalApus + $mananaTotalApus + $matutinaTotalApus + $tardeTotalApus + $nocheTotalApus;
        
        $comision = $totalApus * 0.20;
        $totalGanaPase = $totalApus - $comision - $totalAciert;
        
        // Buscar la liquidación diaria global más reciente anterior a la fecha actual
        // Para la liquidación global, usar el ud_deja del día anterior como anterior
        $prevLiquidation = DailyLiquidation::where('date', '<', $this->date)
                                           ->orderBy('date', 'desc')
                                           ->first();
        $prevGenerDeja = $prevLiquidation ? (float) $prevLiquidation->ud_deja : 0;
        
        // Calcular arrastre global según día de la semana
        if ($selectedDate->isSaturday()) {
            $comiDejaSem = ($totalGanaPase + $prevGenerDeja) * 0.30;
            $udDeja = ($totalGanaPase + $prevGenerDeja) - $comiDejaSem;
            // Arrastre del sábado = Arrastre del viernes + UD Deja del sábado
            $previousDate = $selectedDate->copy()->subDay();
            $prevArrastre = $this->getArrastreGlobalForDate($previousDate->format('Y-m-d'));
            $arrastre = $prevArrastre + $udDeja;
        } else {
            $comiDejaSem = null;
            $udDeja = $totalGanaPase + $prevGenerDeja;
            
            // Calcular Arrastre según el día
            if ($selectedDate->isMonday()) {
                // Lunes: Arrastre = UD Deja (comienza en 0, luego es igual a UD Deja)
            $arrastre = $udDeja;
            } else {
                // Martes a Viernes: Arrastre = Arrastre del día anterior + UD Deja del día actual
                $previousDate = $selectedDate->copy()->subDay();
                $prevArrastre = $this->getArrastreGlobalForDate($previousDate->format('Y-m-d'));
                $arrastre = $prevArrastre + $udDeja;
            }
        }
        
        // Guardar el arrastre global en cache
        $arrastreCacheKey = 'global_' . $this->date . '_arrastre';
        $this->arrastreCacheGlobal[$arrastreCacheKey] = $arrastre;
        
        return [
            'results'           => $results,
            'totalAciert'       => $totalAciert,
            'totalApus'         => $totalApus,
            'comision'          => $comision,
            'totalGanaPase'     => $totalGanaPase,
            'previaTotalApus'   => $previaTotalApus,
            'mananaTotalApus'   => $mananaTotalApus,
            'matutinaTotalApus' => $matutinaTotalApus,
            'tardeTotalApus'    => $tardeTotalApus,
            'nocheTotalApus'    => $nocheTotalApus,
            'anteri'            => $prevGenerDeja,
            'udRecibe'          => $totalAciert,
            'udDeja'            => $udDeja,
            'arrastre'          => $arrastre,
            'comi_deja_sem'     => $comiDejaSem,
        ];
    }

    /**
     * Calcula liquidación individual para clientes
     * Solo muestra datos del cliente específico
     * 
     * @param User $user Usuario cliente
     * @param Carbon $selectedDate Fecha seleccionada
     * @return array Datos de liquidación del cliente
     */
    protected function computeClientLiquidationData($user, Carbon $selectedDate): array
    {
        // Usar la fecha del selectedDate en lugar de $this->date para evitar problemas cuando se calcula arrastre
        $dateStr = $selectedDate->format('Y-m-d');
        
        // Consulta de resultados filtrada por cliente
        $baseQuery = Result::query()->whereDate('date', $dateStr)->where('user_id', $user->id);
        $results = (clone $baseQuery)->get();
        
        // ✅ Ordenar por turno (de más temprano a más tarde)
        $results = $this->sortResultsByTurn($results);
        $totalAciert = (float) (clone $baseQuery)->sum('aciert');
        
        // Consulta de apuestas filtrada por cliente
        // ✅ Excluir jugadas anuladas (status != 'I' en plays_sent)
        $apusQuery = \App\Models\ApusModel::query()
            ->whereDate('created_at', $dateStr)
            ->where('user_id', $user->id)
            ->whereHas('playsSent', function($query) {
                $query->where('status', '!=', 'I');
            });
        $previaTotalApus   = (float) (clone $apusQuery)->where('timeApu', '10:15')->sum('import');
        $mananaTotalApus   = (float) (clone $apusQuery)->where('timeApu', '12:00')->sum('import');
        $matutinaTotalApus = (float) (clone $apusQuery)->where('timeApu', '15:00')->sum('import');
        $tardeTotalApus    = (float) (clone $apusQuery)->where('timeApu', '18:00')->sum('import');
        $nocheTotalApus    = (float) (clone $apusQuery)->where('timeApu', '21:00')->sum('import');
        $totalApus = $previaTotalApus + $mananaTotalApus + $matutinaTotalApus + $tardeTotalApus + $nocheTotalApus;
        
        // Obtener la comisión personalizada del cliente
        $client = \App\Models\Client::where('correo', $user->email)->first();
        $commissionPercentage = $client ? $client->commission_percentage : 20.00;
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
            $cacheKey = $user->id . '_' . $saturdayDate->format('Y-m-d');
            $cacheKeyWithPayments = $user->id . '_' . $saturdayDate->format('Y-m-d') . '_with_payments';
            
            // Primero verificar si tenemos el anterior con pagos aplicados en cache
            if (isset($this->anteriorCache[$cacheKeyWithPayments]) && $this->anteriorCache[$cacheKeyWithPayments] !== null) {
                $prevClientDeja = $this->anteriorCache[$cacheKeyWithPayments];
            } elseif (isset($this->anteriorCache[$cacheKey]) && $this->anteriorCache[$cacheKey] !== null) {
                // Si no está con pagos, usar el sin pagos y aplicar los pagos del sábado
                $prevClientDeja = $this->anteriorCache[$cacheKey];
                $saturdayPayments = $this->getPaymentsForCurrentDate($user->id, $saturdayDate->format('Y-m-d'));
                $prevClientDeja = $prevClientDeja - $saturdayPayments['udDio'] + $saturdayPayments['udRecibe'];
                // Guardar en cache con pagos aplicados
                $this->anteriorCache[$cacheKeyWithPayments] = $prevClientDeja;
            } else {
                // Marcar que estamos calculando para evitar recursión
                $this->anteriorCache[$cacheKey] = null; // Marcador temporal
                // Calcular solo el anterior del sábado sin recursión (ya incluye pagos aplicados)
                $prevClientDeja = $this->getAnteriorForDate($saturdayDate->format('Y-m-d'), $user->id);
                // Guardar en cache (getAnteriorForDate ya aplicó los pagos)
                $this->anteriorCache[$cacheKeyWithPayments] = $prevClientDeja;
            }
            // Los pagos ya están aplicados, no aplicar de nuevo
        } else {
            // Para días que no son lunes, obtener el anterior del día anterior
            // Necesitamos el 'anteri' del día anterior, no el 'ud_deja'
            $previousDate = $selectedDate->copy()->subDay();
            // Si el día anterior es domingo, buscar el sábado anterior
            if ($previousDate->isSunday()) {
                $previousDate = $previousDate->copy()->subDay(); // Sábado anterior
            }
            
            // Usar cache para evitar recursión infinita
            // Buscar primero el anterior con pagos aplicados del día anterior
            $cacheKey = $user->id . '_' . $previousDate->format('Y-m-d');
            $cacheKeyWithPayments = $user->id . '_' . $previousDate->format('Y-m-d') . '_with_payments';
            
            // Primero verificar si tenemos el anterior con pagos aplicados en cache
            if (isset($this->anteriorCache[$cacheKeyWithPayments]) && $this->anteriorCache[$cacheKeyWithPayments] !== null) {
                $prevClientDeja = $this->anteriorCache[$cacheKeyWithPayments];
            } elseif (isset($this->anteriorCache[$cacheKey]) && $this->anteriorCache[$cacheKey] !== null) {
                // Si no está con pagos, usar el sin pagos y aplicar los pagos del día anterior
                $prevClientDeja = $this->anteriorCache[$cacheKey];
                $previousPayments = $this->getPaymentsForCurrentDate($user->id, $previousDate->format('Y-m-d'));
                $prevClientDeja = $prevClientDeja - $previousPayments['udDio'] + $previousPayments['udRecibe'];
                // Guardar en cache con pagos aplicados
                $this->anteriorCache[$cacheKeyWithPayments] = $prevClientDeja;
            } else {
                // Marcar que estamos calculando para evitar recursión
                $this->anteriorCache[$cacheKey] = null; // Marcador temporal
                // Calcular solo el anterior del día anterior sin recursión (ya incluye pagos aplicados)
                $prevClientDeja = $this->getAnteriorForDate($previousDate->format('Y-m-d'), $user->id);
                // Guardar en cache (getAnteriorForDate ya aplicó los pagos)
                $this->anteriorCache[$cacheKeyWithPayments] = $prevClientDeja;
            }
            // Los pagos ya están aplicados, no aplicar de nuevo
        }
        
        // Calcular arrastre individual del cliente
        // Obtener el porcentaje semanal del cliente
        $weeklyCommissionPercentage = $client ? ($client->weekly_commission_percentage ?? 30.00) : 30.00;
        
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
                $prevArrastre = $this->getArrastreForDate($previousDate->format('Y-m-d'), $user->id);
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
            $prevArrastre = $this->getArrastreForDate($previousDate->format('Y-m-d'), $user->id);
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
                $prevArrastre = $this->getArrastreForDate($previousDate->format('Y-m-d'), $user->id);
                $arrastre = $prevArrastre + $udDeja;
            }
        }
        
        // Obtener los pagos registrados para la fecha actual
        $currentPayments = $this->getPaymentsForCurrentDate($user->id, $dateStr);
        
        // Guardar el arrastre en cache para uso en días siguientes
        $arrastreCacheKey = $user->id . '_' . $dateStr . '_arrastre';
        $this->arrastreCache[$arrastreCacheKey] = $arrastre;
        
        return [
            'results'           => $results,
            'totalAciert'       => $totalAciert,
            'totalApus'         => $totalApus,
            'comision'          => $comision,
            'totalGanaPase'     => $totalGanaPase,
            'previaTotalApus'   => $previaTotalApus,
            'mananaTotalApus'   => $mananaTotalApus,
            'matutinaTotalApus' => $matutinaTotalApus,
            'tardeTotalApus'    => $tardeTotalApus,
            'nocheTotalApus'    => $nocheTotalApus,
            'anteri'            => $prevClientDeja,
            'udRecibe'          => $totalAciert,
            'udDeja'            => $udDeja,
            'arrastre'          => $arrastre,
            'comi_deja_sem'     => $comiDejaSem,
            'udDio'             => $currentPayments['udDio'],
            'udRecibePayment'   => $currentPayments['udRecibe'],
        ];
        
        // Guardar el anterior calculado en cache (solo si no es null, para evitar sobrescribir valores calculados)
        $cacheKey = $user->id . '_' . $dateStr;
        $cacheKeyWithPayments = $user->id . '_' . $dateStr . '_with_payments';
        
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
    }
    
    /**
     * Obtiene el anterior para una fecha específica sin recursión
     * Calcula directamente desde los datos sin llamar a computeClientLiquidationData
     */
    protected function getAnteriorForDate(string $date, int $userId): float
    {
        $selectedDate = Carbon::parse($date);
        
        // Si es domingo, el anterior es 0
        if ($selectedDate->isSunday()) {
            return 0;
        }
        
        // Obtener el cliente
        $user = \App\Models\User::find($userId);
        if (!$user) {
            return 0;
        }
        $client = \App\Models\Client::where('correo', $user->email)->first();
        
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
        
        // Si no está en cache, calcularlo
        if (isset($this->anteriorCache[$cacheKey]) && $this->anteriorCache[$cacheKey] !== null) {
            $anteri = $this->anteriorCache[$cacheKey];
        } else {
            // Calcular el anterior del día anterior directamente desde los datos
            // Sin llamar a computeClientLiquidationData para evitar recursión
            $prevResultsQuery = Result::query()->whereDate('date', $previousDate->format('Y-m-d'))->where('user_id', $userId);
            $prevTotalAciert = (float) $prevResultsQuery->sum('aciert');
            
            $prevApusQuery = \App\Models\ApusModel::query()
                ->whereDate('created_at', $previousDate->format('Y-m-d'))
                ->where('user_id', $userId)
                ->whereHas('playsSent', function($query) {
                    $query->where('status', '!=', 'I');
                });
            $prevTotalApus = (float) $prevApusQuery->sum('import');
            
            $commissionPercentage = $client ? $client->commission_percentage : 20.00;
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
            $weeklyCommissionPercentage = $client ? ($client->weekly_commission_percentage ?? 30.00) : 30.00;
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
        $previousPayments = $this->getPaymentsForCurrentDate($userId, $previousDate->format('Y-m-d'));
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
        $selectedDate = Carbon::parse($date);
        
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
        // Necesitamos obtener el usuario y calcular su liquidación
        $user = \App\Models\User::find($userId);
        if (!$user) {
            return 0;
        }
        
        // Calcular la liquidación del día (ya no necesitamos cambiar $this->date porque computeClientLiquidationData usa $selectedDate)
        $liquidationData = $this->computeClientLiquidationData($user, $selectedDate);
        $arrastre = $liquidationData['arrastre'] ?? 0;
        
        // Guardar en cache
        $this->arrastreCache[$arrastreCacheKey] = $arrastre;
        
        return $arrastre;
    }
    
    /**
     * Obtiene el arrastre global para una fecha específica
     * Si está en cache, lo retorna. Si no, calcula la liquidación global del día para obtener el arrastre
     */
    protected function getArrastreGlobalForDate(string $date): float
    {
        $selectedDate = Carbon::parse($date);
        
        // Si es domingo, el arrastre es 0 (no se juega)
        if ($selectedDate->isSunday()) {
            return 0;
        }
        
        // Verificar cache primero
        $arrastreCacheKey = 'global_' . $date . '_arrastre';
        if (isset($this->arrastreCacheGlobal[$arrastreCacheKey]) && $this->arrastreCacheGlobal[$arrastreCacheKey] !== null) {
            return $this->arrastreCacheGlobal[$arrastreCacheKey];
        }
        
        // Si no está en cache, calcular la liquidación global del día para obtener el arrastre
        // Guardar la fecha actual temporalmente
        $originalDate = $this->date;
        $this->date = $date;
        
        // Calcular la liquidación global del día
        $liquidationData = $this->computeGlobalLiquidationData($selectedDate);
        $arrastre = $liquidationData['arrastre'] ?? 0;
        
        // Restaurar la fecha original
        $this->date = $originalDate;
        
        // Guardar en cache
        $this->arrastreCacheGlobal[$arrastreCacheKey] = $arrastre;
        
        return $arrastre;
    }

    /**
     * Obtiene la liquidación anterior del cliente específico
     * Calcula basándose en los datos históricos del cliente
     * 
     * @param int $userId ID del usuario cliente
     * @param string $currentDate Fecha actual
     * @return array|null Datos de la liquidación anterior del cliente
     */
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
        
        // Calcular liquidación del día anterior para este cliente específico
        $prevResultsQuery = Result::query()->whereDate('date', $prevDateStr)->where('user_id', $userId);
        $prevTotalAciert = (float) $prevResultsQuery->sum('aciert');
        
        // ✅ Excluir jugadas anuladas (status != 'I' en plays_sent)
        $prevApusQuery = \App\Models\ApusModel::query()
            ->whereDate('created_at', $prevDateStr)
            ->where('user_id', $userId)
            ->whereHas('playsSent', function($query) {
                $query->where('status', '!=', 'I');
            });
        $prevTotalApus = (float) $prevApusQuery->sum('import');
        
        // Obtener la comisión personalizada del cliente para el cálculo anterior
        $user = \App\Models\User::find($userId);
        $client = \App\Models\Client::where('correo', $user->email)->first();
        $commissionPercentage = $client ? $client->commission_percentage : 20.00;
        
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
            // Obtener el porcentaje semanal del cliente para el cálculo anterior
            $prevWeeklyCommissionPercentage = $client ? ($client->weekly_commission_percentage ?? 30.00) : 30.00;
            
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
     * Obtiene el ajuste de pagos para una fecha específica y cliente
     * Retorna el monto que se debe aplicar al udDeja del día siguiente
     */
    protected function getPaymentsForDate(int $userId, string $date): float
    {
        try {
            // Obtener el cliente asociado al usuario
            $user = \App\Models\User::find($userId);
            if (!$user) {
                return 0.0;
            }
            
            $client = \App\Models\Client::where('correo', $user->email)->first();
            if (!$client) {
                return 0.0;
            }
            
            $payments = ClientPayment::where('client_id', $client->id)
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
            \Log::warning('Error al obtener pagos para fecha en Liquidations: ' . $e->getMessage());
            return 0.0;
        }
    }
    
    /**
     * Obtiene los pagos registrados para una fecha específica y cliente
     * Retorna un array con udDio y udRecibe según el tipo de pago
     * 
     * @param int $userId ID del usuario cliente
     * @param string $date Fecha de la liquidación
     * @return array ['udDio' => float, 'udRecibe' => float]
     */
    protected function getPaymentsForCurrentDate(int $userId, string $date): array
    {
        try {
            // Obtener el cliente asociado al usuario
            $user = \App\Models\User::find($userId);
            if (!$user) {
                return ['udDio' => 0.0, 'udRecibe' => 0.0];
            }
            
            $client = \App\Models\Client::where('correo', $user->email)->first();
            if (!$client) {
                return ['udDio' => 0.0, 'udRecibe' => 0.0];
            }
            
            $payments = ClientPayment::where('client_id', $client->id)
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
            \Log::warning('Error al obtener pagos para fecha actual en Liquidations: ' . $e->getMessage());
            return [
                'udDio' => 0.0,
                'udRecibe' => 0.0,
            ];
        }
    }

    /**
     * Busca recursivamente el anterior de días anteriores hasta encontrar uno con valores
     * 
     * @param \App\Models\User $user
     * @param Carbon $date
     * @param int $depth Profundidad de recursión (máximo 30 días)
     * @return float
     */
    protected function getPreviousAnteriorRecursive($user, Carbon $date, int $depth = 0): float
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
        $previousLiquidation = $this->computeClientLiquidationData($user, $previousDate);
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
        return $this->getPreviousAnteriorRecursive($user, $previousDate, $depth + 1);
    }

    /**
     * ✅ NUEVO: Ordena los resultados por turno (de más temprano a más tarde)
     * Extrae el turno del código de lotería (últimos 4 dígitos) o del campo time
     * 
     * @param \Illuminate\Database\Eloquent\Collection $results
     * @return \Illuminate\Database\Eloquent\Collection
     */
    protected function sortResultsByTurn($results)
    {
        return $results->sortBy(function ($result) {
            // Extraer el turno del código de lotería (ej: NAC1800 -> 1800)
            // El campo lottery puede contener múltiples loterías separadas por comas
            $lotteryCode = trim(explode(',', $result->lottery)[0]); // Tomar la primera lotería
            $turn = null;
            
            // Intentar extraer el turno del código de lotería (últimos 4 dígitos)
            if (preg_match('/(\d{4})$/', $lotteryCode, $matches)) {
                $turn = (int)$matches[1];
            } else {
                // Si no se puede extraer del código, usar el campo time
                // El campo time puede estar en formato HH:MM:SS o HH:MM
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
     * Busca y calcula los datos de liquidación
     * Todos los usuarios solo ven sus datos individuales sin guardar en la tabla global
     */
    public function search()
    {
        $this->resetPage();
        // Los datos se calculan en tiempo real para cada usuario individual
        // No se guardan liquidaciones globales ya que cada usuario ve solo sus datos
    }

    public function resetFilter()
    {
        $this->date = date('Y-m-d');
        $this->resetPage();
    }

    public function render()
    {
        $data = $this->computeLiquidationData();
        return view('livewire.admin.liquidations', array_merge($data, [
            'date' => $this->date,
        ]));
    }
}
