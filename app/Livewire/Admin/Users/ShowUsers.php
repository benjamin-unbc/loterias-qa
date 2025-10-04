<?php

namespace App\Livewire\Admin\Users;

use App\Models\User;
use Livewire\Component;
use Illuminate\Support\Facades\Gate; // Importa la fachada Gate
use Livewire\WithPagination;
use Livewire\Attributes\Layout;

class ShowUsers extends Component
{
    use WithPagination;
    public string $search = '';
    public int $cant = 5;
    public $showConfirmationModal = false;
    public $deletingUserId;

    #[Layout('layouts.app')]
    public function render()
    {
        // Excluir usuarios con rol "Cliente" - solo mostrar usuarios administrativos
        $users = User::whereDoesntHave('roles', function($query) {
            $query->where('name', 'Cliente');
        })->search($this->search)->paginate($this->cant);

        return view('livewire.admin.users.show-users', [
            'users' => $users
        ]);

    }

    public function deleteUser()
    {
        // Verifica si el usuario actual tiene el permiso 'eliminar usuarios'
        Gate::authorize('eliminar usuarios');
        $userId = $this->deletingUserId;

        User::where('id', $userId)->delete();
        $this->showConfirmationModal = false;
        $this->deletingUserId = null;

        banner_message("Usuario eliminado exitosamente!", 'success');

        $this->redirectRoute('users.show');
    }

    public function confirmUserDeletion($userId)
    {
        $this->deletingUserId = $userId;
        $this->showConfirmationModal = true;
    }

}