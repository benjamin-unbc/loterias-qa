<?php

namespace App\Actions\Fortify;

use App\Models\User;
use App\Models\Client;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Fortify\Contracts\UpdatesUserProfileInformation;

class UpdateUserProfileInformation implements UpdatesUserProfileInformation
{
    /**
     * Validate and update the given user's profile information.
     *
     * @param  array<string, mixed>  $input
     */
    public function update(User $user, array $input): void
    {
        if ($user->hasRole('Cliente')) {
            // Para clientes: solo validar y actualizar logo
            $rules = [
                'photo' => ['nullable', 'mimes:jpg,jpeg,png', 'max:1024'],
            ];

            Validator::make($input, $rules)->validateWithBag('updateProfileInformation');

            // Solo manejar logo si el usuario es un cliente
            if (isset($input['photo'])) {
                $user->updateProfilePhoto($input['photo']);
                
                // Sincronizar logo con la tabla clients
                $client = Client::where('correo', $user->email)->first();
                if ($client) {
                    $client->update([
                        'profile_photo_path' => $user->profile_photo_path,
                    ]);
                }
            }
        } else {
            // Para administradores: validación y actualización completa
            $rules = [
                'first_name' => ['required', 'string', 'max:255'],
                'last_name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            ];

            Validator::make($input, $rules)->validateWithBag('updateProfileInformation');

            if ($input['email'] !== $user->email &&
                $user instanceof MustVerifyEmail) {
                $this->updateVerifiedUser($user, $input);
            } else {
                $user->forceFill([
                    'first_name' => $input['first_name'],
                    'last_name' => $input['last_name'],
                    'email' => $input['email'],
                ])->save();
            }
        }
    }

    /**
     * Update the given verified user's profile information.
     *
     * @param  array<string, string>  $input
     */
    protected function updateVerifiedUser(User $user, array $input): void
    {
        $user->forceFill([
            'first_name' => $input['first_name'],
            'email' => $input['email'],
            'email_verified_at' => null,
        ])->save();

        $user->sendEmailVerificationNotification();
    }
}
