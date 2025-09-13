<div class="w-full">
    <div class="grid grid-cols-8 gap-3 gap-y-1">
        @foreach($codes as $code => $description)
        @php
        $colorClasses = match(true) {
        in_array($code, ['AB', 'CH1', 'QW', 'M10', '!', 'ER', 'SD', 'RT']) => 'text-gray-200',
        in_array($code, ['Q', 'CH2', 'W', 'M1', 'M', 'R', 'T', 'K', 'S']) => 'text-yellow-200',
        in_array($code, ['A', 'CH3', 'E', 'M2', 'CT3', 'D', 'L', 'J']) => 'text-blue-400',
        in_array($code, ['F', 'CH4', 'B', 'M3', 'Z', 'V', 'H', 'U']) => 'text-green-400',
        in_array($code, ['N', 'CH5', 'P', 'M4', 'G', 'I', 'C', 'Y', 'O']) => 'text-red-400',
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
