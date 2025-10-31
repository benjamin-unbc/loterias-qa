@can('access_menu_extractos')
    <div class="bg-[#1b1f22] w-full h-full min-h-screen p-4 flex flex-col gap-3">
        <div class="flex flex-col gap-5">
            <!-- Encabezado principal -->
            <div class="flex justify-between gap-1">
                <h2 class="font-semibold text-2xl text-white">Extractos</h2>
                @if($extracts->first() && $extracts->first()->name === 'PREVIA')
                <button wire:click="toggleExtractView"
                    class="w-fit text-sm px-3 py-1 bg-yellow-200 text-gray-600 rounded-md flex items-center gap-2 text-nowrap hover:bg-yellow-200/90 duration-200">
                    <i class="fa-solid fa-wand-magic-sparkles"></i>
                    @if($showFullExtract)
                    Ver solo cabeza
                    @else
                    Ver extracto completo
                    @endif
                </button>
                @endif
            </div>

            <!-- Filtros para administradores -->
            @if($isAdmin && $showFilters)
            <div class="bg-[#1b1f22] p-4 rounded-lg mb-6">
                <h3 class="text-white text-lg font-semibold mb-4 flex items-center gap-2">
                    <i class="fa-solid fa-filter text-blue-400"></i>
                    Filtros
                </h3>
                
                <!-- Filtro Jerárquico por Ciudad -->
                <div class="mb-6 p-4 bg-[#2d3339] rounded-lg">
                    <h4 class="text-white text-md font-medium mb-3 flex items-center gap-2">
                        <i class="fa-solid fa-city text-green-400"></i>
                        Filtro Granular por Loterías
                    </h4>
                    <p class="text-xs text-gray-400 mb-3">
                        Selecciona una ciudad para controlar qué loterías específicas mostrar. Todas las ciudades permanecen visibles.
                    </p>
                    
                    <!-- Selector de Ciudad -->
                    <div class="mb-4">
                        <div class="flex items-center gap-3 mb-2">
                            <label class="text-gray-300 text-sm font-medium">Seleccionar Ciudad:</label>
                            @if($selectedCityFilter)
                            <button wire:click="$set('selectedCityFilter', null)" 
                                    class="text-xs bg-red-600 hover:bg-red-700 text-white px-2 py-1 rounded transition-colors">
                                <i class="fa-solid fa-times mr-1"></i>
                                Limpiar
                            </button>
                            @endif
                        </div>
                        <select wire:model.live="selectedCityFilter" 
                                class="w-full md:w-64 bg-[#1b1f22] border border-gray-600 text-white rounded-md px-3 py-2 focus:border-blue-400 focus:ring-2 focus:ring-blue-400">
                            <option value="">Mostrar todas las ciudades...</option>
                            @foreach($availableCityOptions as $cityName)
                            <option value="{{ $cityName }}">{{ $cityName }}</option>
                            @endforeach
                        </select>
                        @if($selectedCityFilter)
                        <p class="text-xs text-green-400 mt-1">
                            <i class="fa-solid fa-filter mr-1"></i>
                            Filtrando por: <strong>{{ $selectedCityFilter }}</strong>
                        </p>
                        @endif
                        
                        @if($hasUnsavedChanges)
                        <p class="text-xs text-yellow-400 mt-1">
                            <i class="fa-solid fa-exclamation-triangle mr-1"></i>
                            Tienes cambios sin guardar
                        </p>
                        @endif
                    </div>
                    
                    <!-- Loterías de la Ciudad Seleccionada -->
                    @if($selectedCityFilter && !empty($cityLotteries))
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <label class="text-gray-300 text-sm font-medium">
                                Loterías de {{ $selectedCityFilter }}:
                            </label>
                            <span class="text-xs text-gray-400">
                                {{ count(array_intersect(collect($cityLotteries)->pluck('id')->toArray(), $selectedAllLotteries)) }}/{{ count($cityLotteries) }} seleccionadas
                            </span>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                            @foreach($cityLotteries as $lottery)
                            <label class="flex items-center gap-3 text-sm text-gray-300 cursor-pointer hover:text-white transition-colors p-3 bg-[#1b1f22] rounded border border-gray-700 hover:border-blue-400 {{ in_array($lottery['id'], $selectedAllLotteries) ? 'border-blue-400 bg-blue-900/20' : '' }}">
                                <input type="checkbox" 
                                       wire:click="toggleAllLotteryFilter({{ $lottery['id'] }})"
                                       {{ in_array($lottery['id'], $selectedAllLotteries) ? 'checked' : '' }}
                                       class="w-4 h-4 rounded border-gray-600 bg-[#1b1f22] text-blue-400 focus:ring-blue-400 focus:ring-2">
                                <div class="flex flex-col">
                                    <span class="font-medium">{{ $lottery['extract_name'] }}</span>
                                    <span class="text-xs text-gray-400">{{ $lottery['code'] }} - {{ $lottery['time'] }}</span>
                                </div>
                            </label>
                            @endforeach
                        </div>
                        <p class="text-xs text-gray-500 mt-2">
                            <i class="fa-solid fa-info-circle mr-1"></i>
                            Las loterías desmarcadas se ocultarán de la vista principal, pero el filtro afecta a todas las ciudades
                        </p>
                        
                        <!-- Botón Guardar -->
                        <div class="flex justify-end mt-4">
                            <button wire:click="saveFilters" 
                                    class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-sm transition-colors duration-200 flex items-center gap-2 {{ $hasUnsavedChanges ? 'animate-pulse' : '' }}">
                                <i class="fa-solid fa-save"></i>
                                Guardar Filtros
                                @if($hasUnsavedChanges)
                                <span class="text-xs bg-yellow-400 text-black px-1 rounded">!</span>
                                @endif
                            </button>
                        </div>
                    </div>
                    @elseif($selectedCityFilter)
                    <div class="text-gray-400 text-sm italic p-3 bg-[#1b1f22] rounded border border-gray-700">
                        <i class="fa-solid fa-exclamation-triangle mr-2"></i>
                        No hay loterías disponibles para {{ $selectedCityFilter }}
                    </div>
                    @endif
                </div>
                
                <!-- Filtros Originales (Ocultos por defecto) -->
                <div class="mb-4">
                    <button onclick="toggleOriginalFilters()" 
                            class="text-gray-400 hover:text-white text-sm flex items-center gap-2 mb-3">
                        <i class="fa-solid fa-chevron-down" id="originalFiltersIcon"></i>
                        Filtros Avanzados (Originales)
                    </button>
                    
                    <div id="originalFilters" class="hidden">
                        <!-- Filtros de Ciudades -->
                        <div class="mb-4">
                            <h4 class="text-gray-300 text-sm font-medium mb-2">Ciudades:</h4>
                            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-2">
                                @foreach($availableCities as $city)
                                <label class="flex items-center gap-2 text-sm text-gray-300 cursor-pointer hover:text-white transition-colors">
                                    <input type="checkbox" 
                                           wire:click="toggleCityFilter({{ $city['id'] }})"
                                           {{ in_array($city['id'], $selectedCities) ? 'checked' : '' }}
                                           class="w-4 h-4 rounded border-gray-600 bg-[#1b1f22] text-blue-400 focus:ring-blue-400 focus:ring-2">
                                    <span>{{ $city['name'] }}</span>
                                </label>
                                @endforeach
                            </div>
                        </div>
                        
                        <!-- Filtros de Horarios -->
                        <div class="mb-4">
                            <h4 class="text-gray-300 text-sm font-medium mb-2">Horarios:</h4>
                            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-2">
                                @foreach($availableExtracts as $extract)
                                <label class="flex items-center gap-2 text-sm text-gray-300 cursor-pointer hover:text-white transition-colors">
                                    <input type="checkbox" 
                                           wire:click="toggleExtractFilter({{ $extract['id'] }})"
                                           {{ in_array($extract['id'], $selectedExtracts) ? 'checked' : '' }}
                                           class="w-4 h-4 rounded border-gray-600 bg-[#1b1f22] text-blue-400 focus:ring-blue-400 focus:ring-2">
                                    <span>{{ $extract['name'] }}</span>
                                </label>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Botón para resetear filtros -->
                <div class="flex justify-end">
                    <button wire:click="resetFilters" 
                            class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg text-sm transition-colors duration-200">
                        <i class="fa-solid fa-undo mr-2"></i>
                        Resetear Filtros
                    </button>
                </div>
            </div>
            @endif

            <!-- Filtros y botones -->
            <div class="w-full flex items-end gap-3">
                <div wire:keydown.enter="searchDate" class="w-full flex justify-between gap-2 text-sm">
                    <div class="flex flex-col gap-2 w-full">
                        <label for="dateResult" class="text-white">Buscar por fecha:</label>
                        <input type="date" id="dateResult" placeholder="Selecciona una fecha"
                            class="w-full border text-sm bg-[#22272b] border-gray-600 text-white focus:border-yellow-200 rounded-md p-2 py-1"
                            wire:model.defer="filterDate">
                    </div>

                    <div class="flex items-end gap-2">
                        <button wire:click="searchDate" wire:loading.attr="disabled"
                            class="text-sm px-3 py-1 border border-blue-500 bg-blue-500 text-white rounded-md flex items-center gap-2 hover:border-blue-600/90 hover:bg-blue-600/90 duration-200"
                            wire:loading.class="opacity-50"
                            title="Cambiar fecha para ver datos de esa fecha">
                            <i class="fa-solid fa-search" wire:loading.remove></i>
                            <i class="fa-solid fa-sync-alt animate-spin" wire:loading></i>
                            <span wire:loading.remove>Buscar</span>
                            <span wire:loading>Cargando...</span>
                        </button>
                        <button wire:click="resetFilters"
                            class="bg-[#22272b] w-fit border border-green-300 text-sm px-5 py-1 rounded-md text-green-400 hover:bg-green-100/20 duration-200">
                            Reiniciar
                        </button>
                        <!-- Botón de prueba para llenar los campos -->
                        {{-- <button onclick="fillTestNumbers()"
                            class="text-sm px-3 py-1 border border-purple-500 bg-purple-500 text-white rounded-md flex items-center gap-2 hover:border-purple-600/90 hover:bg-purple-600/90 duration-200">
                            Llenar
                        </button> --}}
                    </div>
                </div>
            </div>

            <div class="flex flex-col gap-10">
                @foreach ($this->extracts as $extract)
                <div class="flex flex-col gap-4">
                    <!-- Cabecera del bloque (título + botones) -->
                    <div class="flex items-center justify-between gap-3 border-b border-gray-600 pb-2">
                        <div class="flex flex-col gap-1">
                            <h2 class="font-semibold text-xl text-white">{{ $extract->name }}</h2>
                        </div>

                        <div class="flex items-center justify-between gap-10">
                            <!-- Sólo si es el primer bloque (PREVIA) mostramos los 3 botones -->
                            @if ($extract->id === 1)
                            @can('extracts_print') {{-- Assuming 'extracts_print' for printing --}}
                            <div class="flex items-center gap-2">
                                <button wire:click="showModal('imprimir')"
                                    class="text-sm px-3 py-1 bg-blue-500 text-white rounded-md flex items-center gap-2 hover:bg-blue-600/90 duration-200">
                                    <i class="fas fa-print"></i> Imprimir
                                </button>
                                <!-- Otros botones (guardar, compartir) se pueden habilitar si se desean -->
                            </div>
                            @endcan
                            @endif
                        </div>
                    </div>

                    <!-- Ciudades asociadas a este extracto -->
                    <div class="flex justify-between gap-3 overflow-x-auto w-full pb-2">
                        @foreach ($cities->where('extract_id', $extract->id) as $city)
                            @if($this->isCityAndScheduleConfiguredInQuinielas($city->name, $extract->name))
                        <div class="bg-[#292f34] p-3 rounded-lg flex flex-col gap-3">
                            <p class="text-white font-medium flex flex-col text-center">
                                {{ $city->name }}
                                <span class="font-extrabold text-green-300">{{ $city->code }}</span>
                            </p>
                            @php
                            // Usamos la fecha del filtro o, si no se selecciona, la de hoy (donde se muestran los números actualizados)
                            $dateToUse = $filterDate ?? \Carbon\Carbon::today()->toDateString();
                            
                            // Siempre mostrar los números automáticos, tanto para "solo cabeza" como "extracto completo"
                            $todayNumbers = $city->numbers->where('date', $dateToUse)->keyBy('index');
                            @endphp
                            <!-- Se muestran 20 campos (índices del 1 al 20) en dos columnas -->
                            <div class="min-w-[260px] grid grid-cols-{{ $showFullExtract ? '2' : '1' }} gap-2">
                                @for ($i = 1; $i <= 10; $i++)
                                    @if($showFullExtract || $i==1)
                                    <!-- Primera columna -->
                                    <div class="flex items-center gap-2">
                                        <span class="w-5 text-white">{{ $i }}</span>
                                        @if(isset($todayNumbers[$i]))
                                            @can('extracts_edit') {{-- Only show input if user can edit --}}
                                            <input type="text" placeholder="Valor de hoy"
                                                class="w-full border bg-[#22272b] border-gray-600 text-white text-center rounded-lg px-2 py-1 text-sm"
                                                value="{{ $todayNumbers[$i]->value }}"
                                                wire:blur="updateNumber({{ $todayNumbers[$i]->id }}, $event.target.value)"
                                                wire:keydown.enter.prevent="updateNumber({{ $todayNumbers[$i]->id }}, $event.target.value)"
                                                maxlength="4" pattern="\d{4}" inputmode="numeric">
                                            @else {{-- Show as text if user cannot edit --}}
                                            <h2 class="font-bold text-white bg-[#22272b] py-1 px-3 rounded-lg w-full text-sm text-center">
                                                {{ $todayNumbers[$i]->value }}
                                            </h2>
                                            @endcan
                                        @else
                                            @can('extracts_create') {{-- Only show input if user can create (new number) --}}
                                            <input type="text" placeholder="Valor de hoy"
                                                class="w-full border bg-[#22272b] border-gray-600 text-white text-center rounded-lg px-2 py-1 text-sm"
                                                wire:blur="storeNumber({{ $city->id }}, {{ $extract->id }}, {{ $i }}, $event.target.value)"
                                                wire:keydown.enter.prevent="storeNumber({{ $city->id }}, {{ $extract->id }}, {{ $i }}, $event.target.value)"
                                                maxlength="4" pattern="\d{4}" inputmode="numeric">
                                            @else {{-- Show as text if user cannot create --}}
                                            <h2 class="font-bold text-white bg-[#22272b] py-1 px-3 rounded-lg w-full text-sm text-center">
                                                Pendiente
                                            </h2>
                                            @endcan
                                        @endif
                                    </div>

                                    @if($showFullExtract)
                                    <!-- Segunda columna -->
                                    <div class="flex items-center gap-2">
                                        <span class="w-5 text-white">{{ $i + 10 }}</span>
                                        @if(isset($todayNumbers[$i + 10]))
                                            @can('extracts_edit') {{-- Only show input if user can edit --}}
                                            <input type="text" placeholder="Valor de hoy"
                                                class="w-full border bg-[#22272b] border-gray-600 text-white text-center rounded-lg px-2 py-1 text-sm"
                                                value="{{ $todayNumbers[$i + 10]->value }}"
                                                wire:blur="updateNumber({{ $todayNumbers[$i + 10]->id }}, $event.target.value)"
                                                maxlength="4" pattern="\d{4}" inputmode="numeric">
                                            @else {{-- Show as text if user cannot edit --}}
                                            <h2 class="font-bold text-white bg-[#22272b] py-1 px-3 rounded-lg w-full text-sm text-center">
                                                {{ $todayNumbers[$i + 10]->value }}
                                            </h2>
                                            @endcan
                                        @else
                                            @can('extracts_create') {{-- Only show input if user can create (new number) --}}
                                            <input type="text" placeholder="Valor de hoy"
                                                class="w-full border bg-[#22272b] border-gray-600 text-white text-center rounded-lg px-2 py-1 text-sm"
                                                wire:blur="storeNumber({{ $city->id }}, {{ $extract->id }}, {{ $i + 10 }}, $event.target.value)"
                                                maxlength="4" pattern="\d{4}" inputmode="numeric">
                                            @else {{-- Show as text if user cannot create --}}
                                            <h2 class="font-bold text-white bg-[#22272b] py-1 px-3 rounded-lg w-full text-sm text-center">
                                                Pendiente
                                            </h2>
                                            @endcan
                                        @endif
                                    </div>
                                    @endif
                                    @endif
                                    @endfor
                            </div>
                        </div>
                            @endif
                        @endforeach
                    </div>
                </div>
                @endforeach
            </div>
        </div>

        <!-- Modal para acciones (imprimir, guardar, compartir) -->
        @if($showApusModal)
        <x-ticket-modal wire:model="showApusModal" maxWidth="5xl" overlayClasses="bg-gray-500 bg-opacity-25">
            <x-slot name="title">
                Extractos

                <div class="flex items-center gap-2">
                    @can('extracts_print') {{-- Assuming 'extracts_print' for printing --}}
                    @if($action === 'imprimir')
                    <button wire:click="printPDF"
                        class="text-sm px-3 py-1 bg-blue-500 text-white rounded-md flex items-center gap-2 hover:bg-blue-600/90 duration-200">
                        <i class="fas fa-print"></i> Imprimir
                    </button>
                    @elseif($action === 'guardar')
                    <button wire:click="descargarImagen"
                        class="text-sm px-3 py-1 bg-green-500 text-white rounded-md flex items-center gap-2 hover:bg-green-600/90 duration-200">
                        <i class="fas fa-save"></i> Guardar
                    </button>
                    @elseif($action === 'compartir')
                    <button id="shareButton"
                        class="text-sm px-3 py-1 bg-purple-500 text-white rounded-md flex items-center gap-2 hover:bg-purple-600/90 duration-200">
                        <i class="fas fa-share"></i> Compartir
                    </button>
                    @endif
                    @endcan
                    <button x-on:click="show = false" class="bg-red-500 text-white px-4 py-1 text-sm rounded-md">
                        Cerrar
                    </button>
                </div>
            </x-slot>

            <x-slot name="content">
                <div id="printContent" class="grid grid-cols-2 gap-2 w-full">
                    <!-- Ejemplo de impresión sólo para la 'PREVIA' (extract_id = 1) -->
                    @foreach ($cities->where('extract_id', 1) as $city)
                        @if($this->isCityAndScheduleConfiguredInQuinielas($city->name, 'PREVIA'))
                    <div class="bg-[#2d3339] p-3 rounded-lg flex flex-col gap-3 w-full">
                        <p class="text-white font-medium flex flex-col text-center">
                            {{ $city->name }}
                            <span class="font-extrabold text-green-300">{{ $city->code }}</span>
                        </p>
                        @php
                        $numbersToShow = $city->numbers;
                        @endphp
                        <div class="min-w-[200px] grid grid-cols-2 gap-2">
                            @if(isset($todayNumbers[$i]) && count($numbersToShow) > 0)
                            @php
                            $columna1 = array_slice($numbersToShow, 0, 10);
                            $columna2 = array_slice($numbersToShow, 10, 10);
                            @endphp

                            @for ($j = 0; $j < 10; $j++)
                                <div class="flex items-center gap-2">
                                    <span class="w-5 text-white">
                                        {{ $columna1[$j]->index }}
                                    </span>
                                    <h2 class="font-bold text-white bg-[#3c444b] py-1 px-3 rounded-lg w-full text-sm text-center">
                                        {{ $columna1[$j]->value }}
                                    </h2>
                                </div>

                                <div class="flex items-center gap-2">
                                    <span class="w-5 text-white">
                                        {{ $columna2[$j]->index }}
                                    </span>
                                    <h2 class="font-bold text-white bg-[#3c444b] py-1 px-3 rounded-lg w-full text-sm text-center">
                                        {{ $columna2[$j]->value }}
                                    </h2>
                                </div>
                            @endfor
                            @else
                            <div class="col-span-2 flex justify-center items-center">
                                <span class="text-gray-400">No hay extractos aún</span>
                            </div>
                            @endif
                        </div>
                    </div>
                        @endif
                    @endforeach
                </div>
            </x-slot>
            <x-slot name="footer">
                <p class="text-sm text-gray-500">NOTA: Si el Ticket sale recortado personalize los márgenes de impresión.
                    Ponga 5mm en el margen izquierdo y al resto 0mm</p>
            </x-slot>
        </x-ticket-modal>
        @endif

        <!-- Indicador de actualización automática (discreto) -->
        
    </div>
