<?php

namespace App\Livewire\Admin\Users;

use App\Models\User;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Mail\Message;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rule;

class StoreUser extends Component
{
    use CanResetPassword;
    public ?string $action = null;
    public $roles;
    public $user;

    public $username, $firstName, $lastName, $phone, $email, $role, $rut;
    public bool $status = true;
    
    public function rules()
    {
        $rules = [
            'firstName' => ['required', 'string'],
            'lastName' => ['required', 'string'],
            // 'rut' => [
            //     'required',
            //     'string',
            //     'max:12',
            //     function($attribute, $value, $fail) {
            //         if (!$this->validateRut($value)) {
            //             $fail("El campo {$attribute} no es un RUT válido.");
            //         }
            //     }
            // ],
            'email' => ['required', 'email', 'regex:/@.+\..+/i'],
            'phone' => ['required', 'numeric'],
            'status' => ['required', 'boolean'],
            'role' => ['required'],
        ];

        if ($this->action !== 'edit') {
            $rules['rut'][] = Rule::unique('users', 'rut');
            $rules['email'][] = Rule::unique('users', 'email');
        } else {
            $rules['rut'][] = 'unique:users,rut,' . $this->user->id;
            $rules['email'][] = 'unique:users,email,' . $this->user->id;
        }

        return $rules;
    }

    public function mount($id = null)
    {
        if ($id) {
            $this->action = 'edit';
            $this->user = User::find($id);
            $this->username = $this->user->username;
            $this->firstName = $this->user->first_name;
            $this->lastName = $this->user->last_name;
            $this->rut = $this->user->rut;
            $this->email = $this->user->email;
            $this->phone = $this->user->phone;
            $this->status = $this->user->is_active;
            $this->role = $this->user->getRoleNames()->first();
        } else {
            $this->rut = $this->generateUniqueRut();
        }
        $this->roles = Role::all();
    }
    #[Layout('layouts.app')]
    public function render()
    {
        return view('livewire.admin.users.store-user');
    }
    public function save()
    {

        $this->validate();

        if ($this->action != 'edit') {
            $user = User::create([
                'username' => $this->username,
                'first_name' => $this->firstName,
                'last_name' => $this->lastName,
                'rut' => $this->rut,
                'email' => $this->email,
                'password' => $this->generatePasswordPlainAndHashed()['hashed'],
                'phone' => $this->phone,
                'is_active' => $this->status

            ]);

            $user->assignRole($this->role);
            Password::sendResetLink(['email' => $this->email]);        } else {
            $this->user->update([
                'username' => $this->username,
                'first_name' => $this->firstName,
                'last_name' => $this->lastName,
                'rut' => $this->rut,
                'email' => $this->email,
                'phone' => $this->phone,
                'is_active' => $this->status,
            ]);

            $this->user->syncRoles([$this->role]);
        }

        $message = "Usuario " . ($this->action ? 'editado' : 'creado') . " exitosamente!";
        banner_message($message, 'success');
        $this->redirectRoute('users.show');
    }
    private function generatePasswordPlainAndHashed()
    {
        $plain = str()->random(10);
        return [
            'plain' => $plain,
            'hashed' => bcrypt($plain),
        ];
    }


    private function validateRut($rut)
    {
        $rut = preg_replace('/[^0-9kK]/', '', $rut);

        if (strlen($rut) < 2) {
            return false;
        }

        $dv = strtolower(substr($rut, -1));
        $numero = substr($rut, 0, -1);
        $suma = 0;
        $factor = 2;

        for ($i = strlen($numero) - 1; $i >= 0; $i--) {
            $suma += $factor * $numero[$i];
            $factor = $factor == 7 ? 2 : $factor + 1;
        }

        $resto = $suma % 11;
        $dvCalculado = 11 - $resto;

        if ($dvCalculado == 10) {
            $dvCalculado = 'k';
        } elseif ($dvCalculado == 11) {
            $dvCalculado = '0';
        }

        return $dv == $dvCalculado;
    }

    private function generateUniqueRut()
    {
        do {
            $rut = (string) rand(10000000, 99999999);
        } while (User::where('rut', $rut)->exists());

        return $rut;
    }

}
