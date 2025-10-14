<div>
@if($showModal && $client)
<div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <!-- Background overlay -->
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" wire:click="closeModal"></div>

        <!-- Modal panel -->
        <div class="inline-block align-bottom bg-[#1b1f22] rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-4 sm:align-middle sm:max-w-[95vw] sm:w-full sm:h-[90vh]">
            <!-- Header -->
                        <div class="bg-[#22272b] px-6 py-4 border-b border-gray-600">
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-4">
                        @if($client->profile_photo_path)
                            <img src="{{ asset('storage/' . $client->profile_photo_path) }}" alt="{{ $client->nombre }}" class="w-12 h-12 rounded-full object-cover">
                        @else
                            <div class="w-12 h-12 rounded-full bg-gray-600 flex items-center justify-center">
                                <i class="fa-solid fa-user text-white text-lg"></i>
                            </div>
                        @endif
                        <div>
                            <h3 class="text-lg font-semibold text-white">
                                {{ $client->nombre }} {{ $client->apellido }}
                            </h3>
                            <p class="text-sm text-gray-400">{{ $client->correo }}</p>
                            <p class="text-sm text-gray-400">{{ $client->nombre_fantasia }}</p>
                        </div>
                    </div>
                    <button wire:click="closeModal" class="text-gray-400 hover:text-white">
                        <i class="fa-solid fa-times text-xl"></i>
                    </button>
                </div>
            </div>

            <!-- Tabs -->
            <div class="bg-[#22272b] border-b border-gray-600">
                <nav class="flex space-x-8 px-6">
                    <button wire:click="setActiveTab('jugadas')" 
                            class="py-4 px-1 border-b-2 font-medium text-sm {{ $activeTab === 'jugadas' ? 'border-yellow-200 text-yellow-200' : 'border-transparent text-gray-400 hover:text-white hover:border-gray-300' }}">
                        <i class="fa-solid fa-paper-plane mr-2"></i>
                        Jugadas Enviadas
                    </button>
                    <button wire:click="setActiveTab('extractos')" 
                            class="py-4 px-1 border-b-2 font-medium text-sm {{ $activeTab === 'extractos' ? 'border-yellow-200 text-yellow-200' : 'border-transparent text-gray-400 hover:text-white hover:border-gray-300' }}">
                        <i class="fa-solid fa-list mr-2"></i>
                        Extractos
                    </button>
                    <button wire:click="setActiveTab('resultados')" 
                            class="py-4 px-1 border-b-2 font-medium text-sm {{ $activeTab === 'resultados' ? 'border-yellow-200 text-yellow-200' : 'border-transparent text-gray-400 hover:text-white hover:border-gray-300' }}">
                        <i class="fa-solid fa-trophy mr-2"></i>
                        Resultados
                    </button>
                    <button wire:click="setActiveTab('liquidaciones')" 
                            class="py-4 px-1 border-b-2 font-medium text-sm {{ $activeTab === 'liquidaciones' ? 'border-yellow-200 text-yellow-200' : 'border-transparent text-gray-400 hover:text-white hover:border-gray-300' }}">
                        <i class="fa-solid fa-calculator mr-2"></i>
                        Liquidaciones
                    </button>
                </nav>
            </div>

            <!-- Content -->
            <div class="bg-[#1b1f22] p-6 overflow-y-auto" style="max-height: calc(90vh - 200px);">
                @if($activeTab === 'jugadas')
                    <!-- Jugadas Enviadas Tab -->
                    <div class="space-y-4">
                        <!-- Filtros -->
                        <div class="flex gap-4 items-end">
                            <div class="flex-1">
                                <label class="block text-sm font-medium text-white mb-1">Fecha</label>
                                <input type="date" wire:model.live="jugadasDate" 
                                       class="w-full border bg-[#22272b] text-white text-sm border-gray-600 rounded-md px-3 py-2 focus:border-yellow-200">
                            </div>
                            <div class="flex-1">
                                <label class="block text-sm font-medium text-white mb-1">Tipo</label>
                                <select wire:model.live="jugadasType" 
                                        class="w-full border bg-[#22272b] text-white text-sm border-gray-600 rounded-md px-3 py-2 focus:border-yellow-200">
                                    <option value="">Todos</option>
                                    <option value="J">Jugadas</option>
                                    <option value="R">Redoblonas</option>
                                </select>
                            </div>
                            <div class="flex-1">
                                <label class="block text-sm font-medium text-white mb-1">Por página</label>
                                <select wire:model.live="jugadasPerPage" 
                                        class="w-full border bg-[#22272b] text-white text-sm border-gray-600 rounded-md px-3 py-2 focus:border-yellow-200">
                                    <option value="5">5</option>
                                    <option value="10">10</option>
                                    <option value="25">25</option>
                                </select>
                            </div>
                        </div>

                        <!-- Total -->
                        <div class="bg-[#22272b] p-3 rounded-lg">
                            <p class="text-white">
                                <span class="font-semibold">Total Importe:</span> 
                                ${{ number_format($this->totalJugadas, 2, ',', '.') }}
                            </p>
                        </div>

                        <!-- Tabla -->
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm text-left text-gray-500">
                                <thead class="text-xs text-white uppercase bg-gray-600">
                                    <tr>
                                        <th class="px-4 py-3">Hora</th>
                                        <th class="px-4 py-3">Ticket</th>
                                        <th class="px-4 py-3">Tipo</th>
                                        <th class="px-4 py-3">Apu</th>
                                        <th class="px-4 py-3">Lot</th>
                                        <th class="px-4 py-3">Importe</th>
                                        <th class="px-4 py-3">Estado</th>
                                        <th class="px-4 py-3">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($this->jugadasEnviadas as $jugada)
                                        <tr class="border-b border-gray-600 text-white {{ $jugada->status === 'I' ? 'bg-red-500/30' : 'bg-[#22272b]' }}">
                                            <td class="px-4 py-3 {{ $jugada->status === 'I' ? 'line-through' : ''}}">
                                                {{ $jugada->time }}
                                            </td>
                                            <td class="px-4 py-3 {{ $jugada->status === 'I' ? 'line-through' : ''}}">
                                                {{ $jugada->ticket }}
                                            </td>
                                            <td class="px-4 py-3 {{ $jugada->status === 'I' ? 'line-through' : ''}}">
                                                {{ $jugada->type }}
                                            </td>
                                            <td class="px-4 py-3 {{ $jugada->status === 'I' ? 'line-through' : ''}}">
                                                {{ $jugada->apu }}
                                            </td>
                                            <td class="px-4 py-3 {{ $jugada->status === 'I' ? 'line-through' : ''}}">
                                                {{ count(array_unique(explode(',', $jugada->lot))) }}
                                            </td>
                                            <td class="px-4 py-3 {{ $jugada->status === 'I' ? 'line-through' : ''}}">
                                                ${{ number_format($jugada->amount ?? 0, 2, ',', '.') }}
                                            </td>
                                            <td class="px-4 py-3">
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $jugada->status === 'I' ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800' }}">
                                                    {{ $jugada->status === 'I' ? 'Inactivo' : 'Activo' }}
                                                </span>
                                            </td>
                                            <td class="px-4 py-3">
                                                <button wire:click="viewTicket({{ $jugada->id }})" 
                                                        class="font-medium text-center text-yellow-200 bg-gray-700 p-1 px-2 rounded-md
                                                               hover:underline hover:bg-gray-700/50 duration-200"
                                                        title="Ver ticket">
                                                    <i class="fa-solid fa-rug rotate-90"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="8" class="px-4 py-8 text-center text-gray-400">
                                                No hay jugadas enviadas para esta fecha
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        <!-- Paginación -->
                        @if($this->jugadasEnviadas->hasPages())
                            <div class="mt-4">
                                {{ $this->jugadasEnviadas->links() }}
                            </div>
                        @endif
                    </div>

                @elseif($activeTab === 'extractos')
                    <!-- Extractos Tab -->
                    <div class="space-y-4">
                        <!-- Filtros -->
                        <div class="flex gap-4 items-end">
                            <div class="flex-1">
                                <label class="block text-sm font-medium text-white mb-1">Fecha</label>
                                <input type="date" wire:model.live="extractosDate" 
                                       class="w-full border bg-[#22272b] text-white text-sm border-gray-600 rounded-md px-3 py-2 focus:border-yellow-200">
                            </div>
                        </div>

                        <!-- Botón para ver extracto completo -->
                        <div class="flex justify-end mb-4">
                            <button wire:click="toggleExtractView" 
                                    class="bg-yellow-500 hover:bg-yellow-600 text-white px-4 py-2 rounded-md text-sm transition-colors duration-200 flex items-center gap-2">
                                <i class="fa-solid fa-wand-magic-sparkles"></i>
                                @if($showFullExtract)
                                    Ver solo cabeza
                                @else
                                    Ver extracto completo
                                @endif
                            </button>
                        </div>

                        <!-- Extractos -->
                        <div class="space-y-6">
                            @forelse($this->extractos as $extract)
                                <div class="bg-[#22272b] rounded-lg p-4">
                                    <h4 class="text-lg font-semibold text-white mb-4">{{ $extract->name }}</h4>
                                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                        @foreach($extract->cities as $city)
                                            <div class="bg-[#1b1f22] p-3 rounded-lg">
                                                <h5 class="text-white font-medium text-center mb-2">
                                                    {{ $city->name }}
                                                    <span class="text-green-300 font-bold">{{ $city->code }}</span>
                                                </h5>
                                                <div class="grid grid-cols-{{ $showFullExtract ? '2' : '1' }} gap-1">
                                                    @for($i = 1; $i <= 10; $i++)
                                                        @if($showFullExtract || $i == 1)
                                                            @php
                                                                $number = $city->numbers->where('index', $i)->first();
                                                            @endphp
                                                            <div class="flex items-center gap-1">
                                                                <span class="w-4 text-xs text-gray-400">{{ $i }}</span>
                                                                <div class="flex-1 bg-[#3c444b] text-white text-xs text-center py-1 rounded">
                                                                    {{ $number ? $number->value : '-' }}
                                                                </div>
                                                            </div>
                                                            
                                                            @if($showFullExtract)
                                                                @php
                                                                    $number2 = $city->numbers->where('index', $i + 10)->first();
                                                                @endphp
                                                                <div class="flex items-center gap-1">
                                                                    <span class="w-4 text-xs text-gray-400">{{ $i + 10 }}</span>
                                                                    <div class="flex-1 bg-[#3c444b] text-white text-xs text-center py-1 rounded">
                                                                        {{ $number2 ? $number2->value : '-' }}
                                                                    </div>
                                                                </div>
                                                            @endif
                                                        @endif
                                                    @endfor
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @empty
                                <div class="text-center text-gray-400 py-8">
                                    No hay extractos disponibles para esta fecha
                                </div>
                            @endforelse
                        </div>
                    </div>

                @elseif($activeTab === 'resultados')
                    <!-- Resultados Tab -->
                    <div class="space-y-4">
                        <!-- Filtros -->
                        <div wire:keydown.enter="search" class="flex gap-3 justify-between text-sm mb-4">
                            <div class="w-full flex flex-col gap-1 text-sm">
                                <label for="dateResult" class="text-white">Buscar por fecha:</label>
                                <input type="date" id="dateResult" wire:model.live="resultadosDate" placeholder="Selecciona una fecha"
                                    class="w-full border bg-[#22272b] text-white text-sm border-gray-600 rounded-md p-2 py-1 focus:border-yellow-200">
                            </div>

                            <!-- Botones de buscar y reiniciar -->
                            <div class="flex items-end space-x-2">
                                <button wire:click="search"
                                    class="text-sm px-3 py-1 border border-green-500 bg-green-500 text-white rounded-md flex items-center gap-2 hover:border-green-600/90 hover:bg-green-600/90 duration-200">
                                    Buscar
                                </button>
                                <button wire:click="resetFilter"
                                    class="bg-[#22272b] w-fit border border-green-300 text-sm px-5 py-1 rounded-md text-green-400 hover:bg-green-100/20 duration-200">
                                    Reiniciar
                                </button>
                            </div>
                        </div>

                        <!-- Tarjetas de totales -->
                        <div class="flex justify-between gap-3">
                            <div class="flex flex-col gap-1 w-full bg-[#333a41] border border-gray-600 p-3 rounded-lg">
                                <span class="text-green-400 font-medium">Recaudado Jugadas</span>
                                <h2 class="text-2xl font-bold text-white">
                                    ${{ number_format($this->totalImporteResultados, 2) }}
                                </h2>
                            </div>
                            <div class="flex flex-col gap-1 w-full bg-[#333a41] border border-gray-600 p-3 rounded-lg">
                                <span class="text-green-400 font-medium">Aciertos Jugadas</span>
                                <h2 class="text-2xl font-bold text-white">
                                    ${{ number_format($this->totalAciertosResultados, 2) }}
                                </h2>
                            </div>
                        </div>

                        <div id="paginated-users">
                            <div class="flex justify-between items-center p-4 pt-0 ps-0">
                                <h2 class="font-semibold text-xl text-white leading-tight">
                                    {{ __('Aciertos Jugadas') }}
                                </h2>
                            </div>
                            <div class="bg-[#22272b] rounded-lg">

                                <div class="relative overflow-x-auto rounded-lg">
                                    @if ($this->resultados->hasPages())
                                        <div class="pt-4 mb-2">
                                            {{ $this->resultados->links() }}
                                        </div>
                                    @endif

                                    <table class="w-full text-sm text-left rtl:text-right text-gray-500">
                                        <thead class="text-xs text-white uppercase bg-gray-600 sticky top-0">
                                            <tr>
                                                <th scope="col" class="px-6 py-3">Ticket</th>
                                                <th scope="col" class="px-6 py-3">Loterías</th>
                                                <th scope="col" class="px-6 py-3">Número</th>
                                                <th scope="col" class="px-6 py-3">Posición</th>
                                                <th scope="col" class="px-6 py-3">NumR</th>
                                                <th scope="col" class="px-6 py-3">PosR</th>
                                                <th scope="col" class="px-6 py-3">Importe</th>
                                                <th scope="col" class="px-6 py-3 text-center">Aciertos</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse ($this->resultados as $resultado)
                                                <tr class="border-b border-gray-600 text-white bg-[#22272b]">
                                                    <td class="px-6 py-4">{{ $resultado->ticket }}</td>
                                                    <td class="px-6 py-4">
                                                        {{ $resultado->lottery }}
                                                    </td>                                    

                                                    <td class="px-6 py-4 truncate">{{ $resultado->number }}</td>
                                                    <td class="px-6 py-4">{{ $resultado->position }}</td>
                                                    <td class="px-6 py-4">{{ $resultado->numR ?? '-' }}</td>
                                                    <td class="px-6 py-4">{{ $resultado->posR ?? '-' }}</td>
                                                    <td class="px-6 py-4">
                                                        ${{ number_format($resultado->import, 2, '.') }}
                                                    </td>
                                                    <td class="px-6 py-4 text-end">
                                                        ${{ number_format($resultado->aciert, 2, '.') }}
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="8" class="px-6 py-4 text-center text-gray-300">
                                                        No hay resultados
                                                    </td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>

                                    @if ($this->resultados->hasPages())
                                        <div class="pt-4">
                                            {{ $this->resultados->links() }}
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <!-- Códigos explicativos -->
                        <div class="w-full mt-4">
                            <div class="grid grid-cols-8 gap-3 gap-y-1">
                                @php
                                $codes = [
                                    'AB' => 'NAC 1015',
                                    'CH1' => 'CHA 1015',
                                    'QW' => 'PRO 1015',
                                    'M10' => 'MZA 1015',
                                    '!' => 'CTE 1015',
                                    'ER' => 'SFE 1015',
                                    'SD' => 'COR 1015',
                                    'RT' => 'RIO 1015',
                                    'Q' => 'NAC 1200',
                                    'CH2' => 'CHA 1200',
                                    'W' => 'PRO 1200',
                                    'M1' => 'MZA 1200',
                                    'M' => 'CTE 1200',
                                    'R' => 'SFE 1200',
                                    'T' => 'COR 1200',
                                    'K' => 'RIO 1200',
                                    'A' => 'NAC 1500',
                                    'CH3' => 'CHA 1500',
                                    'E' => 'PRO 1500',
                                    'M2' => 'MZA 1500',
                                    'CT3' => 'CTE 1500',
                                    'D' => 'SFE 1500',
                                    'L' => 'COR 1500',
                                    'J' => 'RIO 1500',
                                    'S' => 'ORO 1500',
                                    'F' => 'NAC 1800',
                                    'CH4' => 'CHA 1800',
                                    'B' => 'PRO 1800',
                                    'M3' => 'MZA 1800',
                                    'Z' => 'CTE 1800',
                                    'V' => 'SFE 1800',
                                    'H' => 'COR 1800',
                                    'U' => 'RIO 1800',
                                    'N' => 'NAC 2100',
                                    'CH5' => 'CHA 2100',
                                    'P' => 'PRO 2100',
                                    'M4' => 'MZA 2100',
                                    'G' => 'CTE 2100',
                                    'I' => 'SFE 2100',
                                    'C' => 'COR 2100',
                                    'Y' => 'RIO 2100',
                                    'O' => 'ORO 2100',
                                ];
                                @endphp
                                @foreach($codes as $code => $description)
                                    @php
                                    $colorClasses = match(true) {
                                        in_array($code, ['AB', 'CH1', 'QW', 'M10', '!', 'ER', 'SD', 'RT']) => 'text-gray-200',
                                        in_array($code, ['Q', 'CH2', 'W', 'M1', 'M', 'R', 'T', 'K', 'S']) => 'text-yellow-200',
                                        in_array($code, ['A', 'CH3', 'E', 'M2', 'CT3', 'D', 'L', 'J']) => 'text-blue-400',
                                        in_array($code, ['F', 'CH4', 'B', 'M3', 'Z', 'V', 'H', 'U']) => 'text-green-400',
                                        in_array($code, ['N', 'CH5', 'P', 'M4', 'G', 'I', 'C', 'Y', 'O']) => 'text-red-400',
                                        default => 'bg-white text-black',
                                    };
                                    @endphp

                                    <div class="flex items-center justify-center gap-1 text-[12px] rounded-lg bg-[#333a41] py-1 {{ $colorClasses }}">
                                        <h4 class="font-extrabold text-nowrap">{{ $code }} = </h4>
                                        <p class="text-nowrap">{{ $description }}</p>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                    </div>

                @elseif($activeTab === 'liquidaciones')
                    <!-- Liquidaciones Tab -->
                    <div class="space-y-4">
                        <!-- Filtros -->
                        <div class="flex gap-4 items-end">
                            <div class="flex-1">
                                <label class="block text-sm font-medium text-white mb-1">Fecha</label>
                                <input type="date" wire:model.live="liquidacionesDate" 
                                       max="{{ \Carbon\Carbon::yesterday()->format('Y-m-d') }}"
                                       class="w-full border bg-[#22272b] text-white text-sm border-gray-600 rounded-md px-3 py-2 focus:border-yellow-200">
                            </div>
                        </div>

                        @php
                            $liquidacionData = $this->liquidacionData;
                            $liquidaciones = $this->liquidaciones;
                        @endphp

                        @if($liquidacionesDate && !\Carbon\Carbon::parse($liquidacionesDate)->isToday())
                            <!-- Diseño de Boleta igual al original -->
                            <div class="flex justify-center">
                                <div id="liquidationContainer" class="w-[80mm] p-2 text-black bg-white relative shadow-lg">
                                    <div class="relative z-10">
                                        <!-- Header -->
                                        <h3 class="font-medium border-b pb-2 w-full text-center">
                                            {{ $client->associatedUser->id ?? 'N/A' }}
                                        </h3>

                                        <!-- Fecha -->
                                        <div class="flex justify-between gap-1 border-b pb-2 w-full text-sm">
                                            <div class="flex flex-col">
                                                <h4 class="font-medium">FECHA:</h4>
                                            </div>
                                            <div class="flex flex-col">
                                                <p>{{ \Carbon\Carbon::parse($liquidacionesDate)->format('d/m/Y') }}</p>
                                            </div>
                                        </div>

                                        <!-- Detalle de resultados -->
                                        <div class="container text-sm mt-2">
                                            <div class="flex flex-col items-center w-full">
                                                <div class="grid grid-cols-6 font-bold w-full justify-around">
                                                    <div class="text-start">LOT</div>
                                                    <div class="text-center">NUM</div>
                                                    <div class="text-center">UBI</div>
                                                    <div class="text-center">APO</div>
                                                    <div class="text-end">GANO</div>
                                                </div>
                                                <div class="w-full pb-2 border-b">
                                                    @forelse ($liquidaciones as $result)
                                                        <div class="grid grid-cols-6 w-full justify-around text-sm">
                                                            <div class="text-start text-nowrap">
                                                                {{ collect(explode(',', $result->lottery))->last() }}
                                                                <span class="font-medium">
                                                                    {{ substr($result->time, 0, 2) }}
                                                                </span>
                                                            </div>
                                                            <div class="text-center text-nowrap">{{ $result->number }}</div>
                                                            <div class="text-center text-nowrap">{{ $result->position }}</div>
                                                            <div class="text-center text-nowrap">
                                                                {{ number_format($result->import) }}
                                                            </div>
                                                            <div class="text-end text-nowrap">
                                                                {{ number_format($result->aciert) }}
                                                            </div>
                                                        </div>
                                                    @empty
                                                        <div class="px-6 py-4 text-center border-b">
                                                            No hay resultados
                                                        </div>
                                                    @endforelse
                                                </div>

                                                <!-- Totales por horario -->
                                                <div class="flex flex-col pt-3 gap-1 border-b pb-2 w-full text-sm">
                                                    <div class="flex justify-between">
                                                        <h4 class="font-medium">PREVIA:</h4>
                                                        <p>{{ number_format($liquidacionData['previaTotalApus'], 2) }}</p>
                                                    </div>
                                                    <div class="flex justify-between">
                                                        <h4 class="font-medium">MAÑANA:</h4>
                                                        <p>{{ number_format($liquidacionData['mananaTotalApus'], 2) }}</p>
                                                    </div>
                                                    <div class="flex justify-between">
                                                        <h4 class="font-medium">MATUTINA:</h4>
                                                        <p>{{ number_format($liquidacionData['matutinaTotalApus'], 2) }}</p>
                                                    </div>
                                                    <div class="flex justify-between">
                                                        <h4 class="font-medium">TARDE:</h4>
                                                        <p>{{ number_format($liquidacionData['tardeTotalApus'], 2) }}</p>
                                                    </div>
                                                    <div class="flex justify-between">
                                                        <h4 class="font-medium">NOCHE:</h4>
                                                        <p>{{ number_format($liquidacionData['nocheTotalApus'], 2) }}</p>
                                                    </div>
                                                </div>

                                                <!-- Cálculos principales -->
                                                <div class="flex flex-col pt-3 gap-1 border-b pb-2 w-full text-sm">
                                                    <div class="flex justify-between">
                                                        <h4 class="font-medium">JUGADAS:</h4>
                                                        <p>{{ number_format($liquidacionData['totalApus'], 2) }}</p>
                                                    </div>
                                                    <div class="flex justify-between">
                                                        <h4 class="font-medium">TOTAL PASE:</h4>
                                                        <p>{{ number_format($liquidacionData['totalApus'], 2) }}</p>
                                                    </div>
                                                    <div class="flex justify-between">
                                                        <h4 class="font-medium">COMIS. J. {{ $client->commission_percentage ?? 20.00 }}%:</h4>
                                                        <p>{{ number_format($liquidacionData['comision'], 2) }}</p>
                                                    </div>
                                                    <div class="flex justify-between">
                                                        <h4 class="font-medium">TOT.ACIERT:</h4>
                                                        <p>{{ number_format($liquidacionData['totalAciert'], 2) }}</p>
                                                    </div>
                                                </div>

                                                <!-- Deja pase -->
                                                <div class="flex flex-col pt-3 gap-1 border-b pb-2 w-full text-sm">
                                                    <div class="flex justify-between">
                                                        <h4 class="font-medium">DEJA PASE:</h4>
                                                        <p>{{ number_format($liquidacionData['totalGanaPase'], 2) }}</p>
                                                    </div>
                                                    <div class="flex justify-between">
                                                        <h4 class="font-medium">TOTAL DEJA:</h4>
                                                        <p>{{ number_format($liquidacionData['totalGanaPase'], 2) }}</p>
                                                    </div>
                                                </div>

                                                <!-- Gener. deja y arrastre -->
                                                <div class="flex flex-col pt-3 gap-1 border-b pb-2 w-full text-sm">
                                                    <div class="flex justify-between">
                                                        <h4 class="font-medium">GENER. DEJA:</h4>
                                                        <p>{{ number_format($liquidacionData['totalGanaPase'], 2) }}</p>
                                                    </div>
                                                    <div class="flex justify-between">
                                                        <h4 class="font-medium">ANTERI:</h4>
                                                        <p>{{ number_format($liquidacionData['anteri'], 2) }}</p>
                                                    </div>
                                                    @if(\Carbon\Carbon::parse($liquidacionesDate)->isSaturday())
                                                        <div class="flex justify-between">
                                                            <h4 class="font-medium">COMI DEJA SEM:</h4>
                                                            <p>{{ number_format($liquidacionData['comiDejaSem'], 2) }}</p>
                                                        </div>
                                                    @endif
                                                    <div class="flex justify-between">
                                                        <h4 class="font-medium">UD DEJA:</h4>
                                                        <p>{{ number_format($liquidacionData['udDeja'], 2) }}</p>
                                                    </div>
                                                </div>

                                                <!-- Arrastre -->
                                                <div class="flex flex-col pt-3 gap-1 w-full text-sm">
                                                    <div class="flex justify-between">
                                                        <h4 class="font-medium">ARRASTRE:</h4>
                                                        <p>{{ number_format($liquidacionData['arrastre'], 2) }}</p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @else
                            <div class="text-center text-gray-400 py-8">
                                @if(!$liquidacionesDate)
                                    Selecciona una fecha para ver la liquidación
                                @else
                                    No se pueden consultar liquidaciones del día actual
                                @endif
                            </div>
                        @endif
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

