<div class="bg-[#1b1f22] w-full h-full min-h-screen p-4 flex flex-col gap-3">
    <div class="flex items-center gap-3 pb-3">
        <a href="{{ route('clients.show') }}" class="bg-gray-600 shadow-lg hover:bg-gray-700 duration-75 transition-all ease-in-out text-white hover:text-gray-300 rounded-full px-2 py-1"><i class="fa-solid fa-arrow-left"></i></a>
        <h2 class="font-semibold text-xl text-white leading-tight">
            {{ $action === 'edit' ? 'Editar cliente' : 'Agregar nuevo cliente' }}
        </h2>
    </div>
    
    <!-- Mensaje de error para archivos -->
    @if ($errors->has('photo'))
        <div class="flex p-4 mb-4 text-sm text-red-200 rounded-lg bg-red-900/50" role="alert">
            <svg class="flex-shrink-0 inline w-4 h-4 me-3 mt-[2px]" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 20 20">
                <path d="M10 .5a9.5 9.5 0 1 0 9.5 9.5A9.51 9.51 0 0 0 10 .5ZM9.5 4a1.5 1.5 0 1 1 0 3 1.5 1.5 0 0 1 0-3ZM12 15H8a1 1 0 0 1 0-2h1v-3H8a1 1 0 0 1 0-2h2a1 1 0 0 1 1 1v4h1a1 1 0 0 1 0 2Z"/>
            </svg>
            <div>
                <span class="font-medium">Error con el logo:</span>
                <p class="mt-1">{{ $errors->first('photo') }}</p>
            </div>
        </div>
    @endif

    <form class="w-full" wire:submit="save" enctype="multipart/form-data">
        @if(!$is_active)
            <div class="flex p-4 mb-4 text-sm text-yellow-200 rounded-lg bg-gray-700" role="alert">
                <svg class="flex-shrink-0 inline w-4 h-4 me-3 mt-[2px]" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M10 .5a9.5 9.5 0 1 0 9.5 9.5A9.51 9.51 0 0 0 10 .5ZM9.5 4a1.5 1.5 0 1 1 0 3 1.5 1.5 0 0 1 0-3ZM12 15H8a1 1 0 0 1 0-2h1v-3H8a1 1 0 0 1 0-2h2a1 1 0 0 1 1 1v4h1a1 1 0 0 1 0 2Z"/>
                </svg>
                <span class="sr-only">Danger</span>
                <div>
                    <span class="font-medium">Consideraciones antes de inactivar un cliente:</span>
                    <ul class="mt-1.5 list-disc list-inside">
                        <li>El cliente no podrá acceder al sistema ni utilizar sus funcionalidades.</li>
                        <li>Se interrumpirá el acceso a información y recursos importantes.</li>
                        <li>Potencial pérdida de datos asociados al cliente inactivo.</li>
                    </ul>
                </div>
            </div>
        @endif
        
        <!-- Logo de perfil -->
        <div class="mb-6">
            <x-label for="photo" value="{{ __('Logo de Perfil') }}" />
            <div x-data="{ photoName: null, photoPreview: null }" class="mt-2">
                <!-- Profile Logo File Input -->
                <input type="file" id="photo" class="hidden"
                       wire:model.live="photo"
                       x-ref="photo"
                       accept="image/jpeg,image/jpg,image/png"
                       x-on:change="
                                photoName = $refs.photo.files[0].name;
                                const reader = new FileReader();
                                reader.onload = (e) => {
                                    photoPreview = e.target.result;
                                };
                                reader.readAsDataURL($refs.photo.files[0]);
                            " />

                <!-- Current Profile Logo -->
                <div class="mt-2" x-show="! photoPreview">
                    @if($action === 'edit' && $client && $client->profile_photo_path)
                        <img src="{{ asset('storage/' . $client->profile_photo_path) }}" alt="{{ $client->nombre }}" class="rounded-full h-20 w-20 object-cover">
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

                <!-- Loading indicator for logo upload -->
                <div wire:loading wire:target="photo" class="mt-2">
                    <div class="flex items-center text-yellow-200">
                        <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-yellow-200" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Subiendo logo...
                    </div>
                </div>

                <x-secondary-button class="mt-2 me-2" type="button" x-on:click.prevent="$refs.photo.click()">
                    {{ __('Seleccionar Logo') }}
                </x-secondary-button>

                @if($action === 'edit' && $client && $client->profile_photo_path)
                    <x-secondary-button type="button" class="mt-2" wire:click="deleteProfilePhoto">
                        {{ __('Eliminar Logo') }}
                    </x-secondary-button>
                @endif

                <x-input-error for="photo" class="mt-2" />
                
                <!-- Información sobre los tipos de archivo permitidos -->
                <p class="mt-1 text-sm text-gray-400">
                    Formatos permitidos: JPG, JPEG, PNG. Tamaño máximo: 1MB.
                </p>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="">
                <x-label for="nombre" value="{{ __('Nombre') }}" />
                <x-input id="nombre" type="text" class="mt-1 block w-full" wire:model.live="nombre" placeholder="Ingrese nombre" required autocomplete="nombre" />
                <x-input-error for="nombre" class="mt-2" />
            </div>
            <div class="">
                <x-label for="apellido" value="{{ __('Apellido') }}" />
                <x-input id="apellido" type="text" class="mt-1 block w-full" wire:model.live="apellido" placeholder="Ingrese apellido" required autocomplete="apellido" />
                <x-input-error for="apellido" class="mt-2" />
            </div>
            <div class="">
                <x-label for="correo" value="{{ __('Correo Electrónico') }}" />
                <x-input id="correo" type="email" class="mt-1 block w-full" wire:model.live="correo" placeholder="cliente@ejemplo.com" required autocomplete="email" />
                <x-input-error for="correo" class="mt-2" />
            </div>
            <div class="">
                <x-label for="nombre_fantasia" value="{{ __('Nombre de Fantasía') }}" />
                <x-input id="nombre_fantasia" type="text" class="mt-1 block w-full" wire:model.live="nombre_fantasia" placeholder="Ingrese nombre de fantasía" required autocomplete="nombre_fantasia" />
                <x-input-error for="nombre_fantasia" class="mt-2" />
            </div>
            <div class="">
                <x-label for="password" value="{{ __('Contraseña') }}" />
                <input id="password" type="password" class="mt-1 block w-full border-gray-300 bg-[#22272b] text-white text-sm focus:border-yellow-200 focus:ring-yellow-200 rounded-md shadow-sm" 
                    wire:model.live="password" 
                    placeholder="{{ $action === 'edit' ? 'Dejar vacío para mantener la actual' : 'Ingrese contraseña' }}" 
                    autocomplete="new-password" />
                <x-input-error for="password" class="mt-2" />
                @if($action === 'edit')
                <p class="mt-1 text-sm text-gray-400">Dejar vacío para mantener la contraseña actual</p>
                @endif
            </div>
            <div class="">
                <x-label for="commission_percentage" value="{{ __('Porcentaje Comisión (%)') }}" />
                <x-input id="commission_percentage" type="number" step="0.01" min="0" max="100" class="mt-1 block w-full" wire:model.live="commission_percentage" placeholder="20.00" required />
                <x-input-error for="commission_percentage" class="mt-2" />
                <p class="mt-1 text-sm text-gray-400">Porcentaje de comisión que se aplicará a las apuestas del cliente</p>
            </div>
            <div>
                <x-label for="is_active" value="{{ __('Estado') }}" />
                <label class="mt-2 inline-flex items-center cursor-pointer">
                    <input type="checkbox" wire:model.live="is_active" class="sr-only peer">
                    <div class="relative w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-yellow-200 rounded-full peer peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-yellow-200"></div>
                    <span class="ms-3 text-sm font-medium text-gray-900 dark:text-gray-300"></span>
                </label>
            </div>
        </div>
        <div class="w-full flex mt-4 2xl:justify-end 2xl:mt-6">
            <div class="text-yellow-200" wire:loading wire:target="save">
                {{ $action === 'edit' ? 'Actualizando cliente...' : 'Creando cliente...' }}
            </div>
            <x-button wire:loading.class="hidden" wire:target="save">
                {{ __('Guardar') }}
            </x-button>
        </div>
    </form>
</div>