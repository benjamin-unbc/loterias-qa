<div>
    <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
        <div class="px-4 py-6 sm:px-0">
            <!-- Header -->
            <div class="mb-8">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900">Cabezas del Día</h1>
                        <p class="mt-2 text-gray-600">Resultados de cabezas de loterías de todo el país</p>
                        @if($lastUpdate)
                            <p class="text-sm text-gray-500 mt-1">
                                <i class="fas fa-clock mr-1"></i>
                                Última actualización: {{ $lastUpdate }}
                            </p>
                        @endif
                        @if($this->getDayInfo())
                            <p class="text-sm text-gray-500">
                                <i class="fas fa-calendar mr-1"></i>
                                {{ $this->getDayInfo()['day_name_es'] }}, {{ $this->getDayInfo()['date'] }} - {{ $this->getDayInfo()['time'] }}
                            </p>
                        @endif
                    </div>
                    <div class="flex space-x-2">
                        <button wire:click="toggleAutoRefresh" 
                                class="inline-flex items-center px-4 py-2 border {{ $autoRefresh ? 'border-green-300 bg-green-50 text-green-700' : 'border-gray-300 bg-white text-gray-700' }} text-sm font-medium rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            <i class="fas {{ $autoRefresh ? 'fa-pause' : 'fa-play' }} mr-2"></i>
                            {{ $autoRefresh ? 'Auto-actualización ON' : 'Auto-actualización OFF' }}
                        </button>
                        <button wire:click="refreshData" 
                                class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            <i class="fas fa-sync-alt mr-2"></i>
                            Actualizar
                        </button>
                    </div>
                </div>
            </div>

            <!-- Indicador de carga -->
            @if($loading)
                <div class="bg-white shadow rounded-lg p-8 mb-8">
                    <div class="flex items-center justify-center">
                        <div class="text-center">
                            <i class="fas fa-spinner fa-spin text-4xl text-blue-500 mb-4"></i>
                            <h3 class="text-xl font-medium text-gray-900">Extrayendo datos de cabezas...</h3>
                            <p class="text-sm text-gray-600 mt-2">Procesando: {{ $url }}</p>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Error -->
            @if($error)
                <div class="bg-white shadow rounded-lg p-6 mb-8">
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
                        <div class="flex items-center">
                            <i class="fas fa-exclamation-triangle mr-2"></i>
                            <span class="font-medium">Error:</span>
                            <span class="ml-2">{{ $error }}</span>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Estado de Turnos -->
            @if($headsData && isset($headsData['turns_info']) && !$loading)
                <div class="bg-white shadow rounded-lg p-6 mb-8">
                    <h3 class="text-xl font-semibold text-gray-900 mb-6">Estado de Turnos de Lotería</h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
                        @foreach($this->getTurnsInfo() as $turnName => $turn)
                            <div class="bg-gradient-to-r {{ $turn['is_active'] ? 'from-green-50 to-green-100 border-green-200' : ($turn['is_completed'] ? 'from-blue-50 to-blue-100 border-blue-200' : 'from-gray-50 to-gray-100 border-gray-200') }} rounded-lg p-4 border">
                                <div class="text-center">
                                    <h4 class="font-semibold text-gray-900 mb-2">{{ $turn['name'] }}</h4>
                                    <p class="text-sm text-gray-600 mb-2">{{ $turn['time'] }} - {{ $turn['end_time'] }}</p>
                                    
                                    @if($turn['is_active'])
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                            <i class="fas fa-play mr-1"></i>
                                            Activo
                                        </span>
                                    @elseif($turn['is_completed'])
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                            <i class="fas fa-check mr-1"></i>
                                            Completado
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                            <i class="fas fa-clock mr-1"></i>
                                            Pendiente
                                        </span>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                    
                    @if($this->getCurrentTurn())
                        <div class="mt-6 p-4 bg-green-50 border border-green-200 rounded-lg">
                            <div class="flex items-center">
                                <i class="fas fa-info-circle text-green-500 mr-2"></i>
                                <span class="text-green-800 font-medium">
                                    Turno actual: {{ $this->getCurrentTurn()['name'] }} ({{ $this->getCurrentTurn()['time'] }} - {{ $this->getCurrentTurn()['end_time'] }})
                                </span>
                            </div>
                        </div>
                    @endif
                    
                    @if($this->getNextTurnTime())
                        <div class="mt-4 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                            <div class="flex items-center">
                                <i class="fas fa-clock text-blue-500 mr-2"></i>
                                <span class="text-blue-800">
                                    Próximo turno en: {{ $this->getNextTurnTime()['hours'] }}h {{ $this->getNextTurnTime()['minutes'] }}m
                                </span>
                            </div>
                        </div>
                    @endif
                </div>
            @endif

            <!-- Datos de Cabezas -->
            @if($headsData && isset($headsData['heads_data']) && !empty($headsData['heads_data']) && !$loading)
                <div class="space-y-6">
                    <!-- Resumen General -->
                    <div class="bg-white shadow rounded-lg p-6">
                        <h3 class="text-xl font-semibold text-gray-900 mb-4">Resumen General</h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div class="bg-blue-50 rounded-lg p-4">
                                <div class="flex items-center">
                                    <i class="fas fa-map-marker-alt text-blue-500 text-2xl mr-3"></i>
                                    <div>
                                        <p class="text-sm font-medium text-blue-700">Ciudades</p>
                                        <p class="text-2xl font-bold text-blue-900">{{ $this->getCitiesCount() }}</p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="bg-green-50 rounded-lg p-4">
                                <div class="flex items-center">
                                    <i class="fas fa-dice text-green-500 text-2xl mr-3"></i>
                                    <div>
                                        <p class="text-sm font-medium text-green-700">Total Números</p>
                                        <p class="text-2xl font-bold text-green-900">{{ $this->getTotalNumbers() }}</p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="bg-purple-50 rounded-lg p-4">
                                <div class="flex items-center">
                                    <i class="fas fa-chart-line text-purple-500 text-2xl mr-3"></i>
                                    <div>
                                        <p class="text-sm font-medium text-purple-700">Promedio por Ciudad</p>
                                        <p class="text-2xl font-bold text-purple-900">
                                            {{ $this->getCitiesCount() > 0 ? round($this->getTotalNumbers() / $this->getCitiesCount(), 1) : 0 }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                <!-- Tabla de Cabezas por Ciudad y Turnos -->
                <div class="bg-white shadow rounded-lg p-6">
                    <h3 class="text-xl font-semibold text-gray-900 mb-6">Resultados de Cabezas por Ciudad y Turno</h3>
                        
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 border border-gray-300">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider border-r border-gray-200">
                                            Ciudad
                                        </th>
                                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider border-r border-gray-200">
                                            La Previa<br><span class="text-xs text-gray-400">09:00-11:00</span>
                                        </th>
                                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider border-r border-gray-200">
                                            El Primero<br><span class="text-xs text-gray-400">11:00-13:00</span>
                                        </th>
                                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider border-r border-gray-200">
                                            Matutina<br><span class="text-xs text-gray-400">13:00-15:00</span>
                                        </th>
                                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider border-r border-gray-200">
                                            Vespertina<br><span class="text-xs text-gray-400">15:00-17:00</span>
                                        </th>
                                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Nocturna<br><span class="text-xs text-gray-400">17:00-19:00</span>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @foreach($headsData['heads_data'] as $index => $head)
                                        <tr class="{{ $index % 2 === 0 ? 'bg-white' : 'bg-gray-50' }} hover:bg-blue-50">
                                            <td class="px-4 py-4 text-sm font-medium text-gray-900 border-r border-gray-200">
                                                <div class="flex items-center">
                                                    <i class="fas fa-map-marker-alt text-blue-500 mr-2"></i>
                                                    {{ $head['city'] }}
                                                </div>
                                            </td>
                                            
                                            <!-- La Previa -->
                                            <td class="px-4 py-4 text-center border-r border-gray-200">
                                                @if(isset($head['turns']['La Previa']) && $head['turns']['La Previa'])
                                                    <span class="inline-flex items-center justify-center w-12 h-8 rounded text-xs font-bold bg-green-500 text-white">
                                                        {{ $head['turns']['La Previa'] }}
                                                    </span>
                                                @else
                                                    <span class="text-gray-400 text-xs">-</span>
                                                @endif
                                            </td>
                                            
                                            <!-- El Primero -->
                                            <td class="px-4 py-4 text-center border-r border-gray-200">
                                                @if(isset($head['turns']['El Primero']) && $head['turns']['El Primero'])
                                                    <span class="inline-flex items-center justify-center w-12 h-8 rounded text-xs font-bold bg-green-500 text-white">
                                                        {{ $head['turns']['El Primero'] }}
                                                    </span>
                                                @else
                                                    <span class="text-gray-400 text-xs">-</span>
                                                @endif
                                            </td>
                                            
                                            <!-- Matutina -->
                                            <td class="px-4 py-4 text-center border-r border-gray-200">
                                                @if(isset($head['turns']['Matutina']) && $head['turns']['Matutina'])
                                                    <span class="inline-flex items-center justify-center w-12 h-8 rounded text-xs font-bold bg-green-500 text-white">
                                                        {{ $head['turns']['Matutina'] }}
                                                    </span>
                                                @else
                                                    <span class="text-gray-400 text-xs">-</span>
                                                @endif
                                            </td>
                                            
                                            <!-- Vespertina -->
                                            <td class="px-4 py-4 text-center border-r border-gray-200">
                                                @if(isset($head['turns']['Vespertina']) && $head['turns']['Vespertina'])
                                                    <span class="inline-flex items-center justify-center w-12 h-8 rounded text-xs font-bold bg-green-500 text-white">
                                                        {{ $head['turns']['Vespertina'] }}
                                                    </span>
                                                @else
                                                    <span class="text-gray-400 text-xs">-</span>
                                                @endif
                                            </td>
                                            
                                            <!-- Nocturna -->
                                            <td class="px-4 py-4 text-center">
                                                @if(isset($head['turns']['Nocturna']) && $head['turns']['Nocturna'])
                                                    <span class="inline-flex items-center justify-center w-12 h-8 rounded text-xs font-bold bg-green-500 text-white">
                                                        {{ $head['turns']['Nocturna'] }}
                                                    </span>
                                                @else
                                                    <span class="text-gray-400 text-xs">-</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Leyenda -->
                        <div class="mt-4 flex items-center justify-center space-x-6 text-sm text-gray-600">
                            <div class="flex items-center">
                                <span class="inline-flex items-center justify-center w-6 h-6 rounded text-xs font-bold bg-green-500 text-white mr-2"></span>
                                <span>Número disponible</span>
                            </div>
                            <div class="flex items-center">
                                <span class="text-gray-400 mr-2">-</span>
                                <span>Sin resultado</span>
                            </div>
                        </div>
                    </div>

                    <!-- Vista de Tarjetas - Solo Ciudades Activas -->
                    <div class="bg-white shadow rounded-lg p-6">
                        <h3 class="text-xl font-semibold text-gray-900 mb-6">Ciudades con Resultados Activos</h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            @foreach($this->getActiveCities() as $head)
                                <div class="bg-gradient-to-r from-blue-50 to-indigo-50 rounded-lg p-6 border border-blue-200 hover:shadow-lg transition-shadow">
                                    <div class="flex items-center justify-between mb-4">
                                        <h4 class="text-lg font-semibold text-gray-900">{{ $head['city'] }}</h4>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                            <i class="fas fa-dice mr-1"></i>
                                            {{ $head['count'] }} números
                                        </span>
                                    </div>
                                    
                                    <div class="space-y-3">
                                        @foreach($head['numbers'] as $number)
                                            <div class="flex items-center justify-center">
                                                <span class="inline-flex items-center justify-center w-12 h-12 rounded-full text-lg font-bold bg-green-500 text-white shadow-lg hover:bg-green-600 transition-colors">
                                                    {{ $number }}
                                                </span>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        
                        @if(count($this->getActiveCities()) === 0)
                            <div class="text-center py-8">
                                <i class="fas fa-info-circle text-4xl text-gray-400 mb-4"></i>
                                <p class="text-gray-600">No hay ciudades con datos activos en este momento.</p>
                            </div>
                        @endif
                    </div>
                    
                    <!-- Ciudades Sin Datos -->
                    @if(count($this->getCitiesWithNoData()) > 0)
                        <div class="bg-white shadow rounded-lg p-6">
                            <h3 class="text-xl font-semibold text-gray-900 mb-6">Ciudades Sin Datos</h3>
                            
                            <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4">
                                @foreach($this->getCitiesWithNoData() as $head)
                                    <div class="bg-gray-50 rounded-lg p-4 border border-gray-200 text-center">
                                        <div class="text-sm font-medium text-gray-600 mb-2">{{ $head['city'] }}</div>
                                        <i class="fas fa-times-circle text-gray-400 text-xl"></i>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <!-- Información de la Fuente -->
                    <div class="bg-gray-50 rounded-lg p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Información de la Fuente</h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm text-gray-600">
                            <div>
                                <span class="font-medium">URL:</span>
                                <a href="{{ $headsData['url'] }}" target="_blank" class="text-blue-600 hover:text-blue-800 ml-2">
                                    {{ $headsData['url'] }}
                                    <i class="fas fa-external-link-alt ml-1"></i>
                                </a>
                            </div>
                            <div>
                                <span class="font-medium">Fuente:</span>
                                <span class="ml-2">{{ $headsData['source'] ?? 'vivitusuerte.com' }}</span>
                            </div>
                            <div>
                                <span class="font-medium">Título:</span>
                                <span class="ml-2">{{ $headsData['title'] ?? 'Cabezas del día' }}</span>
                            </div>
                            <div>
                                <span class="font-medium">Extraído:</span>
                                <span class="ml-2">{{ $headsData['extracted_at'] ?? 'N/A' }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Mensaje si no hay datos -->
            @if((!$headsData || empty($headsData['heads_data'])) && !$loading && !$error)
                <div class="bg-white shadow rounded-lg p-8">
                    <div class="text-center">
                        <i class="fas fa-info-circle text-4xl text-gray-400 mb-4"></i>
                        <h3 class="text-lg font-medium text-gray-900 mb-2">No se encontraron datos de cabezas</h3>
                        <p class="text-gray-600">Los datos de cabezas no están disponibles en este momento.</p>
                        <button wire:click="refreshData" 
                                class="mt-4 inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
                            <i class="fas fa-sync-alt mr-2"></i>
                            Intentar nuevamente
                        </button>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    let autoRefreshInterval;
    
    function startAutoRefresh() {
        if (autoRefreshInterval) {
            clearInterval(autoRefreshInterval);
        }
        
        autoRefreshInterval = setInterval(function() {
            if (@this.autoRefresh) {
                @this.call('refreshData');
            }
        }, @this.refreshInterval * 1000);
    }
    
    function stopAutoRefresh() {
        if (autoRefreshInterval) {
            clearInterval(autoRefreshInterval);
            autoRefreshInterval = null;
        }
    }
    
    // Iniciar auto-refresh si está habilitado
    if (@this.autoRefresh) {
        startAutoRefresh();
    }
    
    // Escuchar cambios en el estado de auto-refresh
    Livewire.on('autoRefreshToggled', function() {
        if (@this.autoRefresh) {
            startAutoRefresh();
        } else {
            stopAutoRefresh();
        }
    });
    
    // Limpiar interval al salir de la página
    window.addEventListener('beforeunload', function() {
        stopAutoRefresh();
    });
    
    // Actualizar cada minuto para verificar cambios de turno
    setInterval(function() {
        @this.call('refreshData');
    }, 60000); // 1 minuto
});
</script>
@endpush
