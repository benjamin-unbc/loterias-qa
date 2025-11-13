<?php

namespace App\Livewire\Admin\Clients;

use App\Models\Client;
use Livewire\Component;
use Illuminate\Support\Facades\Gate;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;

class ShowClients extends Component
{
    use WithPagination;
    
    public string $search = '';
    public int $cant = 5;
    public $showConfirmationModal = false;
    public $deletingClientId;

    #[Layout('layouts.app')]
    public function render()
    {
        try {
            $clients = Client::search($this->search)->paginate($this->cant);

            return view('livewire.admin.clients.show-clients', [
                'clients' => $clients
            ]);
        } catch (\Exception $e) {
            \Log::error('Error en ShowClients: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            
            return view('livewire.admin.clients.show-clients', [
                'clients' => collect(),
                'error' => $e->getMessage(),
                'errorDetails' => config('app.debug') ? [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ] : null
            ]);
        }
    }

    /**
     * Delete a client after confirmation
     */
    public function deleteClient()
    {
        // Verifica si el usuario actual tiene el permiso 'eliminar clientes'
        Gate::authorize('eliminar clientes');
        $clientId = $this->deletingClientId;

        // Obtener el cliente y eliminar junto con su usuario asociado
        $client = Client::find($clientId);
        
        if ($client) {
            $client->deleteWithAssociatedUser();
        }

        $this->showConfirmationModal = false;
        $this->deletingClientId = null;

        banner_message("Cliente y usuario asociado eliminados exitosamente!", 'success');

        $this->redirectRoute('clients.show');
    }

    /**
     * Show confirmation modal for client deletion
     */
    public function confirmClientDeletion($clientId)
    {
        $this->deletingClientId = $clientId;
        $this->showConfirmationModal = true;
    }
}
