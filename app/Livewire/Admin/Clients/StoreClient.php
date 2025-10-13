<?php

namespace App\Livewire\Admin\Clients;

use App\Models\Client;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;
use Illuminate\Validation\Rule;

class StoreClient extends Component
{
    use WithFileUploads;
    
    public ?string $action = null;
    public $client;

    // Form fields
    public $nombre, $apellido, $correo, $nombre_fantasia, $password;
    public bool $is_active = true;
    public $commission_percentage = 20.00;
    public $photo;

    /**
     * Validation rules for client form
     */
    public function rules()
    {
        $rules = [
            'nombre' => ['required', 'string', 'max:255'],
            'apellido' => ['required', 'string', 'max:255'],
            'correo' => ['required', 'email', 'regex:/@.+\..+/i'],
            'nombre_fantasia' => ['required', 'string', 'max:255'],
            'is_active' => ['required', 'boolean'],
            'commission_percentage' => ['required', 'numeric', 'min:0', 'max:100'],
            'photo' => ['nullable', 'mimes:jpg,jpeg,png', 'max:1024'],
        ];

        if ($this->action !== 'edit') {
            $rules['correo'][] = Rule::unique('clients', 'correo');
            $rules['correo'][] = Rule::unique('users', 'email'); // También verificar en users
            $rules['password'] = ['required', 'string', 'min:8'];
        } else {
            $rules['correo'][] = Rule::unique('clients', 'correo')->ignore($this->client->id);
            $rules['password'] = ['nullable', 'string', 'min:8'];
        }

        return $rules;
    }

    /**
     * Mount component with optional client ID for editing
     */
    public function mount($id = null)
    {
        if ($id) {
            $this->action = 'edit';
            $this->client = Client::findOrFail($id);
            $this->nombre = $this->client->nombre;
            $this->apellido = $this->client->apellido;
            $this->correo = $this->client->correo;
            $this->nombre_fantasia = $this->client->nombre_fantasia;
            $this->is_active = $this->client->is_active;
            $this->commission_percentage = $this->client->commission_percentage ?? 20.00;
            $this->password = ''; // Don't pre-fill password
        } else {
            $this->action = 'create';
            $this->password = ''; // Initialize password field
        }
    }

    /**
     * Save or update client
     */
    public function save()
    {
        $this->validate();

        $data = [
            'nombre' => $this->nombre,
            'apellido' => $this->apellido,
            'correo' => $this->correo,
            'nombre_fantasia' => $this->nombre_fantasia,
            'is_active' => $this->is_active,
            'commission_percentage' => $this->commission_percentage,
        ];

        // Manejar el logo de perfil
        if ($this->photo) {
            $data['profile_photo_path'] = $this->photo->store('profile-photos', 'public');
        }

        // Aplicar el hash a la contraseña
        if ($this->action === 'edit') {
            // Solo actualizar la contraseña si se proporcionó
            if (!empty($this->password)) {
                $data['password'] = \Illuminate\Support\Facades\Hash::make($this->password);
            }
        } else {
            // Para crear, la contraseña es obligatoria y se hashea
            $data['password'] = \Illuminate\Support\Facades\Hash::make($this->password);
        }

        if ($this->action === 'edit') {
            $this->client->update($data);

            // Sincronizar también el usuario asociado (buscado por correo actual del cliente)
            if ($this->client->correo) {
                $user = \App\Models\User::where('email', $this->client->correo)->first();
                if ($user) {
                    $userUpdate = [
                        'is_active' => $data['is_active'],
                    ];

                    if (isset($data['profile_photo_path'])) {
                        $userUpdate['profile_photo_path'] = $data['profile_photo_path'];
                    }

                    if (array_key_exists('password', $data)) {
                        $userUpdate['password'] = $data['password'];
                    }

                    $user->update($userUpdate);
                }
            }

            banner_message("Cliente actualizado exitosamente!", 'success');
        } else {
            // Crear como usuario normal con el rol "Cliente"
            $userData = [
                'first_name' => $data['nombre'],
                'last_name' => $data['apellido'],
                'email' => $data['correo'],
                'password' => $data['password'], // Aseguramos que la contraseña esté hasheada
                'phone' => '0000000000', // Teléfono por defecto
                'is_active' => $data['is_active'],
                'rut' => $this->generateUniqueRut(), // RUT único generado
            ];

            // Agregar logo de perfil si existe
            if (isset($data['profile_photo_path'])) {
                $userData['profile_photo_path'] = $data['profile_photo_path'];
            }

            $user = \App\Models\User::create($userData);
            $user->assignRole('Cliente');

            // También crear en la tabla clients para mantener compatibilidad (sin rol)
            Client::create($data);

            banner_message("Cliente creado exitosamente!", 'success');
        }

        $this->redirectRoute('clients.show');
    }
    /**
     * Generate a unique RUT for clients
     */
    private function generateUniqueRut()
    {
        do {
            // Generar un número aleatorio de 8 dígitos
            $number = str_pad(rand(10000000, 99999999), 8, '0', STR_PAD_LEFT);
            
            // Calcular el dígito verificador
            $verifier = $this->calculateRutVerifier($number);
            
            // Formatear el RUT
            $rut = substr($number, 0, 2) . '.' . substr($number, 2, 3) . '.' . substr($number, 5, 3) . '-' . $verifier;
            
        } while (\App\Models\User::where('rut', $rut)->exists());
        
        return $rut;
    }

    /**
     * Calculate RUT verifier digit
     */
    private function calculateRutVerifier($number)
    {
        $sum = 0;
        $multiplier = 2;
        
        for ($i = strlen($number) - 1; $i >= 0; $i--) {
            $sum += intval($number[$i]) * $multiplier;
            $multiplier = $multiplier == 7 ? 2 : $multiplier + 1;
        }
        
        $remainder = $sum % 11;
        $verifier = 11 - $remainder;
        
        if ($verifier == 11) return '0';
        if ($verifier == 10) return 'K';
        return (string)$verifier;
    }

    /**
     * Delete the client's profile logo
     */
    public function deleteProfilePhoto()
    {
        if ($this->client && $this->client->profile_photo_path) {
            // Eliminar archivo físico
            \Illuminate\Support\Facades\Storage::disk('public')->delete($this->client->profile_photo_path);
            
            // Actualizar en la base de datos
            $this->client->update(['profile_photo_path' => null]);
            
            // Actualizar también el usuario asociado
            $user = \App\Models\User::where('email', $this->client->correo)->first();
            if ($user) {
                $user->update(['profile_photo_path' => null]);
            }
            
            banner_message("Logo de perfil eliminado exitosamente!", 'success');
        }
    }

    #[Layout('layouts.app')]
    public function render()
    {
        return view('livewire.admin.clients.store-client');
    }
}
