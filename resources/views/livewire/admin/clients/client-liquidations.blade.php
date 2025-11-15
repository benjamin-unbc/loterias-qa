<div class="bg-[#1b1f22] w-full h-full min-h-screen p-4 flex flex-col gap-3">
    <div class="flex justify-between items-center pb-2">
        <div class="flex flex-col">
            <h2 class="font-semibold text-xl text-white">Liquidaciones del Cliente</h2>
            <p class="text-gray-500">
                Cliente: <span class="text-white">{{ $client->nombre }} {{ $client->apellido }}</span>
                @if($firstDate)
                    - Primera liquidación: <span class="text-white">{{ \Carbon\Carbon::parse($firstDate)->format('d/m/Y') }}</span>
                @endif
            </p>
        </div>
        <div class="flex justify-end">
            <a href="{{ route('clients.show') }}"
                class="bg-gray-600 text-white hover:bg-gray-700 duration-75 rounded-full text-sm px-3 pe-4 py-1">
                <i class="fa-solid fa-arrow-left mr-2"></i>Volver
            </a>
        </div>
    </div>

    @if($firstDate)
        <div class="relative overflow-x-auto">
            <table class="w-full text-sm text-left rtl:text-right text-gray-500 dark:text-gray-400">
                <thead class="text-xs text-white uppercase bg-gray-600">
                    <tr>
                        <th scope="col" class="px-6 py-3">
                            Semana
                        </th>
                        <th scope="col" class="px-6 py-3">
                            Cliente Deja
                        </th>
                        <th scope="col" class="px-6 py-3">
                            Acciones
                        </th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($weeks as $week)
                        <tr class="border-gray-600 {{ ($week['isCurrentWeek'] ?? false) ? 'bg-yellow-900/20 border-yellow-500 border-2' : 'bg-[#22272b]' }} border-b text-white">
                            <td class="px-6 py-4">
                                <div class="flex flex-col gap-2">
                                    <div class="flex items-center gap-2">
                                        <span class="font-medium">{{ $week['label'] }}</span>
                                        @if($week['isCurrentWeek'] ?? false)
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-500 text-yellow-900 border border-yellow-600">
                                                <i class="fa-solid fa-star mr-1"></i>Semana Actual
                                            </span>
                                        @endif
                                    </div>
                                    <span class="text-gray-400 text-xs">
                                        ({{ count($week['dates']) }} día{{ count($week['dates']) > 1 ? 's' : '' }} con liquidación)
                                    </span>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-2">
                                    <span class="font-semibold {{ ($week['clienteDeja'] ?? 0) >= 0 ? 'text-green-400' : 'text-red-400' }}">
                                        ${{ number_format($week['clienteDeja'] ?? 0, 2, ',', '.') }}
                                    </span>
                                    @if(($week['clienteDeja'] ?? 0) >= 0)
                                        <span class="text-xs text-gray-400">(Debe pagar)</span>
                                    @else
                                        <span class="text-xs text-gray-400">(Debe cobrar)</span>
                                    @endif
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <button wire:click="openWeekModal('{{ $week['monday'] }}')"
                                        class="font-medium text-yellow-200 hover:text-yellow-300 transition-colors duration-200"
                                        title="Ver semana">
                                        <i class="fa-solid fa-calendar"></i>
                                    </button>
                                    @if($week['isCurrentWeek'] ?? false)
                                        @php
                                            $lastDate = $week['lastDate'] ?? end($week['dates']);
                                            $hasPayment = \App\Models\ClientPayment::where('client_id', $client->id)
                                                ->whereDate('payment_date', $lastDate)
                                                ->exists();
                                            $clienteDeja = $week['clienteDeja'] ?? 0;
                                        @endphp
                                        @if(!$hasPayment && $clienteDeja != 0)
                                            <button wire:click="openPaymentModal('{{ $lastDate }}')"
                                                class="font-medium text-blue-400 hover:text-blue-300 transition-colors duration-200"
                                                title="Registrar pago">
                                                <i class="fa-solid fa-dollar-sign"></i>
                                            </button>
                                        @elseif($hasPayment)
                                            <span class="text-gray-500 text-xs" title="Ya se registró un pago para este día. Debe esperar al siguiente día para registrar otro.">
                                                <i class="fa-solid fa-check-circle text-green-400"></i>
                                            </span>
                                        @endif
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="px-6 py-8 text-center text-gray-400">
                                No hay liquidaciones disponibles
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @else
        <div class="text-center text-gray-400 py-8">
            Este cliente no tiene liquidaciones registradas
        </div>
    @endif

    <!-- Modal de Semana -->
    @if($showWeekModal)
    <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <!-- Background overlay -->
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" wire:click="closeWeekModal"></div>

            <!-- Modal panel -->
            <div class="inline-block align-bottom bg-[#1b1f22] rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-4 sm:align-middle sm:max-w-[95vw] sm:w-full">
                <!-- Header -->
                <div class="bg-[#22272b] px-6 py-4 border-b border-gray-600">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-lg font-semibold text-white">
                                Semana: 
                                @if(!empty($weekDates))
                                    {{ \Carbon\Carbon::parse($weekDates[0]['date'])->format('d/m/Y') }} - 
                                    {{ \Carbon\Carbon::parse(end($weekDates)['date'])->format('d/m/Y') }}
                                @endif
                            </h3>
                        </div>
                        <button wire:click="closeWeekModal" class="text-gray-400 hover:text-white">
                            <i class="fa-solid fa-times text-xl"></i>
                        </button>
                    </div>
                </div>

                <!-- Content -->
                <div class="bg-[#1b1f22] p-6 overflow-y-auto" style="max-height: calc(90vh - 200px);">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        @foreach($weekDates as $weekDay)
                            @php
                                $dayData = $weekLiquidations[$weekDay['date']] ?? null;
                            @endphp
                            <div class="bg-[#22272b] rounded-lg p-4 border border-gray-600">
                                <div class="flex justify-between items-center mb-3 pb-2 border-b border-gray-600">
                                    <h4 class="text-white font-semibold">{{ $weekDay['dayName'] }}</h4>
                                    <span class="text-gray-400 text-sm">{{ $weekDay['formatted'] }}</span>
                                </div>
                                
                                @if($dayData)
                                    <div class="space-y-2 text-sm">
                                        <div class="flex justify-between text-white">
                                            <span class="text-gray-400">Total Pase:</span>
                                            <span class="font-medium">${{ number_format($dayData['totalApus'], 2, ',', '.') }}</span>
                                        </div>
                                        <div class="flex justify-between text-white">
                                            <span class="text-gray-400">Comisión:</span>
                                            <span class="font-medium">${{ number_format($dayData['comision'], 2, ',', '.') }}</span>
                                        </div>
                                        <div class="flex justify-between text-white">
                                            <span class="text-gray-400">Total Aciertos:</span>
                                            <span class="font-medium text-green-400">${{ number_format($dayData['totalAciert'], 2, ',', '.') }}</span>
                                        </div>
                                        <div class="flex justify-between text-white">
                                            <span class="text-gray-400">UD Deja:</span>
                                            <span class="font-medium">${{ number_format($dayData['udDeja'], 2, ',', '.') }}</span>
                                        </div>
                                        <div class="flex justify-between text-white">
                                            <span class="text-gray-400">Arrastre:</span>
                                            <span class="font-medium">${{ number_format($dayData['arrastre'], 2, ',', '.') }}</span>
                                        </div>
                                        
                                        @if($weekDay['carbon']->isSaturday() && isset($dayData['comiDejaSem']) && $dayData['comiDejaSem'] > 0)
                                            <div class="flex justify-between text-white pt-2 border-t border-gray-600">
                                                <span class="text-gray-400">Comi Deja Sem:</span>
                                                <span class="font-medium text-yellow-400">${{ number_format($dayData['comiDejaSem'], 2, ',', '.') }}</span>
                                            </div>
                                        @endif
                                        
                                        <!-- Botón Ver Liquidación Completa -->
                                        <div class="pt-3 border-t border-gray-600 mt-3">
                                            <button wire:click="openFullLiquidationModal('{{ $weekDay['date'] }}')"
                                                class="w-full font-medium text-blue-400 hover:text-blue-300 transition-colors duration-200 text-sm px-3 py-2 bg-blue-500/20 rounded-md hover:bg-blue-500/30"
                                                title="Ver liquidación completa">
                                                <i class="fa-solid fa-file-invoice mr-1"></i>Ver liquidación completa
                                            </button>
                                        </div>
                                    </div>
                                @else
                                    <div class="text-center text-gray-500 py-4">
                                        <i class="fa-solid fa-calendar-xmark text-2xl mb-2"></i>
                                        <p class="text-sm">Sin liquidación</p>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- Modal de Liquidación Completa -->
    @if($showFullLiquidationModal && $fullLiquidationDate)
    <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <!-- Background overlay -->
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" wire:click="closeFullLiquidationModal"></div>

            <!-- Modal panel -->
            <div class="inline-block align-bottom bg-[#1b1f22] rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-4 sm:align-middle sm:max-w-[95vw] sm:w-full">
                <!-- Header -->
                <div class="bg-[#22272b] px-6 py-4 border-b border-gray-600">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-lg font-semibold text-white">
                                Liquidación Completa - {{ \Carbon\Carbon::parse($fullLiquidationDate)->format('d/m/Y') }}
                            </h3>
                        </div>
                        <div class="flex items-center gap-2">
                            <button wire:click="closeFullLiquidationModal" class="text-gray-400 hover:text-white">
                                <i class="fa-solid fa-times text-xl"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Content -->
                <div class="bg-[#1b1f22] p-6 overflow-y-auto" style="max-height: calc(90vh - 200px);">
                    @php
                        $liquidationData = $this->computeLiquidationDataForDate($fullLiquidationDate, $client->associatedUser->id ?? null);
                        $liquidaciones = $this->fullLiquidationResults;
                    @endphp

                    @if($fullLiquidationDate && !\Carbon\Carbon::parse($fullLiquidationDate)->isToday())
                        <!-- Diseño de Boleta igual al original -->
                        <div class="flex justify-center">
                            <div id="liquidationContainer{{ $fullLiquidationDate }}" class="w-[90mm] p-2 text-black bg-white relative shadow-lg">
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
                                            <p>{{ \Carbon\Carbon::parse($fullLiquidationDate)->format('d/m/Y') }}</p>
                                        </div>
                                    </div>

                                    <!-- Detalle de resultados -->
                                    <div class="container text-sm mt-2">
                                        <div class="flex flex-col items-center w-full">
                                            <div class="grid grid-cols-6 font-bold w-full justify-around">
                                                <div class="text-start pl-4">LOT</div>
                                                <div class="text-center px-4">NUM</div>
                                                <div class="text-center px-4">UBI</div>
                                                <div class="text-center px-4">APO</div>
                                                <div class="text-end pr-4">GANO</div>
                                            </div>
                                            <div class="w-full pb-2 border-b">
                                                @forelse ($liquidaciones as $result)
                                                    <div class="grid grid-cols-6 w-full justify-around text-sm">
                                                        <div class="text-start text-nowrap pl-4">
                                                            @php
                                                                // Convertir códigos de lotería a formato legible para liquidaciones
                                                                $lotteryCodes = !empty($result->lottery) ? explode(',', $result->lottery) : [];
                                                                $displayCodes = [];
                                                                
                                                                $codes = [
                                                                    'AB' => 'NAC1015', 'CH1' => 'CHA1015', 'QW' => 'PRO1015', 'M10' => 'MZA1015', '!' => 'CTE1015',
                                                                    'ER' => 'SFE1015', 'SD' => 'COR1015', 'RT' => 'RIO1015', 'Q' => 'NAC1200', 'CH2' => 'CHA1200',
                                                                    'W' => 'PRO1200', 'M1' => 'MZA1200', 'M' => 'CTE1200', 'R' => 'SFE1200', 'T' => 'COR1200',
                                                                    'K' => 'RIO1200', 'A' => 'NAC1500', 'CH3' => 'CHA1500', 'E' => 'PRO1500', 'M2' => 'MZA1500',
                                                                    'Ct3' => 'CTE1500', 'D' => 'SFE1500', 'L' => 'COR1500', 'J' => 'RIO1500', 'S' => 'ORO1800',
                                                                    'ORO1500' => 'ORO1800', 'ORO1800' => 'ORO1800',
                                                                    'F' => 'NAC1800', 'CH4' => 'CHA1800', 'B' => 'PRO1800', 'M3' => 'MZA1800', 'Z' => 'CTE1800',
                                                                    'V' => 'SFE1800', 'H' => 'COR1800', 'U' => 'RIO1800', 'N' => 'NAC2100', 'CH5' => 'CHA2100',
                                                                    'P' => 'PRO2100', 'M4' => 'MZA2100', 'G' => 'CTE2100', 'I' => 'SFE2100', 'C' => 'COR2100',
                                                                    'Y' => 'RIO2100', 'O' => 'ORO2100',
                                                                    'NQN1015' => 'NQN1015', 'MIS1030' => 'MIS1030', 'Rio1015' => 'Rio1015', 'Tucu1130' => 'Tucu1130', 'San1015' => 'San1015',
                                                                    'NQN1200' => 'NQN1200', 'MIS1215' => 'MIS1215', 'JUJ1200' => 'JUJ1200', 'Salt1130' => 'Salt1130', 'Rio1200' => 'Rio1200',
                                                                    'Tucu1430' => 'Tucu1430', 'San1200' => 'San1200', 'NQN1500' => 'NQN1500', 'MIS1500' => 'MIS1500', 'JUJ1500' => 'JUJ1500',
                                                                    'Salt1400' => 'Salt1400', 'Rio1500' => 'Rio1500', 'Tucu1730' => 'Tucu1730', 'San1500' => 'San1500', 'NQN1800' => 'NQN1800',
                                                                    'MIS1800' => 'MIS1800', 'JUJ1800' => 'JUJ1800', 'Salt1730' => 'Salt1730', 'Rio1800' => 'Rio1800', 'Tucu1930' => 'Tucu1930',
                                                                    'San1945' => 'San1945', 'NQN2100' => 'NQN2100', 'JUJ2100' => 'JUJ2100', 'Rio2100' => 'Rio2100', 'Salt2100' => 'Salt2100',
                                                                    'Tucu2200' => 'Tucu2200', 'MIS2115' => 'MIS2115', 'San2200' => 'San2200'
                                                                ];
                                                                
                                                                foreach ($lotteryCodes as $code) {
                                                                    $code = trim($code);
                                                                    
                                                                    if (preg_match('/^[A-Za-z]+\d{4}$/', $code)) {
                                                                        $prefix = substr($code, 0, -4);
                                                                        preg_match('/\d{4}$/', $code, $matches);
                                                                        $timeSuffix = isset($matches[0]) ? substr($matches[0], 0, 2) : '';
                                                                        $displayCodes[] = $prefix . $timeSuffix;
                                                                    } elseif (isset($codes[$code])) {
                                                                        $systemCode = $codes[$code];
                                                                        $prefix = substr($systemCode, 0, -4);
                                                                        preg_match('/\d{4}$/', $systemCode, $matches);
                                                                        $timeSuffix = isset($matches[0]) ? substr($matches[0], 0, 2) : '';
                                                                        $displayCodes[] = $prefix . $timeSuffix;
                                                                    }
                                                                }
                                                                
                                                                $desiredOrder = ['NAC', 'CHA', 'PRO', 'MZA', 'CTE', 'SFE', 'COR', 'RIO', 'ORO', 'NQN', 'MIS', 'JUJ', 'Salt', 'Rio', 'Tucu', 'San'];
                                                                $uniqueDisplayCodes = array_unique($displayCodes);
                                                                
                                                                usort($uniqueDisplayCodes, function ($a, $b) use ($desiredOrder) {
                                                                    $prefixA = substr($a, 0, -2);
                                                                    $prefixB = substr($b, 0, -2);
                                                                    $posA = array_search($prefixA, $desiredOrder);
                                                                    $posB = array_search($prefixB, $desiredOrder);
                                                                    if ($posA === false) $posA = 999;
                                                                    if ($posB === false) $posB = 999;
                                                                    return $posA - $posB;
                                                                });
                                                                
                                                                // Para liquidaciones, mostrar solo la primera lotería (como en el original)
                                                                $firstLottery = !empty($uniqueDisplayCodes) ? $uniqueDisplayCodes[0] : '';
                                                            @endphp
                                                            {{ $firstLottery }}
                                                        </div>
                                                        <div class="text-center text-nowrap px-4">{{ $result->number }}</div>
                                                        <div class="text-center text-nowrap px-4">{{ $result->position }}</div>
                                                        <div class="text-center text-nowrap px-4">
                                                            {{ number_format($result->import) }}
                                                        </div>
                                                        <div class="text-end text-nowrap pr-4">
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
                                                    <p>{{ number_format($liquidationData['previaTotalApus'], 2) }}</p>
                                                </div>
                                                <div class="flex justify-between">
                                                    <h4 class="font-medium">MAÑANA:</h4>
                                                    <p>{{ number_format($liquidationData['mananaTotalApus'], 2) }}</p>
                                                </div>
                                                <div class="flex justify-between">
                                                    <h4 class="font-medium">MATUTINA:</h4>
                                                    <p>{{ number_format($liquidationData['matutinaTotalApus'], 2) }}</p>
                                                </div>
                                                <div class="flex justify-between">
                                                    <h4 class="font-medium">TARDE:</h4>
                                                    <p>{{ number_format($liquidationData['tardeTotalApus'], 2) }}</p>
                                                </div>
                                                <div class="flex justify-between">
                                                    <h4 class="font-medium">NOCHE:</h4>
                                                    <p>{{ number_format($liquidationData['nocheTotalApus'], 2) }}</p>
                                                </div>
                                            </div>

                                            <!-- Cálculos principales -->
                                            <div class="flex flex-col pt-3 gap-1 border-b pb-2 w-full text-sm">
                                                <div class="flex justify-between">
                                                    <h4 class="font-medium">TOTAL PASE:</h4>
                                                    <p>{{ number_format($liquidationData['totalApus'], 2) }}</p>
                                                </div>
                                                <div class="flex justify-between">
                                                    <h4 class="font-medium">COMIS. J. {{ $client->commission_percentage ?? 20.00 }}%:</h4>
                                                    <p>{{ number_format($liquidationData['comision'], 2) }}</p>
                                                </div>
                                                <div class="flex justify-between">
                                                    <h4 class="font-medium">TOT.ACIERT:</h4>
                                                    <p>{{ number_format($liquidationData['totalAciert'], 2) }}</p>
                                                </div>
                                            </div>

                                            <!-- Gener. deja y arrastre -->
                                            <div class="flex flex-col pt-3 gap-1 border-b pb-2 w-full text-sm">
                                                <div class="flex justify-between">
                                                    <h4 class="font-medium">GENER. DEJA:</h4>
                                                    <p>{{ number_format($liquidationData['totalGanaPase'], 2) }}</p>
                                                </div>
                                                @if($liquidationData['totalGanaPase'] < 0)
                                                    <div class="flex justify-between">
                                                        <h4 class="font-medium">USTED GANA:</h4>
                                                        <p>{{ number_format($liquidationData['totalGanaPase'], 2) }}</p>
                                                    </div>
                                                @endif
                                                <div class="flex justify-between">
                                                    <h4 class="font-medium">ANTERI:</h4>
                                                    <p>{{ number_format($liquidationData['anteri'], 2) }}</p>
                                                </div>
                                                @if(\Carbon\Carbon::parse($fullLiquidationDate)->isSaturday())
                                                    <div class="flex justify-between">
                                                        <h4 class="font-medium">COMI DEJA SEM:</h4>
                                                        <p>{{ number_format($liquidationData['comiDejaSem'], 2) }}</p>
                                                    </div>
                                                @endif
                                                <div class="flex justify-between">
                                                    <h4 class="font-medium">UD DEJA:</h4>
                                                    <p>{{ number_format($liquidationData['udDeja'], 2) }}</p>
                                                </div>
                                            </div>

                                            <!-- Arrastre -->
                                            <div class="flex flex-col pt-3 gap-1 w-full text-sm">
                                                <div class="flex justify-between">
                                                    <h4 class="font-medium">ARRASTRE:</h4>
                                                    <p>{{ number_format($liquidationData['arrastre'], 2) }}</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @else
                        <div class="text-center text-gray-400 py-8">
                            No se pueden consultar liquidaciones del día actual
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
    @endif

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
                                Fecha: {{ \Carbon\Carbon::parse($paymentDate)->format('d/m/Y') }}
                            </p>
                        </div>
                        <button wire:click="closePaymentModal" class="text-gray-400 hover:text-white">
                            <i class="fa-solid fa-times text-xl"></i>
                        </button>
                    </div>
                </div>

                <!-- Content -->
                <div class="bg-[#1b1f22] px-6 py-4">
                    <!-- Cliente Deja Actual -->
                    <div class="mb-4 p-3 rounded-lg {{ $currentUdDeja >= 0 ? 'bg-green-900/30 border border-green-700' : 'bg-red-900/30 border border-red-700' }}">
                        <div class="flex justify-between items-center">
                            <span class="text-gray-300 text-sm">Cliente Deja:</span>
                            <span class="font-semibold text-lg {{ $currentUdDeja >= 0 ? 'text-green-400' : 'text-red-400' }}">
                                ${{ number_format($currentUdDeja, 2, ',', '.') }}
                            </span>
                        </div>
                        <p class="text-xs text-gray-400 mt-1">
                            @if($currentUdDeja < 0)
                                El administrador debe pagar al cliente
                            @else
                                El cliente debe pagar al administrador
                            @endif
                        </p>
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
                                                        // Intentar mantener la posición del cursor
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

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    document.addEventListener('livewire:init', function() {
        Livewire.on('payment-saved', (event) => {
            const data = event[0] || event;
            const message = data?.message || 'Pago registrado correctamente. Se verá reflejado en la siguiente liquidación.';
            
            Swal.fire({
                icon: 'success',
                title: '¡Pago Registrado!',
                text: message,
                confirmButtonText: 'Aceptar',
                confirmButtonColor: '#3b82f6',
                background: '#1b1f22',
                color: '#ffffff',
                iconColor: '#3b82f6'
            });
        });
        
        Livewire.on('payment-error', (event) => {
            const data = event[0] || event;
            const message = data?.message || 'Ya se registró un pago para este día. Debe esperar al siguiente día para registrar otro pago.';
            
            Swal.fire({
                icon: 'warning',
                title: 'Pago Ya Registrado',
                text: message,
                confirmButtonText: 'Aceptar',
                confirmButtonColor: '#f59e0b',
                background: '#1b1f22',
                color: '#ffffff',
                iconColor: '#f59e0b'
            });
        });
    });
</script>
@endpush

