<div class="bg-[#1b1f22] w-full h-full min-h-screen p-4 flex flex-col gap-3">
    <div class="flex justify-between items-center pb-2">
        <div class="flex flex-col">
            <h2 class="font-semibold text-xl text-white">Liquidación Simple - Cliente</h2>
            <p class="text-gray-500">
                Cliente: <span class="text-white">{{ $client->nombre }} {{ $client->apellido }}</span>
            </p>
            @if($liquidationDate)
                <p class="text-gray-500 text-sm mt-1">
                    Fecha de Liquidación: <span class="text-white">{{ \Carbon\Carbon::parse($liquidationDate)->format('d/m/Y') }}</span>
                </p>
            @endif
        </div>
        <div class="flex justify-end">
            <a href="{{ route('clients.show') }}"
                class="bg-gray-600 text-white hover:bg-gray-700 duration-75 rounded-full text-sm px-3 pe-4 py-1">
                <i class="fa-solid fa-arrow-left mr-2"></i>Volver
            </a>
        </div>
    </div>

    @if(session('payment_success'))
        <div class="bg-green-900/30 border border-green-700 text-green-400 px-4 py-3 rounded-lg">
            <i class="fa-solid fa-check-circle mr-2"></i>
            {{ session('payment_success') }}
        </div>
    @endif

    @if($hasPaymentToday && $nextLiquidationDate && !empty($paymentsToday))
        @php
            $paymentMessages = [];
            $totalAmount = 0;
            foreach ($paymentsToday as $payment) {
                $paymentMessages[] = '$' . number_format($payment['amount'], 2, ',', '.');
                $totalAmount += $payment['amount'];
            }
            $paymentCount = count($paymentsToday);
        @endphp
        <div class="bg-blue-900/30 border border-blue-700 text-blue-400 px-4 py-3 rounded-lg">
            <i class="fa-solid fa-info-circle mr-2"></i>
            @if($paymentCount == 1)
                Se registró un pago de <strong>{{ $paymentMessages[0] }}</strong> hoy. Se verá reflejado en la liquidación del <strong>{{ \Carbon\Carbon::parse($nextLiquidationDate)->format('d/m/Y') }}</strong>.
            @else
                Se registraron <strong>{{ $paymentCount }} pagos</strong> hoy ({{ implode(', ', $paymentMessages) }}) por un total de <strong>${{ number_format($totalAmount, 2, ',', '.') }}</strong>. Se verán reflejados en la liquidación del <strong>{{ \Carbon\Carbon::parse($nextLiquidationDate)->format('d/m/Y') }}</strong>.
            @endif
        </div>
    @endif

    <!-- Card de ANTERI Actual -->
    <div class="bg-[#22272b] rounded-lg p-6 border border-gray-600">
        <div class="flex flex-col items-center justify-center gap-4">
            <div class="text-gray-400 text-sm">
                ANTERI de la Liquidación
                @if($liquidationDate)
                    - {{ \Carbon\Carbon::parse($liquidationDate)->format('d/m/Y') }}
                @endif
            </div>
            <div class="text-4xl font-bold {{ $currentAnteri >= 0 ? 'text-green-400' : 'text-red-400' }}">
                ${{ number_format($currentAnteri, 2, ',', '.') }}
            </div>
            <div class="text-gray-500 text-xs text-center">
                @if($currentAnteri >= 0)
                    El cliente debe pagar al administrador
                @else
                    El administrador debe pagar al cliente
                @endif
            </div>
            
            <!-- Botón para registrar pago -->
            <button wire:click="openPaymentModal"
                class="mt-4 bg-blue-600 hover:bg-blue-700 text-white font-medium px-6 py-2 rounded-lg transition-colors duration-200 flex items-center gap-2">
                <i class="fa-solid fa-dollar-sign"></i>
                Registrar Pago
            </button>
        </div>
    </div>

    <!-- Información adicional -->
    <div class="bg-[#22272b] rounded-lg p-4 border border-gray-600">
        <h3 class="text-white font-semibold mb-3">Información</h3>
        <div class="space-y-2 text-sm text-gray-400">
            <p>
                <i class="fa-solid fa-info-circle mr-2"></i>
                El ANTERI mostrado corresponde al lunes de esta semana.
            </p>
            <p>
                <i class="fa-solid fa-calendar mr-2"></i>
                Los pagos registrados hoy se verán reflejados en la liquidación del día siguiente.
            </p>
            <p>
                <i class="fa-solid fa-money-bill-wave mr-2"></i>
                Si el ANTERI es positivo, el pago se registrará como <strong class="text-white">UD.DIO</strong>.
            </p>
            <p>
                <i class="fa-solid fa-hand-holding-dollar mr-2"></i>
                Si el ANTERI es negativo, el pago se registrará como <strong class="text-white">UD.RECIBE</strong>.
            </p>
        </div>
    </div>

    <!-- Modal de Pago -->
    @if($showPaymentModal)
    <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <!-- Background overlay -->
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" wire:click="closePaymentModal"></div>

            <!-- Modal panel -->
            <div class="inline-block align-bottom bg-[#1b1f22] rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                <!-- Header -->
                <div class="bg-[#22272b] px-6 py-4 border-b border-gray-600">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-lg font-semibold text-white">
                                Registrar Pago
                            </h3>
                            <p class="text-sm text-gray-400">
                                Cliente: {{ $client->nombre }} {{ $client->apellido }}
                            </p>
                        </div>
                        <button wire:click="closePaymentModal" class="text-gray-400 hover:text-white">
                            <i class="fa-solid fa-times text-xl"></i>
                        </button>
                    </div>
                </div>

                <!-- Content -->
                <div class="bg-[#1b1f22] px-6 py-4">
                    <!-- ANTERI Actual -->
                    <div class="mb-4 p-3 rounded-lg {{ $currentAnteri >= 0 ? 'bg-green-900/30 border border-green-700' : 'bg-red-900/30 border border-red-700' }}">
                        <div class="flex justify-between items-center">
                            <span class="text-gray-300 text-sm">ANTERI Actual:</span>
                            <span class="font-semibold text-lg {{ $currentAnteri >= 0 ? 'text-green-400' : 'text-red-400' }}">
                                ${{ number_format($currentAnteri, 2, ',', '.') }}
                            </span>
                        </div>
                        <p class="text-xs text-gray-400 mt-1">
                            @if($currentAnteri < 0)
                                El administrador debe pagar al cliente
                            @else
                                El cliente debe pagar al administrador
                            @endif
                        </p>
                    </div>

                    <!-- Tipo de pago que se registrará -->
                    <div class="mb-4 p-3 rounded-lg bg-blue-900/30 border border-blue-700">
                        <div class="flex items-center gap-2">
                            <i class="fa-solid fa-info-circle text-blue-400"></i>
                            <span class="text-sm text-gray-300">
                                Este pago se registrará como: 
                                <strong class="text-white">
                                    @if($currentAnteri >= 0)
                                        UD.DIO
                                    @else
                                        UD.RECIBE
                                    @endif
                                </strong>
                            </span>
                        </div>
                    </div>

                    <!-- Formulario -->
                    <form wire:submit.prevent="savePayment">
                        <div class="space-y-4">
                            <!-- Monto -->
                            <div>
                                <label for="paymentAmount" class="block text-sm font-medium text-gray-300 mb-2">
                                    Monto del Pago
                                </label>
                                <div class="relative">
                                    <input type="text" 
                                        id="paymentAmount"
                                        x-data="{
                                            rawValue: ($wire.paymentAmount || '').toString(),
                                            formatNumber(num) {
                                                if (!num || num === '' || num === '0') return '';
                                                let clean = num.toString().replace(/[^\d.,]/g, '');
                                                clean = clean.replace(',', '.');
                                                let parts = clean.split('.');
                                                if (parts.length > 2) {
                                                    clean = parts[0] + '.' + parts.slice(1).join('');
                                                }
                                                let number = parseFloat(clean);
                                                if (isNaN(number)) return '';
                                                return number.toLocaleString('es-ES', {
                                                    minimumFractionDigits: 0,
                                                    maximumFractionDigits: 2
                                                });
                                            },
                                            getNumericValue(str) {
                                                if (!str) return '';
                                                return str.toString().replace(/\./g, '').replace(',', '.');
                                            }
                                        }"
                                        x-effect="
                                            if ($wire.paymentAmount !== undefined && $wire.paymentAmount !== null) {
                                                rawValue = ($wire.paymentAmount || '').toString();
                                                if (document.activeElement !== $el) {
                                                    $el.value = formatNumber(rawValue);
                                                }
                                            }
                                        "
                                        x-on:input="
                                            let numeric = getNumericValue($event.target.value);
                                            rawValue = numeric;
                                            $wire.paymentAmount = numeric;
                                            $nextTick(() => {
                                                if ($event.target === document.activeElement) {
                                                    let formatted = formatNumber(numeric);
                                                    if (formatted !== $event.target.value) {
                                                        let cursorPos = $event.target.selectionStart;
                                                        $event.target.value = formatted;
                                                        let newPos = Math.max(0, cursorPos - ($event.target.value.length - formatted.length));
                                                        $event.target.setSelectionRange(newPos, newPos);
                                                    }
                                                }
                                            });
                                        "
                                        x-on:focus="
                                            if (rawValue) {
                                                $event.target.value = rawValue;
                                            }
                                        "
                                        x-on:blur="
                                            let formatted = formatNumber(rawValue);
                                            $event.target.value = formatted;
                                        "
                                        :value="formatNumber(rawValue)"
                                        class="w-full px-3 py-2 bg-[#22272b] border border-gray-600 rounded-md text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
                                        placeholder="0"
                                        required>
                                </div>
                                @error('paymentAmount')
                                    <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Notas -->
                            <div>
                                <label for="paymentNotes" class="block text-sm font-medium text-gray-300 mb-2">
                                    Notas (Opcional)
                                </label>
                                <textarea 
                                    id="paymentNotes"
                                    wire:model="paymentNotes" 
                                    rows="3"
                                    class="w-full px-3 py-2 bg-[#22272b] border border-gray-600 rounded-md text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
                                    placeholder="Agregar notas sobre el pago..."></textarea>
                            </div>
                        </div>

                        <!-- Footer -->
                        <div class="mt-6 flex justify-end gap-3">
                            <button type="button" 
                                wire:click="closePaymentModal"
                                class="px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700 transition-colors">
                                Cancelar
                            </button>
                            <button type="submit"
                                class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors">
                                Guardar Pago
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>

