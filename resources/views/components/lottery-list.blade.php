@props(['lotteries'])

<div class="pb-3 border-b border-gray-600 w-full">
    @php
        // Mapeo de códigos del sistema a códigos cortos
        $systemToShortCodes = [
            'NAC1015' => 'AB', 'CHA1015' => 'CH1', 'PRO1015' => 'QW', 'MZA1015' => 'M10', 'CTE1015' => '!',
            'SFE1015' => 'ER', 'COR1015' => 'SD', 'RIO1015' => 'RT', 'NAC1200' => 'Q', 'CHA1200' => 'CH2',
            'PRO1200' => 'W', 'MZA1200' => 'M1', 'CTE1200' => 'M', 'SFE1200' => 'R', 'COR1200' => 'T',
            'RIO1200' => 'K', 'NAC1500' => 'A', 'CHA1500' => 'CH3', 'PRO1500' => 'E', 'MZA1500' => 'M2',
            'CTE1500' => 'Ct3', 'SFE1500' => 'D', 'COR1500' => 'L', 'RIO1500' => 'J', 'ORO1800' => 'S',
            'NAC1800' => 'F', 'CHA1800' => 'CH4', 'PRO1800' => 'B', 'MZA1800' => 'M3', 'CTE1800' => 'Z',
            'SFE1800' => 'V', 'COR1800' => 'H', 'RIO1800' => 'U', 'NAC2100' => 'N', 'CHA2100' => 'CH5',
            'PRO2100' => 'P', 'MZA2100' => 'M4', 'CTE2100' => 'G', 'SFE2100' => 'I', 'COR2100' => 'C',
            'RIO2100' => 'Y', 'ORO2100' => 'O',
            // Nuevos códigos cortos para las loterías adicionales
            'NQN1015' => 'NQ1', 'MIS1030' => 'MI1', 'Rio1015' => 'RN1', 'Tucu1130' => 'TU1', 'San1015' => 'SG1',
            'NQN1200' => 'NQ2', 'MIS1215' => 'MI2', 'JUJ1200' => 'JU1', 'Salt1130' => 'SA1', 'Rio1200' => 'RN2',
            'Tucu1430' => 'TU2', 'San1200' => 'SG2', 'NQN1500' => 'NQ3', 'MIS1500' => 'MI3', 'JUJ1500' => 'JU2',
            'Salt1400' => 'SA2', 'Rio1500' => 'RN3', 'Tucu1730' => 'TU3', 'San1500' => 'SG3', 'NQN1800' => 'NQ4',
            'MIS1800' => 'MI4', 'JUJ1800' => 'JU3', 'Salt1730' => 'SA3', 'Rio1800' => 'RN4', 'Tucu1930' => 'TU4',
            'San1945' => 'SG4', 'NQN2100' => 'NQ5', 'JUJ2100' => 'JU4', 'Rio2100' => 'RN5', 'Salt2100' => 'SA4',
            'Tucu2200' => 'TU5', 'MIS2115' => 'MI5', 'San2200' => 'SG5'
        ];
        
        // Se recorre cada lotería, se separa la cadena en elementos y se unen todos en una sola colección
        $allLotteryItems = collect($lotteries)
            ->flatMap(function($lottery) {
                return explode(',', $lottery->determined_lottery);
            })
            ->map(function($item) {
                $item = trim($item);
                // Mostrar el código completo en lugar del código corto
                return $item;
            })
            ->unique();
    @endphp

    @if($allLotteryItems->isNotEmpty())
        <div class="w-full text-center text-black font-semibold">
            {{ $allLotteryItems->implode(', ') }}
        </div>
    @else
        <div class="text-center px-4 py-2 text-gray-300">
            No hay loterías disponibles.
        </div>
    @endif
</div>
