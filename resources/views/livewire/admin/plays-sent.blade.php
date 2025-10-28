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
                                <button wire:click='viewTicket("{{ $playItem->ticket }}")'
                                        class="font-medium text-center text-yellow-200 bg-gray-700 p-1 px-2 rounded-md
                                               hover:underline hover:bg-gray-700/50 duration-200"
                                        title="Ver ticket">
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
                                            
                                            // Mapeo de códigos del sistema a códigos cortos (igual que PlaysManager)
                                            $systemToShortCodes = [
                                                'NAC1015' => 'AB', 'CHA1015' => 'CH1', 'PRO1015' => 'QW', 'MZA1015' => 'M10', 'CTE1015' => '!',
                                                'ER' => 'SFE1015', 'SD' => 'COR1015', 'RT' => 'RIO1015', 'Q' => 'NAC1200', 'CH2' => 'CHA1200',
                                                'W' => 'PRO1200', 'M1' => 'MZA1200', 'M' => 'CTE1200', 'R' => 'SFE1200', 'T' => 'COR1200',
                                                'K' => 'RIO1200', 'A' => 'NAC1500', 'CH3' => 'CHA1500', 'E' => 'PRO1500', 'M2' => 'MZA1500',
                                                'Ct3' => 'CTE1500', 'D' => 'SFE1500', 'L' => 'COR1500', 'J' => 'RIO1500', 'S' => 'ORO1800',
                                                'F' => 'NAC1800', 'CH4' => 'CHA1800', 'B' => 'PRO1800', 'M3' => 'MZA1800', 'Z' => 'CTE1800',
                                                'V' => 'SFE1800', 'H' => 'COR1800', 'U' => 'RIO1800', 'N' => 'NAC2100', 'CH5' => 'CHA2100',
                                                'P' => 'PRO2100', 'M4' => 'MZA2100', 'G' => 'CTE2100', 'I' => 'SFE2100', 'C' => 'COR2100',
                                                'Y' => 'RIO2100', 'O' => 'ORO2100',
                                                // Nuevos códigos para las loterías adicionales
                                                'NQN1015' => 'NQ1', 'MIS1030' => 'MI1', 'Rio1015' => 'RN1', 'Tucu1130' => 'TU1', 'San1015' => 'SG1',
                                                'NQN1200' => 'NQ2', 'MIS1215' => 'MI2', 'JUJ1200' => 'JU1', 'Salt1130' => 'SA1', 'Rio1200' => 'RN2',
                                                'Tucu1430' => 'TU2', 'San1200' => 'SG2', 'NQN1500' => 'NQ3', 'MIS1500' => 'MI3', 'JUJ1500' => 'JU2',
                                                'Salt1400' => 'SA2', 'Rio1500' => 'RN3', 'Tucu1730' => 'TU3', 'San1500' => 'SG3', 'NQN1800' => 'NQ4',
                                                'MIS1800' => 'MI4', 'JUJ1800' => 'JU3', 'Salt1730' => 'SA3', 'Rio1800' => 'RN4', 'Tucu1930' => 'TU4',
                                                'San1945' => 'SG4', 'NQN2100' => 'NQ5', 'JUJ2100' => 'JU4', 'Rio2100' => 'RN5', 'Salt2100' => 'SA4',
                                                'Tucu2200' => 'TU5', 'MIS2115' => 'MI5', 'San2200' => 'SG5'
                                            ];
                                            
                                            $processedGroups = $groupedByPlayId->map(function ($groupOfApusFromSameOriginalPlay) use ($systemToShortCodes) {
                                                $representativeApu = $groupOfApusFromSameOriginalPlay->first();
                                                
                                                $lotteryCodes = $groupOfApusFromSameOriginalPlay
                                                    ->pluck('lottery')
                                                    ->filter()
                                                    ->unique()
                                                    ->values()
                                                    ->toArray();
                                                
                                                $displayCodes = [];
                                                foreach ($lotteryCodes as $code) {
                                                    $code = trim($code);
                                                    if (preg_match_all('/[A-Za-z]+\d{4}/', $code, $matches)) {
                                                        foreach ($matches[0] as $validCode) {
                                                            $displayCodes[] = $validCode;
                                                        }
                                                    } elseif (preg_match('/^[A-Za-z]+\d{4}$/', $code)) {
                                                        $displayCodes[] = $code;
                                                    }
                                                }
                                                
                                                $uniqueDisplayCodes = array_unique($displayCodes);
                                                $codesDisplayString = implode(', ', $uniqueDisplayCodes);
                                                
                                                return [
                                                    'codes_display_string' => $codesDisplayString,
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
                                                        'codes_display' => array_map(function($code) {
                                                            if (preg_match('/^([A-Za-z]+)(\d{4})$/', $code, $matches)) {
                                                                $letters = $matches[1];
                                                                $time = $matches[2];
                                                                $shortTime = substr($time, 0, 2);
                                                                return $letters . $shortTime;
                                                            }
                                                            return $code;
                                                        }, explode(', ', $key)),
                                                        'numbers' => collect($items->pluck('numbers')->flatten(1)->all())->values()->all(),
                                                    ];
                                                })
                                                ->values();
                                        @endphp

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