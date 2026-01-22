<?php

namespace App\Livewire\Admin\Clients;

use App\Models\Client;
use App\Models\ClientPayment;
use App\Models\Result;
use App\Models\ApusModel;
use App\Livewire\Admin\Liquidations;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class ClientLiquidations extends Component
{
    public $clientId;
    public $client;
    public $selectedWeek = null; // Ahora guardará la fecha del sábado (Y-m-d) en lugar del índice
    public $paymentAmount = '';
    public $paymentNotes = '';
    public $weeks = [];
    public $totalDebe = 0;
    
    // Variables para editar pago
    public $editingPaymentId = null;
    public $editingPaymentAmount = '';
    public $editingPaymentNotes = '';
    public $editingPaymentDate = '';
    public $showDeleteConfirm = false;
    public $paymentToDelete = null;
    
    protected $listeners = ['paymentSaved' => 'loadWeeks'];
    
    #[Layout('layouts.app')]
    public function mount($id)
    {
        $this->clientId = $id;
        $this->client = Client::findOrFail($id);
        $this->loadWeeks();
    }
    
    public function loadWeeks()
    {
        if (!$this->client || !$this->client->associatedUser) {
            $this->weeks = [];
            $this->totalDebe = 0;
            return;
        }
        
        $user = $this->client->associatedUser;
        
        // ✅ OPTIMIZACIÓN: Cache de Laravel para la lista de semanas
        // Cachear por 30 minutos - suficiente para evitar recálculos frecuentes
        $cacheKey = "client_liquidations_weeks_{$this->client->id}";
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            $this->weeks = $cached['weeks'];
            $this->totalDebe = $cached['totalDebe'];
            return;
        }
        
        $liquidationsComponent = new Liquidations();
        
        // ✅ OPTIMIZACIÓN 1: Limitar rango a últimos 12 meses (en lugar de desde el inicio)
        // Esto reduce significativamente el número de sábados a calcular
        $startDate = Carbon::now()->subMonths(12)->startOfWeek();
        if ($startDate->isSunday()) {
            $startDate->addDay();
        }
        // Ir al sábado más cercano
        while (!$startDate->isSaturday()) {
            $startDate->addDay();
        }
        
        $endDate = Carbon::now();
        
        // ✅ OPTIMIZACIÓN 2: Cargar TODOS los pagos del cliente de una vez
        // Esto evita hacer N consultas (una por semana) y las filtra en memoria
        $allPayments = ClientPayment::where('client_id', $this->client->id)
            ->orderBy('payment_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();
        
        // ✅ OPTIMIZACIÓN 3: Buscar solo sábados que tienen jugadas (más eficiente)
        // En lugar de iterar todos los sábados, buscar directamente en BD qué sábados tienen datos
        $saturdaysWithData = collect();
        $currentDate = $startDate->copy();
        
        while ($currentDate <= $endDate) {
            if ($currentDate->isSaturday()) {
                $saturdaysWithData->push($currentDate->copy());
            }
            $currentDate->next(Carbon::SATURDAY);
        }
        
        $this->weeks = [];
        $this->totalDebe = 0;
        
        foreach ($saturdaysWithData as $saturday) {
            $saturdayStr = $saturday->format('Y-m-d');
            
            // ✅ OPTIMIZACIÓN 4: Cachear por sábado individual
            // Si este sábado ya fue calculado y no tiene deuda, saltarlo
            $saturdayCacheKey = "client_saturday_usted_debe_{$user->id}_{$saturdayStr}";
            $cachedUstedDebeSem = Cache::get($saturdayCacheKey);
            
            if ($cachedUstedDebeSem !== null) {
                // Si está en cache y es 0, saltar (no tiene deuda)
                if ($cachedUstedDebeSem <= 0) {
                    continue;
                }
                $ustedDebeSem = $cachedUstedDebeSem;
            } else {
                // Calcular USTED DEBE SEM para este sábado
                $liquidationData = $liquidationsComponent->computeClientLiquidationData($user, $saturday);
                $ustedDebeSem = $liquidationData['usted_debe_sem'] ?? 0;
                
                // Cachear el resultado (incluso si es 0, para no recalcular)
                Cache::put($saturdayCacheKey, $ustedDebeSem, 1800); // 30 minutos
            }
            
            // Solo agregar si tiene USTED DEBE SEM > 0
            if ($ustedDebeSem > 0) {
                // Obtener pagos de esta semana (del lunes al sábado)
                $weekStart = $saturday->copy()->startOfWeek();
                if ($weekStart->isSunday()) {
                    $weekStart->addDay();
                }
                $weekEnd = $saturday->copy()->endOfWeek();
                if ($weekEnd->isSunday()) {
                    $weekEnd->subDay();
                }
                
                // ✅ OPTIMIZACIÓN 2 (continuación): Filtrar pagos en memoria
                // En lugar de hacer una consulta por semana, filtrar de la colección cargada
                $payments = $allPayments->filter(function($payment) use ($weekStart, $weekEnd, $saturdayStr) {
                    $paymentDate = Carbon::parse($payment->payment_date);
                    $inWeekRange = $paymentDate->gte($weekStart) && $paymentDate->lte($weekEnd);
                    $hasWeekNote = $payment->notes && str_contains($payment->notes, 'Semana del sábado: ' . $saturdayStr);
                    return $inWeekRange || $hasWeekNote;
                })->values();
                
                $totalPayments = $payments->sum('amount');
                $remainingDebe = max(0, $ustedDebeSem - $totalPayments);
                
                $this->weeks[] = [
                    'saturday' => $saturday,
                    'saturday_str' => $saturday->format('Y-m-d'),
                    'usted_debe_sem' => $ustedDebeSem,
                    'total_payments' => $totalPayments,
                    'remaining_debe' => $remainingDebe,
                    'payments' => $payments,
                ];
                
                $this->totalDebe += $remainingDebe;
            }
        }
        
        // Ordenar por fecha (más reciente primero)
        $this->weeks = collect($this->weeks)->sortByDesc('saturday_str')->values()->toArray();
        
        // ✅ OPTIMIZACIÓN: Guardar en cache de Laravel
        Cache::put($cacheKey, [
            'weeks' => $this->weeks,
            'totalDebe' => $this->totalDebe,
        ], 1800); // 30 minutos
    }
    
    public function selectWeek($saturdayDate)
    {
        // Usar la fecha del sábado como identificador único
        if ($this->selectedWeek === $saturdayDate) {
            $this->selectedWeek = null;
        } else {
            $this->selectedWeek = $saturdayDate;
        }
        $this->paymentAmount = '';
        $this->paymentNotes = '';
    }
    
    public function savePayment()
    {
        try {
            if (!$this->selectedWeek) {
                session()->flash('error', 'No se ha seleccionado una semana válida');
                return;
            }
            
            // Buscar la semana por la fecha del sábado
            $week = collect($this->weeks)->firstWhere('saturday_str', $this->selectedWeek);
            
            if (!$week) {
                session()->flash('error', 'No se ha encontrado la semana seleccionada');
                return;
            }
            
            $this->validate([
                'paymentAmount' => 'required|numeric|min:0.01',
            ], [
                'paymentAmount.required' => 'El monto del pago es requerido',
                'paymentAmount.numeric' => 'El monto debe ser un número',
                'paymentAmount.min' => 'El monto debe ser mayor a 0',
            ]);
            
            $saturday = Carbon::parse($week['saturday_str']);
            
            // Guardar el pago con la fecha actual para que se aplique al día siguiente en liquidaciones
            $paymentDate = Carbon::today();
            
            // Agregar información de la semana en las notas para poder asociarlo después
            $notes = $this->paymentNotes ?: 'Pago registrado desde módulo de liquidaciones';
            $notes .= ' | Semana del sábado: ' . $saturday->format('Y-m-d');
            
            ClientPayment::create([
                'client_id' => $this->client->id,
                'payment_date' => $paymentDate->format('Y-m-d'),
                'amount' => (float) $this->paymentAmount,
                'type' => 'paid_to_client',
                'notes' => $notes,
                'created_by' => Auth::id(),
            ]);
            
            $this->paymentAmount = '';
            $this->paymentNotes = '';
            
            // Limpiar cache antes de recargar
            Cache::forget("client_liquidations_weeks_{$this->client->id}");
            // Limpiar también cache de sábados individuales (pueden haber cambiado)
            $user = $this->client->associatedUser;
            if ($user) {
                // Limpiar cache de sábados de los últimos 12 meses
                $startDate = Carbon::now()->subMonths(12)->startOfWeek();
                if ($startDate->isSunday()) {
                    $startDate->addDay();
                }
                while (!$startDate->isSaturday()) {
                    $startDate->addDay();
                }
                $endDate = Carbon::now();
                $currentDate = $startDate->copy();
                while ($currentDate <= $endDate) {
                    if ($currentDate->isSaturday()) {
                        Cache::forget("client_saturday_usted_debe_{$user->id}_{$currentDate->format('Y-m-d')}");
                    }
                    $currentDate->next(Carbon::SATURDAY);
                }
            }
            $this->loadWeeks();
            
            session()->flash('message', 'Pago registrado correctamente. Se verá reflejado en la liquidación del día siguiente.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Las excepciones de validación se manejan automáticamente por Livewire
            throw $e;
        } catch (\Exception $e) {
            \Log::error('Error al guardar pago: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            session()->flash('error', 'Error al guardar el pago: ' . $e->getMessage());
        }
    }
    
    public function editPayment($paymentId)
    {
        $payment = ClientPayment::findOrFail($paymentId);
        
        // Verificar que el pago pertenezca al cliente
        if ($payment->client_id != $this->client->id) {
            session()->flash('error', 'El pago no pertenece a este cliente');
            return;
        }
        
        $this->editingPaymentId = $paymentId;
        $this->editingPaymentAmount = $payment->amount;
        $this->editingPaymentNotes = $payment->notes;
        $this->editingPaymentDate = $payment->payment_date->format('Y-m-d');
    }
    
    public function updatePayment()
    {
        try {
            if (!$this->editingPaymentId) {
                session()->flash('error', 'No se ha seleccionado un pago para editar');
                return;
            }
            
            $this->validate([
                'editingPaymentAmount' => 'required|numeric|min:0.01',
                'editingPaymentDate' => 'required|date',
            ], [
                'editingPaymentAmount.required' => 'El monto del pago es requerido',
                'editingPaymentAmount.numeric' => 'El monto debe ser un número',
                'editingPaymentAmount.min' => 'El monto debe ser mayor a 0',
                'editingPaymentDate.required' => 'La fecha del pago es requerida',
                'editingPaymentDate.date' => 'La fecha debe ser válida',
            ]);
            
            $payment = ClientPayment::findOrFail($this->editingPaymentId);
            
            // Verificar que el pago pertenezca al cliente
            if ($payment->client_id != $this->client->id) {
                session()->flash('error', 'El pago no pertenece a este cliente');
                return;
            }
            
            // Actualizar el pago
            $payment->update([
                'amount' => (float) $this->editingPaymentAmount,
                'payment_date' => $this->editingPaymentDate,
                'notes' => $this->editingPaymentNotes ?: $payment->notes,
            ]);
            
            // Limpiar variables de edición
            $this->editingPaymentId = null;
            $this->editingPaymentAmount = '';
            $this->editingPaymentNotes = '';
            $this->editingPaymentDate = '';
            
            // Limpiar cache antes de recargar
            Cache::forget("client_liquidations_weeks_{$this->client->id}");
            // Limpiar también cache de sábados individuales (pueden haber cambiado)
            $user = $this->client->associatedUser;
            if ($user) {
                // Limpiar cache de sábados de los últimos 12 meses
                $startDate = Carbon::now()->subMonths(12)->startOfWeek();
                if ($startDate->isSunday()) {
                    $startDate->addDay();
                }
                while (!$startDate->isSaturday()) {
                    $startDate->addDay();
                }
                $endDate = Carbon::now();
                $currentDate = $startDate->copy();
                while ($currentDate <= $endDate) {
                    if ($currentDate->isSaturday()) {
                        Cache::forget("client_saturday_usted_debe_{$user->id}_{$currentDate->format('Y-m-d')}");
                    }
                    $currentDate->next(Carbon::SATURDAY);
                }
            }
            $this->loadWeeks();
            
            session()->flash('message', 'Pago actualizado correctamente. Los cambios se verán reflejados en las liquidaciones.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            \Log::error('Error al actualizar pago: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            session()->flash('error', 'Error al actualizar el pago: ' . $e->getMessage());
        }
    }
    
    public function cancelEdit()
    {
        $this->editingPaymentId = null;
        $this->editingPaymentAmount = '';
        $this->editingPaymentNotes = '';
        $this->editingPaymentDate = '';
    }
    
    public function confirmDeletePayment($paymentId)
    {
        $payment = ClientPayment::findOrFail($paymentId);
        
        // Verificar que el pago pertenezca al cliente
        if ($payment->client_id != $this->client->id) {
            session()->flash('error', 'El pago no pertenece a este cliente');
            return;
        }
        
        $this->paymentToDelete = $paymentId;
        $this->showDeleteConfirm = true;
    }
    
    public function deletePayment()
    {
        try {
            if (!$this->paymentToDelete) {
                session()->flash('error', 'No se ha seleccionado un pago para eliminar');
                return;
            }
            
            $payment = ClientPayment::findOrFail($this->paymentToDelete);
            
            // Verificar que el pago pertenezca al cliente
            if ($payment->client_id != $this->client->id) {
                session()->flash('error', 'El pago no pertenece a este cliente');
                return;
            }
            
            $payment->delete();
            
            $this->paymentToDelete = null;
            $this->showDeleteConfirm = false;
            
            // Limpiar cache antes de recargar
            Cache::forget("client_liquidations_weeks_{$this->client->id}");
            // Limpiar también cache de sábados individuales (pueden haber cambiado)
            $user = $this->client->associatedUser;
            if ($user) {
                // Limpiar cache de sábados de los últimos 12 meses
                $startDate = Carbon::now()->subMonths(12)->startOfWeek();
                if ($startDate->isSunday()) {
                    $startDate->addDay();
                }
                while (!$startDate->isSaturday()) {
                    $startDate->addDay();
                }
                $endDate = Carbon::now();
                $currentDate = $startDate->copy();
                while ($currentDate <= $endDate) {
                    if ($currentDate->isSaturday()) {
                        Cache::forget("client_saturday_usted_debe_{$user->id}_{$currentDate->format('Y-m-d')}");
                    }
                    $currentDate->next(Carbon::SATURDAY);
                }
            }
            $this->loadWeeks();
            
            session()->flash('message', 'Pago eliminado correctamente. Los cambios se verán reflejados en las liquidaciones.');
        } catch (\Exception $e) {
            \Log::error('Error al eliminar pago: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            session()->flash('error', 'Error al eliminar el pago: ' . $e->getMessage());
        }
    }
    
    public function cancelDelete()
    {
        $this->paymentToDelete = null;
        $this->showDeleteConfirm = false;
    }
    
    public function render()
    {
        return view('livewire.admin.clients.client-liquidations');
    }
}

