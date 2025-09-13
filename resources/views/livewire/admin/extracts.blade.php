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
                        <button wire:click="searchDate"
                            class="text-sm px-3 py-1 border border-green-500 bg-green-500 text-white rounded-md flex items-center gap-2 hover:border-green-600/90 hover:bg-green-600/90 duration-200">
                            Buscar
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
                        <div class="bg-[#292f34] p-3 rounded-lg flex flex-col gap-3">
                            <p class="text-white font-medium flex flex-col text-center">
                                {{ $city->name }}
                                <span class="font-extrabold text-green-300">{{ $city->code }}</span>
                            </p>
                            @php
                            // Usamos la fecha del filtro o, si no se selecciona, la de hoy
                            $dateToUse = $filterDate ?? \Carbon\Carbon::today()->toDateString();
                            // Obtenemos los números de la ciudad para esa fecha, indexados por su 'index'
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
                    @endforeach
                </div>
            </x-slot>
            <x-slot name="footer">
                <p class="text-sm text-gray-500">NOTA: Si el Ticket sale recortado personalize los márgenes de impresión.
                    Ponga 5mm en el margen izquierdo y al resto 0mm</p>
            </x-slot>
        </x-ticket-modal>
        @endif

        @livewire('notification')
        <!-- Overlay de carga -->
        <div wire:loading.delay class="fixed inset-0 flex items-center justify-center bg-gray-800 bg-opacity-75 z-50">
            <div class="flex flex-col items-center">
                <i class="fas fa-spinner fa-spin text-white text-4xl mb-4"></i>
                <span class="text-white text-xl">Cargando datos...</span>
            </div>
        </div>    
    </div>
@endcan


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
</script>
