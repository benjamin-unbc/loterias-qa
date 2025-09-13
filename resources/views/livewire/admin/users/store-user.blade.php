<div class="bg-[#1b1f22] w-full h-full min-h-screen p-4 flex flex-col gap-3">
    <div class="flex items-center gap-3 pb-3">
        <a href="{{ route('users.show') }}" class="bg-gray-600 shadow-lg hover:bg-gray-700 duration-75 transition-all ease-in-out text-white hover:text-gray-300 rounded-full px-2 py-1"><i class="fa-solid fa-arrow-left"></i></a>
        <h2 class="font-semibold text-xl text-white leading-tight">
            {{ $action ? 'Editar usuario' : 'Agregar nuevo usuario' }}
        </h2>
    </div>
    <form class="w-full" wire:submit="save">
        @if($status)
            <div class="flex p-4 mb-4 text-sm text-yellow-200 rounded-lg bg-gray-700" role="alert">
                <svg class="flex-shrink-0 inline w-4 h-4 me-3 mt-[2px]" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M10 .5a9.5 9.5 0 1 0 9.5 9.5A9.51 9.51 0 0 0 10 .5ZM9.5 4a1.5 1.5 0 1 1 0 3 1.5 1.5 0 0 1 0-3ZM12 15H8a1 1 0 0 1 0-2h1v-3H8a1 1 0 0 1 0-2h2a1 1 0 0 1 1 1v4h1a1 1 0 0 1 0 2Z"/>
                </svg>
                <span class="sr-only">Danger</span>
                <div>
                    <span class="font-medium">Consideraciones antes de inactivar un usuario:</span>
                    <ul class="mt-1.5 list-disc list-inside">
                        <li>El usuario no podrá acceder al sistema ni utilizar sus funcionalidades.</li>
                        <li>Se interrumpirá el acceso a información y recursos importantes.</li>
                        <li>Potencial pérdida de datos asociados al usuario inactivo.</li>
                    </ul>
                </div>
            </div>
        @endif
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="">
                <x-label for="firstName" value="{{ __('Name') }}" />
                <x-input id="firstName" type="text" class="mt-1 block w-full" wire:model.live="firstName" placeholder="Ingrese nombre" required autocomplete="firstName" />
                <x-input-error for="firstName" class="mt-2" />
            </div>
            <div class="">
                <x-label for="lastName" value="{{ __('Apellido') }}" />
                <x-input id="lastName" type="text" class="mt-1 block w-full" wire:model.live="lastName" placeholder="Ingrese apellido" required autocomplete="lastName" />
                <x-input-error for="lastName" class="mt-2" />
            </div>
            <div class="">
                <x-label for="rut" value="{{ __('N° RUT') }}" />
                <x-input id="rut" type="text" disabled class="mt-1 block w-full" wire:model.live="rut" placeholder="Ingrese número de RUT" autocomplete="rut" />
                <x-input-error for="rut" class="mt-2" />

                <script>
                    const rutInput = document.getElementById('rut');

                    rutInput.addEventListener('input', function(event) {
                        let rut = event.target.value;
                        rut = rut.replace(/[^0-9kK]+/g, '');
                        const isFormatX = rut.indexOf('.') === -1 || rut.indexOf('.') === 1;
                        const targetFormat = rut.length === 9 ? 'xx.xxx.xxx-x' : 'x.xxx.xxx-x';
                        let formattedRut = '';
                        let rutIndex = 0;
                        for (let i = 0; i < targetFormat.length; i++) {
                            const formatChar = targetFormat[i];
                            if (formatChar === 'x') {
                                formattedRut += rut[rutIndex] || '';
                                rutIndex++;
                            } else if (formatChar === '.' || formatChar === '-') {
                                formattedRut += formatChar;
                            }
                        }
                        event.target.value = formattedRut;
                    });

                    rutInput.addEventListener('keydown', function(event) {
                        if (event.key === 'Backspace' || event.key === 'Delete') {
                            event.preventDefault();
                            let rut = event.target.value;
                            const lastIndex = Math.max(rut.lastIndexOf('.'), rut.lastIndexOf('-'), rut.search(/[^0-9kK.\\-]/));
                            rut = rut.slice(0, lastIndex);
                            event.target.value = rut;
                        }
                    });
                </script>
            </div>
            <div class="">
                <x-label for="email" value="{{ __('Email') }}" />
                <x-input id="email" type="text" class="mt-1 block w-full" wire:model.live="email" placeholder="example@vectorseguros.cl" autocomplete="email" />
                <x-input-error for="email" class="mt-2" />
            </div>
            <div class="">
                <x-label for="phone" value="{{ __('Phone') }}" />
                <x-input id="phone" type="text" class="mt-1 block w-full" wire:model.live="phone" placeholder="Ingrese número de celular" autocomplete="phone" />
                <x-input-error for="phone" class="mt-2" />
            </div>
            <div class="">
                <x-label for="role" value="{{ __('Rol') }}" />
                <select wire:model.live="role" class="mt-1 bg-[#22272b] border border-white text-white text-sm rounded-lg focus:ring-yellow-200 focus:border-yellow-200 block w-full p-2">
                    <option value="" selected>Seleccione un rol</option>
                    @foreach ($roles as $role)
                        <option value="{{ $role->name }}">{{ $role->name }}</option>
                    @endforeach
                </select>
                <x-input-error for="role" class="mt-2" />
            </div>
            <div>
                <x-label for="status" value="{{ __('Estado') }}" />
                <label class="mt-2 inline-flex items-center cursor-pointer">
                    <input type="checkbox" wire:model.live="status" class="sr-only peer">
                    <div class="relative w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-yellow-200 rounded-full peer peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-yellow-200"></div>
                    <span class="ms-3 text-sm font-medium text-gray-900 dark:text-gray-300"></span>
                </label>
            </div>
        </div>
        <div class="w-full flex mt-4 2xl:justify-end 2xl:mt-6">
            <div class="text-yellow-200" wire:loading wire:target="save">
                {{ $action ? 'Actualizando usuario' : 'Creando usuario...' }}
            </div>
            <x-button wire:loading.class="hidden" wire:target="save">
                {{ __('Save') }}
            </x-button>
        </div>
    </form>
</div>
