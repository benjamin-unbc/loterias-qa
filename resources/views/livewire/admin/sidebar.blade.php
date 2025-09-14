<aside id="logo-sidebar"
    class="fixed top-0 left-0 z-50 w-64 h-screen pt-3 transition-transform -translate-x-full bg-[#22272b] border-r border-gray-600 sm:translate-x-0 flex flex-col"
    aria-label="Sidebar">

    <!-- Contenedor principal con scroll -->
    <div class="flex-1 px-3 pb-4 overflow-y-auto bg-[#22272b] dark:bg-gray-800">
        <div class="flex items-center mb-4 justify-center pb-3 border-b border-gray-600">
            <a href="{{ route('plays-manager') }}">
                <img src="{{ asset('assets/images/logo.png') }}" class="h-20 rounded-lg" alt="Loterias Logo" />
            </a>
        </div>
        <ul class="space-y-1 font-medium text-[15px]">
            @can('access_menu_gestor_de_jugadas')
            <li>
                <a href="{{ route('plays-manager') }}"
                    class="flex items-center p-2 text-white rounded-lg dark:text-white {{ request()->routeIs('plays-manager') ? 'bg-yellow-200' : 'hover:bg-gray-200/20 group' }}">
                    <i
                        class="fas fa-play-circle w-5 h-5 {{ request()->routeIs('plays-manager') ? 'text-gray-600 font-extrabold' : 'text-gray-300 transition duration-75' }}"></i>
                    <span
                        class="ms-3 {{ request()->routeIs('plays-manager') ? 'text-gray-600 font-extrabold' : '' }}">Gestor
                        de Jugadas</span>
                </a>
            </li>
            @endcan
            @can('access_menu_jugadas_enviadas')
            <li>
                <a href="{{ route('plays-sent') }}"
                    class="flex items-center p-2 text-white rounded-lg dark:text-white {{ request()->routeIs('plays-sent') ? 'bg-yellow-200' : 'hover:bg-gray-200/20 group' }}">
                    <i
                        class="fas fa-paper-plane w-5 h-5 {{ request()->routeIs('plays-sent') ? 'text-gray-600 font-extrabold' : 'text-gray-300 transition duration-75' }}"></i>
                    <span
                        class="ms-3 {{ request()->routeIs('plays-sent') ? 'text-gray-600 font-extrabold' : '' }}">Jugadas
                        enviadas</span>
                </a>
            </li>
            @endcan
            @can('access_menu_resultados')
            <li>
                <a href="{{ route('results') }}"
                    class="flex items-center p-2 text-white rounded-lg dark:text-white {{ request()->routeIs('results') ? 'bg-yellow-200' : 'hover:bg-gray-200/20 group' }}">
                    <i
                        class="fas fa-chart-bar w-5 h-5 {{ request()->routeIs('results') ? 'text-gray-600 font-extrabold' : 'text-gray-300 transition duration-75' }}"></i>
                    <span
                        class="ms-3 {{ request()->routeIs('results') ? 'text-gray-600 font-extrabold' : '' }}">Resultados</span>
                </a>
            </li>
            @endcan
            @can('access_menu_liquidaciones')
            <li>
                <a href="{{ route('liquidations') }}"
                    class="flex items-center p-2 text-white rounded-lg dark:text-white {{ request()->routeIs('liquidations') ? 'bg-yellow-200' : 'hover:bg-gray-200/20 group' }}">
                    <i
                        class="fas fa-file-invoice w-5 h-5 {{ request()->routeIs('liquidations') ? 'text-gray-600 font-extrabold' : 'text-gray-300 transition duration-75' }}"></i>
                    <span
                        class="ms-3 {{ request()->routeIs('liquidations') ? 'text-gray-600 font-extrabold' : '' }}">Liquidaciones</span>
                </a>
            </li>
            @endcan
            @can('access_menu_tabla_pagos')
            <li>
                <a href="{{ route('payment-table') }}"
                    class="flex items-center p-2 text-white rounded-lg dark:text-white {{ request()->routeIs('payment-table') ? 'bg-yellow-200' : 'hover:bg-gray-200/20 group' }}">
                    <i
                        class="fas fa-table w-5 h-5 {{ request()->routeIs('payment-table') ? 'text-gray-600 font-extrabold' : 'text-gray-300 transition duration-75' }}"></i>
                    <span
                        class="ms-3 {{ request()->routeIs('payment-table') ? 'text-gray-600 font-extrabold' : '' }}">Tabla
                        de pagos</span>
                </a>
            </li>
            @endcan
            @can('access_menu_extractos')
            <li>
                <a href="{{ route('extracts') }}"
                    class="flex items-center p-2 text-white rounded-lg dark:text-white {{ request()->routeIs('extracts') ? 'bg-yellow-200' : 'hover:bg-gray-200/20 group' }}">
                    <i
                        class="fas fa-file-alt w-5 h-5 {{ request()->routeIs('extracts') ? 'text-gray-600 font-extrabold' : 'text-gray-300 transition duration-75' }}"></i>
                    <span
                        class="ms-3 {{ request()->routeIs('extracts') ? 'text-gray-600 font-extrabold' : '' }}">Extractos</span>
                </a>
            </li>
            @endcan
            @can('access_menu_asistencia_remota')
            <li>
                <a href="{{ route('remote-assistance') }}"
                    class="flex items-center p-2 text-white rounded-lg dark:text-white {{ request()->routeIs('remote-assistance') ? 'bg-yellow-200' : 'hover:bg-gray-200/20 group' }}">
                    <i
                        class="fas fa-plug w-5 h-5 {{ request()->routeIs('remote-assistance') ? 'text-gray-600 font-extrabold' : 'text-gray-300 transition duration-75' }}"></i>
                    <span
                        class="ms-3 {{ request()->routeIs('remote-assistance') ? 'text-gray-600 font-extrabold' : '' }}">Asistencia
                        remota</span>
                </a>
            </li>
            @endcan
            @canany(['access_menu_usuarios_y_roles', 'crear roles', 'editar roles', 'ver roles', 'eliminar roles', 'crear usuarios', 'editar usuarios', 'ver usuarios', 'eliminar usuarios'])
            <li>
                <button type="button"
                    class="flex items-center w-full p-2 text-[15px] {{ request()->is('module-users/*') ? 'bg-yellow-200' : 'hover:bg-gray-200/20 group' }} text-white transition duration-75 rounded-lg group  dark:text-white "
                    aria-controls="module-users" data-collapse-toggle="module-users">
                    <i
                        class="fas fa-users w-5 h-5 {{ request()->is('module-users/*') ? 'text-gray-600 font-extrabold' : 'text-white transition duration-75' }}"></i>
                    <span
                        class="flex-1 ms-3 text-left rtl:text-right whitespace-nowrap {{ request()->is('module-users/*') ? 'text-gray-600 font-extrabold' : '' }}">Módulo
                        Usuarios</span>
                    <svg class="w-3 h-3" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none"
                        viewBox="0 0 10 6">
                        <path stroke=" {{ request()->is('module-users/*') ? '#333' : 'currentColor' }}" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="m1 1 4 4 4-4" />
                    </svg>
                </button>
                <ul id="module-users" class="{{ request()->is('module-users/*') ? '' : 'hidden' }} pt-2 space-y-1 text-[15px]">
                    @canany(['ver roles', 'crear roles', 'editar roles', 'eliminar roles'])
                    <li>
                        <a href="{{ route('users.roles.show') }}"
                            class="flex items-center w-full p-2
                        {{ (request()->routeIs('users.roles.show') or request()->is('module-users/roles/store*')) ? 'text-yellow-300 font-extrabold' : 'text-gray-400 hover:text-white' }} transition duration-75 rounded-lg pl-11 group hover:bg-gray-200/20">
                            <i class="fas fa-user-shield w-5 h-5 mr-3"></i> Roles
                        </a>
                    </li>
                    @endcanany
                    @canany(['ver usuarios', 'crear usuarios', 'editar usuarios', 'eliminar usuarios'])
                    <li>
                        <a href="{{ route('users.show') }}"
                            class="flex items-center w-full p-2
                        {{ (request()->routeIs('users.show') or request()->is('module-users/store*')) ? 'text-yellow-300 font-extrabold' : 'text-gray-400 hover:text-white' }} transition duration-75 rounded-lg pl-11 group hover:bg-gray-200/20">
                            <i class="fas fa-users w-5 h-5 mr-3"></i> Usuarios
                        </a>
                    </li>
                    @endcanany
                   
                </ul>
            </li>
            @endcan
        </ul>
    </div>

    <!-- Componente admin.navbar alineado al fondo -->
    <div class="pb-3">
        @livewire('admin.navbar')
    </div>
</aside>
