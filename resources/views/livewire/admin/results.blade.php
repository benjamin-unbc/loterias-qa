<main class="bg-[#1b1f22] w-full h-full min-h-screen p-4 flex flex-col gap-3">
    <div class="flex flex-col gap-5 item">
        <div class="flex gap-1">
            <h2 class="font-semibold text-2xl text-white">Resultados</h2>
        </div>

        <div wire:keydown.enter="search" class="flex gap-3 justify-between text-sm mb-4">
            <div class="w-full flex flex-col gap-1 text-sm">
                <label for="dateResult" class="text-white">Buscar por fecha:</label>
                <input type="date" id="dateResult" wire:model="date" placeholder="Selecciona una fecha"
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
                    ${{ number_format($totalImporte, 2) }}
                </h2>
            </div>
            <div class="flex flex-col gap-1 w-full bg-[#333a41] border border-gray-600 p-3 rounded-lg">
                <span class="text-green-400 font-medium">Aciertos Jugadas</span>
                <h2 class="text-2xl font-bold text-white">
                    ${{ number_format($totalAciertos, 2) }}
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

                <div class="relative overflow-x-auto  rounded-lg">
                    @if ($results->hasPages())
    <div class="pt-4 mb-2">
        {{ $results->links() }}
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
                                <!-- <th scope="col" class="px-6 py-3">XA</th> -->
                                <th scope="col" class="px-6 py-3">Importe</th>
                                <th scope="col" class="px-6 py-3 text-center">Aciertos</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($results as $result)
                                <tr class="border-b border-gray-600 text-white bg-[#22272b]">
                                    <td class="px-6 py-4">{{ $result->ticket }}</td>
                                    <td class="px-6 py-4">
                                        {{ $result->lottery }}
                                    </td>                                    

                                    <td class="px-6 py-4 truncate">{{ $result->number }}</td>
                                    <td class="px-6 py-4">{{ $result->position }}</td>
                                    <td class="px-6 py-4">{{ $result->numR ?? '-' }}</td>
                                    <td class="px-6 py-4">{{ $result->posR ?? '-' }}</td>
                                    <td class="px-6 py-4">
                                        ${{ number_format($result->import, 2, '.') }} {{-- Revertido a import --}}
                                    </td>
                                    <td class="px-6 py-4 text-end">
                                        ${{ number_format($result->aciert, 2, '.') }} {{-- Revertido a aciert --}}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="px-6 py-4 text-center text-gray-300">
                                        No hay resultados
                                    </td>
                                </tr>
                            @endforelse



                        </tbody>
                        @if ($results->count())
                            <!-- Fila para el total de aciertos -->
                            <tfoot>
                                <tr class="border-t border-gray-600 text-white bg-gray-600">
                                    <td colspan="9" class="text-end px-6 py-1 font-bold text-base">
                                        Total: ${{ number_format($totalAciertos, 2) }} {{-- Asegurar que $totalAciertos se calcula con 'aciert' en el backend --}}
                                    </td>
                                </tr>
                            </tfoot>
                        @endif
                    </table>
                </div>
            </div>
        </div>

    </div>

    @livewire('show-codes')
</main>
