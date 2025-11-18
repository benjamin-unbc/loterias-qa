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
        $prevLiquidation = DailyLiquidation::where('date', '<', $this->date)
                                           ->orderBy('date', 'desc')
                                           ->first();
        $prevGenerDeja = $prevLiquidation ? (float) $prevLiquidation->ud_deja : 0;
        
        // Calcular arrastre global según día de la semana
        if ($selectedDate->isSaturday()) {
            $comiDejaSem = ($totalGanaPase + $prevGenerDeja) * 0.30;
            $udDeja = ($totalGanaPase + $prevGenerDeja) - $comiDejaSem;
            $arrastre = 0;
        } else {
            $comiDejaSem = null;
            $udDeja = $totalGanaPase + $prevGenerDeja;
            $arrastre = $udDeja;
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
        // Consulta de resultados filtrada por cliente
        $baseQuery = Result::query()->whereDate('date', $this->date)->where('user_id', $user->id);
        $results = (clone $baseQuery)->get();
        
        // ✅ Ordenar por turno (de más temprano a más tarde)
        $results = $this->sortResultsByTurn($results);
        $totalAciert = (float) (clone $baseQuery)->sum('aciert');
        
        // Consulta de apuestas filtrada por cliente
        // ✅ Excluir jugadas anuladas (status != 'I' en plays_sent)
        $apusQuery = \App\Models\ApusModel::query()
            ->whereDate('created_at', $this->date)
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
        // Si es lunes, obtener el anterior del sábado anterior y aplicar los pagos del sábado
        if ($selectedDate->isMonday()) {
            $saturdayDate = $selectedDate->copy()->subDays(2); // Sábado anterior
            // Calcular la liquidación completa del sábado para obtener su anterior
            $saturdayLiquidation = $this->computeClientLiquidationData($user, $saturdayDate);
            // El anterior del lunes es el anterior del sábado
            $prevClientDeja = $saturdayLiquidation['anteri'] ?? 0; // Tomar el anterior del sábado, no el ud_deja
            
            // Aplicar los pagos registrados del sábado al anterior del lunes
            // Si es UD.DIO (paid_to_client) se resta, si es UD.RECIBE (received_from_client) se suma
            $saturdayPayments = $this->getPaymentsForCurrentDate($user->id, $saturdayDate->format('Y-m-d'));
            // UD.DIO se resta del anterior (cliente pagó, reduce deuda)
            // UD.RECIBE se suma al anterior (admin pagó, aumenta lo que debe el cliente)
            $prevClientDeja = $prevClientDeja - $saturdayPayments['udDio'] + $saturdayPayments['udRecibe'];
        } else {
            // Buscar la última liquidación del cliente (si existe)
            $clientPrevLiquidation = $this->getClientPreviousLiquidation($user->id, $this->date);
            $prevClientDeja = $clientPrevLiquidation ? (float) $clientPrevLiquidation['ud_deja'] : 0;
            
            // Aplicar los pagos registrados del día anterior (solo para días que no son lunes)
            $previousDate = Carbon::parse($this->date)->subDay();
            $paymentsAdjustment = $this->getPaymentsForDate($user->id, $previousDate->format('Y-m-d'));
            $prevClientDeja += $paymentsAdjustment; // Sumar el ajuste (puede ser positivo o negativo)
        }
        
        // Calcular arrastre individual del cliente
        // Obtener el porcentaje semanal del cliente
        $weeklyCommissionPercentage = $client ? ($client->weekly_commission_percentage ?? 30.00) : 30.00;
        
        // Si es lunes y no hay apuestas, todo parte en 0 excepto el anterior
        if ($selectedDate->isMonday() && $totalApus == 0) {
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
        $currentPayments = $this->getPaymentsForCurrentDate($user->id, $this->date);
        
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
