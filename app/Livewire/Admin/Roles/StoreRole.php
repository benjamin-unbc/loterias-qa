<?php

namespace App\Livewire\Admin\Roles;

use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class StoreRole extends Component
{
    public string $nameRole = "";
    public ?string $descriptionRole = null;
    public ?string $action = null;
    public $role;
    public $permissions;
    public $permissionsSelected;

    public function rules()
    {
        $rules = [
            'nameRole' => ['required', 'min:3'],
            'permissionsSelected' => ['required', function ($attribute, $value, $fail) {
                if (!in_array(true, $value, true)) {
                    $fail('Debe seleccionar al menos un permiso.');
                }
            }],
        ];

        if ($this->action !== 'edit') {
            $rules['nameRole'][] = Rule::unique('roles', 'name');
        } else {
            $rules['nameRole'][] = 'unique:roles,name,' . $this->role->id;
        }

        return $rules;
    }

    public function messages()
    {
        return [
            'nameRole.required' => __('El campo nombre es obligatorio.'),
            'nameRole.min' => __('El campo nombre debe tener al menos :min caracteres.'),
            'nameRole.unique' => __('El valor ingresado para nombre ya está en uso.'),
        ];
    }

    public function mount($id = null)
    {
        if ($id) {
            $this->action = 'edit';
            $this->role = Role::where('id', $id)->first();
            $this->nameRole = $this->role->name;
            $this->descriptionRole = $this->role->description;
        }
        $this->getPermissions();
    }

    #[Layout('layouts.app')]
    public function render()
    {
        return view('livewire.admin.roles.store-role');
    }

    public function save()
    {
        $this->validate();
        $tempRole = $this->role ? $this->role : new Role();

        $tempRole->name = $this->nameRole;
        $tempRole->description = $this->descriptionRole;
        $tempRole->guard_name = 'web';
        $tempRole->save();

        foreach ($this->permissionsSelected as $key => $permission) {
            $permissionKey = (string)$key;
            if ($permission) {
                $tempRole->givePermissionTo($permissionKey);
            } elseif ($tempRole->hasPermissionTo($permissionKey)) {
                $tempRole->revokePermissionTo($permissionKey);
            }
        }

        $message = "Rol " . ($this->action ? 'editado' : 'creado') . " exitosamente!";
        banner_message($message, 'success');
        $this->redirectRoute('users.roles.show');
    }
    public function getPermissions()
    {
        $this->permissions = Permission::all();
        $this->createStructure();
    }
    private function createStructure()
    {
        foreach ($this->permissions as $permission) {
            $this->permissionsSelected[$permission->name] = $this->role ? (bool)$this->role->hasPermissionTo($permission->name) : false;
        }
    }
}
