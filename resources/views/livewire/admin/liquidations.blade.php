<div class="bg-[#1b1f22] w-full h-full min-h-screen p-4 flex flex-col gap-3">
    <div class="flex flex-col gap-5 item">

        <div class="flex gap-1 items-center">
            <h2 class="font-semibold text-2xl text-white">Liquidaciones</h2>
        </div>

        <div wire:keydown.enter="search" class="flex gap-3 justify-between text-sm mb-2">
            <div class="w-full flex flex-col gap-1 text-sm">
                <label for="dateResult" class="text-white">Buscar por fecha:</label>
                <input
                  type="date"
                  id="dateResult"
                  wire:model.lazy="date"
                  max="{{ \Carbon\Carbon::yesterday()->format('Y-m-d') }}"
                  class="w-full border bg-[#22272b] text-white text-sm border-gray-600 rounded-md p-2 py-1 focus:border-yellow-200"
                />
            </div>
            <div class="flex items-end space-x-2">
                <button
                    wire:click="search"
                    class="text-sm px-3 py-1 border border-green-500 bg-green-500 text-white rounded-md flex items-center gap-2 hover:border-green-600/90 hover:bg-green-600/90 duration-200"
                >
                    Buscar
                </button>
                <button
                    wire:click="resetFilter"
                    class="bg-[#22272b] w-fit border border-green-300 text-sm px-5 py-1 rounded-md text-green-400 hover:bg-green-100/20 duration-200"
                >
                    Reiniciar
                </button>
            </div>
        </div>

        <script>
            document.addEventListener('livewire:load', () => {
                const dateInput = document.getElementById('dateResult');
                dateInput.addEventListener('change', function () {
                    const d = this.valueAsDate;
                    if (!d) return;
                    if (d.getDay() === 6) {
                        window.dispatchEvent(new CustomEvent('notify', {
                            detail: {
                                message: "No se pueden seleccionar domingos.",
                                type: 'error'
                            }
                        }));
                        this.value = "";
                        @this.set('date', null);
                    }
                });
            });
        </script>

        @if (!\Carbon\Carbon::parse($date)->isToday())
            <div class="flex flex-col gap-5">
                <div class="flex justify-between gap-1">
                    <div class="flex items-center gap-2">
                        <h2 class="font-medium text-lg text-white">Resumen del día - </h2>
                        <p class="bg-gray-600 w-fit px-3 py-1 text-sm rounded-md text-white font-medium">
                            {{ \Carbon\Carbon::parse($date)->format('d/m/Y') }}
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        <button
                            wire:click="printPDF"
                            class="text-sm px-3 py-1 bg-blue-500 text-white rounded-lg flex items-center gap-2 hover:bg-blue-600/90 duration-200"
                        >
                            <i class="fas fa-print"></i> Imprimir
                        </button>

                        <button
                            wire:click="descargarImagen"
                            class="text-sm px-3 py-1 bg-green-500 text-white rounded-lg flex items-center gap-2 hover:bg-green-600/90 duration-200"
                        >
                            <i class="fas fa-save"></i> Guardar
                        </button>

                        <button
                            class="text-sm px-3 py-1 bg-yellow-200 text-gray-600 rounded-lg flex items-center gap-2 hover:bg-yellow-200/85 duration-200"
                        >
                            <i class="fas fa-share"></i> Compartir
                        </button>
                    </div>
                </div>

                <div id="liquidationContainer" class="w-[80mm] mx-auto p-2 text-black bg-white relative">
                    <!-- <img src="{{ asset('assets/images/logo.png') }}" class="w-full opacity-[0.02] absolute top-0 left-0 pointer-events-none" /> -->

                    <div class="relative z-10">
                        <h3 class="font-medium border-b pb-2 w-full text-center">
                            {{ Auth::user()->id }}
                        </h3>

                        <div class="flex justify-between gap-1 border-b pb-2 w-full text-sm">
                            <div class="flex flex-col">
                                <h4 class="font-medium">FECHA:</h4>
                                
                            </div>
                            <div class="flex flex-col">
                                {{-- <h4 class="font-medium">RECORRIDO</h4>
                                <p>BANCA 17 ALE</p> --}}
                                <p>{{ \Carbon\Carbon::parse($date)->format('d/m/Y') }}</p>
                            </div>
                        </div>

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
                                    @forelse ($results as $result)
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

                                <div class="flex flex-col pt-3 gap-1 border-b pb-2 w-full text-sm">
                                    <div class="flex justify-between">
                                        <h4 class="font-medium">PREVIA:</h4>
                                        <p>{{ number_format($previaTotalApus, 2) }}</p>
                                    </div>
                                    <div class="flex justify-between">
                                        <h4 class="font-medium">MAÑANA:</h4>
                                        <p>{{ number_format($mananaTotalApus, 2) }}</p>
                                    </div>
                                    <div class="flex justify-between">
                                        <h4 class="font-medium">MATUTINA:</h4>
                                        <p>{{ number_format($matutinaTotalApus, 2) }}</p>
                                    </div>
                                    <div class="flex justify-between">
                                        <h4 class="font-medium">TARDE:</h4>
                                        <p>{{ number_format($tardeTotalApus, 2) }}</p>
                                    </div>
                                    <div class="flex justify-between">
                                        <h4 class="font-medium">NOCHE:</h4>
                                        <p>{{ number_format($nocheTotalApus, 2) }}</p>
                                    </div>
                                </div>

                                <div class="flex flex-col pt-3 gap-1 border-b pb-2 w-full text-sm">
                                    <div class="flex justify-between">
                                        <h4 class="font-medium">JUGADAS:</h4>
                                        <p>{{ number_format($totalApus, 2) }}</p>
                                    </div>
                                    <div class="flex justify-between">
                                        <h4 class="font-medium">TOTAL PASE:</h4>
                                        <p>{{ number_format($totalApus, 2) }}</p>
                                    </div>
                                    <div class="flex justify-between">
                                        <h4 class="font-medium">COMIS. J. {{ auth()->user()->hasAnyRole(['Administrador']) ? '20%' : (auth()->user()->associatedClient->commission_percentage ?? 20.00) }}%:</h4>
                                        <p>{{ number_format($comision, 2) }}</p>
                                    </div>
                                    <div class="flex justify-between">
                                        <h4 class="font-medium">TOT.ACIERT:</h4>
                                        <p>{{ number_format($totalAciert, 2) }}</p>
                                    </div>
                                </div>

                                <div class="flex flex-col pt-3 gap-1 border-b pb-2 w-full text-sm">
                                    <div class="flex justify-between">
                                        <h4 class="font-medium">DEJA PASE:</h4>
                                        <p>{{ number_format($totalGanaPase, 2) }}</p>
                                    </div>
                                    <div class="flex justify-between">
                                        <h4 class="font-medium">TOTAL DEJA:</h4>
                                        <p>{{ number_format($totalGanaPase, 2) }}</p>
                                    </div>
                                </div>

                                <div class="flex flex-col pt-3 gap-1 border-b pb-2 w-full text-sm">
                                    <div class="flex justify-between">
                                        <h4 class="font-medium">GENER. DEJA:</h4>
                                        <p>{{ number_format($totalGanaPase, 2) }}</p>
                                    </div>
                                    <div class="flex justify-between">
                                        <h4 class="font-medium">ANTERI:</h4>
                                        <p>{{ number_format($anteri, 2) }}</p>
                                    </div>
                                    @if(\Carbon\Carbon::parse($date)->isSaturday())
                                        <div class="flex justify-between">
                                            <h4 class="font-medium">COMI DEJA SEM:</h4>
                                            <p>{{ number_format($comi_deja_sem, 2) }}</p>
                                        </div>
                                    @endif
                                    <div class="flex justify-between">
                                        <h4 class="font-medium">UD DEJA:</h4>
                                        <p>{{ number_format($udDeja, 2) }}</p>
                                    </div>
                                </div>

                                <div class="flex flex-col pt-3 gap-1 w-full text-sm">
                                    <div class="flex justify-between">
                                        <h4 class="font-medium">ARRASTRE:</h4>
                                        <p>{{ number_format($arrastre, 2) }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>

<script>
window.addEventListener('printLiquidation', () => {
    printLiquidation();
});

window.addEventListener('downloadLiquidation', () => {
    guardarLiquidacion();
});

function printLiquidation() {
    const container = document.getElementById('liquidationContainer');
    if (!container) return;

    html2canvas(container, { scale: 2 }).then(canvas => {
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
              <title>Imprimir Liquidación</title>
              <style>
                @page { size: Letter; margin: 0mm; }
                html, body { margin: 0; padding: 0; }
                body { background: #fff; }
                img { width: 100%; height: auto; }
              </style>
            </head>
            <body>
              <img src="${imgData}" alt="Liquidación" onload="window.focus(); window.print();">
            </body>
          </html>
        `);
        doc.close();
        setTimeout(() => {
            document.body.removeChild(iframe);
        }, 1000);
    });
}

async function guardarLiquidacion() {
    const container = document.getElementById('liquidationContainer');
    if (!container) return;

    const canvas = await html2canvas(container, { scale: 2 });
    const imgData = canvas.toDataURL("image/png");

    const link = document.createElement('a');
    link.href = imgData;
    link.download = "liquidacion-{{ $date ?? 'sin-fecha' }}.png";
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}
</script>

<style>
@media print {
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
}
</style>
