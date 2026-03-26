<div class="w-full">
    <div class="grid grid-cols-8 gap-3 gap-y-1">
        @foreach($codes as $code => $description)
        @php
        $colorClasses = match(true) {
        // 10:15 y horarios tempranos (gris)
        in_array($code, ['AB', 'CH1', 'QW', 'M10', '!', 'ER', 'SD', 'RT', 'NQ1', 'MI1', 'RN1', 'TU1', 'SG1']) => 'text-gray-200',
        // 12:00 (amarillo)
        in_array($code, ['Q', 'CH2', 'W', 'M1', 'M', 'R', 'T', 'K', 'NQ2', 'MI2', 'JU1', 'SA1', 'RN2', 'TU2', 'SG2']) => 'text-yellow-200',
        // 15:00 (azul)
        in_array($code, ['A', 'CH3', 'E', 'M2', 'Ct3', 'D', 'L', 'J', 'S', 'NQ3', 'MI3', 'JU2', 'SA2', 'RN3', 'TU3', 'SG3']) => 'text-blue-400',
        // 18:00 (verde)
        in_array($code, ['F', 'CH4', 'B', 'M3', 'Z', 'V', 'H', 'U', 'NQ4', 'MI4', 'JU3', 'SA3', 'RN4', 'TU4', 'SG4']) => 'text-green-400',
        // 21:00 (rojo)
        in_array($code, ['N', 'CH5', 'P', 'M4', 'G', 'I', 'C', 'Y', 'O', 'NQ5', 'JU4', 'RN5', 'SA4', 'TU5', 'MI5', 'SG5']) => 'text-red-400',
        default => 'bg-white text-black',
        };
        @endphp

        <div class="flex items-center justify-center gap-1 text-[12px] rounded-lg bg-[#333a41] py-1 {{ $colorClasses }}">
            <h4 class="font-extrabold text-nowrap">{{ $code }} = </h4>
            <p class="text-nowrap">{{ $description }}</p>
        </div>
        @endforeach
    </div>
</div>