@endcan

<script>
document.addEventListener('DOMContentLoaded', function() {
    let autoUpdateInterval;
    let isUpdating = false;
    
    // Función para actualizar automáticamente
    function autoUpdate() {
        if (isUpdating) return;
        
        isUpdating = true;
        const indicator = document.getElementById('auto-update-indicator');
        
        // Mostrar indicador
        indicator.classList.remove('hidden');
        
        // Hacer petición para verificar nuevos números
        fetch('/api/check-new-numbers', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.hasNewNumbers) {
                // Recargar la página para mostrar nuevos números
                window.location.reload();
            }
        })
        .catch(error => {
            console.log('Error en actualización automática:', error);
        })
        .finally(() => {
            isUpdating = false;
            indicator.classList.add('hidden');
        });
    }
    
    // NO iniciar actualización automática - el usuario debe hacer clic en "Buscar" manualmente
    // autoUpdateInterval = setInterval(autoUpdate, 120000); // 2 minutos
    
    // Limpiar intervalo cuando se sale de la página
    window.addEventListener('beforeunload', function() {
        if (autoUpdateInterval) {
            clearInterval(autoUpdateInterval);
        }
    });
    
    // NO actualizar automáticamente cuando se vuelve a la página
    // document.addEventListener('visibilitychange', function() {
    //     if (!document.hidden && autoUpdateInterval) {
    //         // Actualizar inmediatamente si se vuelve a la página
    //         setTimeout(autoUpdate, 1000);
    //     }
    // });
});
</script>


