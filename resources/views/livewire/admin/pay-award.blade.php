<main class="border border-gray-300 bg-white  rounded-lg p-4 flex flex-col gap-3 w-full">
    <div class="flex flex-col gap-5 item">
        <div>
            <h2 class="font-semibold text-2xl text-gray-700">Pagar ticket</h2>
        </div>

        <div class="flex gap-3 justify-between">
            <div class="w-full flex flex-col gap-1  text-sm">
                <label for="numberTicket" class="text-gray-600">Número de ticket a pagar</label>
                <input type="number" wire:model="numberTicket" placeholder="Número de ticket"
                    class="w-full border  text-sm border-gray-300 rounded-md p-2 py-1">
            </div>
            <div class="w-full flex flex-col gap-1  text-sm">
                <label for="typeTicket" class="text-gray-600">Tipo de Ticket</label>
                <select name="typeTicket" id="typeTicket"
                    class="w-full  text-sm border border-gray-300 rounded-md p-2 py-1">
                    <option value="play">Jugada</option>
                    <option value="Borratine">Borratina</option>
                </select>
            </div>
            <div class="flex justify-end items-end">
                <button
                    class="bg-green-400 border border-gray-400 w-fit text-sm px-5 py-1 rounded-md text-white hover:bg-green-500/80 duration-200">Pagar</button>
            </div>
        </div>

    </div>
</main>