<!-- Modal de Ticket -->
@if($showTicketModal && $selectedTicket)
<x-ticket-modal wire:model="showTicketModal" overlayClasses="bg-gray-500 bg-opacity-25">
    <x-slot name="title">
        Información del Ticket
        <button wire:click="closeTicketModal" class="bg-red-500 text-white px-4 py-1 text-sm rounded-md no-print">
            Cerrar
        </button>
    </x-slot>

    <x-slot name="content">
        <div class="flex items-center justify-between gap-2 no-print z-10 mt-3" id="buttonsContainer">
            <a href="/"
               class="w-full text-sm px-3 py-1 bg-teal-500 text-white rounded-md flex justify-center
                      items-center gap-2 hover:bg-teal-600/90 duration-200">
                <i class="fa-solid fa-rug rotate-90"></i> Nuevo ticket
            </a>

            <button onclick="printTicket()"
                    class="w-full text-sm px-3 py-1 bg-blue-500 text-white rounded-md
                           flex justify-center items-center gap-2 hover:bg-blue-600/90 duration-200">
                <i class="fas fa-print"></i> Imprimir
            </button>

            <button onclick="guardarTicket()"
                    class="w-full text-sm px-3 py-1 bg-green-500 text-white rounded-md
                           flex justify-center items-center gap-2 hover:bg-green-600/90 duration-200">
                <i class="fas fa-save"></i> Guardar
            </button>

            <button wire:click="shareTicket('{{ $selectedTicket->ticket }}')" onclick="guardarTicket()"
                    class="w-full text-sm px-3 py-1 bg-yellow-200 text-gray-600 rounded-md
                           flex justify-center items-center gap-2 hover:bg-yellow-200/85 duration-200">
                <i class="fas fa-share"></i> Compartir
            </button>
        </div>
        <div id="ticketContainer" data-code="{{ $selectedTicket->code }}" data-ticket="{{ $selectedTicket->ticket }}"
             class="w-[80mm] mx-auto p-2 text-black bg-white relative">
            <div class="flex items-center justify-center mb-2">
                @if($client->profile_photo_path)
                    <img src="{{ asset('storage/' . $client->profile_photo_path) }}"
                         class="w-16 h-16 rounded-lg object-cover border-2 border-yellow-300"
                         alt="{{ $client->nombre }}" />
                @else
                    <img src="{{ asset('assets/images/logo.png') }}"
                         class="w-16 h-16 rounded-lg border-2 border-blue-300"
                         alt="Cliente" />
                @endif
            </div>

            <div class="flex justify-between border-b border-gray-400 py-1 text-lg">
                <span>Vendedor: <strong>{{ $selectedTicket->user_id }}</strong></span>
                <span>Ticket: <strong>{{ $selectedTicket->ticket }}</strong></span>
            </div><br>

            <div class="flex justify-between text-lg py-1" style="border-bottom: 3px solid black;">
                <div>
                    <p class="font-semibold">FECHA:</p>
                    <p class="font-semibold">{{ $selectedTicket->date }}</p>
                </div>
                <div>
                    <p class="font-semibold">HORA:</p>
                    <p class="font-semibold">{{ $selectedTicket->time }}</p>
                </div>
            </div>

            <div class="space-y-6">
                @php
                    // Replicar la lógica exacta del componente PlaysSent
                    $rawApus = $selectedTicket->apus->sortBy('original_play_id')->sortBy('id');
                    $groupedByPlayId = $rawApus->groupBy('original_play_id');
                    
                    // Códigos exactos del componente PlaysSent
                    $codes = [
                        'AB' => 'NAC1015', 'CH1' => 'CHA1015', 'QW' => 'PRO1015', 'M10' => 'MZA1015', '!' => 'CTE1015',
                        'ER' => 'SFE1015', 'SD' => 'COR1015', 'RT' => 'RIO1015', 'Q' => 'NAC1200', 'CH2' => 'CHA1200',
                        'W' => 'PRO1200', 'M1' => 'MZA1200', 'M' => 'CTE1200', 'R' => 'SFE1200', 'T' => 'COR1200',
                        'K' => 'RIO1200', 'A' => 'NAC1500', 'CH3' => 'CHA1500', 'E' => 'PRO1500', 'M2' => 'MZA1500',
                        'Ct3' => 'CTE1500', 'D' => 'SFE1500', 'L' => 'COR1500', 'J' => 'RIO1500', 'S' => 'ORO1800',
                        'F' => 'NAC1800', 'CH4' => 'CHA1800', 'B' => 'PRO1800', 'M3' => 'MZA1800', 'Z' => 'CTE1800',
                        'V' => 'SFE1800', 'H' => 'COR1800', 'U' => 'RIO1800', 'N' => 'NAC2100', 'CH5' => 'CHA2100',
                        'P' => 'PRO2100', 'M4' => 'MZA2100', 'G' => 'CTE2100', 'I' => 'SFE2100', 'C' => 'COR2100',
                        'Y' => 'RIO2100', 'O' => 'ORO2100'
                    ];
                    
                    $processedGroups = $groupedByPlayId->map(function ($groupOfApusFromSameOriginalPlay) use ($codes) {
                        $representativeApu = $groupOfApusFromSameOriginalPlay->first();
                        
                        $lotteryCodes = $groupOfApusFromSameOriginalPlay
                            ->pluck('lottery')
                            ->filter()
                            ->unique()
                            ->values()
                            ->toArray();
                        
                        // Usar la misma lógica determineLottery
                        $systemCodes = [];
                        foreach ($lotteryCodes as $uiCode) {
                            $uiCode = trim($uiCode);
                            if (isset($codes[$uiCode])) {
                                $systemCodes[] = $codes[$uiCode];
                            }
                        }
                        $displayCodes = [];
                        foreach ($systemCodes as $systemCode) {
                            $prefix = substr($systemCode, 0, -4); // Extracts 'NAC' from 'NAC1015'
                            preg_match('/\d{4}$/', $systemCode, $matches); // Finds the 4-digit time suffix (e.g., '1015')
                            $timeSuffix = isset($matches[0]) ? substr($matches[0], 0, 2) : ''; // Gets '10' from '1015'
                            $displayCodes[] = $prefix . $timeSuffix; // Combines to 'NAC10'
                        }
                        $desiredOrder = ['NAC', 'CHA', 'PRO', 'MZA', 'CTE', 'SFE', 'COR', 'RIO', 'ORO'];
                        $uniqueDisplayCodes = array_unique($displayCodes);
                        usort($uniqueDisplayCodes, function ($a, $b) use ($desiredOrder) {
                            $prefixA = substr($a, 0, -2);
                            $prefixB = substr($b, 0, -2);
                            $posA = array_search($prefixA, $desiredOrder);
                            $posB = array_search($prefixB, $desiredOrder);
                            if ($posB === false) return -1;
                            return $posA - $posB;
                        });
                        $determinedLotteryKeyString = implode(', ', $uniqueDisplayCodes);

                        return [
                            'codes_display_string' => $determinedLotteryKeyString,
                            'numbers' => [[
                                'number' => $representativeApu->number,
                                'pos' => $representativeApu->position,
                                'imp' => $representativeApu->import,
                                'numR' => $representativeApu->numberR,
                                'posR' => $representativeApu->positionR,
                            ]],
                        ];
                    });

                    $groups = $processedGroups
                        ->groupBy('codes_display_string')
                        ->map(function ($items, $key) {
                            return [
                                'codes_display' => explode(', ', $key),
                                'numbers' => $items->pluck('numbers')->flatten(1)->all(),
                            ];
                        })
                        ->sortBy(function ($group, $key) {
                            static $desiredOrder = ['NAC', 'CHA', 'PRO', 'MZA', 'CTE', 'SFE', 'COR', 'RIO', 'ORO'];
                            $firstLotteryInGroup = explode(', ', $key)[0];
                            $prefix = substr($firstLotteryInGroup, 0, -2);
                            return array_search($prefix, $desiredOrder) ?: 999;
                        })
                        ->values();
                @endphp

                @foreach($groups as $block)
                    {{-- Encabezado de loterías --}}
                    <div class="grid grid-cols-6 gap-2 text-sm font-bold text-black py-1" style="border-bottom: 3px solid black;">
                        @foreach($block['codes_display'] as $lot)
                            <div class="text-center">{{ $lot }}</div>
                        @endforeach
                    </div>

                    {{-- Lista de números --}}
                    <div class="space-y-1 text-sm">
                        @foreach ($block['numbers'] as $item)
                            <div class="grid grid-cols-6 px-2">
                                <span class="font-semibold col-span-1">{{ $item['number'] }}</span>
                                <span class="text-center col-span-1">{{ $item['pos'] }}</span>
                                <span class="text-left col-span-2">${{ number_format($item['imp'], 2, ',', '.') }}</span>
                                <span class="text-center col-span-1">{{ $item['numR'] ? str_pad($item['numR'], 2, '*', STR_PAD_LEFT) : '' }}</span>
                                <span class="text-center col-span-1">{{ $item['posR'] }}</span>
                            </div>
                        @endforeach
                    </div>
                    <hr class="py-1" style="border-bottom: 3px solid black;">
                @endforeach
            </div>

            <!-- Total -->
            <div>
                <div class="flex justify-end font-medium">
                    <h4 class="text-lg">
                        TOTAL:
                        <span class="font-extrabold">
                            ${{ number_format($selectedTicket->amount ?? 0, 2, ',', '.') }}
                        </span>
                    </h4>
                </div>
                <div class="flex justify-end">
                    <p class="text-sm text-gray-500">
                        {{ $selectedTicket->code }}
                    </p>
                </div>
            </div>
        </div>

        <p class="text-xs text-center mt-2">
            NOTA: Ajuste márgenes en 0mm o 5mm para evitar recortes.
        </p>
    </x-slot>
    <x-slot name="footer"></x-slot>
