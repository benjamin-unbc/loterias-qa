@props(['lotteries'])

<div class="pb-3 border-b border-gray-600 w-full">
    @php
        // Se recorre cada lotería, se separa la cadena en elementos y se unen todos en una sola colección
        $allLotteryItems = collect($lotteries)
            ->flatMap(function($lottery) {
                return explode(',', $lottery->determined_lottery);
            })
            ->map(function($item) {
                return trim($item);
            })
            ->unique();
    @endphp

    @if($allLotteryItems->isNotEmpty())
        <div class="w-full grid grid-cols-6 text-blac">
            @foreach($allLotteryItems as $lotteryItem)
                <p class="font-semibold min-w-[60px] w-full">{{ $lotteryItem }}</p>
            @endforeach
        </div>
    @else
        <div class="text-center px-4 py-2 text-gray-300">
            No hay loterías disponibles.
        </div>
    @endif
</div>
