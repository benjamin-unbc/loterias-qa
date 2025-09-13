<div class="bg-[#1b1f22] w-full h-full min-h-screen p-4 flex flex-col gap-3">
    <div class="flex space-x-2 items-center gap-3 pb-3">
        <a href="{{ route('users.roles.show') }}" class="bg-gray-600 shadow-lg hover:bg-gray-700 duration-75 transition-all ease-in-out text-white hover:text-gray-300 rounded-full px-2 py-1"><i class="fa-solid fa-arrow-left"></i></a>
        <h2 class="font-semibold text-xl text-white leading-tight">
            {{ $action ? 'Editar rol' : 'Agregar nuevo rol' }}
        </h2>
    </div>

    <form class="w-full" wire:submit.prevent="save">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <x-label for="nameRole" value="{{ __('Name') }}" />
                <x-input id="nameRole" type="text" class="mt-1 block w-full" wire:model.live="nameRole" required autocomplete="nameRole" />
                <x-input-error for="nameRole" class="mt-2" />
            </div>
            <div>
                <x-label for="descriptionRole" value="{{ __('Description') }}" />
                <x-input id="descriptionRole" type="text" class="mt-1 block w-full" wire:model.live="descriptionRole" autocomplete="descriptionRole" />
                <x-input-error for="descriptionRole" class="mt-2" />
            </div>
        </div>

        <div class="mt-12 grid grid-cols-1 lg:grid-cols-3 gap-8">
            
            <x-input-error for="permissionsSelected" class="my-2 lg:col-span-3" />

            <div class="flex flex-col gap-6">
                <h3 class="font-semibold text-lg text-white leading-tight border-b border-gray-700 pb-2">Visibilidad del Menú Lateral</h3>
                <div class="flex flex-col gap-3 mt-2">
                    @foreach ($permissions->filter(fn($permission) => Str::startsWith($permission->name, 'access_menu_')) as $permission)
                        <div class="flex justify-start items-center space-x-2 p-2 rounded-md bg-gray-800">
                            <label for="perm_menu_{{ $permission->id }}" class="flex items-center gap-2 w-full cursor-pointer">
                                <x-checkbox id="perm_menu_{{ $permission->id }}" value="{{ $permission->name }}" wire:model.live="permissionsSelected.{{ $permission->name }}" />
                                <span class="ms-2 text-sm text-gray-300">
                                    {{ Str::of($permission->name)->after('access_menu_')->replace('_', ' ')->ucfirst() }}
                                </span>
                            </label>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="flex flex-col gap-6">
                <h3 class="font-semibold text-lg text-white leading-tight border-b border-gray-700 pb-2">Acciones - Usuarios y Roles</h3>
                <div class="flex flex-col gap-3 mt-2">
                    {{-- El filtro aquí es complejo, lo dejamos tal cual --}}
                    @foreach ($permissions->filter(fn($permission) => (Str::contains($permission->name, ['user', 'users', 'usuario', 'usuarios', 'role', 'roles', 'permission', 'permissions']) || Str::startsWith($permission->name, 'user_') || Str::startsWith($permission->name, 'role_') || Str::startsWith($permission->name, 'permission_')) && !Str::startsWith($permission->name, 'access_menu_')) as $permission)
                        <div class="flex justify-start items-center space-x-2 p-2 rounded-md bg-gray-800">
                            <label for="perm_action_{{ $permission->id }}" class="flex items-center gap-2 w-full cursor-pointer">
                                <x-checkbox id="perm_action_{{ $permission->id }}" value="{{ $permission->name }}" wire:model.live="permissionsSelected.{{ $permission->name }}" />
                                <span class="ms-2 text-sm text-gray-300">{{ ucfirst(str_replace('_', ' ', $permission->name)) }}</span>
                            </label>
                        </div>
                    @endforeach
                </div>
            </div>

        
        </div>

        <div class="w-full flex justify-end mt-8">
            <x-action-message class="me-3" on="saved">
                {{ __('Saved.') }}
            </x-action-message>

            <x-button wire:loading.attr="disabled">
                {{ __('Save') }}
            </x-button>
        </div>
    </form>
</div>