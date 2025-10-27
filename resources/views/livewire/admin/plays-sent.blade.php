<div class="bg-[#1b1f22] w-full h-full min-h-screen p-4 flex flex-col gap-3">
    <div class="flex flex-col gap-5">
        <div class="flex justify-between gap-3">
            <h2 class="font-semibold text-2xl text-white">Jugadas enviadas</h2>
        </div>

        <div wire:keydown.enter="applyFilters" class="flex gap-3 justify-between text-sm mb-4">
            <div class="w-full flex flex-col gap-1">
                <label for="dateSend" class="text-white">Buscar por fecha:</label>
                <input type="date" id="dateSend" name="dateSend" wire:model.defer="filterDateSend"
                       class="w-full border bg-[#22272b] text-white text-sm border-gray-600 rounded-md px-2 py-1
                              focus:border-yellow-200">
            </div>
            <div class="w-full flex flex-col gap-1">
                <label for="typeTicket" class="text-white">Tipo de Ticket</label>
                <select name="typeTicket" id="typeTicket" wire:model.defer="filterTypeTicket"
                        class="w-full text-sm border bg-[#22272b] text-white border-gray-600 rounded-md px-2 py-1
                               focus:border-yellow-200">
                    <option value="">Jugadas y Redoblonas</option>
                    <option value="J">Jugadas</option>
                    <option value="R">Redoblonas</option>
                </select>
            </div>
            <div class="flex items-end space-x-2">
                <button wire:click="applyFilters"
                        class="text-sm px-3 py-1 border border-green-500 bg-green-500 text-white rounded-md
                               flex items-center gap-2 hover:border-green-600/90 hover:bg-green-600/90 duration-200">
                    Buscar
                </button>
                <button wire:click="resetFilters"
                        class="bg-[#22272b] w-fit border border-green-300 text-sm px-5 py-1 rounded-md text-green-400
                               hover:bg-green-100/20 duration-200">
                    Reiniciar
                </button>
            </div>
        </div>
    </div>

    <div class="flex justify-between gap-3">
        <div class="flex flex-col gap-1 w-full bg-[#333a41] border border-gray-600 p-3 rounded-lg">
            <span class="text-green-400 font-medium">Total</span>
            <h2 class="text-2xl font-bold text-white">
                ${{ number_format($totalGlobal, 2, ',', '.') }}
            </h2>
        </div>
        <div class="flex flex-col gap-1 w-full bg-[#333a41] border border-gray-600 p-3 rounded-lg">
            <span class="text-green-400 font-medium">Total por página</span>
            <h2 class="text-2xl font-bold text-white">
                ${{ number_format($totalPorPagina, 2, ',', '.') }}
            </h2>
        </div>
    </div>

    <div class="bg-[#22272b] rounded-lg">
        <div id="paginated-playsSent" class="relative overflow-x-auto rounded-lg">
            @if ($playsSent->hasPages())
                <div class="pt-4 px-4 mb-2">
                    {{ $playsSent->links(data: ['scrollTo' => '#paginated-playsSent']) }}
                </div>
            @endif

            <table class="w-full text-sm text-left rtl:text-right text-gray-500">
                <thead class="text-xs text-white uppercase bg-gray-600">
                    <tr>
                        <th scope="col" class="px-6 py-3">Hora</th>
                        <th scope="col" class="px-6 py-3">Ticket</th>
                        <th scope="col" class="px-6 py-3">Tipo</th>
                        <th scope="col" class="px-6 py-3">Apu</th>
                        <th scope="col" class="px-6 py-3">Lot</th>
                        <th scope="col" class="px-6 py-3">Pago</th>
                        <th scope="col" class="px-6 py-3">Importe</th>
                        <th scope="col" class="px-6 py-3 text-center">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($playsSent as $playItem)
                        <tr class="border-b border-gray-600 text-white {{ $playItem->status === 'I' ? 'bg-red-500/30' : 'bg-[#22272b]' }}">
                            <td class="px-6 py-4 {{ $playItem->status === 'I' ? 'line-through' : ''}}">
                                {{ $playItem->time }}
                            </td>
                            <td class="px-6 py-4 {{ $playItem->status === 'I' ? 'line-through' : ''}}">
                                {{ $playItem->ticket }}
                            </td>
                            <td class="px-6 py-4 {{ $playItem->status === 'I' ? 'line-through' : ''}} truncate">
                                {{ $playItem->type }}
                            </td>
                            <td class="px-6 py-4 {{ $playItem->status === 'I' ? 'line-through' : ''}}">
                                {{ $playItem->apu }}
                            </td>
                            <td class="px-6 py-4 {{ $playItem->status === 'I' ? 'line-through' : ''}}">
                                {{ count(array_unique(explode(',', $playItem->lot))) }}
                            </td>
                            <td class="px-6 py-4 {{ $playItem->status === 'I' ? 'line-through' : ''}}">
                                No
                            </td>
                            <td class="px-6 py-4 {{ $playItem->status === 'I' ? 'line-through' : ''}}">
                                @if($playItem->status === 'I' && isset($play) && $play->ticket == $playItem->ticket && isset($totalImport) && $totalImport > 0)
                                    ${{ number_format($totalImport, 2, ',', '.') }}
                                @else
                                    ${{ number_format($playItem->amount ?? 0, 2, ',', '.') }}
                                @endif
                            </td>
                            <td class="px-2 py-4 flex gap-1 items-center justify-center">
                                <button wire:click='viewApus("{{ $playItem->ticket }}")'
                                        class="font-medium text-center text-yellow-200 bg-gray-700 p-1 px-2 rounded-md
                                               hover:underline hover:bg-gray-700/50 duration-200">
                                    <i class="fa-solid fa-rug rotate-90"></i>
                                </button>

                                @if($playItem->status === 'A' && $playItem->statusPlay === 'A')
                                    <button wire:click="confirmDisablePlay('{{ $playItem->ticket }}')"
                                            class="font-medium text-red-400 bg-gray-700 p-1 px-2 rounded-md
                                                   hover:underline hover:bg-gray-700/50 duration-200">
                                        <i class="fa-solid fa-circle-minus"></i>
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-6 py-4 text-center text-gray-500">
                                No hay jugadas enviadas
                            </td>
                        </tr>
                    @endforelse

                    @if($showConfirmationModal)
                        <x-confirmation-modal wire:model="showConfirmationModal" overlayClasses="bg-gray-500 bg-opacity-25">
                            <x-slot name="title">
                                <h3 class="text-white font-semibold text-lg">Cancelar Jugada</h3>
                            </x-slot>
                            <x-slot name="content">
                                <p class="text-white">
                                    ¿Estás seguro que deseas cancelar esta jugada? <br>
                                    Esta acción no se puede deshacer.
                                </p>
                            </x-slot>
                            <x-slot name="footer">
                                <button wire:click="disablePlay"
                                        class="w-full inline-flex justify-center rounded-md border border-transparent
                                               shadow-sm px-4 py-1.5 bg-red-600 text-base font-medium text-white
                                               hover:bg-red-700 focus:outline-none sm:ml-3 sm:w-auto sm:text-sm">
                                    Aceptar
                                </button>
                                <button wire:click="$set('showConfirmationModal', false)"
                                        class="w-full inline-flex justify-center rounded-md shadow-sm px-4 py-1.5 bg-white
                                               text-base font-medium text-zinc-800 hover:bg-blue-100 focus:outline-none
                                               sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                                    Cerrar
                                </button>
                            </x-slot>
                        </x-confirmation-modal>
                    @endif

                    @if($showApusModal && $play)
                        <x-ticket-modal wire:model="showApusModal" overlayClasses="bg-gray-500 bg-opacity-25">
                            <x-slot name="title">
                                Información del Ticket
                                <button onclick="cerrarModal()" id="buttonCancel"
                                        class="bg-red-500 text-white px-4 py-1 text-sm rounded-md no-print">
                                    Cerrar
                                </button>
                            </x-slot>

                            
                            <x-slot name="content">
                                {{-- DEBUG INFO --}}
                                @if(session('debug_info') && session('debug_info')['ticket'] === '24-0008')
                                    <div class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded mb-4 no-print">
                                        <h4 class="font-bold">DEBUG INFO - Raw APUs:</h4>
                                        <p><strong>Count:</strong> {{ session('debug_info')['raw_apus_count'] }}</p>
                                        <p><strong>Loterías únicas en BD:</strong> {{ implode(', ', session('debug_info')['unique_lotteries']) }}</p>
                                        <details class="mt-2">
                                            <summary class="cursor-pointer font-semibold">Ver datos completos</summary>
                                            <pre class="mt-2 text-xs overflow-auto max-h-40">{{ json_encode(session('debug_info')['raw_apus_data'], JSON_PRETTY_PRINT) }}</pre>
                                        </details>
                                    </div>
                                @endif
                                
                                @if(session('debug_groups') && session('debug_groups')['ticket'] === '24-0008')
                                    <div class="bg-blue-100 border border-blue-400 text-blue-700 px-4 py-3 rounded mb-4 no-print">
                                        <h4 class="font-bold">DEBUG INFO - Processed Groups:</h4>
                                        <details class="mt-2">
                                            <summary class="cursor-pointer font-semibold">Ver grupos procesados</summary>
                                            <pre class="mt-2 text-xs overflow-auto max-h-40">{{ json_encode(session('debug_groups')['processed_groups'], JSON_PRETTY_PRINT) }}</pre>
                                        </details>
                                    </div>
                                @endif
                                
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
    
                                    <button wire:click="shareTicket('{{ $play->ticket }}')" onclick="guardarTicket()"
                                            class="w-full text-sm px-3 py-1 bg-yellow-200 text-gray-600 rounded-md
                                                   flex justify-center items-center gap-2 hover:bg-yellow-200/85 duration-200">
                                        <i class="fas fa-share"></i> Compartir
                                    </button>
                                </div>
                                <div id="ticketContainer" data-code="{{ $play->code }}" data-ticket="{{ $play->ticket }}"
                                     class="w-[80mm] mx-auto p-2 text-black bg-white relative">
                                    <div class="flex items-center justify-center mb-2">
                                        @if(Auth::user()->hasRole('Cliente') && Auth::user()->profile_photo_path)
                                            <img src="{{ asset('storage/' . Auth::user()->profile_photo_path) }}"
                                                 class="w-16 h-16 rounded-lg object-cover border-2 border-yellow-300"
                                                 alt="{{ Auth::user()->first_name }}" />
                                        @elseif(Auth::user()->hasRole('Cliente'))
                                            <img src="{{ asset('assets/images/logo.png') }}"
                                                 class="w-16 h-16 rounded-lg border-2 border-blue-300"
                                                 alt="Cliente" />
                                        @else
                                            <img src="{{ asset('assets/images/logo.png') }}"
                                                 class="w-16 h-16"
                                                 alt="Logo" />
                                        @endif
                                    </div>

                                    {{-- <h3 class="text-center font-bold text-lg">
                                        REIMPRESIÓN
                                    </h3> --}}

                                    <div class="flex justify-between border-b border-gray-400 py-1 text-lg">
                                        <span>Vendedor: <strong>{{ $play->user_id }}</strong></span>
                                        <span>Ticket: <strong>{{ $play->ticket }}</strong></span>
                                    </div><br>

                                    <div class="flex justify-between text-lg py-1"style="border-bottom: 3px solid black;">
                                        <div>
                                            <p class="font-semibold">FECHA:</p>
                                            <p class="font-semibold">{{ $play->date }}</p>
                                        </div>
                                        <div>
                                            <p class="font-semibold">HORA:</p>
                                            <p class="font-semibold">{{ $play->time }}</p>
                                        </div>
                                    </div>

                                    <div class="space-y-6">

                                        @foreach($groups as $block)
                                            {{-- Encabezado de loterías --}}
                                             <div class="text-center text-black font-bold py-1" style="border-bottom: 3px solid black;">
                                                {{ implode(' ', $block['codes_display']) }}
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
                                                    ${{ number_format($totalImport, 2, ',', '.') }}
                                                </span>
                                            </h4>
                                        </div>
                                        <div class="flex justify-end">
                                            <p class="text-sm text-gray-500">
                                                {{ $play->code }}
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
                </tbody>
            </table>

            @if ($playsSent->hasPages())
                <div class="pt-4 px-4 mb-2">
                    {{ $playsSent->links(data: ['scrollTo' => '#paginated-playsSent']) }}
                </div>
            @endif
        </div>
    </div>

    <div class="pagina-carta" style="display:none;">
        <img id="ticketImagen" alt="Ticket Quiniela">
    </div>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script>
    // Función simple para cerrar el modal
    function cerrarModal() {
        window.location.reload();
    }

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

    async function generarImagenTicket() {
        const ticketElem = document.getElementById('ticketContainer');
        if (!ticketElem) return;

        const buttons = document.getElementById('buttonsContainer');
        const closeBtn = document.getElementById('buttonCancel');
        if (buttons) buttons.style.display = 'none';
        if (closeBtn) closeBtn.style.display = 'none';

        try {
            const canvas = await html2canvas(ticketElem, { scale: 2 });
            const imgData = canvas.toDataURL("image/png");

            const ticketImg = document.getElementById('ticketImagen');
            if (ticketImg) {
                ticketImg.src = imgData;
            }
        } catch (error) {
            console.error('Error generando imagen:', error);
        } finally {
            if (buttons) buttons.style.display = 'flex';
            if (closeBtn) closeBtn.style.display = 'block';
        }
    }

    async function guardarTicket() {
        await generarImagenTicket();
        const ticketImg = document.getElementById('ticketImagen');
        if (!ticketImg.src) return;
        
        const ticketContainer = document.getElementById('ticketContainer');
        const ticketNumber = ticketContainer.getAttribute('data-ticket');
        const ticketCode = ticketContainer.getAttribute('data-code');
        const fileName = "Ticket " + ticketNumber + " (" + ticketCode + ").jpg";
        
        const link = document.createElement('a');
        link.href = ticketImg.src;
        link.download = fileName;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }

    window.addEventListener('descargar-imagen', () => {
        guardarTicket();
    });
    </script>

    <style>
    @media print {
        .pagina-carta, .pagina-carta * {
            visibility: visible !important;
            display: block !important;
        }
        @page {
            size: letter;
            margin: 0;
        }
        html, body {
            margin: 0;
            padding: 0;
            width: 100%;
            height: 100%;
            -webkit-print-color-adjust: exact;
            overflow: hidden;
        }
        .pagina-carta img {
            width: 100%;
            height: auto;
            object-fit: contain;
        }
    }
    </style>
</div>