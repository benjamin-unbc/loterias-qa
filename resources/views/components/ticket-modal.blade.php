@props(['id' => null, 'maxWidth' => null])

<x-modal :id="$id" :maxWidth="$maxWidth" {{ $attributes }}>
    <div class="bg-[#22272b] px-4 pt-5 pb-4 sm:p-6 sm:pb-4" id="downloadTicketContent">
        <div class="sm:flex sm:items-start">
            <div class="w-full mt-3 text-center sm:mt-0 sm:text-start">
                <div class="text-lg flex justify-between gap-2 font-medium text-white">
                    {{ $title }}
                </div>

                <div class="my-4 text-sm text-white flex flex-col gap-2 relative overflow-hidden">
                    {{ $content }}
                </div>
            </div>
        </div>
    </div>

    <div class="flex items-center gap-4 justify-between px-6 py-4 bg-[#22272b] border-t border-gray-600 text-end">
        {{ $footer }}
    </div>
</x-modal>