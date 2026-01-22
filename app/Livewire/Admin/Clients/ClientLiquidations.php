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
        $liquidationsComponent = new Liquidations();
        
        // Buscar todos los sábados posibles desde que el cliente tiene jugadas
        // Obtener la fecha más antigua de resultados o apuestas
        $oldestResult = Result::where('user_id', $user->id)->min('date');
        $oldestApus = ApusModel::where('user_id', $user->id)
            ->whereHas('playsSent', function($query) {
                $query->where('status', '!=', 'I');
            })
            ->min('created_at');
        
        $startDate = null;
        if ($oldestResult && $oldestApus) {
            $startDate = Carbon::parse(min($oldestResult, $oldestApus));
        } elseif ($oldestResult) {
            $startDate = Carbon::parse($oldestResult);
        } elseif ($oldestApus) {
            $startDate = Carbon::parse($oldestApus);
        }
        
        // Si no hay fechas, buscar desde hace 1 año
        if (!$startDate) {
            $startDate = Carbon::now()->subYear();
        }
        
        // Buscar todos los sábados desde la fecha más antigua hasta hoy
        $allSaturdays = collect();
        $currentDate = $startDate->copy();
        $endDate = Carbon::now();
        
        while ($currentDate <= $endDate) {
            // Si es sábado, agregarlo
            if ($currentDate->isSaturday()) {
                $allSaturdays->push($currentDate->copy());
            }
            // Avanzar al siguiente sábado
            $currentDate->next(Carbon::SATURDAY);
        }
        
        $this->weeks = [];
        $this->totalDebe = 0;
        
        foreach ($allSaturdays as $saturday) {
            // Calcular USTED DEBE SEM para este sábado
            $liquidationData = $liquidationsComponent->computeClientLiquidationData($user, $saturday);
            $ustedDebeSem = $liquidationData['usted_debe_sem'] ?? 0;
            
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
                
                // Obtener pagos de esta semana O pagos que tengan referencia a esta semana en las notas
                // Esto incluye pagos guardados después de la semana pero asociados a ella
                $saturdayStr = $saturday->format('Y-m-d');
                $payments = ClientPayment::where('client_id', $this->client->id)
                    ->where(function($query) use ($weekStart, $weekEnd, $saturdayStr) {
                        // Pagos dentro del rango de la semana
                        $query->where(function($q) use ($weekStart, $weekEnd) {
                            $q->whereDate('payment_date', '>=', $weekStart->format('Y-m-d'))
                              ->whereDate('payment_date', '<=', $weekEnd->format('Y-m-d'));
                        })
                        // O pagos que tengan referencia a esta semana en las notas
                        ->orWhere('notes', 'like', '%Semana del sábado: ' . $saturdayStr . '%');
                    })
                    ->orderBy('payment_date', 'desc')
                    ->orderBy('created_at', 'desc')
                    ->get();
                
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
    
    public function render()
    {
        return view('livewire.admin.clients.client-liquidations');
    }
}

