<div id="paginated-users">
    <div class="bg-[#1b1f22] w-full h-full min-h-screen p-4 flex flex-col gap-3">
        <div class="flex justify-between items-center pb-2">
            <div class="flex flex-col">
                <h2 class="font-semibold text-xl text-white">{{ __('Lista Usuarios') }}</h2>
                <p class="text-gray-500">Aca podras crear, editar o eliminar <span class="text-white">Usuarios</span>.</p>
            </div>
            @can(['crear usuarios'])
            <div class="flex justify-end">
                <a href="{{ route('users.store') }}"
                    class="bg-yellow-200 text-gray-500 hover:text-gray-700 duration-75 rounded-full text-sm px-3 pe-4 py-1 ">+
                    Crear usuario</a>
            </div>
            @endcan
        </div>
        <div class="pb-2 flex items-center space-x-2">
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
                        placeholder="Buscar por nombre, apellido, RUT, email, rol o especificar el estado (activo/inactivo)." />
                </div>
            </div>
            <div class="flex items-center space-x-4">
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
        <div class="relative overflow-x-auto">
            <table class="w-full text-sm text-left rtl:text-right text-gray-500 dark:text-gray-400">
                <thead class="text-xs text-white uppercase bg-gray-600">
                    <tr>
                        <th scope="col" class="px-6 py-3">
                            Nombres
                        </th>
                        <th scope="col" class="px-6 py-3">
                            Apellidos
                        </th>
                        <!-- <th scope="col" class="px-6 py-3">
                            RUT
                        </th> -->
                        <th scope="col" class="px-6 py-3">
                            Email
                        </th>
                        <th scope="col" class="px-6 py-3">
                            Teléfono
                        </th>
                        <th scope="col" class="px-6 py-3">
                            Rol
                        </th>
                        <th scope="col" class="px-6 py-3">
                            Estado
                        </th>
                        @can(['editar usuarios', 'eliminar usuarios'])
                        <th scope="col" class="px-6 py-3">
                            Acciones
                        </th>
                        @endcan
                    </tr>
                </thead>
                <tbody>
                    @foreach ($users as $user)
                    <tr class="border-gray-600 bg-[#22272b]  border-b text-white">
                        <td class="px-6 py-4">
                            {{ $user->first_name }}
                        </td>
                        <td class="px-6 py-4">
                            {{ $user->last_name }}
                        </td>
                        <!-- <td class="px-6 py-4 truncate">
                            {{ $user->rut }}
                        </td> -->
                        <td class="px-6 py-4">
                            {{ $user->email }}
                        </td>
                        <td class="px-6 py-4">
                            {{ $user->phone }}
                        </td>
                        <td class="px-6 py-4">
                            @foreach ($user->getRoleNames() as $nameRol)
                            {{ $nameRol }}
                            @endforeach
                        </td>
                        <td class="px-6 py-4">
                            <span
                                class="inline-flex items-center {{ $user->is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }} text-xs font-medium px-2.5 py-0.5 rounded-full ">
                                <span
                                    class="w-2 h-2 me-1 {{ $user->is_active ? 'bg-green-500' : 'bg-red-500' }}  rounded-full"></span>
                                {{ $user->is_active ? 'Activo' : 'Inactivo' }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-left flex space-x-2 text-lg">
                            @can('editar usuarios')
                            <a href="{{ route('users.store', $user->id) }}"
                                class="font-medium text-white hover:underline"><i
                                    class="fa-solid fa-pen-to-square"></i></a>
                            @endcan
                            @can('eliminar usuarios')
                            @if ($user->id === auth()->user()->id)
                            <small
                                class="px-2 w-14 flex justify-center items-center rounded-full bg-yellow-200 text-xs text-gray-600">En
                                uso</small>
                            @else
                            <a href="#" wire:click.prevent="confirmUserDeletion('{{ (int) $user->id }}')"
                                class="font-medium text-white  hover:underline">
                                <i class="fa-solid fa-trash"></i>
                            </a>
                            @endif
                            @endcan
                        </td>
                    </tr>
                    @endforeach
                    @if($showConfirmationModal)
                    <x-confirmation-modal wire:model="showConfirmationModal" overlayClasses="bg-gray-500 bg-opacity-25">
                        <x-slot name="title">
                            Eliminar Usuario
                        </x-slot>
                        <x-slot name="content">
                            ¿Estás seguro que deseas eliminar este usuario? Esta acción no se puede deshacer.
                        </x-slot>
                        <x-slot name="footer">
                            <button wire:click="deleteUser"
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
            @if ($users->hasPages())
            <div class="pt-4 text-white">
                {{ $users->links(data: ['scrollTo' => '#paginated-users']) }}
            </div>
            @endif
        </div>
    </div>
</div>
