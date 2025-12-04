<?php

namespace App\Livewire\Admin\Clients;

use App\Models\Client;
use App\Models\ClientPayment;
use App\Livewire\Admin\Liquidations;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class ClientSimpleLiquidation extends Component
{
    #[Layout('layouts.app')]
    
    public $client;
    public $clientId;
    public $currentAnteri = 0;
    public $paymentAmount = 0;
    public $paymentNotes = '';
    public $showPaymentModal = false;
    public $liquidationDate = null; // Fecha de la liquidación (lunes de la semana)
    public $hasPaymentToday = false; // Si hay pago registrado hoy
    public $paymentsToday = []; // Todos los pagos registrados hoy
    public $nextLiquidationDate = null; // Fecha en la que se verá reflejado el pago (mañana)
    
    public function mount($id)
    {
        $this->clientId = $id;
        $this->client = Client::with('associatedUser')->findOrFail($id);
        $this->loadCurrentAnteri();
        $this->checkPaymentsToday();
    }
    
    /**
     * Carga el ANTERI del día actual
     * El ANTERI es el del lunes de la semana actual
     * La fecha mostrada es la fecha actual
     */
    public function loadCurrentAnteri()
    {
        if (!$this->client || !$this->client->associatedUser) {
            $this->currentAnteri = 0;
            return;
        }
        
        $user = $this->client->associatedUser;
        $today = Carbon::today();
        
        // Mostrar la fecha actual
        $this->liquidationDate = $today->format('Y-m-d');
        
        // Obtener el lunes de la semana actual
        $mondayOfWeek = $today->copy()->startOfWeek();
        
        // Fecha en la que se verá reflejado el pago (mañana)
        $this->nextLiquidationDate = $today->copy()->addDay()->format('Y-m-d');
        
        // Crear instancia de Liquidations para calcular el ANTERI
        $liquidationsComponent = new Liquidations();
        
        // Calcular la liquidación del lunes para obtener su ANTERI
        $mondayLiquidation = $liquidationsComponent->computeClientLiquidationData($user, $mondayOfWeek);
        
        // El ANTERI del lunes es el que se usa toda la semana
        $this->currentAnteri = $mondayLiquidation['anteri'] ?? 0;
    }
    
    /**
     * Verifica si hay pagos registrados hoy y los obtiene todos
     */
    public function checkPaymentsToday()
    {
        if (!$this->client) {
            $this->hasPaymentToday = false;
            $this->paymentsToday = [];
            return;
        }
        
        $today = Carbon::today();
        $payments = ClientPayment::where('client_id', $this->client->id)
            ->whereDate('payment_date', $today->format('Y-m-d'))
            ->orderBy('created_at', 'desc')
            ->get();
        
        $this->hasPaymentToday = $payments->count() > 0;
        $this->paymentsToday = $payments->map(function($payment) {
            return [
                'amount' => (float) $payment->amount,
                'type' => $payment->type,
                'created_at' => $payment->created_at->format('H:i'),
            ];
        })->toArray();
    }
    
    /**
     * Abre el modal de pago
     */
    public function openPaymentModal()
    {
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
        $this->paymentAmount = 0;
        $this->paymentNotes = '';
    }
    
    /**
     * Guarda el pago
     */
    public function savePayment()
    {
        $this->validate([
            'paymentAmount' => 'required|numeric|min:0.01',
        ], [
            'paymentAmount.required' => 'El monto es requerido',
            'paymentAmount.numeric' => 'El monto debe ser un número',
            'paymentAmount.min' => 'El monto debe ser mayor a 0',
        ]);
        
        $today = Carbon::today();
        
        // Determinar el tipo de pago basado en el ANTERI
        // Si ANTERI es positivo → cliente debe pagar → UD.DIO (paid_to_client)
        // Si ANTERI es negativo → administrador debe pagar → UD.RECIBE (received_from_client)
        $type = $this->currentAnteri >= 0 ? 'paid_to_client' : 'received_from_client';
        
        // Crear el pago
        ClientPayment::create([
            'client_id' => $this->client->id,
            'payment_date' => $today->format('Y-m-d'),
            'amount' => abs($this->paymentAmount), // Siempre positivo
            'type' => $type,
            'notes' => $this->paymentNotes,
            'created_by' => Auth::id(),
        ]);
        
        // Guardar el monto del pago antes de cerrar el modal
        $paymentAmountSaved = $this->paymentAmount;
        
        // Cerrar el modal
        $this->closePaymentModal();
        
        // Verificar pagos de hoy
        $this->checkPaymentsToday();
        
        // Recargar el ANTERI (aunque no cambiará hasta mañana, pero por si acaso)
        $this->loadCurrentAnteri();
        
        // Obtener todos los pagos de hoy para el mensaje
        $today = Carbon::today();
        $allPaymentsToday = ClientPayment::where('client_id', $this->client->id)
            ->whereDate('payment_date', $today->format('Y-m-d'))
            ->orderBy('created_at', 'asc')
            ->get();
        
        // Construir mensaje con todos los pagos
        $tomorrow = Carbon::tomorrow();
        $paymentMessages = [];
        foreach ($allPaymentsToday as $payment) {
            $paymentMessages[] = '$' . number_format($payment->amount, 2, ',', '.');
        }
        
        $totalAmount = $allPaymentsToday->sum('amount');
        $paymentCount = $allPaymentsToday->count();
        
        if ($paymentCount == 1) {
            $message = 'Se registró un pago de ' . $paymentMessages[0] . ' hoy. Se verá reflejado en la liquidación del ' . $tomorrow->format('d/m/Y') . '.';
        } else {
            $message = 'Se registraron ' . $paymentCount . ' pagos hoy (' . implode(', ', $paymentMessages) . ') por un total de $' . number_format($totalAmount, 2, ',', '.') . '. Se verán reflejados en la liquidación del ' . $tomorrow->format('d/m/Y') . '.';
        }
        
        session()->flash('payment_success', $message);
    }
    
    public function render()
    {
        return view('livewire.admin.clients.client-simple-liquidation');
    }
}

