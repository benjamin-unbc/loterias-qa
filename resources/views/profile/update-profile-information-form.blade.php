<x-form-section submit="updateProfileInformation">
    <x-slot name="title">
        @if (Auth::user()->hasRole('Cliente'))
            {{ __('Mi Logo de Perfil') }}
        @else
            {{ __('Profile Information') }}
        @endif
    </x-slot>

    <x-slot name="description">
        @if (Auth::user()->hasRole('Cliente'))
            {{ __('Actualiza tu logo de perfil personalizado.') }}
        @else
            {{ __('Update your account\'s profile information and email address.') }}
        @endif
    </x-slot>

    <x-slot name="form">
        @if (Auth::user()->hasRole('Cliente'))
            <!-- Solo logo para clientes -->
            <div x-data="{ photoName: null, photoPreview: null }" class="col-span-6 sm:col-span-4">
                <!-- Profile Logo File Input -->
                <input type="file" id="photo" class="hidden"
                       wire:model.live="photo"
                       x-ref="photo"
                       x-on:change="
                                    photoName = $refs.photo.files[0].name;
                                    const reader = new FileReader();
                                    reader.onload = (e) => {
                                        photoPreview = e.target.result;
                                    };
                                    reader.readAsDataURL($refs.photo.files[0]);
                            " />

                <x-label for="photo" value="{{ __('Logo de Perfil') }}" />

                <!-- Current Profile Logo -->
                <div class="mt-2" x-show="! photoPreview">
                    @if($this->user->profile_photo_path)
                        <img src="{{ asset('storage/' . $this->user->profile_photo_path) }}" alt="{{ $this->user->name }}" class="rounded-full h-20 w-20 object-cover">
                    @else
                        <div class="rounded-full h-20 w-20 bg-gray-600 flex items-center justify-center">
                            <i class="fa-solid fa-user text-white text-2xl"></i>
                        </div>
                    @endif
                </div>

                <!-- New Profile Logo Preview -->
                <div class="mt-2" x-show="photoPreview" style="display: none;">
                    <span class="block rounded-full w-20 h-20 bg-cover bg-no-repeat bg-center"
                          x-bind:style="'background-image: url(\'' + photoPreview + '\');'">
                    </span>
                </div>

                <x-secondary-button class="mt-2 me-2" type="button" x-on:click.prevent="$refs.photo.click()">
                    {{ __('Seleccionar Nuevo Logo') }}
                </x-secondary-button>

                @if ($this->user->profile_photo_path)
                    <x-secondary-button type="button" class="mt-2" wire:click="deleteProfilePhoto">
                        {{ __('Eliminar Logo') }}
                    </x-secondary-button>
                @endif

                <x-input-error for="photo" class="mt-2" />
            </div>

            <!-- Información personal de solo lectura para clientes -->
            <div class="col-span-6 sm:col-span-4">
                <x-label for="client_info" value="{{ __('Información Personal') }}" />
                <div class="mt-2 p-4 bg-gray-100 rounded-lg">
                    <p class="text-sm text-gray-700"><strong>Nombre:</strong> {{ $this->user->first_name }}</p>
                    <p class="text-sm text-gray-700"><strong>Apellido:</strong> {{ $this->user->last_name }}</p>
                    <p class="text-sm text-gray-700"><strong>Correo:</strong> {{ $this->user->email }}</p>
                    <p class="text-xs text-gray-500 mt-2">* Esta información solo puede ser modificada por un administrador</p>
                </div>
            </div>
        @else
            <!-- Formulario completo para administradores -->
            <!-- Name -->
            <div class="col-span-6 sm:col-span-4">
                <x-label for="first_name" value="{{ __('Name') }}" />
                <x-input id="first_name" type="text" class="mt-1 block w-full" wire:model="state.first_name" required autocomplete="first_name" />
                <x-input-error for="first_name" class="mt-2" />
            </div>
            <div class="col-span-6 sm:col-span-4">
                <x-label for="last_name" value="{{ __('Last name') }}" />
                <x-input id="last_name" type="text" class="mt-1 block w-full" wire:model="state.last_name" required autocomplete="last_name" />
                <x-input-error for="last_name" class="mt-2" />
            </div>

            <!-- Email -->
            <div class="col-span-6 sm:col-span-4">
                <x-label for="email" value="{{ __('Email') }}" />
                <x-input id="email" type="email" class="mt-1 block w-full" wire:model="state.email" required autocomplete="username" disabled/>
                <x-input-error for="email" class="mt-2" />

                @if (Laravel\Fortify\Features::enabled(Laravel\Fortify\Features::emailVerification()) && !$this->user->hasVerifiedEmail())
                    <p class="text-sm mt-2">
                        {{ __('Your email address is unverified.') }}

                        <button type="button" class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500" wire:click.prevent="sendEmailVerification">
                            {{ __('Click here to re-send the verification email.') }}
                        </button>
                    </p>

                    @if ($this->verificationLinkSent)
                        <p class="mt-2 font-medium text-sm text-green-600">
                            {{ __('A new verification link has been sent to your email address.') }}
                        </p>
                    @endif
                @endif
            </div>
        @endif
    </x-slot>

    <x-slot name="actions">
        <x-action-message class="me-3" on="saved">
            {{ __('Saved.') }}
        </x-action-message>

        <x-button wire:loading.attr="disabled" wire:target="photo">
            {{ __('Save') }}
        </x-button>
    </x-slot>
</x-form-section>
