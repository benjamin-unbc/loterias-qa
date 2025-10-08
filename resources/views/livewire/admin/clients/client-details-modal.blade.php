<div>
@if($showModal && $client)
<div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <!-- Background overlay -->
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" wire:click="closeModal"></div>

        <!-- Modal panel -->
        <div class="inline-block align-bottom bg-[#1b1f22] rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-7xl sm:w-full">
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
            <div class="bg-[#1b1f22] p-6 max-h-96 overflow-y-auto">
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
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="7" class="px-4 py-8 text-center text-gray-400">
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
                                                <div class="grid grid-cols-2 gap-1">
                                                    @for($i = 1; $i <= 20; $i++)
                                                        @php
                                                            $number = $city->numbers->where('index', $i)->first();
                                                        @endphp
                                                        <div class="flex items-center gap-1">
                                                            <span class="w-4 text-xs text-gray-400">{{ $i }}</span>
                                                            <div class="flex-1 bg-[#3c444b] text-white text-xs text-center py-1 rounded">
                                                                {{ $number ? $number->value : '-' }}
                                                            </div>
                                                        </div>
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
                        <div class="flex gap-4 items-end">
                            <div class="flex-1">
                                <label class="block text-sm font-medium text-white mb-1">Fecha</label>
                                <input type="date" wire:model.live="resultadosDate" 
                                       class="w-full border bg-[#22272b] text-white text-sm border-gray-600 rounded-md px-3 py-2 focus:border-yellow-200">
                            </div>
                            <div class="flex-1">
                                <label class="block text-sm font-medium text-white mb-1">Por página</label>
                                <select wire:model.live="resultadosPerPage" 
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
                                <span class="font-semibold">Total Aciertos:</span> 
                                ${{ number_format($this->totalResultados, 2, ',', '.') }}
                            </p>
                        </div>

                        <!-- Tabla -->
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm text-left text-gray-500">
                                <thead class="text-xs text-white uppercase bg-gray-600">
                                    <tr>
                                        <th class="px-4 py-3">Ticket</th>
                                        <th class="px-4 py-3">Loterías</th>
                                        <th class="px-4 py-3">Número</th>
                                        <th class="px-4 py-3">Posición</th>
                                        <th class="px-4 py-3">NumR</th>
                                        <th class="px-4 py-3">PosR</th>
                                        <th class="px-4 py-3">Importe</th>
                                        <th class="px-4 py-3">Aciertos</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($this->resultados as $resultado)
                                        <tr class="border-b border-gray-600 text-white bg-[#22272b]">
                                            <td class="px-4 py-3">{{ $resultado->ticket }}</td>
                                            <td class="px-4 py-3">{{ $resultado->lottery }}</td>
                                            <td class="px-4 py-3">{{ $resultado->number }}</td>
                                            <td class="px-4 py-3">{{ $resultado->position }}</td>
                                            <td class="px-4 py-3">{{ $resultado->numR ?? '-' }}</td>
                                            <td class="px-4 py-3">{{ $resultado->posR ?? '-' }}</td>
                                            <td class="px-4 py-3">
                                                ${{ number_format($resultado->import, 2, ',', '.') }}
                                            </td>
                                            <td class="px-4 py-3">
                                                ${{ number_format($resultado->aciert, 2, ',', '.') }}
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="8" class="px-4 py-8 text-center text-gray-400">
                                                No hay resultados para esta fecha
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        <!-- Paginación -->
                        @if($this->resultados->hasPages())
                            <div class="mt-4">
                                {{ $this->resultados->links() }}
                            </div>
                        @endif
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
                                                        <h4 class="font-medium">COMIS. J. 20%:</h4>
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
@endif
</div>
