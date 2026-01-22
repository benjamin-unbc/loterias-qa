<div class="bg-[#1b1f22] w-full h-full min-h-screen p-4 flex flex-col gap-3">
    <div class="flex justify-between items-center pb-2">
        <div class="flex flex-col">
            <h2 class="font-semibold text-xl text-white">Liquidaciones y Pagos - {{ $client->nombre }} {{ $client->apellido }}</h2>
            <p class="text-gray-500">Gestiona los pagos del cliente por semana</p>
        </div>
        <a href="{{ route('clients.show') }}" class="bg-gray-600 text-white px-4 py-2 rounded-md hover:bg-gray-700">
            <i class="fa-solid fa-arrow-left"></i> Volver
        </a>
    </div>

    @if(session()->has('message'))
    <div class="bg-green-500/20 border border-green-500 rounded-lg p-4 mb-4">
        <p class="text-green-400">{{ session('message') }}</p>
    </div>
    @endif

    @if(session()->has('error'))
    <div class="bg-red-500/20 border border-red-500 rounded-lg p-4 mb-4">
        <p class="text-red-400">{{ session('error') }}</p>
    </div>
    @endif

    <!-- Total Debe -->
    <div class="bg-[#22272b] rounded-lg p-6 border border-gray-600">
        <div class="flex justify-between items-center">
            <div>
                <h3 class="text-gray-400 text-sm mb-1">Total Debe</h3>
                <p class="text-3xl font-bold text-white">{{ number_format($totalDebe, 2) }}</p>
            </div>
            <div class="text-right">
                <p class="text-gray-400 text-sm">Suma de todas las semanas</p>
            </div>
        </div>
    </div>

    <!-- Lista de Semanas -->
    <div class="flex flex-col gap-3">
        @forelse($weeks as $index => $week)
        <div class="bg-[#22272b] rounded-lg border border-gray-600 overflow-hidden">
            <!-- Encabezado de la Semana -->
            <div 
                class="p-4 cursor-pointer hover:bg-[#2a2f35] transition-colors flex justify-between items-center"
                wire:click="selectWeek('{{ $week['saturday_str'] }}')"
            >
                <div class="flex items-center gap-3">
                    <i class="fa-solid fa-chevron-{{ $selectedWeek === $week['saturday_str'] ? 'down' : 'right' }} text-gray-400"></i>
                    <div>
                        <h3 class="text-white font-semibold">Semana del {{ $week['saturday']->format('d/m/Y') }}</h3>
                        <p class="text-gray-400 text-sm">Sábado con USTED DEBE SEM</p>
                    </div>
                </div>
                <div class="text-right">
                    <p class="text-gray-400 text-sm">USTED DEBE SEM</p>
                    <p class="text-xl font-bold text-white">{{ number_format($week['usted_debe_sem'], 2) }}</p>
                    @if($week['total_payments'] > 0)
                    <p class="text-green-400 text-sm">Pagado: {{ number_format($week['total_payments'], 2) }}</p>
                    <p class="text-yellow-400 text-sm">Restante: {{ number_format($week['remaining_debe'], 2) }}</p>
                    @endif
                </div>
            </div>

            <!-- Contenido desplegable -->
            @if($selectedWeek === $week['saturday_str'])
            <div class="border-t border-gray-600 p-4">
                <!-- Formulario de Pago -->
                <div class="bg-[#1b1f22] rounded-lg p-4 mb-4">
                    <h4 class="text-white font-semibold mb-3">Ingresar Pago</h4>
                    <div class="flex flex-col gap-3">
                        <div>
                            <label class="text-gray-400 text-sm mb-1 block">Monto del Pago</label>
                            <input 
                                type="number" 
                                step="0.01" 
                                min="0.01"
                                wire:model="paymentAmount"
                                class="w-full bg-[#22272b] border border-gray-600 text-white rounded-md p-2 focus:border-yellow-200"
                                placeholder="0.00"
                            >
                            @error('paymentAmount') <span class="text-red-400 text-xs">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="text-gray-400 text-sm mb-1 block">Notas (opcional)</label>
                            <textarea 
                                wire:model="paymentNotes"
                                class="w-full bg-[#22272b] border border-gray-600 text-white rounded-md p-2 focus:border-yellow-200"
                                rows="2"
                                placeholder="Notas adicionales sobre el pago..."
                            ></textarea>
                        </div>
                        <button 
                            type="button"
                            wire:click="savePayment"
                            wire:loading.attr="disabled"
                            wire:target="savePayment"
                            class="bg-green-500 text-white px-4 py-2 rounded-md hover:bg-green-600 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            <span wire:loading.remove wire:target="savePayment">
                                <i class="fa-solid fa-save"></i> Guardar Pago
                            </span>
                            <span wire:loading wire:target="savePayment">
                                <i class="fa-solid fa-spinner fa-spin"></i> Guardando...
                            </span>
                        </button>
                    </div>
                </div>

                <!-- Historial de Pagos -->
                <div>
                    <h4 class="text-white font-semibold mb-3">Historial de Pagos de la Semana</h4>
                    @if($week['payments']->count() > 0)
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-gray-600">
                                    <th class="text-left text-gray-400 p-2">Fecha de Pago</th>
                                    <th class="text-left text-gray-400 p-2">Fecha de Registro</th>
                                    <th class="text-right text-gray-400 p-2">Monto</th>
                                    <th class="text-left text-gray-400 p-2">Notas</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($week['payments'] as $payment)
                                <tr class="border-b border-gray-700">
                                    <td class="text-white p-2">{{ $payment->payment_date->format('d/m/Y') }}</td>
                                    <td class="text-gray-400 p-2">{{ $payment->created_at->format('d/m/Y H:i') }}</td>
                                    <td class="text-green-400 text-right p-2 font-semibold">{{ number_format($payment->amount, 2) }}</td>
                                    <td class="text-gray-400 p-2">{{ $payment->notes ?? '-' }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr class="border-t border-gray-600">
                                    <td colspan="2" class="text-white font-semibold p-2">Total Pagado:</td>
                                    <td class="text-green-400 text-right font-bold p-2">{{ number_format($week['total_payments'], 2) }}</td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    @else
                    <div class="bg-[#1b1f22] rounded-lg p-4 text-center text-gray-400">
                        <i class="fa-solid fa-inbox text-3xl mb-2"></i>
                        <p>No hay pagos registrados para esta semana</p>
                    </div>
                    @endif
                </div>
            </div>
            @endif
        </div>
        @empty
        <div class="bg-[#22272b] rounded-lg p-8 text-center border border-gray-600">
            <i class="fa-solid fa-calendar-check text-4xl text-gray-400 mb-3"></i>
            <p class="text-gray-400 text-lg">No se encontraron semanas con USTED DEBE SEM</p>
            <p class="text-gray-500 text-sm mt-2">El cliente no tiene sábados con jugadas que generen deuda semanal</p>
        </div>
        @endforelse
    </div>
</div>