</x-ticket-modal>
@endif

@endif
</div>

<!-- Scripts para el modal de ticket -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script>
function printTicket() {
    html2canvas(document.getElementById('ticketContainer'), { scale: 2 }).then(canvas => {
        const imgData = canvas.toDataURL('image/png');
        let iframe = document.createElement('iframe');
        iframe.style.position = "fixed";
        iframe.style.right = "0";
        iframe.style.bottom = "0";
        iframe.style.width = "0";
        iframe.style.height = "0";
        iframe.style.border = "0";
        document.body.appendChild(iframe);
        
        const doc = iframe.contentWindow.document;
        doc.open();
        doc.write(`
          <html>
            <head>
              <title>Imprimir Ticket</title>
              <style>
                @page { size: Letter; margin: 0mm; }
                html, body { margin: 0; padding: 0; }
                body { background: #fff; }
                img { width: 100%; height: auto; }
              </style>
            </head>
            <body>
              <img src="${imgData}" alt="Ticket" onload="window.focus(); window.print();">
            </body>
          </html>
        `);
        doc.close();
        
        setTimeout(() => {
            document.body.removeChild(iframe);
        }, 1000);
    });
}

async function guardarTicket() {
    const ticketElem = document.getElementById('ticketContainer');
    if (!ticketElem) return;

    const buttons = document.getElementById('buttonsContainer');
    if (buttons) buttons.style.display = 'none';

    try {
        const canvas = await html2canvas(ticketElem, { scale: 2 });
        const imgData = canvas.toDataURL('image/png');
        
        // Crear enlace de descarga
        const link = document.createElement('a');
        link.download = `ticket-${ticketElem.dataset.ticket}.png`;
        link.href = imgData;
        link.click();
        
    } catch (error) {
        console.error('Error al generar imagen:', error);
    } finally {
        if (buttons) buttons.style.display = 'flex';
    }
}
</script>