<!-- Scripts para impresión, descarga de imagen y llenar campos de prueba -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script>
    window.addEventListener('printContent', () => {
        const printContent = document.getElementById('printContent');
        const originalBody = document.body.innerHTML;

        // Solo mostrar el contenido del div a imprimir
        document.body.innerHTML = printContent.outerHTML;

        // Imprimir
        window.print();

        // Restaurar el contenido original
        document.body.innerHTML = originalBody;
        location.reload();
    });

    function capturarDiv() {
        const buttonsContainer = document.getElementById('buttonsContainer');
        buttonsContainer.style.display = 'none';
        html2canvas(document.getElementById("printContent")).then(canvas => {
            let link = document.createElement('a');
            link.href = canvas.toDataURL("image/png");
            link.download = 'extracto.png';
            link.click();
            buttonsContainer.style.display = 'flex';
        });
    }

    window.addEventListener('descargar-imagen', event => {
        capturarDiv();
    });

    function fillTestNumbers() {
        // Seleccionar todos los inputs con placeholder "Valor de hoy"
        const inputs = document.querySelectorAll('input[placeholder="Valor de hoy"]');
        inputs.forEach((input, index) => {
            // Generar un número secuencial de 4 dígitos: 0001, 0002, etc.
            const number = String(index + 1).padStart(4, '0');
            input.value = number;
            // Disparar el evento blur para que Livewire capture el cambio si es necesario
            input.dispatchEvent(new Event('blur'));
        });
    }

    // Auto-refresh functionality
    let autoRefreshInterval = null;

    // Escuchar eventos de Livewire para iniciar/detener auto-refresh
    document.addEventListener('livewire:init', () => {
        Livewire.on('start-auto-refresh', (event) => {
            const interval = event.interval || 30000; // 30 segundos por defecto
            
            if (autoRefreshInterval) {
                clearInterval(autoRefreshInterval);
            }
            
            autoRefreshInterval = setInterval(() => {
                Livewire.dispatch('executeAutoRefresh');
            }, interval);
            
            console.log('Auto-refresh iniciado cada', interval / 1000, 'segundos');
        });

        Livewire.on('stop-auto-refresh', () => {
            if (autoRefreshInterval) {
                clearInterval(autoRefreshInterval);
                autoRefreshInterval = null;
            }
            console.log('Auto-refresh detenido');
        });
    });

    // Limpiar interval al salir de la página
    window.addEventListener('beforeunload', () => {
        if (autoRefreshInterval) {
            clearInterval(autoRefreshInterval);
        }
    });
    
    // Función para toggle de filtros originales
    function toggleOriginalFilters() {
        const originalFilters = document.getElementById('originalFilters');
        const icon = document.getElementById('originalFiltersIcon');
        
        if (originalFilters.classList.contains('hidden')) {
            originalFilters.classList.remove('hidden');
            icon.classList.remove('fa-chevron-down');
            icon.classList.add('fa-chevron-up');
        } else {
            originalFilters.classList.add('hidden');
            icon.classList.remove('fa-chevron-up');
            icon.classList.add('fa-chevron-down');
        }
    }
</script>
