<div class="bg-[#22272b] border-gray-600 shadow-xl border px-4 pt-5 pb-4 sm:p-6 sm:pb-4 max-w-3xl rounded-lg" id="downloadTicketContent">
    <div class="text-lg flex justify-between gap-2 font-medium text-white">
        Información del Ticket
    </div>

    <div class="my-4 text-sm text-gray-600 flex flex-col gap-2 relative overflow-hidden">
        <img src="{{ asset('assets/images/logo.png') }}"
             class="w-full h-auto opacity-[0.02] absolute top-0 rounded-lg -z-0" alt="Vector Seguros Logo" />

        <div class="flex items-center w-full justify-between gap-2 font-medium">
            <div class="flex items-center gap-2 text-white">
                Ticket: <h2 class="text-white">{{ $ticket->ticket }}</h2>
            </div>
            <p class="text-white font-normal">
                {{ $ticket->code }}
            </p>
        </div>

        <div id="printContentTicket" class="flex flex-col items-center gap-2 border border-gray-600 rounded-md p-3 w-full">
            <div class="w-full flex items-center justify-center border-b border-gray-600  pb-2">
                <img src="{{ asset('assets/images/logo.png') }}" class="w-16 h-16 rounded-lg" alt="Vector Seguros Logo" />
                {{-- <h3 class="font-bold text-lg w-full text-center text-white">REIMPRESION</h3> --}}
            </div>

            <div class="flex justify-between gap-1 border-b border-gray-600 pb-2 w-full text-sm">
                <div class="flex items-center gap-2">
                    <h4 class="font-medium text-gray-300">Vendedor:</h4>
                    <p class="text-white font-bold">{{ $user->id }}</p>
                </div>
                <div class="flex items-center gap-2">
                    <h4 class="font-medium text-gray-300">Ticket:</h4>
                    <p class="text-white font-bold">{{ $ticket->ticket }}</p>
                </div>
            </div>

            <div class="flex justify-between gap-1 border-b border-gray-600 pb-2 w-full text-sm">
                <div class="flex flex-col">
                    <h4 class="font-medium text-gray-300">FECHA:</h4>
                    <p class="text-white">{{ $ticket->created_at->format('Y-m-d') }}</p>
                </div>
                <div class="flex flex-col">
                    <h4 class="font-medium text-gray-300">HORA:</h4>
                    <p class="text-white">{{ $ticket->created_at->format('H:i:s') }}</p>
                </div>
            </div>

            <div class="container text-sm">
                <div class="flex flex-col items-center w-full">
                    <!-- Loterías -->
                    <div class="w-full" wire:ignore>
                        <x-lottery-list :lotteries="$apusData" />
                    </div>

                    <!-- Datos de apuestas -->
                    <div class="flex flex-col pt-3 justify-between gap-1 pb-3 w-full text-sm text-white">
                        @forelse($apusData->reverse() as $apu)
                        <div class="6 w-full justify-between">
                            <p class="min-w-[60px] w-full font-semibold">{{ $apu->number }}</p>
                            <p class="min-w-[60px] w-full font-semibold">{{ $apu->position ? '0' . $apu->position : '' }}</p>

                            <p class="min-w-[60px] w-full font-semibold text-wrap">
                                ${{ number_format($apu->import, 2, ',', '.') }}
                            </p>
                            <p class="min-w-[60px] w-full font-semibold">{{ $apu->numberR ? '0' . $apu->numberR : '' }}</p>
                            <p class="min-w-[60px] w-full font-semibold">{{ $apu->positionR ? '0' . $apu->positionR : '' }}</p>
                        </div>
                        @empty
                            <div class="text-center px-4 text-white">
                                No hay información de apuestas.
                            </div>
                        @endforelse
                    </div>

                    <!-- Sección de total -->
                    <div class="flex pt-3 justify-between w-full text-sm border-t border-gray-600">
                        <p class="text-sm text-white flex items-center justify-center">{{ $ticket->code }}</p>
                        <div class="flex justify-end font-medium text-white">
                            <h4 class="text-lg">
                                TOTAL:
                                <span class="font-extrabold">
                                    ${{ $play->status === 'I' ? '0.00' : number_format($totalImport, 2, ',', '.') }}
                                </span>
                            </h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="flex items-center justify-between gap-2 no-print z-10" id="buttonsContainer">
            <a href="{{ route('plays-manager') }}"
               class="w-full text-sm px-3 py-1 bg-teal-500 text-white rounded-md flex justify-center items-center gap-2 hover:bg-teal-600/90 duration-200">
                <i class="fa-solid fa-ticket rotate-90"></i> Nuevo ticket
            </a>

            <button wire:click="printPDF"
                    class="w-full text-sm px-3 py-1 bg-blue-500 text-white rounded-md flex justify-center items-center gap-2 hover:bg-blue-600/90 duration-200">
                <i class="fas fa-print"></i> Imprimir
            </button>

            <button wire:click="descargarImagen"
                    class="w-full text-sm px-3 py-1 bg-green-500 text-white rounded-md flex justify-center items-center gap-2 hover:bg-green-600/90 duration-200">
                <i class="fas fa-save"></i> Guardar
            </button>
        </div>
    </div>
    <div class="pt-1 rounded-md">
        <p class="text-sm text-gray-500 text-start">
            NOTA: Si el Ticket sale recortado personalice los márgenes de impresión. Ponga 5mm en el margen izquierdo y al resto 0mm.
        </p>
    </div>
</div>

<!-- Scripts para impresión y descarga de imagen -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script>
    window.addEventListener('printContentTicket', () => {
        window.print();
    });

    function capturarDiv() {
        const buttonsContainer = document.getElementById('buttonsContainer');
        buttonsContainer.style.display = 'none';
        html2canvas(document.getElementById("downloadTicketContent")).then(canvas => {
            let link = document.createElement('a');
            link.href = canvas.toDataURL("image/png");
            link.download = 'reimpresion.png';
            link.click();
            buttonsContainer.style.display = 'flex';
        });
    }

    window.addEventListener('descargar-imagen', event => {
        capturarDiv();
    });

    document.addEventListener('DOMContentLoaded', function () {
        Livewire.on('open-share-link', (url) => {
            window.open(url, '_blank');
        });
    });
</script>
