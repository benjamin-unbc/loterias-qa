<div class="container mx-auto px-4 py-8">
    <!-- Header -->
    <div class="bg-white shadow rounded-lg p-6 mb-8">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between">
            <div>
                <h1 class="text-3xl font-bold text-gray-900 mb-2">20 Números Ganadores</h1>
                <p class="text-gray-600">Resultados de loterías por ciudad y turno</p>
            </div>
            
            <div class="mt-4 md:mt-0 flex items-center space-x-4">
                <div class="text-sm text-gray-500">
                    <i class="fas fa-clock mr-1"></i>
                    Última actualización: {{ $this->lastUpdate ?: 'Nunca' }}
                </div>
                
                <div class="flex items-center space-x-2">
                    <label class="flex items-center">
                        <input type="checkbox" wire:model="autoRefresh" wire:change="toggleAutoRefresh" 
                               class="form-checkbox h-4 w-4 text-blue-600">
                        <span class="ml-2 text-sm text-gray-700">Auto-actualización</span>
                    </label>
                    
                    <button wire:click="refreshData" 
                            class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                        <i class="fas fa-sync-alt mr-1"></i>
                        Actualizar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Selector de Ciudad -->
    <div class="bg-white shadow rounded-lg p-6 mb-8">
        <h2 class="text-xl font-semibold text-gray-900 mb-4">Seleccionar Ciudad</h2>
        
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-2">
            @foreach($this->getAvailableCities() as $city)
                <button wire:click="selectCity('{{ $city }}')" 
                        class="px-4 py-2 rounded-lg border text-sm font-medium transition-colors
                               {{ $selectedCity === $city 
                                   ? 'bg-blue-500 text-white border-blue-500' 
                                   : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50' }}">
                    {{ $city }}
                </button>
            @endforeach
        </div>
        
        @if($selectedCity)
            <div class="mt-4 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="font-medium text-blue-900">{{ $this->selectedCity }}</h3>
                        <p class="text-sm text-blue-700">
                            @if($loading)
                                Cargando números ganadores...
                            @elseif($winningNumbers)
                                {{ count($winningNumbers['turns']) }} turnos disponibles
                            @else
                                Selecciona una ciudad para ver los números ganadores
                            @endif
                        </p>
                    </div>
                    
                    @if($loading)
                        <div class="animate-spin rounded-full h-6 w-6 border-b-2 border-blue-600"></div>
                    @endif
                </div>
            </div>
        @endif
    </div>

    <!-- Error Message -->
    @if($error)
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-8">
            <div class="flex items-center">
                <i class="fas fa-exclamation-triangle mr-2"></i>
                {{ $this->error }}
            </div>
        </div>
    @endif

    <!-- Números Ganadores por Turno -->
    @if($winningNumbers && !$loading)
        <div class="space-y-8">
            @foreach($winningNumbers['turns'] as $turn => $numbers)
                <div class="bg-white shadow rounded-lg p-6">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-xl font-semibold text-gray-900">
                            {{ $this->getTurnDisplayName($turn) }}
                        </h3>
                        <span class="bg-gray-100 text-gray-800 text-sm font-medium px-3 py-1 rounded-full">
                            {{ count($numbers) }} números
                        </span>
                    </div>
                    
                    @if(!empty($numbers))
                        <div class="grid grid-cols-4 md:grid-cols-5 lg:grid-cols-10 gap-3">
                            @foreach($numbers as $index => $number)
                                <div class="bg-green-100 border border-green-300 rounded-lg p-3 text-center">
                                    <div class="text-xs text-green-600 mb-1">{{ $index + 1 }}</div>
                                    <span class="text-lg font-bold text-green-800">{{ $number }}</span>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-8 text-gray-500">
                            <i class="fas fa-minus-circle text-4xl mb-4"></i>
                            <p>No hay números disponibles para este turno</p>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
        
        <!-- Información de extracción -->
        <div class="mt-8 bg-gray-50 border border-gray-200 rounded-lg p-4">
            <div class="text-sm text-gray-600 text-center">
                <p><strong>Ciudad:</strong> {{ $this->winningNumbers['city'] }}</p>
                <p><strong>Fuente:</strong> {{ $this->winningNumbers['url'] }}</p>
                <p><strong>Extraído el:</strong> {{ $this->winningNumbers['extracted_at'] }}</p>
            </div>
        </div>
    @endif
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
    
    // Esperar a que Livewire esté completamente cargado
    function initLivewireEvents() {
        if (typeof window.Livewire === 'undefined') {
            setTimeout(initLivewireEvents, 100);
            return;
        }
        
        // Escuchar cambios en el estado de auto-refresh
        Livewire.on('autoRefreshToggled', function() {
            if (@this.autoRefresh) {
                startAutoRefresh();
            } else {
                stopAutoRefresh();
            }
        });
    }
    
    // Inicializar cuando Livewire esté listo
    document.addEventListener('livewire:init', initLivewireEvents);
    setTimeout(initLivewireEvents, 500);
    
    // Actualizar cada 5 minutos para verificar cambios
    setInterval(function() {
        @this.call('refreshData');
    }, 300000); // 5 minutos
});
</script>
@endpush
