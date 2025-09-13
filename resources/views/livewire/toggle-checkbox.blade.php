<div x-data @keydown.window="if ($event.key === 'x' || $event.key === 'X') $wire.toggle()"
    class="font-medium text-gray-700 flex flex-col items-center gap-1">

    <label for="front" class="select-none text-sm font-medium text-gray-800 dark:text-white text-nowrap">
        Por Afuera
    </label>

    <input type="checkbox" id="front" wire:model="checked"
        class="w-8 h-8 cursor-pointer rounded border-gray-300 text-green-600 focus:ring-green-500">
</div>