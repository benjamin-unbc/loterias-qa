<?php

namespace App\Livewire\Admin\Roles;

use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Illuminate\Support\Facades\Gate; // Importa la fachada Gate
use Livewire\WithoutUrlPagination;
use Livewire\WithPagination;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class ShowRoles extends Component
{
    use WithPagination, WithoutUrlPagination;

    public $search = '';
    public $cant = 5;
    public $visualizeRoleModal = false;
    public $dataRole, $permissionsList;

    public $showConfirmationModal = false;
    public $roleIdBeingDeleted;

    #[Layout('layouts.app')]
    public function render()
    {

        $roles = Role::when($this->search, function ($query) {
            $query->where('name', 'LIKE', '%' . $this->search . '%')->orWhere('description', 'LIKE', '%' . $this->search . '%');
        })
            ->orderBy('name')
            ->paginate($this->cant);

        return view('livewire.admin.roles.show-roles', [
            'roles' => $roles
        ]);
    }
    public function updatingSearch()
    {
        $this->resetPage();
    }
    public function showModalRoleDetail($roleId)
    {
        $this->dataRole = Role::where('id', $roleId)->first();
        $this->visualizeRoleModal = true;
        $this->getPermissions();
    }
    public function closeModal()
    {
        $this->visualizeRoleModal = false;
        $this->reset('dataRole');
    }
    public function deleteRole()
    {
        // Verifica si el usuario actual tiene el permiso 'eliminar roles'
        Gate::authorize('eliminar roles');
        $role = Role::findOrFail($this->roleIdBeingDeleted);
        $role->delete();
        $this->showConfirmationModal = false;

        banner_message("Rol eliminado exitosamente!", 'success');
        $this->redirectRoute('users.roles.show');
    }
    public function confirmRoleDeletion($roleId)
    {
        $this->showConfirmationModal = true;
        $this->roleIdBeingDeleted = $roleId;
    }
    private function getPermissions()
    {
        $this->permissionsList = Permission::all();
    }
}
