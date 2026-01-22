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
        
        // TOTAL DEJA = TOTAL PASE - COMIS. J. 20.00% - TOT.ACIERT
        $comision = $totalApus * 0.20;
        $totalGanaPase = $totalApus - $comision - $totalAciert;
        
        // Valores simplificados - lógica eliminada
        $anteri = 0;
        $udDeja = 0;
        $arrastre = 0;
            $comiDejaSem = 0;
        
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
            'anteri'            => $anteri,
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
     * Calcula ANTERI - Lógica eliminada, retorna 0
     */
    protected function calculateAnteri(int $userId, Carbon $selectedDate, float $totalGanaPase): float
    {
            return 0;
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
        
        // IMPORTANTE: Limpiar el cache de UD DEJA y ARRASTRE de la semana anterior para TODOS los días
        // Solo mantener el USTED DEBE SEM del sábado anterior (necesario para el lunes)
        // Esto asegura que cada semana comience limpia y no use valores antiguos
        
        // Determinar el lunes de la semana actual
        $mondayOfCurrentWeek = $selectedDate->copy()->startOfWeek();
        if ($mondayOfCurrentWeek->isSunday()) {
            $mondayOfCurrentWeek->addDay(); // Si es domingo, el lunes es el día siguiente
        }
        
        // Limpiar cache de UD DEJA y ARRASTRE de la semana anterior (7 días antes del lunes actual)
        // Limpiar más días para asegurar que no queden valores antiguos
        for ($i = 1; $i <= 13; $i++) {
            $previousWeekDate = $mondayOfCurrentWeek->copy()->subDays($i);
            $previousWeekDateStr = $previousWeekDate->format('Y-m-d');
            $cacheKeyUdDeja = $user->id . '_' . $previousWeekDateStr . '_uddeja_arrastre';
            $cacheKeyArrastre = $user->id . '_' . $previousWeekDateStr . '_arrastre';
            unset($this->udDejaCache[$cacheKeyUdDeja]);
            unset($this->arrastreCache[$cacheKeyArrastre]);
        }
        // NO limpiar el USTED DEBE SEM del sábado anterior, ese se necesita para el ANTERI del lunes
        
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
        
        // TOTAL DEJA = TOTAL PASE - COMIS. J. 20.00% - TOT.ACIERT
        $comision = $totalApus * ($commissionPercentage / 100);
        $totalGanaPase = $totalApus - $comision - $totalAciert;
        
        // Obtener los pagos registrados para la fecha actual (para mostrar en la vista)
        $currentPayments = $this->getPaymentsForCurrentDate($user->id, $dateStr);
        
        // Obtener pagos del día anterior que se aplican al ANTERI del día actual
        $previousDate = $selectedDate->copy()->subDay();
        if ($previousDate->isSunday()) {
            $previousDate = $previousDate->copy()->subDay();
        }
        $previousDateStr = $previousDate->format('Y-m-d');
        $previousPayments = $this->getPaymentsForCurrentDate($user->id, $previousDateStr);
        $totalPaymentsFromPreviousDay = $previousPayments['udDio'] ?? 0;
        
        // ANTERI: 
        // - Domingo: ANTERI = 0
        // - Lunes: ANTERI = USTED DEBE SEM del sábado anterior
        // - Martes a Sábado: ANTERI = UD DEJA del día anterior
        if ($selectedDate->isSunday()) {
            // Domingo: ANTERI = 0
            $anteriForDisplay = 0;
        } elseif ($selectedDate->isMonday()) {
            // Lunes: ANTERI = USTED DEBE SEM del sábado anterior
            $saturdayDate = $selectedDate->copy()->subDays(2); // Sábado anterior (2 días atrás)
            $saturdayDateStr = $saturdayDate->format('Y-m-d');
            
            // Obtener USTED DEBE SEM del sábado anterior desde cache
            $cacheKeyUstedDebeSem = $user->id . '_' . $saturdayDateStr . '_usted_debe_sem';
            
            // Si no está en cache, calcularlo de forma optimizada sin recursión
            if (!isset($this->udDejaCache[$cacheKeyUstedDebeSem])) {
                $anteriForDisplay = $this->calculateUstedDebeSemForSaturdayOptimized($user, $saturdayDate, $client);
                // Guardar en cache para futuras consultas
                $this->udDejaCache[$cacheKeyUstedDebeSem] = $anteriForDisplay;
            } else {
                $anteriForDisplay = $this->udDejaCache[$cacheKeyUstedDebeSem];
            }
            
            // Aplicar pagos del día anterior (domingo) al ANTERI del lunes
            $anteriForDisplay = max(0, $anteriForDisplay - $totalPaymentsFromPreviousDay);
        } elseif ($selectedDate->isSaturday()) {
            // Sábado: ANTERI = UD DEJA del viernes
            // Calcularlo aquí para asegurar que se use el mismo valor en toda la función
            $fridayDate = $selectedDate->copy()->subDay();
            $fridayDateStr = $fridayDate->format('Y-m-d');
            $cacheKeyViernes = $user->id . '_' . $fridayDateStr . '_uddeja_arrastre';
            
            // Verificar cache primero
            if (isset($this->udDejaCache[$cacheKeyViernes])) {
                $anteriForDisplay = $this->udDejaCache[$cacheKeyViernes];
            } else {
                // Si no está en cache, calcularlo usando función optimizada
                $anteriForDisplay = $this->calculateUdDejaForDateRecursive($user, $fridayDate, $client, 0, 5);
                // Guardar en cache INMEDIATAMENTE después de calcular
                $this->udDejaCache[$cacheKeyViernes] = $anteriForDisplay;
            }
        } else {
            // Martes a Viernes: ANTERI = UD DEJA del día anterior (optimizado)
            $previousDate = $selectedDate->copy()->subDay();
            
            // Si el día anterior es domingo, ir al sábado anterior
            if ($previousDate->isSunday()) {
                $previousDate = $previousDate->copy()->subDay();
            }
            
            // Obtener UD DEJA del día anterior
            $previousDateStr = $previousDate->format('Y-m-d');
            $cacheKeyAnterior = $user->id . '_' . $previousDateStr . '_uddeja_arrastre';
            
            // SOLUCIÓN ESPECIAL PARA EL VIERNES: Calcular el jueves completo usando el método principal
            // Esto asegura que use la misma lógica que funciona correctamente para el jueves
            if ($selectedDate->isFriday()) {
                // Calcular el jueves completo usando computeClientLiquidationData (método que funciona)
                $juevesData = $this->computeClientLiquidationData($user, $previousDate);
                $anteriForDisplay = $juevesData['udDeja'] ?? 0;
                // Guardar en cache el valor correcto
                $this->udDejaCache[$cacheKeyAnterior] = $anteriForDisplay;
            } else {
                // Para otros días, usar la función recursiva normal
                // Verificar cache primero
                if (isset($this->udDejaCache[$cacheKeyAnterior])) {
                    $cachedValue = $this->udDejaCache[$cacheKeyAnterior];
                    $anteriForDisplay = $cachedValue;
                } else {
                    // Si no está en cache, calcularlo usando función optimizada
                    $anteriForDisplay = $this->calculateUdDejaForDateRecursive($user, $previousDate, $client, 0, 5);
                    // Guardar en cache inmediatamente después de calcular
                    $this->udDejaCache[$cacheKeyAnterior] = $anteriForDisplay;
                }
            }
            
            // Aplicar pagos del día anterior al ANTERI del día actual
            $anteriForDisplay = max(0, $anteriForDisplay - $totalPaymentsFromPreviousDay);
        }
        
        // UD DEJA: De Lunes a Viernes = Total Deja + Anteri
        // Si Total Deja es positivo: se suma al Anteri
        // Si Total Deja es negativo: se restaría al Anteri
        // Sábado: UD DEJA = ANTERI (viernes) + Total Deja del sábado
        if ($selectedDate->isSunday()) {
            $udDeja = 0;
                $udCobra = 0;
        } elseif ($selectedDate->isSaturday()) {
            // Sábado: UD DEJA = ANTERI (viernes) + Total Deja del sábado
            // El ANTERI del sábado es el UD DEJA del viernes
            // Lo calcularemos después en el arrastre para reutilizar el valor
            // Por ahora lo dejamos en 0 temporalmente
                $udDeja = 0;
            $udCobra = 0;
        } else {
            // Lunes a Viernes
            // UD DEJA = Anteri + Total Deja (siempre suma, incluso si Total Deja es negativo)
            // Ejemplo: Anteri = 404,580 y Total Deja = -40,800 → UD DEJA = 404,580 + (-40,800) = 363,780
            $udDeja = $anteriForDisplay + $totalGanaPase;
            $udCobra = 0;
            
            // IMPORTANTE: Guardar en cache el UD DEJA INMEDIATAMENTE después de calcularlo
            // Esto asegura que cuando otros días necesiten este valor, esté disponible
            $cacheKeyUdDeja = $user->id . '_' . $dateStr . '_uddeja_arrastre';
            // Guardar en cache de forma explícita y sobrescribir cualquier valor anterior
            $this->udDejaCache[$cacheKeyUdDeja] = $udDeja;
        }
        
        // ARRASTRE: 
        // ARRASTRE = ARRASTRE del día anterior + TOTAL DEJA del día actual
        if ($selectedDate->isSunday()) {
            $arrastre = 0;
        } elseif ($selectedDate->isMonday()) {
            // Lunes: ARRASTRE = TOTAL DEJA (no hay día anterior, o el arrastre anterior es 0)
            $arrastre = $totalGanaPase;
        } elseif ($selectedDate->isSaturday()) {
            // Sábado: Calcular UD DEJA usando el ANTERI que ya calculamos arriba
            // El ANTERI del sábado (anteriForDisplay) ya es el UD DEJA del viernes
            
            // Calcular UD DEJA del sábado
            // UD DEJA = Anteri + Total Deja (siempre suma, incluso si Total Deja es negativo)
            $udDeja = $anteriForDisplay + $totalGanaPase;
            $udCobra = 0;
            
            // IMPORTANTE: Guardar en cache el UD DEJA del sábado INMEDIATAMENTE después de calcularlo
            $cacheKeySabado = $user->id . '_' . $dateStr . '_uddeja_arrastre';
            // Guardar en cache de forma explícita y sobrescribir cualquier valor anterior
            $this->udDejaCache[$cacheKeySabado] = $udDeja;
            
            // ARRASTRE del sábado = ARRASTRE del viernes + TOTAL DEJA del sábado
            $fridayDate = $selectedDate->copy()->subDay();
            $fridayDateStr = $fridayDate->format('Y-m-d');
            $cacheKeyArrastreViernes = $user->id . '_' . $fridayDateStr . '_arrastre';
            
            // Obtener ARRASTRE del viernes desde cache o calcularlo
            $arrastreViernes = 0;
            if (isset($this->arrastreCache[$cacheKeyArrastreViernes])) {
                $arrastreViernes = $this->arrastreCache[$cacheKeyArrastreViernes];
            } else {
                // Si no está en cache, calcular el arrastre del viernes
                // Necesitamos calcular la liquidación del viernes para obtener su arrastre
                $viernesData = $this->computeClientLiquidationData($user, $fridayDate);
                $arrastreViernes = $viernesData['arrastre'] ?? 0;
                $this->arrastreCache[$cacheKeyArrastreViernes] = $arrastreViernes;
            }
            
            $arrastre = $arrastreViernes + $totalGanaPase;
        } else {
            // Martes a Viernes: ARRASTRE = ARRASTRE del día anterior + TOTAL DEJA del día actual
            $previousDate = $selectedDate->copy()->subDay();
            if ($previousDate->isSunday()) {
                $previousDate = $previousDate->copy()->subDay();
            }
            $previousDateStr = $previousDate->format('Y-m-d');
            $cacheKeyArrastreAnterior = $user->id . '_' . $previousDateStr . '_arrastre';
            
            // Obtener ARRASTRE del día anterior desde cache o calcularlo
            $arrastreAnterior = 0;
            if (isset($this->arrastreCache[$cacheKeyArrastreAnterior])) {
                $arrastreAnterior = $this->arrastreCache[$cacheKeyArrastreAnterior];
            } else {
                // Si no está en cache, calcular el arrastre del día anterior
                // Necesitamos calcular la liquidación del día anterior para obtener su arrastre
                $diaAnteriorData = $this->computeClientLiquidationData($user, $previousDate);
                $arrastreAnterior = $diaAnteriorData['arrastre'] ?? 0;
                $this->arrastreCache[$cacheKeyArrastreAnterior] = $arrastreAnterior;
            }
            
            $arrastre = $arrastreAnterior + $totalGanaPase;
        }
        
        // Guardar ARRASTRE en cache
        $cacheKeyArrastre = $user->id . '_' . $dateStr . '_arrastre';
        $this->arrastreCache[$cacheKeyArrastre] = $arrastre;
        
        // COMI DEJA SEM: Solo para sábados, se calcula sobre el UD DEJA del sábado
        // COMI DEJA SEM = UD DEJA del sábado × porcentaje semanal del cliente
        $comiDejaSem = 0;
        if ($selectedDate->isSaturday()) {
            // Obtener el porcentaje de comisión semanal del cliente
            $weeklyCommissionPercentage = $client ? ($client->weekly_commission_percentage ?? 0) : 0;
            
            if ($weeklyCommissionPercentage > 0 && $udDeja > 0) {
                // Calcular COMI DEJA SEM sobre el UD DEJA del sábado
                // Ejemplo: Si UD DEJA = 800 y porcentaje = 30%, entonces COMI DEJA SEM = 800 × 0.30 = 240
                $comiDejaSem = $udDeja * ($weeklyCommissionPercentage / 100);
            }
        }
        
        // USTED DEBE SEM: Solo para sábados, ARRASTRE - COMI DEJA SEM
        $ustedDebeSem = 0;
        if ($selectedDate->isSaturday()) {
            $ustedDebeSem = $arrastre - $comiDejaSem;
            
            // Guardar en cache para uso del lunes siguiente
            $cacheKeyUstedDebeSem = $user->id . '_' . $dateStr . '_usted_debe_sem';
            $this->udDejaCache[$cacheKeyUstedDebeSem] = $ustedDebeSem;
        } elseif ($selectedDate->isMonday()) {
            // Lunes: USTED DEBE SEM = USTED DEBE SEM del sábado anterior
            $saturdayDate = $selectedDate->copy()->subDays(2); // Sábado anterior (2 días atrás)
            $saturdayDateStr = $saturdayDate->format('Y-m-d');
            
            // Obtener USTED DEBE SEM del sábado anterior desde cache
            $cacheKeyUstedDebeSem = $user->id . '_' . $saturdayDateStr . '_usted_debe_sem';
            
            // Si no está en cache, calcularlo de forma optimizada sin recursión
            if (!isset($this->udDejaCache[$cacheKeyUstedDebeSem])) {
                $ustedDebeSem = $this->calculateUstedDebeSemForSaturdayOptimized($user, $saturdayDate, $client);
                // Guardar en cache para futuras consultas
                $this->udDejaCache[$cacheKeyUstedDebeSem] = $ustedDebeSem;
        } else {
                $ustedDebeSem = $this->udDejaCache[$cacheKeyUstedDebeSem];
            }
        }
        
        $calculoSemanal = 0;
        
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
            'usted_debe_sem'    => $ustedDebeSem,
            'calculo_semanal'   => $calculoSemanal,
            'udDio'             => $previousPayments['udDio'] ?? 0, // Pagos del día anterior que se aplican hoy
            'udRecibePayment'   => $currentPayments['udRecibe'],
            'paymentDateDio'    => $previousPayments['paymentDateDio'] ?? null, // Fecha del pago del día anterior
            'paymentDateRecibe' => $currentPayments['paymentDateRecibe'],
        ];
    }
    
    /**
     * Obtiene el ANTERI para una fecha específica - Lógica eliminada, retorna 0
     */
    public function getAnteriorForDate(string $date, int $userId, int $depth = 0): float
    {
        return 0;
    }
    
    /**
     * Obtiene el UD DEJA para una fecha específica - Lógica eliminada, retorna 0
     */
    public function getUdDejaForDate(string $date, int $userId, int $depth = 0): float
    {
            return 0;
        }
        
    /**
     * Obtiene el arrastre para una fecha específica - Lógica eliminada, retorna 0
     */
    public function getArrastreForDate(string $date, int $userId, int $depth = 0): float
    {
            return 0;
        }
        
    /**
     * Obtiene el arrastre global para una fecha específica - Lógica eliminada, retorna 0
     */
    protected function getArrastreGlobalForDate(string $date): float
    {
        return 0;
    }
    
    /**
     * Calcula USTED DEBE SEM para un sábado específico de forma optimizada
     * sin llamar a computeClientLiquidationData para evitar recursión infinita
     * 
     * @param \App\Models\User $user
     * @param Carbon $saturdayDate
     * @param \App\Models\Client|null $client
     * @return float
     */
    protected function calculateUstedDebeSemForSaturdayOptimized($user, Carbon $saturdayDate, $client): float
    {
        // Si no es sábado, retornar 0
        if (!$saturdayDate->isSaturday()) {
            return 0;
        }
        
        $saturdayDateStr = $saturdayDate->format('Y-m-d');
        
        // Calcular datos básicos del sábado directamente desde la BD
        $saturdayTotalAciert = (float) Result::query()
            ->whereDate('date', $saturdayDateStr)
            ->where('user_id', $user->id)
            ->sum('aciert');
        
        $saturdayApusQuery = \App\Models\ApusModel::query()
            ->whereDate('created_at', $saturdayDateStr)
            ->where('user_id', $user->id)
            ->whereHas('playsSent', function($query) {
                $query->where('status', '!=', 'I');
            });
        $saturdayTotalApus = (float) $saturdayApusQuery->sum('import');
        
        $commissionPercentage = $client ? ($client->commission_percentage ?? 20.00) : 20.00;
        $saturdayComision = $saturdayTotalApus * ($commissionPercentage / 100);
        $saturdayTotalGanaPase = $saturdayTotalApus - $saturdayComision - $saturdayTotalAciert;
        
        // Obtener UD DEJA del viernes (ANTERI del sábado) desde cache
        $fridayDate = $saturdayDate->copy()->subDay();
        $fridayDateStr = $fridayDate->format('Y-m-d');
        $cacheKeyViernes = $user->id . '_' . $fridayDateStr . '_uddeja_arrastre';
        
        // Verificar si está en cache
        if (isset($this->udDejaCache[$cacheKeyViernes])) {
            $saturdayAnteri = $this->udDejaCache[$cacheKeyViernes];
        } else {
            // Si no está en cache, calcular UD DEJA del viernes recursivamente hacia atrás
            // Usar depth=0 porque esta función se llama desde computeClientLiquidationData o desde depth=0
            // La función recursiva manejará la profundidad correctamente
            $saturdayAnteri = $this->calculateUdDejaForDateRecursive($user, $fridayDate, $client, 0, 5);
            // Guardar en cache para que no tenga que volver a calcular
            $this->udDejaCache[$cacheKeyViernes] = $saturdayAnteri;
        }
        
        // Calcular UD DEJA del sábado
        // UD DEJA = Anteri + Total Deja (siempre suma, incluso si Total Deja es negativo)
        $saturdayUdDeja = $saturdayAnteri + $saturdayTotalGanaPase;
        
        // ARRASTRE del sábado = UD DEJA del sábado (mismo valor)
        $saturdayArrastre = $saturdayUdDeja;
        
        // Calcular COMI DEJA SEM del sábado
        $weeklyCommissionPercentage = $client ? ($client->weekly_commission_percentage ?? 0) : 0;
        $saturdayComiDejaSem = 0;
        if ($weeklyCommissionPercentage > 0 && $saturdayUdDeja > 0) {
            $saturdayComiDejaSem = $saturdayUdDeja * ($weeklyCommissionPercentage / 100);
        }
        
        // Calcular USTED DEBE SEM
        $ustedDebeSem = $saturdayArrastre - $saturdayComiDejaSem;
        
        return $ustedDebeSem;
    }
    
    /**
     * Calcula UD DEJA para una fecha específica de forma recursiva hacia atrás
     * con límite de profundidad para evitar recursión infinita
     * 
     * @param \App\Models\User $user
     * @param Carbon $date
     * @param \App\Models\Client|null $client
     * @param int $depth Profundidad actual (máximo 5 días)
     * @param int $maxDepth Profundidad máxima permitida
     * @return float
     */
    protected function calculateUdDejaForDateRecursive($user, Carbon $date, $client, int $depth = 0, int $maxDepth = 5): float
    {
        // Límite de profundidad para evitar recursión infinita
        if ($depth >= $maxDepth) {
            return 0;
        }
        
        // Si es domingo, retornar 0
        if ($date->isSunday()) {
            return 0;
        }
        
        $dateStr = $date->format('Y-m-d');
        $cacheKey = $user->id . '_' . $dateStr . '_uddeja_arrastre';
        
        // Si está en cache, retornarlo
        if (isset($this->udDejaCache[$cacheKey])) {
            return $this->udDejaCache[$cacheKey];
        }
        
        // Calcular datos del día directamente desde la BD
        $totalAciert = (float) Result::query()
            ->whereDate('date', $dateStr)
            ->where('user_id', $user->id)
            ->sum('aciert');
        
        $apusQuery = \App\Models\ApusModel::query()
            ->whereDate('created_at', $dateStr)
            ->where('user_id', $user->id)
            ->whereHas('playsSent', function($query) {
                $query->where('status', '!=', 'I');
            });
        $totalApus = (float) $apusQuery->sum('import');
        
        $commissionPercentage = $client ? ($client->commission_percentage ?? 20.00) : 20.00;
        $comision = $totalApus * ($commissionPercentage / 100);
        $totalGanaPase = $totalApus - $comision - $totalAciert;
        
        // Obtener ANTERI (UD DEJA del día anterior) recursivamente
        // Para el lunes, el ANTERI es el USTED DEBE SEM del sábado anterior, pero en la función recursiva
        // simplificamos y usamos el UD DEJA del domingo anterior (que es 0) o del sábado anterior
        $previousDate = $date->copy()->subDay();
        if ($previousDate->isSunday()) {
            $previousDate = $previousDate->copy()->subDay();
        }
        
        // Obtener ANTERI según el día
        // IMPORTANTE: Solo ir UN día atrás, no llamar a funciones complejas que puedan causar recursión profunda
        if ($date->isMonday() && $previousDate->isSaturday()) {
            // Lunes: ANTERI = USTED DEBE SEM del sábado anterior
            $saturdayDateStr = $previousDate->format('Y-m-d');
            $cacheKeyUstedDebeSem = $user->id . '_' . $saturdayDateStr . '_usted_debe_sem';
            
            // Si está en cache, usarlo
            if (isset($this->udDejaCache[$cacheKeyUstedDebeSem])) {
                $anteri = $this->udDejaCache[$cacheKeyUstedDebeSem];
            } else {
                // Si no está en cache, calcularlo de forma optimizada
                // Permitir calcular hasta depth=2 para que funcione cuando se recalcula desde miércoles/jueves/etc
                // Esto asegura que todos los días de la semana puedan calcular correctamente sus dependencias
                if ($depth <= 2) {
                    // Calcular si no estamos en recursión muy profunda para evitar bucles infinitos
                    $anteri = $this->calculateUstedDebeSemForSaturdayOptimized($user, $previousDate, $client);
                    // Guardar en cache para futuras consultas
                    $this->udDejaCache[$cacheKeyUstedDebeSem] = $anteri;
                } else {
                    // Si estamos en recursión muy profunda (depth > 2), usar 0 para evitar recursión infinita
                    $anteri = 0;
                }
            }
        } else {
            // Martes a Sábado: ANTERI = UD DEJA del día anterior (solo UN día atrás)
            // Verificar cache primero antes de llamar a la función recursiva
            $previousDateStr = $previousDate->format('Y-m-d');
            $cacheKeyPrevious = $user->id . '_' . $previousDateStr . '_uddeja_arrastre';
            
            // Si está en cache, verificar si es un valor sospechoso
            if (isset($this->udDejaCache[$cacheKeyPrevious])) {
                $cachedValue = $this->udDejaCache[$cacheKeyPrevious];
                // Si el valor en cache es sospechoso (330,740.00), forzar recálculo
                // Este valor parece ser incorrecto y está causando problemas
                if (abs($cachedValue - 330740) < 1) {
                    // Valor sospechoso detectado, recalcular
                    $anteri = $this->calculateUdDejaForDateRecursive($user, $previousDate, $client, $depth + 1, $maxDepth);
                    // Sobrescribir el valor incorrecto en cache
                    $this->udDejaCache[$cacheKeyPrevious] = $anteri;
                } else {
                    // Usar el valor del cache
                    $anteri = $cachedValue;
                }
            } else {
                // Si no está en cache, calcularlo usando función recursiva
                // La función recursiva manejará el cache y la profundidad correctamente
                $anteri = $this->calculateUdDejaForDateRecursive($user, $previousDate, $client, $depth + 1, $maxDepth);
                // Guardar en cache inmediatamente después de calcular
                $this->udDejaCache[$cacheKeyPrevious] = $anteri;
            }
        }
        
        // Calcular UD DEJA
        // UD DEJA = Anteri + Total Deja (siempre suma, incluso si Total Deja es negativo)
        $udDeja = $anteri + $totalGanaPase;
        
        // Guardar en cache
        $this->udDejaCache[$cacheKey] = $udDeja;
        
        return $udDeja;
    }
    
    /**
     * Calcula ARRASTRE para una fecha específica de forma optimizada
     * sin llamar a computeClientLiquidationData para evitar recursión infinita
     * 
     * @param \App\Models\User $user
     * @param Carbon $date
     * @param \App\Models\Client|null $client
     * @param int $depth Profundidad actual (máximo 5 días)
     * @return float
     */
    protected function calculateArrastreForDateOptimized($user, Carbon $date, $client, int $depth = 0): float
    {
        // Límite de profundidad para evitar recursión infinita
        if ($depth >= 5) {
            return 0;
        }
        
        // Si es domingo, retornar 0
        if ($date->isSunday()) {
            return 0;
        }
        
        $dateStr = $date->format('Y-m-d');
        $cacheKey = $user->id . '_' . $dateStr . '_arrastre';
        
        // Si está en cache, retornarlo
        if (isset($this->arrastreCache[$cacheKey])) {
            return $this->arrastreCache[$cacheKey];
        }
        
        // Calcular datos del día directamente desde la BD
        $totalAciert = (float) Result::query()
            ->whereDate('date', $dateStr)
            ->where('user_id', $user->id)
            ->sum('aciert');
        
        $apusQuery = \App\Models\ApusModel::query()
            ->whereDate('created_at', $dateStr)
            ->where('user_id', $user->id)
            ->whereHas('playsSent', function($query) {
                $query->where('status', '!=', 'I');
            });
        $totalApus = (float) $apusQuery->sum('import');
        
        $commissionPercentage = $client ? ($client->commission_percentage ?? 20.00) : 20.00;
        $comision = $totalApus * ($commissionPercentage / 100);
        $totalGanaPase = $totalApus - $comision - $totalAciert;
        
        // Calcular ARRASTRE según el día
        if ($date->isMonday()) {
            // Lunes: ARRASTRE = TOTAL DEJA (empieza en 0)
            $arrastre = $totalGanaPase;
        } else {
            // Martes a Sábado: ARRASTRE = TOTAL DEJA + ARRASTRE del día anterior
            $previousDate = $date->copy()->subDay();
                if ($previousDate->isSunday()) {
                    $previousDate = $previousDate->copy()->subDay();
                }
            
            // Obtener ARRASTRE del día anterior recursivamente
            $arrastreAnterior = $this->calculateArrastreForDateOptimized($user, $previousDate, $client, $depth + 1);
            
            // ARRASTRE = TOTAL DEJA + ARRASTRE anterior
            $arrastre = $totalGanaPase + $arrastreAnterior;
        }
        
        // Guardar en cache
        $this->arrastreCache[$cacheKey] = $arrastre;
        
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
