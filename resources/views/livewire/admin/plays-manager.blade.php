<div>
    <div class=" bg-[#1b1f22] w-full h-screen max-h-screen p-3 md:p-4 flex flex-col justify-between gap-3 lg:flex-row lg:items-start lg:justify-between">
        <div class="flex flex-col gap-3 rounded-lg item text-sm sm:w-full w-full lg:w-3/6  max-w-4xl">

            <div class="flex justify-between items-center gap-2 pb-2">
                <h2 class="font-bold text-xl text-white">Gestor de jugada</h2>
            </div>
            <div class="grid grid-cols-2 sm:grid-cols-3 md:flex md:flex-row items-center gap-1">
                <div class="flex items-center justify-center w-full">
                    @livewire('date-current')
                </div>
                <span class="hidden md:block w-full h-[1px] border-b border-gray-600 border-dashed"></span>
                <div class="flex items-center justify-center w-full">
                    @livewire('real-time')
                </div>
                <span class="hidden md:block w-full h-[1px] border-b border-gray-600 border-dashed"></span>
                <div class="col-span-2 sm:col-span-1 flex items-center justify-center gap-2 px-3 py-1 rounded-md text-xs md:text-sm bg-gray-700 text-yellow-200 w-full">
                    <i class="fa-solid fa-user-tie"></i>
                    <span>{{ Auth::user()->id }}</span>
                </div>
            </div>

            <div class="w-full bg-yellow-200/10 border border-yellow-200/80 py-2 px-2.5 grid grid-cols-2 sm:grid-cols-3 md:flex md:justify-between md:flex-wrap md:items-center gap-2 gap-x-3 text-xs 2xl:text-sm rounded-lg 2xl:justify-evenly">
                <p class="text-white text-nowrap">
                    <span class="text-white font-bold bg-yellow-200/30 w-fit px-3 py-1 rounded-md">
                        <i class="fa-solid fa-arrow-up"></i>
                    </span> = Posición
                </p>
                <p class="text-white text-nowrap">
                    <span class="text-white font-bold bg-yellow-200/30 w-fit px-3 py-1 rounded-md">Enter</span> = Tab
                </p>
                <p class="text-white text-nowrap">
                    <span class="text-white font-bold bg-yellow-200/30 w-fit px-3 py-1 rounded-md">+=</span> = Hacer
                    bajada
                </p>
                <p class="text-white text-nowrap">
                    <span class="text-white font-bold bg-yellow-200/30 w-fit px-3 py-1 rounded-md">AvPag</span> =
                    Finalizar
                </p>
            </div>

            <div class="flex flex-col gap-2 border-2 border-transparent rounded-lg duration-200 {{ $editingRowId ? 'bg-[#343f328f] p-3 border-white/20 border-dashed' : '' }}">
                <div class="flex items-center justify-center">
                    <div id="lottery-selection-box"
                        class="flex items-center justify-center border-2 border-transparent bg-[#22272b] {{ $editingRowId ? 'p-3 border-white/20 border-dashed' : '' }} rounded-lg p-2 md:p-3 w-full md:max-w-sm 2xl:max-w-md">
                        <table class="w-full text-center rounded-lg text-xs sm:text-sm max-w-full overflow-x-auto">
                            <thead>
                                <tr class="text-[10px] md:text-xs">
                                    <th class="md:ps-1 py-1 flex items-center gap-1 sm:gap-2">
                                        <input type="checkbox" id="all"
                                            class="w-5 h-5 2xl:w-6 2xl:h-6 bg-[#22272b] cursor-pointer border border-gray-300 rounded text-green-400 focus:ring-green-400"
                                            wire:click="toggleAllCheckboxes($event.target.checked)"
                                            aria-label="Seleccionar todos los horarios">
                                        <label for="all"
                                            class="font-medium select-none text-white text-[11px] sm:text-xs md:text-sm">Todos</label>
                                    </th>
                                    @foreach (['NAC', 'CHA', 'PRO', 'MZA', 'CTE', 'SFE', 'COR', 'RIO', 'ORO'] as $col)
                                        <th class="text-[10px] sm:text-[10px] md:text-xs text-white">{{ $col }}
                                        </th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($horariosConEstado as $horario)
                                    <tr data-time="{{ $horario['time'] }}" class="h-full">
                                        <td
                                            class="pt-[5px] pb-[4px] me-3 font-medium flex items-center h-full gap-1 sm:gap-2 md:p-1">
                                            <input type="checkbox" id="time-{{ $horario['time'] }}-all"
                                                class="w-5 h-5 2xl:w-6 2xl:h-6 bg-[#22272b] rounded border border-gray-300 {{ $horario['checkboxClass'] }}"
                                                {{ $horario['disabledAttr'] }}
                                                wire:model="selected.{{ $horario['time'] }}"
                                                wire:click="toggleRowCheckboxes('{{ $horario['time'] }}', $event.target.checked)"
                                                aria-label="Seleccionar horario {{ $horario['time'] }}">
                                            <label for="time-{{ $horario['time'] }}-all"
                                                class="select-none text-[11px] sm:text-xs md:text-sm text-white {{ $horario['textClass'] }}">
                                                {{ $horario['time'] }}
                                            </label>
                                        </td>
                                        @for ($col = 1; $col <= 8; $col++)
                                            <td class="p-1">
                                                <input type="checkbox"
                                                    id="time-{{ $horario['time'] }}-col-{{ $col }}"
                                                    class="w-5 h-5 2xl:w-6 2xl:h-6 bg-[#22272b] rounded border border-gray-300 {{ $horario['checkboxClass'] }}"
                                                    {{ $horario['disabledAttr'] }}
                                                    wire:model="selected.{{ $horario['time'] }}_col_{{ $col }}"
                                                    wire:click="toggleColumnCheckbox('{{ $horario['time'] }}', {{ $col }}, $event.target.checked)"
                                                    aria-label="Seleccionar opción {{ $horario['time'] }} - Columna {{ $col }}">
                                            </td>
                                        @endfor
                                        @if (in_array($horario['time'], ['15:00', '21:00']))
                                            <td class="p-1">
                                                <input type="checkbox" id="time-{{ $horario['time'] }}-col-oro"
                                                    class="w-5 h-5 2xl:w-6 2xl:h-6 bg-[#22272b] rounded border border-gray-300 {{ $horario['checkboxClass'] }}"
                                                    {{ $horario['disabledAttr'] }}
                                                    wire:model="selected.{{ $horario['time'] }}_oro"
                                                    wire:click="toggleOroCheckbox('{{ $horario['time'] }}', $event.target.checked)"
                                                    aria-label="Seleccionar opción ORO para {{ $horario['time'] }}">
                                            </td>
                                        @else
                                            <td class="p-1 sm:p-2"></td>
                                        @endif
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="flex flex-col gap-2">
                    <div class="flex justify-between gap-2 items-center border-b border-gray-600">
                        <h3 class="font-bold text-lg text-white pb-1.5">Jugadas</h3>
                        <p class="text-gray-400 text-sm">
                            @if (count($checkboxCodes) > 0)
                                Loterías:
                                @foreach ($checkboxCodes as $code)
                                    <span class="text-white font-bold">{{ $code }}</span>
                                @endforeach
                            @else
                                Loterías: No hay Loterías
                            @endif
                        </p>
                    </div>

                    {{-- CORRECCIÓN: Se eliminan los wire:keydown.enter de los inputs. El JS se encargará de esto. --}}
                    <div class="flex justify-between gap-2">
                        <div class="w-full flex flex-col gap-0.5">
                            <label for="number" class="text-sm font-medium text-white">Número</label>
                            <input type="number" id="number" wire:model.live="number" pattern="[0-9]+" inputmode="numeric" min="0" max="9999"
                                title="Solo se permiten números." maxlength="4"
                                class="block w-full py-1 px-2 text-sm bg-[#22272b] text-white border border-gray-300 rounded-md
                                       focus:ring-yellow-200 focus:border-yellow-200 disabled:bg-gray-100
                                       disabled:text-white disabled:border-gray-200 disabled:cursor-not-allowed"
                                placeholder="0" {{ $inputsDisabled ? 'disabled' : '' }} autocomplete="off"
                                onkeydown="if(['e','E','+','-','.'].includes(event.key)){event.preventDefault();} if(this.value.length>=4 && event.key.match(/[0-9]/) && !['Backspace','Delete','ArrowLeft','ArrowRight','Tab'].includes(event.key)){event.preventDefault();}" />
                            @error('number')
                                <span class="text-red-400 text-xs">{{ $message }}</span>
                            @enderror
                        </div>
                        <div class="w-full flex flex-col gap-0.5">
                            <label for="position" class="text-sm font-medium text-white">Posición</label>
                            <input type="number" id="position" wire:model.live="position" min="1" max="20"
                                class="block w-full py-1 px-2 text-sm bg-[#22272b] text-white border border-gray-300 rounded-md
                                    focus:ring-yellow-200 focus:border-yellow-200 disabled:bg-gray-100
                                    disabled:text-white disabled:border-gray-200 disabled:cursor-not-allowed"
                                placeholder="1" maxlength="2" {{ $inputsDisabled ? 'disabled' : '' }} autocomplete="off"
                                onkeydown="if(['e','E','+','-','.'].includes(event.key)){event.preventDefault();} if(this.value.length>=2 && event.key.match(/[0-9]/) && !['Backspace','Delete','ArrowLeft','ArrowRight','Tab'].includes(event.key)){event.preventDefault();}" /> <!-- maxlength y JS agregados para limitar a 2 dígitos -->
                            @error('position')
                                <span class="text-red-400 text-xs">{{ $message }}</span>
                            @enderror
                        </div>
                        <div class="w-full flex flex-col gap-0.5">
                            <label for="import" class="text-sm font-medium text-white">Importe</label>
                            <input type="number" id="import" wire:model.live="import" min="0" step="1"
                                class="block w-full py-1 px-2 text-sm bg-[#22272b] text-white border border-gray-300 rounded-md
                                    focus:ring-yellow-200 focus:border-yellow-200 disabled:bg-gray-100
                                    disabled:text-white disabled:border-gray-200 disabled:cursor-not-allowed"
                                placeholder="" {{ $inputsDisabled ? 'disabled' : '' }} autocomplete="off"
                                onfocus="this.select()" onkeydown="if(['e','E','+','-','.'].includes(event.key)){event.preventDefault();}" />
                            @error('import')
                                <span class="text-red-400 text-xs">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>

                    <h3 class="font-bold text-lg text-white pb-1.5 border-b border-gray-600">Redoblona</h3>
                    <div class="flex justify-between gap-2">
                        <div class="w-full flex flex-col gap-0.5">
                            <label for="numberR" class="text-sm font-medium text-white">Número</label>
                            <input type="number" id="numberR" wire:model.live="numberR" min="0" max="9999"
                                class="block w-full py-1 px-2 text-sm bg-[#22272b] text-white border border-gray-300 rounded-md
                                       focus:ring-yellow-200 focus:border-yellow-200 disabled:bg-gray-100
                                       disabled:text-white disabled:border-gray-200 disabled:cursor-not-allowed"
                                placeholder="0" maxlength="4" {{ $inputsDisabled ? 'disabled' : '' }} autocomplete="off"
                                onkeydown="if(['e','E','+','-','.'].includes(event.key)){event.preventDefault();} if(this.value.length>=4 && event.key.match(/[0-9]/) && !['Backspace','Delete','ArrowLeft','ArrowRight','Tab'].includes(event.key)){event.preventDefault();}" />
                            @error('numberR')
                                <span class="text-red-400 text-xs">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="w-full flex flex-col gap-0.5">
                            <label for="positionR" class="text-sm font-medium text-white">Posición</label>
                            <input type="number" id="positionR" wire:model.live="positionR" min="1" max="20"
                                class="block w-full py-1 px-2 text-sm bg-[#22272b] text-white border border-gray-300 rounded-md
                                    focus:ring-yellow-200 focus:border-yellow-200 disabled:bg-gray-100
                                    disabled:text-white disabled:border-gray-200 disabled:cursor-not-allowed"
                                placeholder="1" maxlength="2" {{ $inputsDisabled ? 'disabled' : '' }} autocomplete="off"
                                onkeydown="if(['e','E','+','-','.'].includes(event.key)){event.preventDefault();} if(this.value.length>=2 && event.key.match(/[0-9]/) && !['Backspace','Delete','ArrowLeft','ArrowRight','Tab'].includes(event.key)){event.preventDefault();}" /> <!-- maxlength y JS agregados para limitar a 2 dígitos -->
                            @error('positionR')
                                <span class="text-red-400 text-xs">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="flex justify-between gap-2 mt-1">
                    <button wire:click.prevent="saveRow" wire:loading.attr="disabled"
                        wire:target="saveRow,addRow,updateRow" wire:loading.class="opacity-50 cursor-not-allowed"
                        class="w-full text-sm px-5 py-1 rounded-md text-white bg-green-500/80 hover:bg-green-500/90
                            duration-200 flex items-center justify-center gap-2 disabled:bg-gray-300
                            disabled:text-white disabled:cursor-not-allowed disabled:hover:bg-gray-300"
                        {{ $inputsDisabled || $isSaving ? 'disabled' : '' }}>
                        <i wire:loading wire:target="saveRow,addRow,updateRow"
                            class="fa-solid fa-spinner fa-spin"></i>
                        <span>{{ $editingRowId ? 'Actualizar apuesta' : 'Agregar apuesta' }}</span>
                    </button>
                    @if ($editingRowId)
                        <button wire:click="resetForm"
                            class="w-40 bg-red-600/80 text-white px-5 py-1 rounded-md hover:bg-red-600/90 duration-200
                                   text-sm disabled:bg-gray-300 disabled:text-white disabled:cursor-not-allowed
                                   disabled:hover:bg-gray-300"
                            {{ $inputsDisabled ? 'disabled' : '' }}>
                            Cancelar
                        </button>
                    @endif
                </div>
            </div>
        </div>

        <div
            class="flex flex-col gap-5 items-center h-full border-gray-600 lg:ps-3 lg:border-s w-full sm:w-full lg:w-3/6 pb-3 lg:p-0">
            @if ($rows->count() > 0)
                <div class="w-full flex flex-col justify-between rounded-lg h-full ">
                    <div id="paginated-users" class="flex flex-col relative">
                        <div id="playsContainer"
                            class="bg-[#22272b] rounded-se-lg rounded-ss-lg overflow-y-auto max-h-[calc(100vh-8.1rem)]">
                            <div class="overflow-x-auto" x-data
                                x-on:keydown.window="
                                    if (event.key === 'PageDown') { event.preventDefault(); @this.call('sendPlays'); }
                                    if (event.key === '+' || (event.key === '=' && event.shiftKey)) { event.preventDefault(); @this.call('addRowWithDerived'); }
                                ">
                                <table
                                    class="w-full text-sm text-left rtl:text-right text-white dark:text-gray-400 relative">
                                    <thead class="text-xs text-white uppercase bg-gray-600 sticky top-0 z-10">
                                        <tr>
                                            <th scope="col" class="px-2.5 py-1">Núm</th>
                                            <th scope="col" class="px-2.5 py-1">Pos</th>
                                            <th scope="col" class="px-2.5 py-1">NúmR</th>
                                            <th scope="col" class="px-2.5 py-1">PosR</th>
                                            <th scope="col" class="px-2.5 py-1">Lot</th>
                                            <th scope="col" class="px-2.5 py-1">xFue</th>
                                            <th scope="col" class="px-2.5 py-1">Importe</th>
                                            <th scope="col" class="px-2.5 py-1 text-center">Opciones</th>
                                        </tr>
                                    </thead>
                                    <tbody class="max-h-[calc(100vh-0rem)] overflow-y-auto">

                                        @forelse ($rows  as $row)
                                            <tr id="row-{{ $row->id }}" tabindex="-1"
                                                class="bg-[#22272b] border-b border-gray-600 ">
                                                <td class="px-2 py-2">{{ $this->formatNumber($row->number) }}</td>
                                                <td class="px-2 py-2">{{ $row['position'] }}</td>
                                                <td class="px-2 py-2">{{ $row['numberR'] }}</td>
                                                <td class="px-2 py-2">{{ $row['positionR'] }}</td>
                                                <td class="px-2 py-2">{{ count(explode(',', $row['lottery'])) }}</td>
                                                <td class="px-2 py-2">{{ $row['isChecked'] ? 'X' : '' }}</td>
                                                <td class="px-2 py-2">${{ number_format($row->import, 2) }}</td>
                                                <td class="px-2 py-2 flex gap-1 items-center justify-center">
                                                    <a href="#"
                                                        wire:click.prevent="editRow({{ $row['id'] }})"
                                                        class="font-medium text-white bg-zinc-700 p-1 px-2 rounded-md hover:underline">
                                                        <i class="fa-solid fa-pen text-sm"></i>
                                                    </a>
                                                    <a href="#"
                                                        wire:click.prevent="deleteRow({{ $row['id'] }})"
                                                        class="font-medium text-white bg-zinc-700 p-1 px-2 rounded-md hover:underline">
                                                        <i class="fa-solid fa-trash text-sm"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="8" class="text-center text-white pt-5">No hay jugadas
                                                    agregadas.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div
                            class="flex flex-col gap-2 pb-3 w-full bg-[#22272b] rounded-b-lg shadow-lg z-20 pt-2 sticky bottom-0">
                            @if ($rows->count() > 0)
                                <div class="border-b border-gray-600 pb-3 px-3 flex justify-end">
                                    <h3 class="text-white dark:text-gray-400 font-medium">
                                        Total:
                                        <span class="font-extrabold text-white">
                                            ${{ number_format($total, 2) }}
                                        </span>
                                    </h3>
                                </div>
                            @endif
                            <div class="flex gap-2 px-3 justify-between md:justify-start">
                                <button wire:click="openRepeatModal" {{ count($checkboxCodes) > 0 ? '' : 'disabled' }}
                                    class="flex w-full md:w-fit items-center justify-between gap-3 px-3 py-1.5 rounded-md
                                           duration-200 {{ count($checkboxCodes) > 0 ? 'bg-green-500 cursor-pointer hover:bg-green-500/90' : 'bg-gray-500/50 cursor-not-allowed' }}">
                                    <span class="text-white font-bold text-sm">Repetir</span>
                                    <i class="fa-solid fa-repeat font-bold text-white"></i>
                                </button>

                                <button wire:click="deleteAllRows"
                                    class="flex w-full md:w-fit items-center justify-between gap-3 bg-red-600/85 px-3 py-1.5 rounded-md hover:bg-red-600 duration-200">
                                    <span class="text-white font-bold text-sm">Vaciar</span>
                                    <i class="fa-solid fa-floppy-disk font-bold text-white"></i>
                                </button>

                                <button wire:click="sendPlays"
                                    class="flex w-full md:w-fit items-center justify-between gap-3 bg-yellow-200/90 px-3 py-1.5 rounded-md hover:bg-yellow-200 duration-200">
                                    <span class="text-gray-600 font-bold text-sm">Enviar</span>
                                    <i class="fa-solid fa-share font-bold text-gray-600"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            @if ($rows->count() == 0)
                <div class="w-full flex flex-col gap-5 rounded-lg h-[40rem]">
                    <div class="flex items-center gap-3 border-b border-gray-600 pb-2">
                        <div class="w-8 h-8 bg-yellow-200 text-gray-600 rounded-full flex items-center justify-center">
                            <i class="fa-solid fa-dollar-sign"></i>
                        </div>
                        <div class="flex flex-col">
                            <h3 class="text-white font-bold text-sm">No hay apuestas</h3>
                            <p class="text-gray-400 text-xs">La lista está vacía</p>
                        </div>
                    </div>
                    <button wire:click="openRepeatLoteriaModal" {{ count($checkboxCodes) > 0 ? '' : 'disabled' }}
                        class="flex items-center justify-between gap-3 px-3 py-1.5 rounded-md duration-200
                               {{ count($checkboxCodes) > 0 ? 'bg-green-500 cursor-pointer hover:bg-green-500/90' : 'bg-gray-500/50 cursor-not-allowed' }}">
                        <span class="text-white font-bold text-sm">Repetir</span>
                        <i class="fa-solid fa-repeat font-bold text-white"></i>
                    </button>
                </div>
            @endif

            @if ($showRepeatModal)
                <x-ticket-modal wire:model="showRepeatModal" overlayClasses="bg-gray-500 bg-opacity-25">
                    <x-slot name="title"> Repetir Jugada </x-slot>
                    <x-slot name="content">
                        <div class="ps-1">
                            <label for="searchTicketNumber" class="block text-white text-sm font-bold mb-2"> Número de
                                Ticket: </label>
                            <div class="flex items-center gap-2 w-full">
                                <input type="text" id="searchTicketNumber" wire:model="searchTicketNumber"
                                    placeholder="Ingrese el número de ticket" wire:keydown.enter="searchTicket"
                                    class="block bg-[#22272b] w-full py-1.5 px-2 text-sm text-white border border-gray-300 rounded-md focus:ring-yellow-200 focus:border-yellow-200 disabled:bg-gray-100 disabled:text-white disabled:border-gray-200 disabled:cursor-not-allowed">
                                <button wire:click="searchTicket"
                                    class="flex items-center justify-between gap-1 text-white bg-green-500/80 px-4 py-2 rounded-md hover:bg-green-500/90 duration-200">
                                    Buscar </button>
                                @if ($searchTicketError)
                                    <div class="p-2 my-2 text-sm text-red-700 bg-red-100 rounded-md">
                                        {{ $searchTicketError }} </div>
                                @endif
                            </div>
                        </div>
                    </x-slot>
                    <x-slot name="footer">
                        <div class="flex justify-end w-full">
                            <button wire:click="$set('showRepeatModal', false)"
                                class="flex items-center justify-between gap-1 bg-red-500/80 text-sm text-white px-4 py-2 rounded-lg hover:bg-red-500/90 duration-200">
                                Cancelar </button>
                        </div>
                    </x-slot>
                </x-ticket-modal>
            @endif

            @if ($showApusModal && $play)
                <x-ticket-modal wire:model="showApusModal" overlayClasses="bg-gray-500 bg-opacity-25">
                    <x-slot name="title"> Información del Ticket <button wire:click="$set('showApusModal', false)"
                            id="buttonCancel" class="bg-red-500 text-white px-4 py-1 text-sm rounded-md no-print">
                            Cerrar </button> </x-slot>
                    <x-slot name="content">
                        <div class="flex items-center justify-between gap-2 no-print z-10 mt-3" id="buttonsContainer">
                            <a href="{{ route('plays-manager') }}"
                                class="w-full text-sm px-3 py-1 bg-teal-500 text-white rounded-md flex justify-center items-center gap-2 hover:bg-teal-600/90 duration-200">
                                <i class="fa-solid fa-rug rotate-90"></i> Nuevo ticket </a>
                            <button onclick="printTicket()"
                                class="w-full text-sm px-3 py-1 bg-blue-500 text-white rounded-md flex justify-center items-center gap-2 hover:bg-blue-600/90 duration-200">
                                <i class="fas fa-print"></i> Imprimir </button>
                            <button onclick="guardarTicket()"
                                class="w-full text-sm px-3 py-1 bg-green-500 text-white rounded-md flex justify-center items-center gap-2 hover:bg-green-600/90 duration-200">
                                <i class="fas fa-save"></i> Guardar </button>
                            <button wire:click="shareTicket('{{ $play->ticket }}')" onclick="guardarTicket()"
                                class="w-full text-sm px-3 py-1 bg-yellow-200 text-gray-600 rounded-md flex justify-center items-center gap-2 hover:bg-yellow-200/85 duration-200">
                                <i class="fas fa-share"></i> Compartir </button>
                        </div>
                        <div class="pagina-carta">
                            <div id="ticketContainer" data-code="{{ $play->code }}" data-ticket="{{ $play->ticket }}"
                                class="w-[80mm] mx-auto p-2 text-black bg-white relative">
                                <div class="flex items-center justify-center mb-2"> <img
                                        src="{{ asset('assets/images/logo.png') }}" class="w-16 h-16" alt="Logo" />
                                </div>
                                {{-- <h3 class="text-center font-bold text-lg"> REIMPRESIÓN </h3> --}}
                                <div class="flex justify-between border-b border-gray-400 py-1 text-lg"> <span>Vendedor:
                                        <strong>{{ $play->user_id }}</strong></span> <span>Ticket:
                                        <strong>{{ $play->ticket }}</strong></span> </div><br>
                                <div class="flex justify-between text-lg py-2"style="border-bottom: 3px solid black;">
                                    <div>
                                        <p class="font-bold">FECHA:</p>
                                        <p class="font-bold">{{ $play->date }}</p>
                                    </div>
                                    <div>
                                        <p class="font-bold">HORA:</p>
                                        <p class="font-bold">{{ $play->time }}</p>
                                    </div>
                                </div>
                                <div class="space-y-4">
                                    @foreach ($groups as $block)
                                        <div class="grid grid-cols-6 gap-2 text-sm font-bold text-black py-1"
                                            style="border-bottom: 3px solid black;">
                                            @foreach ($block['codes_display'] as $lot)
                                                <div class="text-center">{{ $lot }}</div>
                                            @endforeach
                                        </div>
                                        <div class="space-y-1 text-sm">
                                            @foreach ($block['numbers'] as $item)
                                                <div class="grid grid-cols-6 px-2">
                                                    <span class="font-semibold col-span-1">{{ $item['number'] }}</span>
                                                    <span class="text-center col-span-1">{{ $item['pos'] }}</span>
                                                    <span
                                                        class="text-left col-span-2">${{ number_format($item['imp'], 2, ',', '.') }}</span>
                                                    <span
                                                        class="text-center col-span-1">{{ $item['numR'] ? str_pad($item['numR'], 2, '*', STR_PAD_LEFT) : '' }}</span>
                                                    <span class="text-center col-span-1">{{ $item['posR'] }}</span>
                                                </div>
                                            @endforeach
                                        </div>
                                        <hr class="py-1" style="border-bottom: 3px solid black;">
                                    @endforeach
                                </div>
                                <div>
                                    <div class="flex justify-end font-bold">
                                        <h4 class="text-lg"> TOTAL: <span class="font-extrabold">
                                                ${{ number_format($totalImport ?? $play->pay, 2, ',', '.') }} </span> </h4>
                                    </div>
                                    <div class="flex justify-end">
                                        <p class="text-sm text-gray-500"> {{ $play->code }} </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <p class="text-xs text-center mt-2"> NOTA: Ajuste márgenes en 0mm o 5mm para evitar recortes.
                        </p>
                    </x-slot>
                    <x-slot name="footer"></x-slot>
                </x-ticket-modal>
            @endif

        </div>
    </div>
    @push('scripts')
        <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
        <script>
            document.addEventListener("keydown", function(event) {
                const inputs = {
                    number: document.getElementById("number"),
                    position: document.getElementById("position"),
                    import: document.getElementById("import"),
                    numberR: document.getElementById("numberR"),
                    positionR: document.getElementById("positionR")
                };
                const activeElement = document.activeElement;
                const key = event.key;
                console.log("keydown detected", key, activeElement ? activeElement.id : null);

                const alpineShortcutKeys = ['PageDown', '+', '='];
                if (alpineShortcutKeys.includes(key) && (key !== '=' || event.shiftKey)) {
                    return;
                }

                if (key === "Enter" && activeElement && activeElement.type === 'checkbox') {
                    if (document.getElementById('lottery-selection-box') && document.getElementById('lottery-selection-box').contains(activeElement)) {
                        event.preventDefault();
                        if(inputs.number) { inputs.number.focus(); inputs.number.select(); }
                        return;
                    }
                }

                if (!Object.values(inputs).includes(activeElement)) return;

                if (key === "ArrowUp") {
                    event.preventDefault();
                    if(inputs.position) { inputs.position.focus(); inputs.position.select(); }
                    return;
                }

                if (key === "Enter") {
                    event.preventDefault();
                    if (activeElement === inputs.number) {
                        if (inputs.number.value.trim() === "") return;
                        if(inputs.import) { inputs.import.focus(); inputs.import.select(); }
                    } else if (activeElement === inputs.position) {
                        if(inputs.import) { inputs.import.focus(); inputs.import.select(); }
                    } else if (activeElement === inputs.import) {
                        if(inputs.numberR) { inputs.numberR.focus(); inputs.numberR.select(); }
                    } else if (activeElement === inputs.numberR) {
                        if(inputs.positionR) { inputs.positionR.focus(); inputs.positionR.select(); }
                    } else if (activeElement === inputs.positionR) {
                        // Buscar el wire:id más cercano al input number (root del componente)
                        let root = inputs.number;
                        while (root && !root.hasAttribute('wire:id')) {
                            root = root.parentElement;
                        }
                        if (root && root.hasAttribute('wire:id')) {
                            const inst = window.Livewire.find(root.getAttribute('wire:id'));
                            if (inst) {
                                inst.call('saveRow');
                            } else {
                                alert('No se encontró el componente Livewire PlaysManager.');
                            }
                        } else {
                            alert('No se encontró el root wire:id para PlaysManager.');
                        }
                    }
                }
            });

            Livewire.on('focus-on-input', (event) => {
                setTimeout(() => {
                    const numberInput = document.getElementById("number");
                    if (numberInput) {
                        numberInput.focus();
                        numberInput.select();
                    }
                }, 100);
            });

            // Scroll automático a la última jugada agregada
            Livewire.on('scroll-to-last-play', (event) => {
                setTimeout(() => {
                    const playId = event.playId;
                    const playRow = document.getElementById(`row-${playId}`);
                    const playsContainer = document.getElementById('playsContainer');
                    
                    if (playRow && playsContainer) {
                        // Hacer scroll suave hasta la fila de la jugada
                        playRow.scrollIntoView({ 
                            behavior: 'smooth', 
                            block: 'end',
                            inline: 'nearest'
                        });
                        
                        // Resaltar brevemente la fila para indicar que es nueva
                        playRow.style.backgroundColor = '#4ade80';
                        setTimeout(() => {
                            playRow.style.backgroundColor = '';
                        }, 1000);
                    } else if (playsContainer) {
                        // Fallback: scroll al final del contenedor
                        playsContainer.scrollTop = playsContainer.scrollHeight;
                    }
                }, 150);
            });

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


            function guardarTicket() {
                const ticket = document.getElementById('ticketContainer');
                if (!ticket) return;
                html2canvas(ticket, { scale: 2 }).then(function(canvas) {
                    const link = document.createElement('a');
                    link.download = 'ticket.png';
                    link.href = canvas.toDataURL('image/png');
                    link.click();
                });
            }
        </script>
    @endpush
    @push('styles')
        <style>    @media print {
        @page {
            size: letter;
            margin: 0;
        }

        html,
        body {
            margin: 0;
            padding: 0;
            width: 100%;
            height: 100%;
            -webkit-print-color-adjust: exact;
            overflow: hidden;
        }
    }
        /* Ocultar flechas de los inputs type=number */
        input[type=number]::-webkit-inner-spin-button, 
        input[type=number]::-webkit-outer-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }
        input[type=number] {
            -moz-appearance: textfield;
        }
        /* Evitar selección automática solo en el campo Número */
#number {
    -webkit-user-select: none;
    -moz-user-select: none;
    -ms-user-select: none;
    user-select: none;
}

#number:focus {
    -webkit-user-select: text;
    -moz-user-select: text;
    -ms-user-select: text;
    user-select: text;
}

#number::selection {
    background: transparent;
}

#number::-moz-selection {
    background: transparent;
}
        </style>
    @endpush
