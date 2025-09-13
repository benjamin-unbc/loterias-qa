<main class="bg-[#1b1f22] w-full h-full min-h-screen p-4 flex flex-col gap-3">
    <div class="flex flex-col gap-5 item">
        <div class="flex gap-1">
            <h2 class="font-semibold text-2xl text-white">Tablas de pagos</h2>
        </div>

        <div id="paginated-users">
            <div class="bg-[#1b1f22] rounded-lg">
                <div class="flex justify-between items-center p-4 ps-0">
                    <h2 class="font-semibold text-xl text-white leading-tight">
                        {{ __('Quiniela') }}
                    </h2>
                </div>
                <div class="relative overflow-x-auto rounded-lg border-e border-gray-600">
                    <table class="w-full text-sm text-left rtl:text-right text-gray-500 dark:text-gray-400">
                        <thead class="text-xs text-white uppercase bg-gray-600 text-end">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-start">
                                    Juega $
                                </th>
                                <th scope="col" class="px-6 py-3">
                                    1 Cifra cobra
                                </th>
                                <th scope="col" class="px-6 py-3">
                                    2 Cifra cobra
                                </th>
                                <th scope="col" class="px-6 py-3">
                                    3 Cifra cobra
                                </th>
                                <th scope="col" class="px-6 py-3">
                                    4 Cifra cobra
                                </th>
                            </tr>
                        </thead>
                        @foreach ($quinielas as $quiniela)
                        <tr class="bg-[#22272b]  border-b border-gray-600 text-white">
                            <td class="px-6 py-4 bg-gray-600 text-white font-medium border-t border-gray-500">
                                ${{$quiniela->juega }}
                            </td>
                            <td class="px-6 py-4 text-end">${{$quiniela->cobra_1_cifra }}</td>
                            <td class="px-6 py-4 text-end">${{$quiniela->cobra_2_cifra }}</td>
                            <td class="px-6 py-4 text-end">${{$quiniela->cobra_3_cifra }}</td>
                            <td class="px-6 py-4 text-end">${{$quiniela->cobra_4_cifra }}</td>
                        </tr>
                        @endforeach
                    </table>

                </div>
            </div>
        </div>

        <div id="paginated-users">
            <div class="bg-[#1b1f22] rounded-lg">
                <div class="flex justify-between items-center p-4 ps-0">
                    <h2 class="font-semibold text-xl text-white leading-tight">
                        {{ __('Cobro por apuesta a los premios') }}
                    </h2>
                </div>
                <div class="relative overflow-x-auto rounded-lg border-e border-gray-600">
                    <table class="w-full text-sm text-left rtl:text-right text-gray-500 dark:text-gray-400">
                        <thead class="text-xs text-white uppercase bg-gray-600 text-end">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-start">
                                    Juega $
                                </th>
                                <th scope="col" class="px-6 py-3">
                                    A los 5 Cobra
                                </th>
                                <th scope="col" class="px-6 py-3">
                                    A los 10 Cobra
                                </th>
                                <th scope="col" class="px-6 py-3">
                                    A los 20 Cobra
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($prizes as $prizesItem)
                            <tr class="bg-[#22272b]  border-b border-gray-600 text-white">
                                <td class="px-6 py-4 bg-gray-600 text-white font-medium border-t border-gray-500">
                                    ${{$prizesItem->juega
                                    }}</td>
                                <td class="px-6 py-4  text-end">${{$prizesItem->cobra_5 }}</td>
                                <td class="px-6 py-4  text-end">${{$prizesItem->cobra_10 }}</td>
                                <td class="px-6 py-4  text-end">${{$prizesItem->cobra_20 }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>

                </div>
            </div>
        </div>


        <div id="paginated-users">
            <div class="bg-[#1b1f22] rounded-lg">
                <div class="flex justify-between items-center p-4 ps-0">
                    <h2 class="font-semibold text-xl text-white leading-tight">
                        {{ __('Terminación 3 cifras') }}
                    </h2>
                </div>
                <div class="relative overflow-x-auto rounded-lg border-e border-gray-600">
                    <table class="w-full text-sm text-left rtl:text-right text-gray-500 dark:text-gray-400">
                        <thead class="text-xs text-white uppercase bg-gray-600  text-end">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-start">
                                    Juega $
                                </th>
                                <th scope="col" class="px-6 py-3">
                                    A los 5 Cobra
                                </th>
                                <th scope="col" class="px-6 py-3">
                                    A los 10 Cobra
                                </th>
                                <th scope="col" class="px-6 py-3">
                                    A los 20 Cobra
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($figureone as $figureoneItem)
                            <tr class="bg-[#22272b]  border-b border-gray-600 text-white">
                                <td class="px-6 py-4 bg-gray-600 text-white font-medium border-t border-gray-500">
                                    ${{$figureoneItem->juega }}</td>
                                <td class="px-6 py-4 text-end">${{$figureoneItem->cobra_5 }}</td>
                                <td class="px-6 py-4 text-end">${{$figureoneItem->cobra_10 }}</td>
                                <td class="px-6 py-4 text-end">${{$figureoneItem->cobra_20 }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>

                </div>
            </div>
        </div>

        <div id="paginated-users">
            <div class="bg-[#1b1f22] rounded-lg">
                <div class="flex justify-between items-center p-4 ps-0">
                    <h2 class="font-semibold text-xl text-white leading-tight">
                        {{ __('Terminación 4 cifras') }}
                    </h2>
                </div>
                <div class="relative overflow-x-auto rounded-lg border-e border-gray-600">
                    <table class="w-full text-sm text-left rtl:text-right text-gray-500 dark:text-gray-400">
                        <thead class="text-xs text-white uppercase bg-gray-600 text-end">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-start">
                                    Juega $
                                </th>
                                <th scope="col" class="px-6 py-3">
                                    A los 5 Cobra
                                </th>
                                <th scope="col" class="px-6 py-3">
                                    A los 10 Cobra
                                </th>
                                <th scope="col" class="px-6 py-3">
                                    A los 20 Cobra
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($figuretwo as $figuretwoItem)
                            <tr class="bg-[#22272b]  border-b border-gray-600 text-white">
                                <td class="px-6 py-4 bg-gray-600 text-white font-medium border-t border-gray-500">
                                    ${{$figuretwoItem->juega }}</td>
                                <td class="px-6 py-4 text-end">${{$figuretwoItem->cobra_5 }}</td>
                                <td class="px-6 py-4 text-end">${{$figuretwoItem->cobra_10 }}</td>
                                <td class="px-6 py-4 text-end">${{$figuretwoItem->cobra_20 }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>

                </div>
            </div>
        </div>

        <div id="paginated-users">
            <div class="bg-[#1b1f22] rounded-lg">
                <div class="flex flex-col p-4 ps-0">
                    <h2 class="font-semibold text-xl text-white leading-tight">
                        {{ __('Cobro por apuesta en Redoblona') }}
                    </h2>
                    <p class="text-gray-500">Se toma únicamente por terminación de 2 cifras</p>
                </div>
                <div class="relative overflow-x-auto flex flex-col gap-5 rounded-lg">

                    <div class="overflow-x-auto rounded-lg border-e border-gray-600">
                        <table class="w-full text-sm text-left rtl:text-right text-gray-500 dark:text-gray-400">
                            <thead class="text-xs text-white uppercase bg-gray-600 text-end">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-start">
                                        Juega $
                                    </th>
                                    <th scope="col" class="px-6 py-3">
                                        A los 10 todo a los 10 Cobra
                                    </th>
                                    <th scope="col" class="px-6 py-3">
                                        A los 10 todo a los 20 Cobra
                                    </th>
                                    <th scope="col" class="px-6 py-3">
                                        A los 20 todo a los 20 Cobra
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($betcollection10to20 as $betcollection10to20Item)
                                <tr class="bg-[#22272b]  border-b border-gray-600 text-white">
                                    <td class="px-6 py-4 bg-gray-600 text-white font-medium border-t border-gray-500">
                                        ${{$betcollection10to20Item->bet_amount
                                        }}</td>
                                    <td class="px-6 py-4 text-end">${{$betcollection10to20Item->payout_10_to_10 }}</td>
                                    <td class="px-6 py-4 text-end">${{$betcollection10to20Item->payout_10_to_20 }}</td>
                                    <td class="px-6 py-4 text-end">${{$betcollection10to20Item->payout_20_to_20 }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="overflow-x-auto rounded-lg border-e border-gray-600">
                        <table class="w-full text-sm text-left rtl:text-right text-gray-500 dark:text-gray-400">
                            <thead class="text-xs text-white uppercase bg-gray-600 text-end">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-start">
                                        Juega $
                                    </th>
                                    <th scope="col" class="px-6 py-3">
                                        A los 5 todo a los 5 Cobra
                                    </th>
                                    <th scope="col" class="px-6 py-3">
                                        A los 5 todo a los 10 Cobra
                                    </th>
                                    <th scope="col" class="px-6 py-3">
                                        A los 5 todo a los 20 Cobra
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($betcollection5to20 as $betcollection5to20Item)
                                <tr class="bg-[#22272b]  border-b border-gray-600 text-white">
                                    <td class="px-6 py-4 bg-gray-600 text-white font-medium border-t border-gray-500">
                                        ${{$betcollection5to20Item->bet_amount }}
                                    </td>
                                    <td class="px-6 py-4 text-end">${{$betcollection5to20Item->payout_5_to_5 }}</td>
                                    <td class="px-6 py-4 text-end">${{$betcollection5to20Item->payout_5_to_10 }}</td>
                                    <td class="px-6 py-4 text-end">${{$betcollection5to20Item->payout_5_to_20 }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="overflow-x-auto rounded-lg border-e border-gray-600">
                        <table class="w-full text-sm text-left rtl:text-right text-gray-500 dark:text-gray-400">
                            <thead class="text-xs text-white uppercase bg-gray-600 text-end">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-start">
                                        Juega $
                                    </th>
                                    <th scope="col" class="px-6 py-3">
                                        A los 1 todo a los 5 Cobra
                                    </th>
                                    <th scope="col" class="px-6 py-3">
                                        A los 1 todo a los 10 Cobra
                                    </th>
                                    <th scope="col" class="px-6 py-3">
                                        A los 1 todo a los 20 Cobra
                                    </th>
                                </tr>
                            </thead>
                            <tbody>

                                @foreach ($betcollectionredoblona as $betcollectionredoblonaItem)
                                <tr class="bg-[#22272b]  border-b border-gray-600 text-white">
                                    <td class="px-6 py-4 bg-gray-600 text-white font-medium border-t border-gray-500">
                                        ${{$betcollectionredoblonaItem->bet_amount }}</td>
                                    <td class="px-6 py-4 text-end">${{$betcollectionredoblonaItem->payout_1_to_5 }}</td>
                                    <td class="px-6 py-4 text-end">${{$betcollectionredoblonaItem->payout_1_to_10 }}</td>
                                    <td class="px-6 py-4 text-end">${{$betcollectionredoblonaItem->payout_1_to_20 }}</td>
                                </tr>
                                @endforeach
                                
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>
