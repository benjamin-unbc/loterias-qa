<div class="bg-[#1b1f22] w-full h-full min-h-screen p-4 flex justify-center items-center gap-3">

    <div class="w-full sm:max-w-md mt-6 px-6 py-4 shadow-md overflow-hidden sm:rounded-lg bg-[#24292d] border border-gray-600">
        <div class="w-full grid place-content-center py-6">
            {{ $logo }}
        </div>
        {{ $slot }}
    </div>
</div>
