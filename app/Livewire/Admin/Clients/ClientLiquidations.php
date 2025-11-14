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
     */
    public function getLiquidationWeeksProperty()
    {
        if (!$this->client || !$this->client->associatedUser) {
            return collect();
        }
        
        $dates = Result::where('user_id', $this->client->associatedUser->id)
            ->select('date')
            ->distinct()
            ->orderBy('date', 'desc')
            ->get()
            ->pluck('date')
            ->map(function($date) {
                return Carbon::parse($date)->format('Y-m-d');
            })
            ->unique()
            ->values();
        
        if ($dates->isEmpty()) {
            return collect();
        }
        
        // Obtener la primera y última fecha
        $firstDate = Carbon::parse($dates->last());
        $lastDate = Carbon::parse($dates->first());
        
        // Obtener el lunes de la primera semana
        $firstMonday = $firstDate->copy()->startOfWeek();
        // Obtener el lunes de la última semana
        $lastMonday = $lastDate->copy()->startOfWeek();
        
        // Agrupar fechas por semanas (lunes a sábado)
        $weeks = collect();
        $datesCollection = $dates->toArray();
        
        // Iterar desde la primera semana hasta la última
        $currentMonday = $firstMonday->copy();
        
        while ($currentMonday->lte($lastMonday)) {
            $saturday = $currentMonday->copy()->addDays(5);
            
            // Generar todas las fechas de la semana (lunes a sábado)
            $weekDates = [];
            for ($i = 0; $i < 6; $i++) {
                $day = $currentMonday->copy()->addDays($i);
                $dayStr = $day->format('Y-m-d');
                
                // Solo incluir si tiene liquidación
                if (in_array($dayStr, $datesCollection)) {
                    $weekDates[] = $dayStr;
                }
            }
            
            // Solo agregar la semana si tiene al menos un día con liquidación
            if (!empty($weekDates)) {
                // Obtener el último día de la semana con liquidación (sábado o el más reciente)
                $lastDate = end($weekDates);
                $liquidationData = $this->computeLiquidationDataForDate($lastDate, $this->client->associatedUser->id);
                $clienteDeja = $liquidationData['udDeja'] ?? 0;
                
                // Detectar si es la semana actual
                $today = Carbon::today();
                $currentWeekMonday = $today->copy()->startOfWeek();
                $isCurrentWeek = $currentMonday->format('Y-m-d') === $currentWeekMonday->format('Y-m-d');
                
                $weeks->push([
                    'monday' => $currentMonday->format('Y-m-d'),
                    'saturday' => $saturday->format('Y-m-d'),
                    'mondayFormatted' => $currentMonday->format('d-m-Y'),
                    'saturdayFormatted' => $saturday->format('d-m-Y'),
                    'dates' => $weekDates,
                    'label' => "Semana {$currentMonday->format('d-m-Y')} hasta {$saturday->format('d-m-Y')}",
                    'lastDate' => $lastDate,
                    'clienteDeja' => $clienteDeja,
                    'isCurrentWeek' => $isCurrentWeek
                ]);
            }
            
            // Avanzar a la siguiente semana (7 días después)
            $currentMonday->addWeek();
        }
        
        return $weeks->sortByDesc('monday')->values();
    }
    
    /**
     * Calcula los datos de liquidación para una fecha específica
     */
    public function computeLiquidationDataForDate($date, $userId)
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
            ];
        }
        
        $selectedDate = Carbon::parse($date);
        
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
        
        // Obtener la comisión personalizada del cliente
        $commissionPercentage = $this->client->commission_percentage ?? 20.00;
        $comision = $totalApus * ($commissionPercentage / 100);
        $totalGanaPase = $totalApus - $comision - $totalAciert;
        
        // Calcular arrastre
        $clientPrevLiquidation = $this->getClientPreviousLiquidation($userId, $date);
        $prevClientDeja = $clientPrevLiquidation ? (float) $clientPrevLiquidation['ud_deja'] : 0;
        
        // Aplicar los pagos registrados del día anterior
        $previousDate = Carbon::parse($date)->subDay();
        if ($selectedDate->isMonday()) {
            $previousDate = $selectedDate->copy()->subDays(2); // Sábado anterior
        }
        $paymentsAdjustment = $this->getPaymentsForDate($previousDate->format('Y-m-d'));
        $prevClientDeja += $paymentsAdjustment; // Sumar el ajuste (puede ser positivo o negativo)
        
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
        ];
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
        
        if ($prevTotalApus == 0) {
            return null;
        }
        
        $commissionPercentage = $this->client->commission_percentage ?? 20.00;
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
     * Abre el modal de semana para una semana específica
     */
    public function openWeekModal($mondayDate)
    {
        $this->selectedDate = $mondayDate;
        $monday = Carbon::parse($mondayDate);
        
        // Generar fechas de lunes a sábado
        $this->weekDates = [];
        for ($i = 0; $i < 6; $i++) {
            $day = $monday->copy()->addDays($i);
            $this->weekDates[] = [
                'date' => $day->format('Y-m-d'),
                'formatted' => $day->format('d/m/Y'),
                'dayName' => $day->locale('es')->dayName,
                'carbon' => $day
            ];
        }
        
        // Calcular liquidaciones para cada día de la semana
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
     * Obtiene el "Cliente Deja" (UD Deja) del día actual
     */
    public function getCurrentDayUdDejaProperty()
    {
        if (!$this->client || !$this->client->associatedUser) {
            return 0;
        }
        
        $today = Carbon::today()->format('Y-m-d');
        $liquidationData = $this->computeLiquidationDataForDate($today, $this->client->associatedUser->id);
        
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
     * Abre el modal de pago para una fecha específica
     */
    public function openPaymentModal($date)
    {
        $this->paymentDate = $date;
        $liquidationData = $this->computeLiquidationDataForDate($date, $this->client->associatedUser->id ?? null);
        $this->currentUdDeja = $liquidationData['udDeja'] ?? 0;
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
        
        // Verificar si ya existe un pago para esta fecha
        $existingPayment = ClientPayment::where('client_id', $this->client->id)
            ->whereDate('payment_date', $this->paymentDate)
            ->first();
        
        if ($existingPayment) {
            // Cerrar el modal
            $this->closePaymentModal();
            
            // Mostrar mensaje de error
            $this->dispatch('payment-error', message: 'Ya se registró un pago para este día. Debe esperar al siguiente día para registrar otro pago.');
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

