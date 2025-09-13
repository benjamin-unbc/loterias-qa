<div id="paginated-roles">
    <div class="bg-[#1b1f22] w-full h-full min-h-screen p-4 flex flex-col gap-3">
        <div class="flex justify-between items-center pb-2">
            <div class="flex flex-col">
                <h2 class="font-semibold text-xl text-white">{{ __('Lista Roles') }}</h2>
                <p class="text-gray-500">Aca podras crear, editar o eliminar <span class="text-white">Roles</span>.</p>
            </div>
            @can(['crear roles'])
            <div class="flex justify-end">
                <a href="{{ route('users.roles.store') }}"
                    class="bg-yellow-200 text-gray-500 hover:text-gray-700 duration-75 rounded-full text-sm px-3 pe-4 py-1 ">+
                    Crear Rol</a>
            </div>
            @endcan
        </div>
        <div class="pb-2 flex items-center justify-between gap-3">
            <div class="w-full">
                <label for="default-search" class="mb-2 text-sm font-medium text-white sr-only">Search</label>
                <div class="relative">
                    <div class="absolute inset-y-0 start-0 flex items-center ps-3    pointer-events-none">
                        <svg class="w-4 h-4 text-gray-500 dark:text-gray-400" aria-hidden="true"
                            xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 20 20">
                            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="m19 19-4-4m0-7A7 7 0 1 1 1 8a7 7 0 0 1 14 0Z" />
                        </svg>
                    </div>
                    <input type="search" id="default-search" wire:model.live="search"
                        class="block w-full pt-2 px-6 ps-10 text-sm text-white bg-[#22272b] border border-gray-600 rounded-lg focus:ring-yellow-200 focus:border-yellow-200 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-green-500 dark:focus:border-green-500"
                        placeholder="Buscar rol por nombre o descripción." />
                </div>
            </div>
            <div class="flex items-center gap-3">
                <span class="text-sm text-white">Mostrar</span>
                <select wire:model.live="cant"
                    class="border-gray-600 bg-[#22272b]  border text-white text-sm rounded-lg focus:ring-yellow-200 focus:border-yellow-200 block w-16 p-2">
                    <option value="5">5</option>
                    <option value="10">10</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                </select>
            </div>
        </div>
        <div class="relative overflow-x-auto rounded-lg">
            <table class="w-full text-sm text-left rtl:text-right text-gray-500 dark:text-gray-400">
                <thead class="text-xs text-white uppercase bg-gray-600">
                    <tr>
                        <th scope="col" class="px-6 py-3">
                            Nombre
                        </th>
                        <th scope="col" class="px-6 py-3">
                            Descripción
                        </th>
                        @can(['ver roles'])
                        <th scope="col" class="px-6 py-3">
                            Acciones
                        </th>
                        @endcan
                    </tr>
                </thead>
                <tbody>
                    @foreach ($roles as $role)
                    <tr class="bg-[#22272b] border-b border-gray-600">
                        <th scope="row" class="px-6 py-4 font-medium text-white whitespace-nowrap dark:text-white">
                            {{ $role->name }}
                        </th>
                        <td class="px-6 py-4">
                            {{ $role->description }}
                        </td>
                        <td class="px-6 py-4 text-left flex space-x-2 text-lg">
                            @can('ver roles')
                            <a href="#" wire:click.prevent="showModalRoleDetail({{ $role->id }})"
                                class="font-medium text-yellow-200 hover:underline"><i
                                    class="fa-solid fa-eye"></i></a>
                            @endcan
                            @can('editar roles')
                            <a href="{{ route('users.roles.store', $role->id) }}"
                                class="font-medium text-white hover:underline"><i
                                    class="fa-solid fa-pen-to-square"></i></a>
                            @endcan
                            @can('eliminar roles')
                            @role($role->name)
                            <small
                                class="px-2 w-14 flex justify-center items-center rounded-full bg-yellow-200 text-xs text-gray-600">En
                                uso</small>
                            @else
                            <a href="#" wire:click.prevent="confirmRoleDeletion('{{ (int) $role->id }}')"
                                class="font-medium text-white  hover:underline">
                                <i class="fa-solid fa-trash"></i>
                            </a>
                            @endrole
                            @endcan
                        </td>
                    </tr>
                    @endforeach
                    @if($showConfirmationModal)
                    <x-confirmation-modal wire:model="showConfirmationModal">
                        <x-slot name="title">
                            Eliminar Rol
                        </x-slot>
                        <x-slot name="content">
                            ¿Estás seguro de que quieres eliminar este rol? Esta acción no se puede deshacer.
                        </x-slot>
                        <x-slot name="footer">
                            <button wire:click="deleteRole"
                                class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 focus:outline-none sm:ml-3 sm:w-auto sm:text-sm">Eliminar
                            </button>
                            <button wire:click="$set('showConfirmationModal', false)"
                                class="mt-3 w-full inline-flex justify-center rounded-md border border-blue-300 shadow-sm px-4 py-2 bg-blue-50 text-base font-medium text-blue-700 hover:bg-blue-100 focus:outline-none sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                                Cancelar
                            </button>
                        </x-slot>
                    </x-confirmation-modal>
                    @endif
                </tbody>
            </table>
            @if ($roles->hasPages())
            <div class="pt-4">
                {{ $roles->links(data: ['scrollTo' => '#paginated-roles']) }}
            </div>
            @endif
        </div>
    </div>

    @if ($visualizeRoleModal)
    <x-dialog-modal>
        <x-slot name="title">
            <h3 class="text-white font-semibold">
                {{ __('Visualización Rol') }}
            </h3>
        </x-slot>

        <x-slot name="content">
            <div class="grid grid-cols-2 text-white">
                <div>
                    <span class="font-bold">Nombre:</span> {{ $dataRole->name }}
                </div>
                <div>
                    <span class="font-bold">Descripción:</span> {{ $dataRole->description }}
                </div>
            </div>
            <div class="mt-6 grid grid-cols-1 lg:grid-cols-3 gap-8">
                <div class="flex flex-col gap-6">
                    <h3 class="font-semibold text-lg text-white leading-tight border-b border-gray-700 pb-2">Visibilidad del Menú Lateral</h3>
                    <div class="flex flex-col gap-3 mt-2">
                        @foreach ($permissionsList->filter(fn($permission) => Str::startsWith($permission->name, 'access_menu_')) as $permission)
                            <div class="flex justify-start items-center space-x-2">
                                <i class="fa-solid fa-circle-check text-{{ $dataRole->hasPermissionTo($permission->name) ? 'green-400' : 'red-400' }}"></i>
                                <span class="text-sm text-white">
                                    {{ Str::of($permission->name)->after('access_menu_')->replace('_', ' ')->ucfirst() }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                </div>
    
                <div class="flex flex-col gap-6">
                    <h3 class="font-semibold text-lg text-white leading-tight border-b border-gray-700 pb-2">Acciones - Usuarios y Roles</h3>
                    <div class="flex flex-col gap-3 mt-2">
                        @foreach ($permissionsList->filter(fn($permission) => (Str::contains($permission->name, ['user', 'users', 'usuario', 'usuarios', 'role', 'roles', 'permission', 'permissions']) || Str::startsWith($permission->name, 'user_') || Str::startsWith($permission->name, 'role_') || Str::startsWith($permission->name, 'permission_')) && !Str::startsWith($permission->name, 'access_menu_')) as $permission)
                            <div class="flex justify-start items-center space-x-2">
                                <i class="fa-solid fa-circle-check text-{{ $dataRole->hasPermissionTo($permission->name) ? 'green-400' : 'red-400' }}"></i>
                                <span class="text-sm text-white">{{ ucfirst(str_replace('_', ' ', $permission->name)) }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
    
     
            </div>
        </x-slot>

        <x-slot name="footer">
            <button wire:click="closeModal()" class="bg-white text-black px-3 py-2 rounded-full text-sm">Cerrar</button>
        </x-slot>
    </x-dialog-modal>
    @endif
</div>
