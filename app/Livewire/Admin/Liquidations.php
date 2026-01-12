<?php

namespace App\Livewire\Admin;

use App\Models\Result; // Use the correct Result model
use App\Models\DailyLiquidation;
use App\Models\ClientPayment;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

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
    
    /**
     * Cache de instancia para almacenar UD DEJA calculados (por cliente)
     */
    protected $udDejaCache = [];
    
    /**
     * Cache de instancia para almacenar el primer día de liquidación de cada usuario
     */
    protected $firstLiquidationDateCache = [];

    protected $listeners = ['paymentSaved' => 'clearCacheOnPayment'];
    
    public function mount()
    {
        $this->date = Carbon::yesterday()->format('Y-m-d');
    }
    
    /**
     * Limpia el cache cuando se guarda un pago desde otro componente
     */
    public function clearCacheOnPayment($userId, $paymentDate)
    {
        // Limpiar cache del día del pago y días siguientes (hasta 30 días)
        $paymentDateCarbon = Carbon::parse($paymentDate);
        
        for ($i = 0; $i <= 30; $i++) {
            $dateToClear = $paymentDateCarbon->copy()->addDays($i);
            $cacheKey = $userId . '_' . $dateToClear->format('Y-m-d');
            $cacheKeyWithPayments = $userId . '_' . $dateToClear->format('Y-m-d') . '_with_payments';
            
            unset($this->anteriorCache[$cacheKey]);
            unset($this->anteriorCache[$cacheKeyWithPayments]);
        }
        
        // También limpiar cache de arrastre y UD DEJA
        for ($i = 0; $i <= 30; $i++) {
            $dateToClear = $paymentDateCarbon->copy()->addDays($i);
            $arrastreCacheKey = $userId . '_' . $dateToClear->format('Y-m-d') . '_arrastre';
            $udDejaCacheKey = $userId . '_' . $dateToClear->format('Y-m-d') . '_uddeja';
            unset($this->arrastreCache[$arrastreCacheKey]);
            unset($this->udDejaCache[$udDejaCacheKey]);
        }
        
        // Forzar recarga del componente para que se recalculen los valores
        $this->dispatch('$refresh');
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
        // ✅ Calcular tiempo automáticamente: usar timeApu si existe, sino extraer del código lottery
        // Extrae los últimos 4 dígitos del código (ej: NAC1015 -> 1015 -> 10:15)
        // Usar REGEXP para extraer solo cuando el código termine en 4 dígitos
        $previaTotalApus   = (float) (clone $apusQuery)->whereRaw("
            COALESCE(
                TIME_FORMAT(timeApu, '%H:%i'),
                CASE 
                    WHEN lottery REGEXP '[0-9]{4}$' THEN
                        CONCAT(
                            LPAD(SUBSTRING(lottery, -4, 2), 2, '0'),
                            ':',
                            LPAD(SUBSTRING(lottery, -2, 2), 2, '0')
                        )
                    ELSE NULL
                END
            ) = '10:15'
        ")->sum('import');
        $mananaTotalApus   = (float) (clone $apusQuery)->whereRaw("
            COALESCE(
                TIME_FORMAT(timeApu, '%H:%i'),
                CASE 
                    WHEN lottery REGEXP '[0-9]{4}$' THEN
                        CONCAT(
                            LPAD(SUBSTRING(lottery, -4, 2), 2, '0'),
                            ':',
                            LPAD(SUBSTRING(lottery, -2, 2), 2, '0')
                        )
                    ELSE NULL
                END
            ) = '12:00'
        ")->sum('import');
        $matutinaTotalApus = (float) (clone $apusQuery)->whereRaw("
            COALESCE(
                TIME_FORMAT(timeApu, '%H:%i'),
                CASE 
                    WHEN lottery REGEXP '[0-9]{4}$' THEN
                        CONCAT(
                            LPAD(SUBSTRING(lottery, -4, 2), 2, '0'),
                            ':',
                            LPAD(SUBSTRING(lottery, -2, 2), 2, '0')
                        )
                    ELSE NULL
                END
            ) = '15:00'
        ")->sum('import');
        $tardeTotalApus    = (float) (clone $apusQuery)->whereRaw("
            COALESCE(
                TIME_FORMAT(timeApu, '%H:%i'),
                CASE 
                    WHEN lottery REGEXP '[0-9]{4}$' THEN
                        CONCAT(
                            LPAD(SUBSTRING(lottery, -4, 2), 2, '0'),
                            ':',
                            LPAD(SUBSTRING(lottery, -2, 2), 2, '0')
                        )
                    ELSE NULL
                END
            ) = '18:00'
        ")->sum('import');
        $nocheTotalApus    = (float) (clone $apusQuery)->whereRaw("
            COALESCE(
                TIME_FORMAT(timeApu, '%H:%i'),
                CASE 
                    WHEN lottery REGEXP '[0-9]{4}$' THEN
                        CONCAT(
                            LPAD(SUBSTRING(lottery, -4, 2), 2, '0'),
                            ':',
                            LPAD(SUBSTRING(lottery, -2, 2), 2, '0')
                        )
                    ELSE NULL
                END
            ) = '21:00'
        ")->sum('import');
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
            // ✅ NUEVA LÓGICA: Calcular comisión semanal basada en (ANTERI + TOTAL DEJA) × porcentaje
            // Base para comisión = ANTERI + TOTAL DEJA (suma algebraica)
            $baseComision = $prevGenerDeja + $totalGanaPase;
            
            // Calcular comisión semanal: Base × 30% (fijo para liquidación global)
            $comiDejaSem = $baseComision * 0.30;
            
            // UD DEJA del sábado = Gener DEJA (totalGanaPase) - comiDejaSem
            $udDeja = $totalGanaPase - $comiDejaSem;
            
            // Calcular arrastre del viernes para el arrastre del sábado
            $previousDate = $selectedDate->copy()->subDay();
            $prevArrastre = $this->getArrastreGlobalForDate($previousDate->format('Y-m-d'));
            
            // Calcular UD Deja temporal del sábado (sin comisión) para el arrastre
            $udDejaTemp = $totalGanaPase + $prevGenerDeja;
            
            // Calcular arrastre del sábado (arrastre del viernes + UD Deja temporal del sábado)
            $arrastre = $prevArrastre + $udDejaTemp;
        } else {
            // Cuando no es sábado, la comisión semanal es 0 (no aplica)
            $comiDejaSem = 0;
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
     * ✅ NUEVO: Encuentra el primer día de liquidación de un usuario
     * Busca el primer día donde el usuario tiene apuestas o resultados
     * 
     * @param int $userId ID del usuario
     * @return string|null Fecha del primer día de liquidación (Y-m-d) o null si no hay datos
     */
    protected function getFirstLiquidationDate(int $userId): ?string
    {
        // Verificar cache primero
        if (isset($this->firstLiquidationDateCache[$userId])) {
            return $this->firstLiquidationDateCache[$userId];
        }
        
        // Buscar el primer día con apuestas (excluyendo anuladas)
        $firstApuDate = \App\Models\ApusModel::query()
            ->where('user_id', $userId)
            ->whereHas('playsSent', function($query) {
                $query->where('status', '!=', 'I');
            })
            ->orderBy('created_at', 'asc')
            ->value('created_at');
        
        // Buscar el primer día con resultados
        $firstResultDate = Result::query()
            ->where('user_id', $userId)
            ->orderBy('date', 'asc')
            ->value('date');
        
        // Determinar el primer día (el más antiguo entre apuestas y resultados)
        $firstDate = null;
        if ($firstApuDate && $firstResultDate) {
            $firstApuCarbon = Carbon::parse($firstApuDate);
            $firstResultCarbon = Carbon::parse($firstResultDate);
            $firstDate = $firstApuCarbon->lt($firstResultCarbon) ? $firstApuCarbon : $firstResultCarbon;
        } elseif ($firstApuDate) {
            $firstDate = Carbon::parse($firstApuDate);
        } elseif ($firstResultDate) {
            $firstDate = Carbon::parse($firstResultDate);
        }
        
        $firstDateStr = $firstDate ? $firstDate->format('Y-m-d') : null;
        
        // Guardar en cache
        $this->firstLiquidationDateCache[$userId] = $firstDateStr;
        
        return $firstDateStr;
    }
    
    /**
     * ✅ MODIFICADO: Calcula ANTERI según la nueva lógica
     * Si es el primer día de liquidación del usuario: ANTERI = 0
     * Si no es el primer día: ANTERI = UD DEJA del día anterior (con pagos aplicados)
     * ✅ EXCEPCIÓN: Si es lunes, ANTERI = cálculo semanal + total deja (o - total deja si es negativo)
     * 
     * @param int $userId ID del usuario
     * @param Carbon $selectedDate Fecha seleccionada
     * @param float $totalGanaPase TOTAL DEJA del día actual
     * @return float Valor de ANTERI para el día actual
     */
    protected function calculateAnteri(int $userId, Carbon $selectedDate, float $totalGanaPase): float
    {
        // Si es domingo, ANTERI = 0
        if ($selectedDate->isSunday()) {
            return 0;
        }
        
        $dateStr = $selectedDate->format('Y-m-d');
        
        // Verificar cache primero
        $anteriCacheKey = $userId . '_' . $dateStr . '_anteri_new';
        if (isset($this->anteriorCache[$anteriCacheKey]) && $this->anteriorCache[$anteriCacheKey] !== null) {
            return $this->anteriorCache[$anteriCacheKey];
        }
        
        // Obtener el primer día de liquidación del usuario
        $firstLiquidationDate = $this->getFirstLiquidationDate($userId);
        
        // Si no hay primer día de liquidación, ANTERI = 0
        if (!$firstLiquidationDate) {
            $this->anteriorCache[$anteriCacheKey] = 0;
            return 0;
        }
        
        $firstLiquidationCarbon = Carbon::parse($firstLiquidationDate);
        
        // Si la fecha seleccionada es anterior al primer día de liquidación, ANTERI = 0
        if ($selectedDate->lt($firstLiquidationCarbon)) {
            $this->anteriorCache[$anteriCacheKey] = 0;
            return 0;
        }
        
        // ✅ Si es el primer día de liquidación, ANTERI = 0 (día de activación)
        if ($dateStr === $firstLiquidationDate) {
            $this->anteriorCache[$anteriCacheKey] = 0;
            return 0;
        }
        
        // ✅ EXCEPCIÓN: Si es lunes, usar UD DEJA/COBRA del sábado anterior
        if ($selectedDate->isMonday()) {
            // Obtener el sábado anterior (2 días atrás)
            $lastSaturday = $selectedDate->copy()->subDays(2);
            $saturdayDateStr = $lastSaturday->format('Y-m-d');
        
            // Obtener el UD DEJA/COBRA del sábado anterior del cache (con pagos aplicados)
            $saturdayUdDejaCacheKey = $userId . '_' . $saturdayDateStr . '_uddeja_with_payments';
            $saturdayUdDeja = null;
            
            if (isset($this->udDejaCache[$saturdayUdDejaCacheKey]) && $this->udDejaCache[$saturdayUdDejaCacheKey] !== null) {
                $saturdayUdDeja = $this->udDejaCache[$saturdayUdDejaCacheKey];
            } else {
                // Si no está en cache, calcular el UD DEJA/COBRA del sábado anterior
                // Calcular datos del sábado anterior
                $saturdayTotalAciert = (float) Result::query()
                    ->whereDate('date', $saturdayDateStr)
                ->where('user_id', $userId)
                ->sum('aciert');
            
                $saturdayApusQuery = \App\Models\ApusModel::query()
                    ->whereDate('created_at', $saturdayDateStr)
                ->where('user_id', $userId)
                ->whereHas('playsSent', function($query) {
                    $query->where('status', '!=', 'I');
                });
                $saturdayTotalApus = (float) $saturdayApusQuery->sum('import');
            
            $user = \App\Models\User::find($userId);
            $client = $user ? \App\Models\Client::where('correo', $user->email)->first() : null;
            $commissionPercentage = $client ? $client->commission_percentage : 20.00;
                $saturdayComision = $saturdayTotalApus * ($commissionPercentage / 100);
                $saturdayTotalGanaPase = $saturdayTotalApus - $saturdayComision - $saturdayTotalAciert;
            
                // Obtener el ANTERI del sábado anterior
                $saturdayAnteri = $this->calculateAnteri($userId, $lastSaturday, $saturdayTotalGanaPase);
                
                // Calcular COMI DEJA SEM del sábado anterior
                $weeklyCommissionPercentage = $client ? ($client->weekly_commission_percentage ?? 30.00) : 30.00;
                $saturdayBaseComision = $saturdayAnteri + $saturdayTotalGanaPase;
                
                if ($weeklyCommissionPercentage > 0) {
                    $saturdayComiDejaSem = $saturdayBaseComision * ($weeklyCommissionPercentage / 100);
                } else {
                    $saturdayComiDejaSem = $saturdayBaseComision * 0.30;
                }
                
                // Calcular UD DEJA/COBRA del sábado anterior
                if ($saturdayTotalGanaPase >= 0) {
                    $saturdayUdDejaNoPayments = ($saturdayAnteri + $saturdayTotalGanaPase) - $saturdayComiDejaSem;
                } else {
                    $saturdayUdDejaNoPayments = ($saturdayAnteri - $saturdayTotalGanaPase) - $saturdayComiDejaSem;
        }
        
                // Aplicar pagos del sábado anterior
                $saturdayPayments = $this->getPaymentsForCurrentDate($userId, $saturdayDateStr);
                $saturdayUdDeja = $saturdayUdDejaNoPayments - $saturdayPayments['udDio'] + $saturdayPayments['udRecibe'];
                
                // Guardar en cache
                $this->udDejaCache[$saturdayUdDejaCacheKey] = $saturdayUdDeja;
            }
            
            // ANTERI del lunes = UD DEJA/COBRA del sábado anterior (puede ser positivo o negativo)
            $anteri = $saturdayUdDeja;
            
            // Guardar en cache
            $this->anteriorCache[$anteriCacheKey] = $anteri;
            return $anteri;
        }
        
        // Obtener el día anterior (saltando domingos)
        $previousDate = $selectedDate->copy()->subDay();
        if ($previousDate->isSunday()) {
            $previousDate = $previousDate->copy()->subDay(); // Sábado anterior
        }
        
        $previousDateStr = $previousDate->format('Y-m-d');
        
        // ✅ NUEVA LÓGICA: ANTERI = UD DEJA del día anterior (con pagos aplicados)
        // Intentar obtener del cache primero
        $previousUdDejaCacheKey = $userId . '_' . $previousDateStr . '_uddeja_with_payments';
        $previousUdDeja = null;
        
        if (isset($this->udDejaCache[$previousUdDejaCacheKey]) && $this->udDejaCache[$previousUdDejaCacheKey] !== null) {
            $previousUdDeja = $this->udDejaCache[$previousUdDejaCacheKey];
        } else {
            // Si no está en cache, calcular el UD DEJA del día anterior completamente
            // Calcular datos del día anterior
            $previousTotalAciert = (float) Result::query()
                ->whereDate('date', $previousDateStr)
                ->where('user_id', $userId)
                ->sum('aciert');
            
            $previousApusQuery = \App\Models\ApusModel::query()
                ->whereDate('created_at', $previousDateStr)
                ->where('user_id', $userId)
                ->whereHas('playsSent', function($query) {
                    $query->where('status', '!=', 'I');
                });
            $previousTotalApus = (float) $previousApusQuery->sum('import');
            
            $user = \App\Models\User::find($userId);
            $client = $user ? \App\Models\Client::where('correo', $user->email)->first() : null;
            $commissionPercentage = $client ? $client->commission_percentage : 20.00;
            $previousComision = $previousTotalApus * ($commissionPercentage / 100);
            $previousTotalGanaPase = $previousTotalApus - $previousComision - $previousTotalAciert;
            
            // Obtener el ANTERI del día anterior (que es el UD DEJA del día anterior al anterior)
            $previousAnteri = $this->calculateAnteri($userId, $previousDate, $previousTotalGanaPase);
            
            // Calcular UD DEJA del día anterior según el día
            $previousUdDejaNoPayments = 0;
            if ($previousDate->isSunday()) {
                $previousUdDejaNoPayments = 0;
            } elseif ($previousDate->isSaturday()) {
                // Para sábado, calcular comiDejaSem usando nueva lógica
                $weeklyCommissionPercentage = $client ? ($client->weekly_commission_percentage ?? 30.00) : 30.00;
                
                // ✅ NUEVA LÓGICA: Calcular comisión semanal basada en (ANTERI + TOTAL DEJA) × porcentaje
                // Base para comisión = ANTERI + TOTAL DEJA (suma algebraica)
                $baseComision = $previousAnteri + $previousTotalGanaPase;
                
                // Calcular comisión semanal: Base × porcentaje
                if ($weeklyCommissionPercentage > 0) {
                    $comiDejaSem = $baseComision * ($weeklyCommissionPercentage / 100);
        } else {
                    $comiDejaSem = $baseComision * 0.30;
                }
                
                // ✅ NUEVA LÓGICA: UD DEJA/COBRA del sábado según si TOTAL DEJA es positivo o negativo
                // Si TOTAL DEJA es positivo: UD DEJA = (ANTERI + TOTAL DEJA) - COMI DEJA SEM
                // Si TOTAL DEJA es negativo: UD DEJA/COBRA = (ANTERI - TOTAL DEJA) - COMI DEJA SEM
                if ($previousTotalGanaPase >= 0) {
                    $previousUdDejaNoPayments = ($previousAnteri + $previousTotalGanaPase) - $comiDejaSem;
                } else {
                    $previousUdDejaNoPayments = ($previousAnteri - $previousTotalGanaPase) - $comiDejaSem;
        }
            } elseif ($previousTotalApus == 0) {
                $previousUdDejaNoPayments = 0;
            } else {
                // Para otros días, UD DEJA = totalGanaPase + anterior
                $previousUdDejaNoPayments = $previousTotalGanaPase + $previousAnteri;
            }
            
            // Obtener los pagos del día anterior
            $previousPayments = $this->getPaymentsForCurrentDate($userId, $previousDateStr);
            
            // Calcular UD DEJA con pagos: UD DEJA - UD.DIO + UD.RECIBE
            $previousUdDeja = $previousUdDejaNoPayments - $previousPayments['udDio'] + $previousPayments['udRecibe'];
            
            // Guardar en cache para uso futuro
            $this->udDejaCache[$previousUdDejaCacheKey] = $previousUdDeja;
        }
        
        // ANTERI = UD DEJA del día anterior (con pagos aplicados)
        $anteri = $previousUdDeja;
        
        // Guardar en cache
        $this->anteriorCache[$anteriCacheKey] = $anteri;
        
        return $anteri;
    }

    /**
     * Calcula liquidación individual para clientes
     * Solo muestra datos del cliente específico
     * 
     * @param User $user Usuario cliente
     * @param Carbon $selectedDate Fecha seleccionada
     * @return array Datos de liquidación del cliente
     */
    public function computeClientLiquidationData($user, Carbon $selectedDate): array
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
        // ✅ Calcular tiempo automáticamente: usar timeApu si existe, sino extraer del código lottery
        // Extrae los últimos 4 dígitos del código (ej: NAC1015 -> 1015 -> 10:15)
        // Usar REGEXP para extraer solo cuando el código termine en 4 dígitos
        $previaTotalApus   = (float) (clone $apusQuery)->whereRaw("
            COALESCE(
                TIME_FORMAT(timeApu, '%H:%i'),
                CASE 
                    WHEN lottery REGEXP '[0-9]{4}$' THEN
                        CONCAT(
                            LPAD(SUBSTRING(lottery, -4, 2), 2, '0'),
                            ':',
                            LPAD(SUBSTRING(lottery, -2, 2), 2, '0')
                        )
                    ELSE NULL
                END
            ) = '10:15'
        ")->sum('import');
        $mananaTotalApus   = (float) (clone $apusQuery)->whereRaw("
            COALESCE(
                TIME_FORMAT(timeApu, '%H:%i'),
                CASE 
                    WHEN lottery REGEXP '[0-9]{4}$' THEN
                        CONCAT(
                            LPAD(SUBSTRING(lottery, -4, 2), 2, '0'),
                            ':',
                            LPAD(SUBSTRING(lottery, -2, 2), 2, '0')
                        )
                    ELSE NULL
                END
            ) = '12:00'
        ")->sum('import');
        $matutinaTotalApus = (float) (clone $apusQuery)->whereRaw("
            COALESCE(
                TIME_FORMAT(timeApu, '%H:%i'),
                CASE 
                    WHEN lottery REGEXP '[0-9]{4}$' THEN
                        CONCAT(
                            LPAD(SUBSTRING(lottery, -4, 2), 2, '0'),
                            ':',
                            LPAD(SUBSTRING(lottery, -2, 2), 2, '0')
                        )
                    ELSE NULL
                END
            ) = '15:00'
        ")->sum('import');
        $tardeTotalApus    = (float) (clone $apusQuery)->whereRaw("
            COALESCE(
                TIME_FORMAT(timeApu, '%H:%i'),
                CASE 
                    WHEN lottery REGEXP '[0-9]{4}$' THEN
                        CONCAT(
                            LPAD(SUBSTRING(lottery, -4, 2), 2, '0'),
                            ':',
                            LPAD(SUBSTRING(lottery, -2, 2), 2, '0')
                        )
                    ELSE NULL
                END
            ) = '18:00'
        ")->sum('import');
        $nocheTotalApus    = (float) (clone $apusQuery)->whereRaw("
            COALESCE(
                TIME_FORMAT(timeApu, '%H:%i'),
                CASE 
                    WHEN lottery REGEXP '[0-9]{4}$' THEN
                        CONCAT(
                            LPAD(SUBSTRING(lottery, -4, 2), 2, '0'),
                            ':',
                            LPAD(SUBSTRING(lottery, -2, 2), 2, '0')
                        )
                    ELSE NULL
                END
            ) = '21:00'
        ")->sum('import');
        $totalApus = $previaTotalApus + $mananaTotalApus + $matutinaTotalApus + $tardeTotalApus + $nocheTotalApus;
        
        // Obtener la comisión personalizada del cliente
        $client = \App\Models\Client::where('correo', $user->email)->first();
        $commissionPercentage = $client ? $client->commission_percentage : 20.00;
        $comision = $totalApus * ($commissionPercentage / 100);
        $totalGanaPase = $totalApus - $comision - $totalAciert;
        
        // ✅ NUEVA LÓGICA: Calcular ANTERI usando la nueva función
        // ANTERI = ANTERI del día anterior + TOTAL DEJA del día actual
        // Si es el primer día de liquidación, ANTERI = 0
        $anteriForDisplay = $this->calculateAnteri($user->id, $selectedDate, $totalGanaPase);
        
        // Para compatibilidad con código existente, mantener prevClientDeja como ANTERI
        // pero ahora se calcula con la nueva lógica
        $prevClientDeja = $anteriForDisplay;
        
        // Calcular arrastre individual del cliente
        // Obtener el porcentaje semanal del cliente
        $weeklyCommissionPercentage = $client ? ($client->weekly_commission_percentage ?? 30.00) : 30.00;
        
        // Si es domingo, todo en 0 (no se juega)
        if ($selectedDate->isSunday()) {
            $udDeja = 0;
            $udCobra = 0;
            $udDejaCalculado = 0;
            $udDejaParaArrastre = 0;
            $arrastre = 0;
            $comiDejaSem = 0; // No aplica en domingo
        }
        // Si es sábado, SIEMPRE calcular comisión semanal basada en ANTERI + TOTAL DEJA
        elseif ($selectedDate->isSaturday()) {
            // ✅ NUEVA LÓGICA: Calcular comisión semanal basada en (ANTERI + TOTAL DEJA) × porcentaje
            // Base para comisión = ANTERI + TOTAL DEJA (suma algebraica)
            $baseComision = $prevClientDeja + $totalGanaPase;
            
            // Calcular comisión semanal: Base × porcentaje
            // Por defecto es el 30% si no está configurado
            if ($weeklyCommissionPercentage > 0) {
                $comiDejaSem = $baseComision * ($weeklyCommissionPercentage / 100);
            } else {
                // Si no hay porcentaje configurado, usar 30% por defecto
                $comiDejaSem = $baseComision * 0.30;
            }
            
            // ✅ NUEVA LÓGICA: UD DEJA/COBRA del sábado según si TOTAL DEJA es positivo o negativo
            // Si TOTAL DEJA es positivo: UD DEJA = (ANTERI + TOTAL DEJA) - COMI DEJA SEM
            // Si TOTAL DEJA es negativo: UD DEJA/COBRA = (ANTERI - TOTAL DEJA) - COMI DEJA SEM
            if ($totalGanaPase >= 0) {
                $udDejaCalculado = ($prevClientDeja + $totalGanaPase) - $comiDejaSem;
            } else {
                // Cuando TOTAL DEJA es negativo: (ANTERI - TOTAL DEJA) - COMI DEJA SEM
                // Nota: Si TOTAL DEJA = -200,000, entonces ANTERI - (-200,000) = ANTERI + 200,000
                $udDejaCalculado = ($prevClientDeja - $totalGanaPase) - $comiDejaSem;
            }
            
            // Calcular arrastre del viernes para el arrastre del sábado
            $previousDate = $selectedDate->copy()->subDay();
            $prevArrastre = $this->getArrastreForDate($previousDate->format('Y-m-d'), $user->id);
            
            // Calcular UD Deja temporal del sábado (sin comisión) para el arrastre
            $udDejaTemp = $totalGanaPase + $prevClientDeja;
            
            // Calcular arrastre del sábado (arrastre del viernes + UD Deja temporal del sábado)
            $arrastre = $prevArrastre + $udDejaTemp;
            
            // Separar UD DEJA y UD COBRA según el resultado
            if ($udDejaCalculado >= 0) {
                $udDeja = $udDejaCalculado;
                $udCobra = 0;
            } else {
                $udDeja = 0;
                $udCobra = $udDejaCalculado; // Mantener el valor negativo
            }
            
            // Para el arrastre, usar el valor calculado
            $udDejaParaArrastre = $udDejaCalculado;
        }
        // Si no hay apuestas (y no es sábado), UD Deja es 0 pero el arrastre mantiene el del día anterior
        elseif ($totalApus == 0) {
            $udDeja = 0; // UD Deja en 0 cuando no hay apuestas
            $udCobra = 0; // UD Cobra en 0 cuando no hay apuestas
            $udDejaCalculado = 0;
            $udDejaParaArrastre = 0;
            $comiDejaSem = 0; // No aplica cuando no hay apuestas
            
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
        } else {
            // Cuando no es sábado, la comisión semanal es 0 (no aplica)
            $comiDejaSem = 0;
            // ✅ Calcular UD Deja/Cobra: ANTERI + TOTAL DEJA (suma algebraica)
            // Si el resultado es positivo: UD DEJA
            // Si el resultado es negativo: UD COBRA (se mostrará con signo negativo)
            $udDejaCalculado = $prevClientDeja + $totalGanaPase;
            
            // Separar UD DEJA y UD COBRA según el resultado
            if ($udDejaCalculado >= 0) {
                $udDeja = $udDejaCalculado;
                $udCobra = 0;
            } else {
                $udDeja = 0;
                $udCobra = $udDejaCalculado; // Mantener el valor negativo
            }
            
            // Para el arrastre, usar el valor calculado (puede ser positivo o negativo)
            $udDejaParaArrastre = $udDejaCalculado;
            
            // Calcular Arrastre según el día
            if ($selectedDate->isMonday()) {
                // Lunes: Arrastre = UD Deja/Cobra (comienza en 0, luego es igual a UD Deja/Cobra)
                    $arrastre = $udDejaParaArrastre;
            } else {
                // Martes a Viernes: Arrastre = Arrastre del día anterior + UD Deja/Cobra del día actual
                $previousDate = $selectedDate->copy()->subDay();
                $prevArrastre = $this->getArrastreForDate($previousDate->format('Y-m-d'), $user->id);
                $arrastre = $prevArrastre + $udDejaParaArrastre;
            }
        }
        
        // Obtener los pagos registrados para la fecha actual
        $currentPayments = $this->getPaymentsForCurrentDate($user->id, $dateStr);
        
        // ✅ ANTERI ya está calculado con la nueva lógica en anteriForDisplay
        // No necesita ajustes adicionales
        
        // El UD DEJA/COBRA del día actual se calcula usando el ANTERI (que es el UD DEJA/COBRA del día anterior)
        // Los pagos del día actual afectan al UD DEJA/COBRA del día actual
        $udDejaCalculadoWithPayments = $udDejaCalculado - $currentPayments['udDio'] + $currentPayments['udRecibe'];
        
        // Separar UD DEJA y UD COBRA con pagos aplicados
        if ($udDejaCalculadoWithPayments >= 0) {
            $udDejaWithPayments = $udDejaCalculadoWithPayments;
            $udCobraWithPayments = 0;
        } else {
            $udDejaWithPayments = 0;
            $udCobraWithPayments = $udDejaCalculadoWithPayments; // Mantener el valor negativo
        }
        
        // Guardar el UD DEJA/COBRA del día actual (con pagos aplicados) en cache
        // Este será el ANTERI del día siguiente (puede ser positivo o negativo)
        $udDejaCacheKey = $user->id . '_' . $dateStr . '_uddeja_with_payments';
        $this->udDejaCache[$udDejaCacheKey] = $udDejaCalculadoWithPayments; // Guardar el valor completo (puede ser negativo)
        
        // También guardar el UD DEJA/COBRA sin pagos para referencia (valor completo)
        $udDejaCacheKeyNoPayments = $user->id . '_' . $dateStr . '_uddeja';
        if (!isset($this->udDejaCache[$udDejaCacheKeyNoPayments]) || $this->udDejaCache[$udDejaCacheKeyNoPayments] === null) {
            $this->udDejaCache[$udDejaCacheKeyNoPayments] = $udDejaCalculado; // Guardar el valor completo
        }
        
        // Guardar el arrastre en cache para uso en días siguientes
        $arrastreCacheKey = $user->id . '_' . $dateStr . '_arrastre';
        $this->arrastreCache[$arrastreCacheKey] = $arrastre;
        
        // ✅ Calcular cálculo semanal los sábados: arrastre - comiDejaSem
        $calculoSemanal = 0;
        if ($selectedDate->isSaturday()) {
            $calculoSemanal = $arrastre - $comiDejaSem;
            
            // Guardar en cache de Laravel (persistente)
            $cacheKey = 'calculo_semanal_' . $user->id . '_' . $dateStr;
            Cache::put($cacheKey, $calculoSemanal, now()->addDays(30)); // Guardar por 30 días
        } else {
            // Si no es sábado, intentar obtener el último cálculo semanal del cache
            $lastSaturday = $selectedDate->copy()->previous(Carbon::SATURDAY);
            if ($lastSaturday && $lastSaturday->lte($selectedDate)) {
                $cacheKey = 'calculo_semanal_' . $user->id . '_' . $lastSaturday->format('Y-m-d');
                $calculoSemanal = Cache::get($cacheKey, 0);
            }
        }
        
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
            'anteri'            => $anteriForDisplay,
            'udRecibe'          => $totalAciert,
            'udDeja'            => $udDeja,
            'udCobra'           => $udCobra,
            'arrastre'          => $arrastre,
            'comi_deja_sem'     => $comiDejaSem,
            'calculo_semanal'   => $calculoSemanal,
            'udDio'             => $currentPayments['udDio'],
            'udRecibePayment'   => $currentPayments['udRecibe'],
            'paymentDateDio'    => $currentPayments['paymentDateDio'],
            'paymentDateRecibe' => $currentPayments['paymentDateRecibe'],
        ];
        
        // ✅ Guardar ANTERI en cache para uso futuro
        // El ANTERI ya está calculado y guardado en calculateAnteri
        // No necesitamos guardar valores adicionales aquí
    }
    
    /**
     * ✅ MODIFICADO: Obtiene el ANTERI para una fecha específica usando la nueva lógica
     * Ahora usa calculateAnteri que implementa: ANTERI = ANTERI día anterior + TOTAL DEJA día actual
     */
    public function getAnteriorForDate(string $date, int $userId, int $depth = 0): float
    {
        $selectedDate = Carbon::parse($date);
        
        // Si es domingo, el anterior es 0
        if ($selectedDate->isSunday()) {
            return 0;
        }
        
        // Limitar la recursión a máximo 30 días para evitar consultas excesivas
        if ($depth > 30) {
            return 0;
        }
        
        // Verificar cache primero
        $anteriCacheKey = $userId . '_' . $date . '_anteri_new';
        if (isset($this->anteriorCache[$anteriCacheKey]) && $this->anteriorCache[$anteriCacheKey] !== null) {
            return $this->anteriorCache[$anteriCacheKey];
        }
        
        // Calcular TOTAL DEJA del día solicitado
        $totalAciert = (float) Result::query()
            ->whereDate('date', $date)
            ->where('user_id', $userId)
            ->sum('aciert');
        
        $apusQuery = \App\Models\ApusModel::query()
            ->whereDate('created_at', $date)
            ->where('user_id', $userId)
            ->whereHas('playsSent', function($query) {
                $query->where('status', '!=', 'I');
            });
        $totalApus = (float) $apusQuery->sum('import');
        
        $user = \App\Models\User::find($userId);
        $client = $user ? \App\Models\Client::where('correo', $user->email)->first() : null;
        $commissionPercentage = $client ? $client->commission_percentage : 20.00;
        $comision = $totalApus * ($commissionPercentage / 100);
        $totalGanaPase = $totalApus - $comision - $totalAciert;
        
        // Usar la nueva función calculateAnteri
        $anteri = $this->calculateAnteri($userId, $selectedDate, $totalGanaPase);
        
        return $anteri;
    }
    
    /**
     * Obtiene el UD DEJA para una fecha específica
     * Calcula directamente el UD DEJA sin llamar a computeClientLiquidationData para evitar recursión
     */
    public function getUdDejaForDate(string $date, int $userId, int $depth = 0): float
    {
        $selectedDate = Carbon::parse($date);
        
        // Si es domingo, el UD DEJA es 0 (no se juega)
        if ($selectedDate->isSunday()) {
            return 0;
        }
        
        // Limitar la recursión a máximo 30 días para evitar consultas excesivas
        if ($depth > 30) {
            return 0;
        }
        
        // Verificar cache primero
        $udDejaCacheKey = $userId . '_' . $date . '_uddeja';
        if (isset($this->udDejaCache[$udDejaCacheKey]) && $this->udDejaCache[$udDejaCacheKey] !== null) {
            return $this->udDejaCache[$udDejaCacheKey];
        }
        
        // Si está marcado como null, significa que está siendo calculado, retornar 0 para evitar recursión
        if (isset($this->udDejaCache[$udDejaCacheKey]) && $this->udDejaCache[$udDejaCacheKey] === null) {
            return 0;
        }
        
        // Marcar que estamos calculando para evitar recursión infinita
        $this->udDejaCache[$udDejaCacheKey] = null;
        
        // Calcular UD DEJA directamente sin llamar a computeClientLiquidationData
        $user = \App\Models\User::find($userId);
        if (!$user) {
            $this->udDejaCache[$udDejaCacheKey] = 0;
            return 0;
        }
        
        $dateStr = $selectedDate->format('Y-m-d');
        
        // Calcular totalAciert
        $totalAciert = (float) Result::query()->whereDate('date', $dateStr)->where('user_id', $userId)->sum('aciert');
        
        // Calcular totalApus (excluyendo jugadas anuladas)
        $apusQuery = \App\Models\ApusModel::query()
            ->whereDate('created_at', $dateStr)
            ->where('user_id', $userId)
            ->whereHas('playsSent', function($query) {
                $query->where('status', '!=', 'I');
            });
        $totalApus = (float) $apusQuery->sum('import');
        
        // Si no hay apuestas, UD DEJA es 0
        if ($totalApus == 0) {
            $this->udDejaCache[$udDejaCacheKey] = 0;
            return 0;
        }
        
        // Obtener comisión del cliente
        $client = \App\Models\Client::where('correo', $user->email)->first();
        $commissionPercentage = $client ? $client->commission_percentage : 20.00;
        $comision = $totalApus * ($commissionPercentage / 100);
        $totalGanaPase = $totalApus - $comision - $totalAciert;
        
        // Obtener el anterior del día
        $prevClientDeja = 0;
        if ($selectedDate->isMonday()) {
            // Si es lunes, el anterior es del sábado anterior (2 días atrás)
            $prevDate = $selectedDate->copy()->subDays(2);
            $prevClientDeja = $this->getAnteriorForDate($prevDate->format('Y-m-d'), $userId, $depth + 1);
        } else {
            // Para otros días, obtener el anterior del día anterior
            $prevDate = $selectedDate->copy()->subDay();
            if ($prevDate->isSunday()) {
                $prevDate = $prevDate->copy()->subDay(); // Sábado anterior
            }
            $prevClientDeja = $this->getAnteriorForDate($prevDate->format('Y-m-d'), $userId, $depth + 1);
        }
        
        // Calcular UD DEJA según el día
        if ($selectedDate->isSaturday()) {
            // Para sábado, calcular comiDejaSem usando nueva lógica
            $weeklyCommissionPercentage = $client ? ($client->weekly_commission_percentage ?? 30.00) : 30.00;
            
            // ✅ NUEVA LÓGICA: Calcular comisión semanal basada en (ANTERI + TOTAL DEJA) × porcentaje
            // Base para comisión = ANTERI + TOTAL DEJA (suma algebraica)
            $baseComision = $prevClientDeja + $totalGanaPase;
            
            // Calcular comisión semanal: Base × porcentaje
            if ($weeklyCommissionPercentage > 0) {
                $comiDejaSem = $baseComision * ($weeklyCommissionPercentage / 100);
            } else {
                $comiDejaSem = $baseComision * 0.30;
            }
            
            // ✅ NUEVA LÓGICA: UD DEJA/COBRA del sábado según si TOTAL DEJA es positivo o negativo
            // Si TOTAL DEJA es positivo: UD DEJA = (ANTERI + TOTAL DEJA) - COMI DEJA SEM
            // Si TOTAL DEJA es negativo: UD DEJA/COBRA = (ANTERI - TOTAL DEJA) - COMI DEJA SEM
            if ($totalGanaPase >= 0) {
                $udDeja = ($prevClientDeja + $totalGanaPase) - $comiDejaSem;
        } else {
                $udDeja = ($prevClientDeja - $totalGanaPase) - $comiDejaSem;
            }
        } else {
            // ✅ Para otros días, UD DEJA = ANTERI + TOTAL DEJA (suma algebraica)
            // Si totalGanaPase es positivo: UD DEJA = ANTERI + totalGanaPase
            // Si totalGanaPase es negativo: UD DEJA = ANTERI + totalGanaPase (suma algebraica)
            $udDeja = $prevClientDeja + $totalGanaPase;
        }
        
        // Guardar en cache
        $this->udDejaCache[$udDejaCacheKey] = $udDeja;
        
        return $udDeja;
    }
    
    /**
     * Obtiene el arrastre para una fecha específica
     * Calcula directamente el arrastre sin llamar a computeClientLiquidationData para evitar recursión
     */
    public function getArrastreForDate(string $date, int $userId, int $depth = 0): float
    {
        $selectedDate = Carbon::parse($date);
        
        // Si es domingo, el arrastre es 0 (no se juega)
        if ($selectedDate->isSunday()) {
            return 0;
        }
        
        // Limitar la recursión a máximo 30 días para evitar consultas excesivas
        if ($depth > 30) {
            return 0;
        }
        
        // Verificar cache primero
        $arrastreCacheKey = $userId . '_' . $date . '_arrastre';
        if (isset($this->arrastreCache[$arrastreCacheKey]) && $this->arrastreCache[$arrastreCacheKey] !== null) {
            return $this->arrastreCache[$arrastreCacheKey];
        }
        
        // Si está marcado como null, significa que está siendo calculado, retornar 0 para evitar recursión
        if (isset($this->arrastreCache[$arrastreCacheKey]) && $this->arrastreCache[$arrastreCacheKey] === null) {
            return 0;
        }
        
        // Marcar que estamos calculando para evitar recursión infinita
        $this->arrastreCache[$arrastreCacheKey] = null;
        
        // Calcular arrastre directamente sin llamar a computeClientLiquidationData
        $user = \App\Models\User::find($userId);
        if (!$user) {
            $this->arrastreCache[$arrastreCacheKey] = 0;
            return 0;
        }
        
        $dateStr = $selectedDate->format('Y-m-d');
        
        // Calcular totalAciert
        $totalAciert = (float) Result::query()->whereDate('date', $dateStr)->where('user_id', $userId)->sum('aciert');
        
        // Calcular totalApus (excluyendo jugadas anuladas)
        $apusQuery = \App\Models\ApusModel::query()
            ->whereDate('created_at', $dateStr)
            ->where('user_id', $userId)
            ->whereHas('playsSent', function($query) {
                $query->where('status', '!=', 'I');
            });
        $totalApus = (float) $apusQuery->sum('import');
        
        // Obtener comisión del cliente
        $client = \App\Models\Client::where('correo', $user->email)->first();
        $commissionPercentage = $client ? $client->commission_percentage : 20.00;
        $comision = $totalApus * ($commissionPercentage / 100);
        $totalGanaPase = $totalApus - $comision - $totalAciert;
        
        // Obtener el anterior del día
        $prevClientDeja = 0;
        if ($selectedDate->isMonday()) {
            $prevDate = $selectedDate->copy()->subDays(2);
            $prevClientDeja = $this->getAnteriorForDate($prevDate->format('Y-m-d'), $userId, $depth + 1);
        } else {
            $prevDate = $selectedDate->copy()->subDay();
            if ($prevDate->isSunday()) {
                $prevDate = $prevDate->copy()->subDay();
            }
            $prevClientDeja = $this->getAnteriorForDate($prevDate->format('Y-m-d'), $userId, $depth + 1);
        }
        
        // Calcular arrastre según el día
        if ($totalApus == 0) {
            // Si no hay apuestas, mantener el arrastre del día anterior
            if ($selectedDate->isMonday()) {
                $arrastre = 0;
            } else {
                $previousDate = $selectedDate->copy()->subDay();
                if ($previousDate->isSunday()) {
                    $previousDate = $previousDate->copy()->subDay();
                }
                $arrastre = $this->getArrastreForDate($previousDate->format('Y-m-d'), $userId, $depth + 1);
            }
        } elseif ($selectedDate->isSaturday()) {
            // Calcular arrastre del viernes
            $fridayDate = $selectedDate->copy()->subDay();
            $prevArrastre = $this->getArrastreForDate($fridayDate->format('Y-m-d'), $userId, $depth + 1);
            
            // Calcular arrastre del sábado
            $udDejaTemp = $totalGanaPase + $prevClientDeja;
            $arrastre = $prevArrastre + $udDejaTemp;
        } elseif ($selectedDate->isMonday()) {
            // Lunes: Arrastre = UD Deja
            $udDeja = $totalGanaPase + $prevClientDeja;
            $arrastre = $udDeja;
        } else {
            // Martes a Viernes: Arrastre = Arrastre del día anterior + UD Deja del día actual
            $previousDate = $selectedDate->copy()->subDay();
            $prevArrastre = $this->getArrastreForDate($previousDate->format('Y-m-d'), $userId, $depth + 1);
            $udDeja = $totalGanaPase + $prevClientDeja;
            $arrastre = $prevArrastre + $udDeja;
        }
        
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
                // ✅ NUEVA LÓGICA: Calcular comisión semanal basada en (ANTERI + TOTAL DEJA) × porcentaje
                // Base para comisión = ANTERI + TOTAL DEJA (suma algebraica)
                $baseComision = $prevPrevDeja + $prevTotalGanaPase;
                
                // Calcular comisión semanal: Base × porcentaje
                if ($prevWeeklyCommissionPercentage > 0) {
                    $comiDejaSem = $baseComision * ($prevWeeklyCommissionPercentage / 100);
                } else {
                    $comiDejaSem = $baseComision * 0.30;
                }
                
                // ✅ NUEVA LÓGICA: UD DEJA/COBRA del sábado según si TOTAL DEJA es positivo o negativo
                // Si TOTAL DEJA es positivo: UD DEJA = (ANTERI + TOTAL DEJA) - COMI DEJA SEM
                // Si TOTAL DEJA es negativo: UD DEJA/COBRA = (ANTERI - TOTAL DEJA) - COMI DEJA SEM
                if ($prevTotalGanaPase >= 0) {
                    $prevUdDeja = ($prevPrevDeja + $prevTotalGanaPase) - $comiDejaSem;
                } else {
                    $prevUdDeja = ($prevPrevDeja - $prevTotalGanaPase) - $comiDejaSem;
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
    public function getPaymentsForCurrentDate(int $userId, string $date): array
    {
        try {
            // Obtener el cliente asociado al usuario
            $user = \App\Models\User::find($userId);
            if (!$user) {
                return ['udDio' => 0.0, 'udRecibe' => 0.0, 'paymentDateDio' => null, 'paymentDateRecibe' => null];
            }
            
            $client = \App\Models\Client::where('correo', $user->email)->first();
            if (!$client) {
                return ['udDio' => 0.0, 'udRecibe' => 0.0, 'paymentDateDio' => null, 'paymentDateRecibe' => null];
            }
            
            $payments = ClientPayment::where('client_id', $client->id)
                ->whereDate('payment_date', $date)
                ->orderBy('created_at', 'desc')
                ->get();
            
            $udDio = 0.0;
            $udRecibe = 0.0;
            $paymentDateDio = null;
            $paymentDateRecibe = null;
            
            foreach ($payments as $payment) {
                if ($payment->type === 'paid_to_client') {
                    // Si el cliente debe pagar (paid_to_client), se suma a UD.DIO
                    $udDio += (float) $payment->amount;
                    // Guardar la fecha del último pago UD.DIO
                    if (!$paymentDateDio) {
                        $paymentDateDio = $payment->created_at->format('d/m/Y');
                    }
                } else {
                    // Si el cliente debe cobrar (received_from_client), se suma a UD.RECIBE
                    $udRecibe += (float) $payment->amount;
                    // Guardar la fecha del último pago UD.RECIBE
                    if (!$paymentDateRecibe) {
                        $paymentDateRecibe = $payment->created_at->format('d/m/Y');
                    }
                }
            }
            
            return [
                'udDio' => $udDio,
                'udRecibe' => $udRecibe,
                'paymentDateDio' => $paymentDateDio,
                'paymentDateRecibe' => $paymentDateRecibe,
            ];
        } catch (\Exception $e) {
            \Log::warning('Error al obtener pagos para fecha actual en Liquidations: ' . $e->getMessage());
            return [
                'udDio' => 0.0,
                'udRecibe' => 0.0,
                'paymentDateDio' => null,
                'paymentDateRecibe' => null,
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
    public function sortResultsByTurn($results)
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
        // Limpiar el cache cuando se cambia la fecha para asegurar cálculos correctos
        $this->anteriorCache = [];
        $this->arrastreCache = [];
        $this->arrastreCacheGlobal = [];
        $this->udDejaCache = [];
        
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
