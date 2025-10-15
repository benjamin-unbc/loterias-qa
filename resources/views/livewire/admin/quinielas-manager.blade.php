<div>
    <div class="bg-[#1b1f22] w-full h-screen max-h-screen p-3 md:p-4 flex flex-col">
        <div class="flex flex-col gap-3 rounded-lg text-sm w-full max-w-6xl mx-auto">
            
            <!-- Header -->
            <div class="flex justify-between items-center gap-2 pb-2">
                <h2 class="font-bold text-xl text-white">Configuración Global de Quinielas <span class="text-yellow-400 text-sm">(Solo Administradores)</span></h2>
                <div class="flex items-center gap-2 px-3 py-1 rounded-md text-xs md:text-sm bg-gray-700 text-yellow-200">
                    <i class="fa-solid fa-user-tie"></i>
                    <span>{{ Auth::user()->id }}</span>
                </div>
            </div>

            <!-- Información -->
            <div class="w-full bg-blue-200/10 border border-blue-200/80 py-2 px-2.5 rounded-lg">
                <p class="text-white text-sm">
                    <i class="fa-solid fa-info-circle text-blue-400 mr-2"></i>
                    <strong>Configuración Global:</strong> Configura aquí qué horarios de loterías verán TODOS los usuarios en el sistema. 
                    Estos ajustes se aplicarán automáticamente en el <strong>Gestor de Jugadas</strong> y otros módulos para todos los roles.
                </p>
                <p class="text-white text-xs mt-1 text-blue-200">
                    <i class="fa-solid fa-star text-yellow-400 mr-1"></i>
                    Por defecto están seleccionadas: <strong>NAC, CHA, PRO, MZA, CTE, SFE, COR, RIO, ORO</strong> con todos sus horarios.
                </p>
            </div>

            <!-- Acceso rápido al Gestor de Jugadas -->
            <div class="w-full bg-green-200/10 border border-green-200/80 py-2 px-2.5 rounded-lg">
                <div class="flex items-center justify-between">
                    <p class="text-white text-sm">
                        <i class="fa-solid fa-play-circle text-green-400 mr-2"></i>
                        Una vez configurados los horarios globalmente, todos los usuarios podrán verlos en el <strong>Gestor de Jugadas</strong>.
                    </p>
                    <a href="{{ route('plays-manager') }}" 
                       class="bg-green-600 hover:bg-green-700 text-white px-3 py-1 rounded-lg text-sm font-medium transition-colors">
                        <i class="fa-solid fa-arrow-right mr-1"></i>
                        Ir al Gestor
                    </a>
                </div>
            </div>

            <!-- Selector de Ciudades y Horarios -->
            <div class="bg-[#2a2a2a] rounded-lg p-4 border border-gray-600">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-white text-lg font-medium">Seleccionar Horarios por Ciudad</h3>
                    <div class="flex gap-2">
                        <button wire:click="deselectAll" 
                                class="bg-red-600 hover:bg-red-700 text-white px-3 py-2 rounded-lg text-sm font-medium transition-colors">
                            <i class="fa-solid fa-times mr-2"></i>Desmarcar Todas
                        </button>
                        <button wire:click="applyDefaultConfiguration" 
                                class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-2 rounded-lg text-sm font-medium transition-colors">
                            <i class="fa-solid fa-rotate-left mr-2"></i>Configuración por Defecto
                        </button>
                        <button wire:click="saveScheduleChanges" 
                                class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors {{ $hasUnsavedChanges ? 'animate-pulse' : '' }}"
                                {{ !$hasUnsavedChanges ? 'disabled' : '' }}>
                            @if($hasUnsavedChanges)
                                <i class="fa-solid fa-save mr-2"></i>Guardar y Aplicar
                            @else
                                <i class="fa-solid fa-check mr-2"></i>Configuración Guardada
                            @endif
                        </button>
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                    @foreach($citySchedules as $cityName => $schedules)
                        <div class="bg-[#1a1a1a] rounded-lg p-3 border border-gray-500">
                            <!-- Header de la ciudad -->
                            <div class="flex items-center gap-2 mb-3">
                                <h4 class="text-white font-medium text-sm">{{ $cityName }}</h4>
                                <button wire:click="toggleCitySchedules('{{ $cityName }}')" 
                                        class="text-blue-400 hover:text-blue-300 text-xs underline">
                                    {{ count($selectedCitySchedules[$cityName] ?? []) === count($schedules) ? 'Ninguno' : 'Todos' }}
                                </button>
                            </div>
                            
                            <!-- Horarios de la ciudad -->
                            <div class="flex flex-wrap gap-1">
                                @foreach($schedules as $schedule)
                                    <label class="flex items-center gap-1 bg-[#2a2a2a] border border-gray-600 rounded px-2 py-1 cursor-pointer hover:bg-[#333333] transition-colors">
                                        <input type="checkbox" 
                                               wire:model.live="selectedCitySchedules.{{ $cityName }}" 
                                               value="{{ $schedule }}"
                                               class="w-3 h-3 bg-[#1a1a1a] border border-gray-400 rounded text-blue-400 focus:ring-blue-400">
                                        <span class="text-white text-xs">{{ $schedule }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <!-- Resumen de configuración -->
            <div class="bg-[#2a2a2a] rounded-lg p-4 border border-gray-600">
                <h3 class="text-white text-lg font-medium mb-3">Resumen de Configuración</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="bg-[#1a1a1a] rounded-lg p-3">
                        <h4 class="text-white font-medium text-sm mb-2">Horarios Seleccionados</h4>
                        <div class="text-gray-300 text-xs">
                            @php
                                $totalSelected = 0;
                                $citiesWithSelection = 0;
                            @endphp
                            @foreach($selectedCitySchedules as $cityName => $schedules)
                                @if(!empty($schedules))
                                    @php
                                        $totalSelected += count($schedules);
                                        $citiesWithSelection++;
                                    @endphp
                                    <div class="mb-1">
                                        <span class="text-blue-400">{{ $cityName }}:</span>
                                        <span class="text-white">{{ implode(', ', $schedules) }}</span>
                                    </div>
                                @endif
                            @endforeach
                            @if($totalSelected === 0)
                                <p class="text-gray-500">No hay horarios seleccionados</p>
                            @else
                                <div class="mt-2 pt-2 border-t border-gray-600">
                                    <p class="text-green-400 font-medium">
                                        Total: {{ $totalSelected }} horarios en {{ $citiesWithSelection }} ciudades
                                    </p>
                                </div>
                            @endif
                        </div>
                    </div>
                    
                    <div class="bg-[#1a1a1a] rounded-lg p-3">
                        <h4 class="text-white font-medium text-sm mb-2">Estado</h4>
                        <div class="text-gray-300 text-xs">
                            @if($hasUnsavedChanges)
                                <div class="flex items-center gap-2 text-yellow-400">
                                    <i class="fa-solid fa-exclamation-triangle"></i>
                                    <span>Hay cambios sin guardar</span>
                                </div>
                            @else
                                <div class="flex items-center gap-2 text-green-400">
                                    <i class="fa-solid fa-check-circle"></i>
                                    <span>Configuración guardada</span>
                                </div>
                            @endif
                            
                            <div class="mt-3 pt-2 border-t border-gray-600">
                                <p class="text-gray-400 text-xs">
                                    <i class="fa-solid fa-info-circle mr-1"></i>
                                    Los cambios se aplicarán automáticamente en el Gestor de Jugadas
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>
